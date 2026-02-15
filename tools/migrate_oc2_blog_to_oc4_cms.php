<?php
/**
 * Migrate Manline OC2 blog (record/blog tables) into OC4 CMS (article/topic tables).
 *
 * Usage:
 *   php tools/migrate_oc2_blog_to_oc4_cms.php [--dry-run] [--force]
 *
 * Notes:
 * - Connects using DB_* constants from:
 *   - OC4: ./config.php
 *   - OC2: ../manline/www/config.php (via workspace symlink)
 * - Preserves IDs when possible (topic_id/article_id = blog_id/record_id).
 * - Aborts if OC4 already has topics/articles unless --force.
 */

declare(strict_types=1);

$dryRun = in_array('--dry-run', $argv, true);
$force  = in_array('--force', $argv, true);

function out(string $s): void { fwrite(STDOUT, $s . "\n"); }
function err(string $s): void { fwrite(STDERR, $s . "\n"); }

$oc4Root = realpath(__DIR__ . '/..');
if (!$oc4Root) {
    err('Cannot resolve OC4 repo root.');
    exit(1);
}

// IMPORTANT: Per user constraint, we ONLY read source data from a LOCAL dump DB.
// Source DB name is fixed: manline_src (same creds as OC4 config.php).
$oc2Config = null; // legacy (unused)

/**
 * Parse OpenCart config.php without executing it (avoids constant collisions).
 * Returns: host/user/pass/db/pfx
 */
function parseOcConfig(string $file): array {
    $src = file_get_contents($file);
    if ($src === false) {
        throw new RuntimeException("Cannot read config: $file");
    }

    $get = function(string $name) use ($src): string {
        // match: define('DB_HOSTNAME', 'localhost'); OR define("DB_HOSTNAME", "localhost");
        $re = "/define\(\s*['\"]" . preg_quote($name, '/') . "['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)\s*;/";
        if (!preg_match($re, $src, $m)) {
            return '';
        }
        return (string)$m[1];
    };

    return [
        'host' => $get('DB_HOSTNAME'),
        'user' => $get('DB_USERNAME'),
        'pass' => $get('DB_PASSWORD'),
        'db'   => $get('DB_DATABASE'),
        'pfx'  => $get('DB_PREFIX'),
    ];
}

$oc4 = parseOcConfig($oc4Root . '/config.php');
$oc2 = $oc4;
$oc2['db'] = 'manline_src';

$outCfg = fn(array $c) => sprintf('%s/%s (prefix %s)', $c['host'], $c['db'], $c['pfx']);
out('OC2: ' . $outCfg($oc2));
out('OC4: ' . $outCfg($oc4));
out('Mode: ' . ($dryRun ? 'DRY-RUN' : 'WRITE') . ($force ? ' (force)' : ''));

$mysqliOC2 = @new mysqli($oc2['host'], $oc2['user'], $oc2['pass'], $oc2['db']);
if ($mysqliOC2->connect_errno) {
    err('OC2 connect error: ' . $mysqliOC2->connect_error);
    exit(1);
}
$mysqliOC2->set_charset('utf8mb4');

$mysqliOC4 = @new mysqli($oc4['host'], $oc4['user'], $oc4['pass'], $oc4['db']);
if ($mysqliOC4->connect_errno) {
    err('OC4 connect error: ' . $mysqliOC4->connect_error);
    exit(1);
}
$mysqliOC4->set_charset('utf8mb4');

function q(mysqli $db, string $sql) {
    $res = $db->query($sql);
    if ($res === false) {
        throw new RuntimeException('SQL error: ' . $db->error . "\nSQL: $sql");
    }
    return $res;
}

function esc(mysqli $db, ?string $s): string {
    return $db->real_escape_string((string)$s);
}

$P2 = $oc2['pfx'];
$P4 = $oc4['pfx'];

// Detect OC4 CMS tables (article/topic).
$required = [
    'article', 'article_description', 'article_to_store',
    'topic', 'topic_description', 'topic_to_store'
];
foreach ($required as $t) {
    $r = q($mysqliOC4, "SHOW TABLES LIKE '" . esc($mysqliOC4, $P4 . $t) . "'");
    if ($r->num_rows === 0) {
        err("Missing OC4 table: {$P4}{$t}");
        exit(1);
    }
}

// Abort if already has data (unless --force)
$topicCount = (int)q($mysqliOC4, "SELECT COUNT(*) c FROM `{$P4}topic`")->fetch_assoc()['c'];
$articleCount = (int)q($mysqliOC4, "SELECT COUNT(*) c FROM `{$P4}article`")->fetch_assoc()['c'];
if (!$force && ($topicCount > 0 || $articleCount > 0)) {
    err("OC4 already has topics/articles (topic={$topicCount}, article={$articleCount}). Re-run with --force if you want to upsert on top.");
    exit(2);
}

