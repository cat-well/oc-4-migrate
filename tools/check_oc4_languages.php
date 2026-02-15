<?php
$c = file_get_contents(__DIR__ . '/../config.php');
$get = function(string $name) use ($c): string {
  $re = "/define\\(\\s*['\"]" . preg_quote($name,'/') . "['\"]\\s*,\\s*['\"]([^'\"]*)['\"]\\s*\\)\\s*;/";
  return preg_match($re, $c, $m) ? (string)$m[1] : '';
};
$h=$get('DB_HOSTNAME'); $u=$get('DB_USERNAME'); $p=$get('DB_PASSWORD'); $dbn=$get('DB_DATABASE'); $x=$get('DB_PREFIX');
$db=new mysqli($h,$u,$p,$dbn);
if($db->connect_errno){fwrite(STDERR,$db->connect_error."\n"); exit(1);} 
$db->set_charset('utf8mb4');

echo "DB: $dbn prefix: $x\n\n";

$res=$db->query("SELECT language_id, name, code, status, sort_order FROM `{$x}language` ORDER BY language_id");
echo "Languages:\n";
while($row=$res->fetch_assoc()){
  echo "- {$row['language_id']}\t{$row['code']}\t{$row['name']}\tstatus={$row['status']}\tsort={$row['sort_order']}\n";
}

// config_language_id may be stored in setting table as serialized value.
$cfgId = null;
$res=$db->query("SELECT value, serialized FROM `{$x}setting` WHERE `key`='config_language_id' LIMIT 1");
if($res && $res->num_rows){
  $row=$res->fetch_assoc();
  $val=$row['value'];
  if ((int)$row['serialized'] === 1) {
    $tmp=@unserialize($val);
    $val=is_scalar($tmp)?(string)$tmp:$val;
  }
  $cfgId=(int)$val;
}

echo "\nconfig_language_id (setting): " . ($cfgId===null?'(missing)':$cfgId) . "\n";

$res=$db->query("SELECT COUNT(*) c FROM `{$x}article_description` GROUP BY language_id");
