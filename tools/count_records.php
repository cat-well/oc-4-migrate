<?php
$c = file_get_contents(__DIR__ . '/../config.php');
$get = function(string $name) use ($c): string {
  $re = "/define\\(\\s*['\"]" . preg_quote($name,'/') . "['\"]\\s*,\\s*['\"]([^'\"]*)['\"]\\s*\\)\\s*;/";
  return preg_match($re, $c, $m) ? (string)$m[1] : '';
};
$h=$get('DB_HOSTNAME'); $u=$get('DB_USERNAME'); $p=$get('DB_PASSWORD'); $x=$get('DB_PREFIX');
$db=new mysqli($h,$u,$p,'manline_src');
$db->set_charset('utf8mb4');
$r=$db->query("SELECT COUNT(*) c FROM `{$x}record`");
echo "record count: ".$r->fetch_assoc()['c']."\n";
$r=$db->query("SELECT status, COUNT(*) c FROM `{$x}record` GROUP BY status ORDER BY status");
while($row=$r->fetch_assoc()){echo "status {$row['status']}: {$row['c']}\n";}
$r=$db->query("SELECT MIN(date_added) mi, MAX(date_added) ma FROM `{$x}record`");
$row=$r->fetch_assoc();
echo "date_added range: {$row['mi']} .. {$row['ma']}\n";
