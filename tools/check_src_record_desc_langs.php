<?php
$c = file_get_contents(__DIR__ . '/../config.php');
$get = function(string $name) use ($c): string {
  $re = "/define\\(\\s*['\"]" . preg_quote($name,'/') . "['\"]\\s*,\\s*['\"]([^'\"]*)['\"]\\s*\\)\\s*;/";
  return preg_match($re, $c, $m) ? (string)$m[1] : '';
};
$h=$get('DB_HOSTNAME'); $u=$get('DB_USERNAME'); $p=$get('DB_PASSWORD'); $x=$get('DB_PREFIX');
$db=new mysqli($h,$u,$p,'manline_src');
$db->set_charset('utf8mb4');
$res=$db->query("SELECT language_id, COUNT(*) cnt FROM `{$x}record_description` GROUP BY language_id ORDER BY language_id");
while($row=$res->fetch_assoc()){
  echo $row['language_id'] . ": " . $row['cnt'] . "\n";
}