// Get OC4 language mapping: code -> language_id
$langMap = [];
$resLang = q($mysqliOC4, "SELECT language_id, code FROM `{$P4}language`");
while ($row = $resLang->fetch_assoc()) {
    $langMap[(string)$row['code']] = (int)$row['language_id'];
}
if (!$langMap) {
    err('Cannot load OC4 languages.');
    exit(1);
}

// OC2 language_id -> OC4 language_id mapping (best effort)
// Manline uses ru-ru and uk-ua.
$oc2Lang = [];
$resOc2Lang = q($mysqliOC2, "SELECT language_id, code FROM `{$P2}language`");
while ($row = $resOc2Lang->fetch_assoc()) {
    $oc2Lang[(int)$row['language_id']] = (string)$row['code'];
}
if (!$oc2Lang) {
    err('Cannot load OC2 languages.');
    exit(1);
}

$mapLangId = []; // oc2_language_id -> oc4_language_id
foreach ($oc2Lang as $oc2Id => $code) {
    // Normalize a bit
    $candidates = [$code, str_replace('_', '-', $code)];
    $oc4Id = null;
    foreach ($candidates as $cand) {
        if (isset($langMap[$cand])) { $oc4Id = $langMap[$cand]; break; }
    }
    if ($oc4Id !== null) {
        $mapLangId[$oc2Id] = $oc4Id;
    }
}

out('Language map (OC2 -> OC4):');
foreach ($mapLangId as $k => $v) {
    out("  {$k} ({$oc2Lang[$k]}) -> {$v}");
}

// Fetch OC2 blogs (categories)
$blogs = [];
$resBlog = q($mysqliOC2, "SELECT * FROM `{$P2}blog`");
while ($row = $resBlog->fetch_assoc()) {
    $blogs[(int)$row['blog_id']] = $row;
}

$blogDescs = []; // blog_id -> [lang_id => desc]
$resBlogDesc = q($mysqliOC2, "SELECT * FROM `{$P2}blog_description`");
while ($row = $resBlogDesc->fetch_assoc()) {
    $bid = (int)$row['blog_id'];
    $lid = (int)$row['language_id'];
    $blogDescs[$bid][$lid] = $row;
}

// OC2 blog_to_store
$blogStores = []; // blog_id -> [store_id]
$resB2S = q($mysqliOC2, "SELECT * FROM `{$P2}blog_to_store`");
while ($row = $resB2S->fetch_assoc()) {
    $blogStores[(int)$row['blog_id']][] = (int)$row['store_id'];
}

// Fetch OC2 records (articles)
$records = [];
$resRec = q($mysqliOC2, "SELECT * FROM `{$P2}record`");
while ($row = $resRec->fetch_assoc()) {
    $records[(int)$row['record_id']] = $row;
}

$recordDescs = []; // record_id -> [lang_id => desc]
$resRecDesc = q($mysqliOC2, "SELECT * FROM `{$P2}record_description`");
while ($row = $resRecDesc->fetch_assoc()) {
    $rid = (int)$row['record_id'];
    $lid = (int)$row['language_id'];
    $recordDescs[$rid][$lid] = $row;
}

// OC2 record_to_blog
$recToBlog = []; // record_id => [blog_id]
$resR2B = q($mysqliOC2, "SELECT * FROM `{$P2}record_to_blog`");
while ($row = $resR2B->fetch_assoc()) {
    $recToBlog[(int)$row['record_id']][] = (int)$row['blog_id'];
}

out('OC2 counts: blogs=' . count($blogs) . ', records=' . count($records));

