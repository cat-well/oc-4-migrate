<?php
// Standalone Rozetka auto-import endpoint for a system cron. "cart" in the name
// keeps the host's Cloudflare from caching it (same trick as cart1c.php). Guarded
// by ?key= that must match the stored cron key. Reuses the admin import model.
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
$config->set('config_language_id', 3); // uk-ua — for product-name lookups
$registry->set('config', $config);

date_default_timezone_set($config->get('date_timezone'));

$registry->set('log', new \Opencart\System\Library\Log($config->get('error_filename')));
$registry->set('request', new \Opencart\System\Library\Request());

if ($config->get('db_autostart')) {
	$registry->set('db', new \Opencart\System\Library\DB($config->get('db_engine'), $config->get('db_hostname'), $config->get('db_username'), $config->get('db_password'), $config->get('db_database'), $config->get('db_port'), $config->get('db_ssl_key'), $config->get('db_ssl_cert'), $config->get('db_ssl_ca')));
}

require_once(DIR_EXTENSION . 'manline/admin/model/module/rozetka_orders.php');
$model = new \Opencart\Admin\Model\Extension\Manline\Module\RozetkaOrders($registry);

header('Content-Type: application/json; charset=utf-8');

$key = (string)($registry->get('request')->get['key'] ?? '');
if ($key === '' || !hash_equals($model->cronKey(), $key)) {
	http_response_code(403);
	echo json_encode(['error' => 'forbidden']);
	return;
}

@set_time_limit(0);
$stats = $model->importNewOrders();

$log = new \Opencart\System\Library\Log('manline_rozetka_cron.log');
$log->write('cron: imported=' . $stats['imported'] . ' skipped=' . $stats['skipped'] . ' errors=' . $stats['errors'] . ' unmatched=' . $stats['unmatched'] . ($stats['ok'] ? '' : ' API_ERROR=' . $stats['error']));

echo json_encode([
	'ok'        => $stats['ok'],
	'imported'  => $stats['imported'],
	'skipped'   => $stats['skipped'],
	'errors'    => $stats['errors'],
	'unmatched' => $stats['unmatched'],
	'error'     => $stats['error'],
]);
