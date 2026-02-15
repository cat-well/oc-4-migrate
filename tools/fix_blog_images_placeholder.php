<?php
// Set placeholder image for CMS articles whose image file is missing.
$c = file_get_contents(__DIR__ . '/../config.php');
$get = function(string $name) use ($c): string {
  $re = "/define\\(\\s*['\"]" . preg_quote($name,'/') . "['\"]\\s*,\\s*['\"]([^'\"]*)['\"]\\s*\\)\\s*;/";
  return preg_match($re, $c, $m) ? (string)$m[1] : '';
};
$h=$get('DB_HOSTNAME'); $u=$get('DB_USERNAME'); $p=$get('DB_PASSWORD'); $dbn=$get('DB_DATABASE'); $x=$get('DB_PREFIX');
$db=new mysqli($h,$u,$p,$dbn);
if($db->connect_errno){fwrite(STDERR,$db->connect_error."\n"); exit(1);} 
$db->set_charset('utf8mb4');

$placeholder = 'data/blog/blog.jpg';
$dirImage = realpath(__DIR__ . '/../image');
if (!$dirImage) { fwrite(STDERR, "Cannot resolve image dir\n"); exit(1);} 
$phPath = $dirImage . '/' . $placeholder;
if (!is_file($phPath)) { fwrite(STDERR, "Placeholder missing: $phPath\n"); exit(1);} 

$res = $db->query("SELECT article_id, language_id, image FROM `{$x}article_description`");
$updated = 0; $total = 0;
while($row=$res->fetch_assoc()){
  $total++;
  $img = (string)$row['image'];
  if ($img === '') continue;
  $path = $dirImage . '/' . $img;
  if (!is_file($path)) {
    $aid = (int)$row['article_id'];
    $lid = (int)$row['language_id'];
    $db->query("UPDATE `{$x}article_description` SET image='".$db->real_escape_string($placeholder)."' WHERE article_id={$aid} AND language_id={$lid}");
    $updated++;
  }
}

echo "Checked {$total} article_description rows. Updated {$updated} to placeholder {$placeholder}.\n";
