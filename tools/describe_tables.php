<?php
$c = file_get_contents(__DIR__ . '/../config.php');
$get = function(string $name) use ($c): string {
  $re = "/define\\(\\s*['\"]" . preg_quote($name,'/') . "['\"]\\s*,\\s*['\"]([^'\"]*)['\"]\\s*\\)\\s*;/";
  return preg_match($re, $c, $m) ? (string)$m[1] : '';
};
$h=$get('DB_HOSTNAME'); $u=$get('DB_USERNAME'); $p=$get('DB_PASSWORD'); $x=$get('DB_PREFIX');
$db=new mysqli($h,$u,$p,$get('DB_DATABASE'));
$db->set_charset('utf8mb4');
foreach (['topic','topic_description','article','article_description'] as $t) {
  $table = $x.$t;
  echo "\n== $table ==\n";
  $res = $db->query("DESCRIBE `$table`");
  while($row=$res->fetch_assoc()) {
    echo $row['Field'] . "\t" . $row['Type'] . "\n";
  }
}
