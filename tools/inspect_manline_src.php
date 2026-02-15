<?php
$c = file_get_contents(__DIR__ . '/../config.php');
if ($c === false) { fwrite(STDERR, "cannot read config.php\n"); exit(1);} 
$get = function(string $name) use ($c): string {
  $re1 = "/define\\(\\s*['\"]" . preg_quote($name,'/') . "['\"]\\s*,\\s*['\"]([^'\"]*)['\"]\\s*\\)\\s*;/";
  if (preg_match($re1, $c, $m)) return (string)$m[1];
  return '';
};
$h=$get('DB_HOSTNAME');
$u=$get('DB_USERNAME');
$p=$get('DB_PASSWORD');
$x=$get('DB_PREFIX');
$db = new mysqli($h,$u,$p,'manline_src');
if ($db->connect_errno) { fwrite(STDERR, $db->connect_error."\n"); exit(1);} 
$db->set_charset('utf8mb4');
foreach (['record%','blog%'] as $like) {
  $sql = "SHOW TABLES LIKE '" . $db->real_escape_string($x . $like) . "'";
  $res = $db->query($sql);
  echo "tables like {$x}{$like}: " . $res->num_rows . "\n";
  while ($row = $res->fetch_row()) echo $row[0] . "\n";
}