$mysqliOC4->begin_transaction();
try {
    if (!$dryRun) {
        // Optional: if force, clear existing CMS content
        if ($force) {
            out('Force enabled: clearing OC4 CMS tables...');
            $tablesToClear = [
                'article_to_layout', 'article_to_store', 'article_description', 'article',
                'topic_to_layout', 'topic_to_store', 'topic_description', 'topic'
            ];
            foreach ($tablesToClear as $t) {
                q($mysqliOC4, "DELETE FROM `{$P4}{$t}`");
            }
        }
    }

    // Insert topics
    $topicInserted = 0;
    foreach ($blogs as $blogId => $b) {
        $sort = (int)($b['sort_order'] ?? 0);
        $status = (int)($b['status'] ?? 0);

        // Note: this OC4 schema has no parent_id/date fields on topic.
        $sqlTopic = "REPLACE INTO `{$P4}topic` SET `topic_id`='{$blogId}', `sort_order`='{$sort}', `status`='{$status}'";
        if ($dryRun) {
            $topicInserted++;
        } else {
            q($mysqliOC4, $sqlTopic);
            $topicInserted++;
        }

        // Stores
        $stores = $blogStores[$blogId] ?? [0];
        foreach (array_unique($stores) as $storeId) {
            $sql = "REPLACE INTO `{$P4}topic_to_store` SET `topic_id`='{$blogId}', `store_id`='" . (int)$storeId . "'";
            if (!$dryRun) q($mysqliOC4, $sql);
        }

        // Descriptions
        $descs = $blogDescs[$blogId] ?? [];
        foreach ($descs as $oc2LangId => $d) {
            if (!isset($mapLangId[$oc2LangId])) continue;
            $oc4LangId = (int)$mapLangId[$oc2LangId];

            // OC2 blog_description fields may include: name, description, meta_title, meta_description, meta_keyword
            $name = $d['name'] ?? '';
            $description = $d['description'] ?? '';
            $meta_title = $d['meta_title'] ?? $name;
            $meta_description = $d['meta_description'] ?? '';
            $meta_keyword = $d['meta_keyword'] ?? '';

            $sql = "REPLACE INTO `{$P4}topic_description` SET `topic_id`='{$blogId}', `language_id`='{$oc4LangId}', `image`='', `name`='" . esc($mysqliOC4, $name) . "', `description`='" . esc($mysqliOC4, $description) . "', `meta_title`='" . esc($mysqliOC4, $meta_title) . "', `meta_description`='" . esc($mysqliOC4, $meta_description) . "', `meta_keyword`='" . esc($mysqliOC4, $meta_keyword) . "'";
            if (!$dryRun) q($mysqliOC4, $sql);
        }
    }

    // Insert articles
    $articleInserted = 0;
    foreach ($records as $recordId => $r) {
        $status = (int)($r['status'] ?? 0);
        $author = (string)($r['author'] ?? '');
        $sort = (int)($r['sort_order'] ?? 0);
        $dateAdded = (string)($r['date_added'] ?? '');

        // Pick topic_id: prefer blog_main if present, else first record_to_blog, else 0.
        $topicId = 0;
        if (isset($r['blog_main']) && (int)$r['blog_main'] > 0) {
            $topicId = (int)$r['blog_main'];
        } elseif (!empty($recToBlog[$recordId][0])) {
            $topicId = (int)$recToBlog[$recordId][0];
        }

        // Note: this OC4 schema has no sort_order column on article.
        $sqlArticle = "REPLACE INTO `{$P4}article` SET `article_id`='{$recordId}', `topic_id`='{$topicId}', `author`='" . esc($mysqliOC4, $author) . "', `rating`='0', `status`='{$status}', `date_added`='" . esc($mysqliOC4, $dateAdded ?: date('Y-m-d H:i:s')) . "', `date_modified`=NOW()";
        if (!$dryRun) q($mysqliOC4, $sqlArticle);
        $articleInserted++;

        // Store (assume 0)
        $sql = "REPLACE INTO `{$P4}article_to_store` SET `article_id`='{$recordId}', `store_id`='0'";
        if (!$dryRun) q($mysqliOC4, $sql);

        // Descriptions
        $descs = $recordDescs[$recordId] ?? [];
        foreach ($descs as $oc2LangId => $d) {
            if (!isset($mapLangId[$oc2LangId])) continue;
            $oc4LangId = (int)$mapLangId[$oc2LangId];

            // OC2 record_description: name, description, tag, meta_title, meta_description, meta_keyword
            $name = $d['name'] ?? '';
            $description = $d['description'] ?? '';
            $tag = $d['tag'] ?? '';
            $meta_title = $d['meta_title'] ?? $name;
            $meta_description = $d['meta_description'] ?? '';
            $meta_keyword = $d['meta_keyword'] ?? '';
            $image = $r['image'] ?? '';

            $sql = "REPLACE INTO `{$P4}article_description` SET `article_id`='{$recordId}', `language_id`='{$oc4LangId}', `image`='" . esc($mysqliOC4, $image) . "', `name`='" . esc($mysqliOC4, $name) . "', `description`='" . esc($mysqliOC4, $description) . "', `tag`='" . esc($mysqliOC4, $tag) . "', `meta_title`='" . esc($mysqliOC4, $meta_title) . "', `meta_description`='" . esc($mysqliOC4, $meta_description) . "', `meta_keyword`='" . esc($mysqliOC4, $meta_keyword) . "'";
            if (!$dryRun) q($mysqliOC4, $sql);
        }
    }

    if ($dryRun) {
        $mysqliOC4->rollback();
        out("DRY-RUN done. Would insert topics={$topicInserted}, articles={$articleInserted}.");
    } else {
        $mysqliOC4->commit();
        out("DONE. Inserted/updated topics={$topicInserted}, articles={$articleInserted}.");
    }

} catch (Throwable $e) {
    $mysqliOC4->rollback();
    err('FAILED: ' . $e->getMessage());
    exit(1);
}
