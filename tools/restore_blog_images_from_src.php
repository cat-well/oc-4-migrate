<?php
// Restore CMS article images in OC4 DB from source dump DB (manline_src).
// Writes ONLY to OC4 DB.

$c = file_get_contents(__DIR__ . '/../config.php');
$get = function(string $name) use ($c): string {
  $re = "/define\\(\\s*['\"]" . preg_quote($name,'/') . "['\"]\\s*,\\s*['\"]([^'\"]*)['\"]\\s*\\)\\s*;/";
  return preg_match($re, $c, $m) ? (string)$m[1] : '';
};

$h=$get('DB_HOSTNAME');
$u=$get('DB_USERNAME');
$p=$get('DB_PASSWORD');
$dstDb=$get('DB_DATABASE');
$prefix=$get('DB_PREFIX');

$src = new mysqli($h, $u, $p, 'manline_src');
if ($src->connect_errno) { fwrite(STDERR, "src connect: {$src->connect_error}\n"); exit(1);} 
$src->set_charset('utf8mb4');

$dst = new mysqli($h, $u, $p, $dstDb);
if ($dst->connect_errno) { fwrite(STDERR, "dst connect: {$dst->connect_error}\n"); exit(1);} 
$dst->set_charset('utf8mb4');

$imageDir = realpath(__DIR__ . '/../image');
if (!$imageDir) { fwrite(STDERR, "cannot resolve image dir\n"); exit(1);} 

$ids = [];
$res = $dst->query("SELECT DISTINCT article_id FROM `{$prefix}article`");
while ($row = $res->fetch_assoc()) $ids[] = (int)$row['article_id'];

$updated = 0;
foreach ($ids as $id) {
  $r = $src->query("SELECT image FROM `{$prefix}record` WHERE record_id=" . (int)$id . " LIMIT 1");
  if (!$r || !$r->num_rows) continue;

  $img = (string)$r->fetch_assoc()['image'];
  if ($img === '') continue;

  $full = $imageDir . '/' . $img;
  if (!is_file($full)) {
    // keep current value; source points to missing file
    continue;
  }

  $dst->query("UPDATE `{$prefix}article_description` SET image='" . $dst->real_escape_string($img) . "' WHERE article_id=" . (int)$id);
  $updated++;
}

echo "Restored image paths for {$updated} articles (only when file exists).\n";
