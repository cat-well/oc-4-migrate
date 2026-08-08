<?php
// Standalone 1C exchange entry — bypasses OpenCart's front controller, whose startup pre-actions redirect (a browser follows them, the 1C client does not → empty response). Minimal registry, controller dispatched directly.
chdir(__DIR__ . '/..');

require_once('config.php');
require_once(DIR_SYSTEM . 'startup.php');

$autoloader = new \Opencart\System\Engine\Autoloader();
$autoloader->register('Opencart\\' . APPLICATION, DIR_APPLICATION);
$autoloader->register('Opencart\Extension', DIR_EXTENSION);
$autoloader->register('Opencart\System', DIR_SYSTEM);
require_once(DIR_SYSTEM . 'vendor.php');

$registry = new \Opencart\System\Engine\Registry();
$registry->set('autoloader', $autoloader);

$config = new \Opencart\System\Engine\Config();
$config->addPath(DIR_CONFIG);
$config->load('default');
$config->load(strtolower(APPLICATION));
$config->set('application', APPLICATION);
$registry->set('config', $config);

date_default_timezone_set($config->get('date_timezone'));

$registry->set('log', new \Opencart\System\Library\Log($config->get('error_filename')));
$registry->set('event', new \Opencart\System\Engine\Event($registry));
$registry->set('factory', new \Opencart\System\Engine\Factory($registry));
$registry->set('load', new \Opencart\System\Engine\Loader($registry));
$registry->set('request', new \Opencart\System\Library\Request());
$registry->set('response', new \Opencart\System\Library\Response());
$registry->set('cache', new \Opencart\System\Library\Cache($config->get('cache_engine'), $config->get('cache_expire')));

if ($config->get('db_autostart')) {
	$registry->set('db', new \Opencart\System\Library\DB($config->get('db_engine'), $config->get('db_hostname'), $config->get('db_username'), $config->get('db_password'), $config->get('db_database'), $config->get('db_port'), $config->get('db_ssl_key'), $config->get('db_ssl_cert'), $config->get('db_ssl_ca')));
}

require_once(DIR_EXTENSION . 'manline/catalog/controller/exchange.php');

(new \Opencart\Catalog\Controller\Extension\Manline\Exchange($registry))->index();

$registry->get('response')->output();
