<?php
namespace Opencart\Admin\Controller\Extension\Manline\Shipping;

class Novaposhta extends \Opencart\System\Engine\Controller {
	public function install(): void {
		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/manline/shipping/novaposhta');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/manline/shipping/novaposhta');

		$this->load->model('setting/setting');

		$defaults = [
			'shipping_novaposhta_api_key' => '',
			'shipping_novaposhta_api_url' => 'https://api.novaposhta.ua/v2.0/json/',
			'shipping_novaposhta_free_total' => 1500,
			'shipping_novaposhta_branch_cost' => 50,
			'shipping_novaposhta_courier_cost' => 50,
			'shipping_novaposhta_locker_cost' => 50,
			'shipping_novaposhta_replace_flat' => 1,
			'shipping_novaposhta_tax_class_id' => 0,
			'shipping_novaposhta_geo_zone_id' => 0,
			'shipping_novaposhta_status' => 1,
			'shipping_novaposhta_branch_status' => 1,
			'shipping_novaposhta_courier_status' => 1,
			'shipping_novaposhta_locker_status' => 1,
			'shipping_novaposhta_sort_order' => 10
		];

		$this->model_setting_setting->editSetting('shipping_novaposhta', $defaults);
		$this->createOrderMetaTable();
	}

	public function index(): void {
		$this->load->language('extension/manline/shipping/novaposhta');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/manline/shipping/novaposhta', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/manline/shipping/novaposhta.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping');

		$data['shipping_novaposhta_api_key'] = (string)$this->configValue('shipping_novaposhta_api_key', '');
		$data['shipping_novaposhta_api_url'] = (string)$this->configValue('shipping_novaposhta_api_url', 'https://api.novaposhta.ua/v2.0/json/');
		$data['shipping_novaposhta_free_total'] = (float)$this->configValue('shipping_novaposhta_free_total', 1500);
		$data['shipping_novaposhta_branch_cost'] = (float)$this->configValue('shipping_novaposhta_branch_cost', 50);
		$data['shipping_novaposhta_courier_cost'] = (float)$this->configValue('shipping_novaposhta_courier_cost', 50);
		$data['shipping_novaposhta_locker_cost'] = (float)$this->configValue('shipping_novaposhta_locker_cost', 50);
		$data['shipping_novaposhta_replace_flat'] = (int)$this->configValue('shipping_novaposhta_replace_flat', 1);
		$data['shipping_novaposhta_branch_status'] = (int)$this->configValue('shipping_novaposhta_branch_status', 1);
		$data['shipping_novaposhta_courier_status'] = (int)$this->configValue('shipping_novaposhta_courier_status', 1);
		$data['shipping_novaposhta_locker_status'] = (int)$this->configValue('shipping_novaposhta_locker_status', 1);

		// Tax Class
		$this->load->model('localisation/tax_class');

		$data['shipping_novaposhta_tax_class_id'] = (int)$this->configValue('shipping_novaposhta_tax_class_id', 0);
		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

		// Geo Zone
		$this->load->model('localisation/geo_zone');

		$data['shipping_novaposhta_geo_zone_id'] = (int)$this->configValue('shipping_novaposhta_geo_zone_id', 0);
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['shipping_novaposhta_status'] = (int)$this->configValue('shipping_novaposhta_status', 1);
		$data['shipping_novaposhta_sort_order'] = (int)$this->configValue('shipping_novaposhta_sort_order', 10);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/manline/shipping/novaposhta', $data));
	}

	public function save(): void {
		$this->load->language('extension/manline/shipping/novaposhta');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/manline/shipping/novaposhta')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$post = $this->request->post;

			$post['shipping_novaposhta_api_key'] = trim((string)($post['shipping_novaposhta_api_key'] ?? ''));
			$post['shipping_novaposhta_api_url'] = trim((string)($post['shipping_novaposhta_api_url'] ?? 'https://api.novaposhta.ua/v2.0/json/'));
			$post['shipping_novaposhta_free_total'] = (float)($post['shipping_novaposhta_free_total'] ?? 1500);
			$post['shipping_novaposhta_branch_cost'] = (float)($post['shipping_novaposhta_branch_cost'] ?? 50);
			$post['shipping_novaposhta_courier_cost'] = (float)($post['shipping_novaposhta_courier_cost'] ?? 50);
			$post['shipping_novaposhta_locker_cost'] = (float)($post['shipping_novaposhta_locker_cost'] ?? 50);
			$post['shipping_novaposhta_tax_class_id'] = (int)($post['shipping_novaposhta_tax_class_id'] ?? 0);
			$post['shipping_novaposhta_geo_zone_id'] = (int)($post['shipping_novaposhta_geo_zone_id'] ?? 0);
			$post['shipping_novaposhta_status'] = !empty($post['shipping_novaposhta_status']) ? 1 : 0;
			$post['shipping_novaposhta_branch_status'] = !empty($post['shipping_novaposhta_branch_status']) ? 1 : 0;
			$post['shipping_novaposhta_courier_status'] = !empty($post['shipping_novaposhta_courier_status']) ? 1 : 0;
			$post['shipping_novaposhta_locker_status'] = !empty($post['shipping_novaposhta_locker_status']) ? 1 : 0;
			$post['shipping_novaposhta_replace_flat'] = !empty($post['shipping_novaposhta_replace_flat']) ? 1 : 0;
			$post['shipping_novaposhta_sort_order'] = (int)($post['shipping_novaposhta_sort_order'] ?? 10);

			// Setting
			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting('shipping_novaposhta', $post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function configValue(string $key, $default) {
		$value = $this->config->get($key);

		if ($value === null || $value === '') {
			return $default;
		}

		return $value;
	}

	private function createOrderMetaTable(): void {
		$this->db->query(
			"CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "order_novaposhta` (
				`order_id` INT(11) NOT NULL,
				`shipping_code` VARCHAR(64) NOT NULL DEFAULT '',
				`delivery_type` VARCHAR(32) NOT NULL DEFAULT '',
				`city` VARCHAR(128) NOT NULL DEFAULT '',
				`city_ref` VARCHAR(64) NOT NULL DEFAULT '',
				`address` VARCHAR(255) NOT NULL DEFAULT '',
				`address_ref` VARCHAR(64) NOT NULL DEFAULT '',
				`zone_id` INT(11) NOT NULL DEFAULT '0',
				`zone` VARCHAR(128) NOT NULL DEFAULT '',
				`country_id` INT(11) NOT NULL DEFAULT '0',
				`country` VARCHAR(128) NOT NULL DEFAULT '',
				`payload` TEXT NULL,
				`date_added` DATETIME NOT NULL,
				`date_modified` DATETIME NOT NULL,
				PRIMARY KEY (`order_id`),
				KEY `city_ref` (`city_ref`),
				KEY `address_ref` (`address_ref`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);
	}
}
