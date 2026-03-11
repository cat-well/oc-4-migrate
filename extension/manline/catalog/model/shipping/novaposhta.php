<?php
namespace Opencart\Catalog\Model\Extension\Manline\Shipping;

class Novaposhta extends \Opencart\System\Engine\Model {
	/**
	 * @param array<string, mixed> $address
	 *
	 * @return array<string, mixed>
	 */
	public function getQuote(array $address): array {
		$this->load->language('extension/manline/shipping/novaposhta');

		// Geo Zone
		$this->load->model('localisation/geo_zone');

		$results = $this->model_localisation_geo_zone->getGeoZone(
			(int)$this->config->get('shipping_novaposhta_geo_zone_id'),
			(int)($address['country_id'] ?? 0),
			(int)($address['zone_id'] ?? 0)
		);

		if (!$this->config->get('shipping_novaposhta_geo_zone_id')) {
			$status = true;
		} elseif ($results) {
			$status = true;
		} else {
			$status = false;
		}

		$method_data = [];

		if (!$status) {
			return $method_data;
		}

		$sub_total = (float)$this->cart->getSubTotal();
		$free_total = (float)$this->config->get('shipping_novaposhta_free_total');
		$is_free = $free_total > 0 && $sub_total >= $free_total;
		$tax_class_id = (int)$this->config->get('shipping_novaposhta_tax_class_id');

		$quote_data = [];

		if ($this->config->get('shipping_novaposhta_branch_status')) {
			$branch_cost = $is_free ? 0.0 : (float)$this->config->get('shipping_novaposhta_branch_cost');

			$quote_data['branch'] = [
				'code'         => 'novaposhta.branch',
				'name'         => $this->language->get('text_branch'),
				'description'  => $this->language->get('text_branch_desc'),
				'cost'         => $branch_cost,
				'tax_class_id' => $tax_class_id,
				'text'         => $this->formatCost($branch_cost, $tax_class_id)
			];
		}

		if ($this->config->get('shipping_novaposhta_courier_status')) {
			$courier_cost = $is_free ? 0.0 : (float)$this->config->get('shipping_novaposhta_courier_cost');

			$quote_data['courier'] = [
				'code'         => 'novaposhta.courier',
				'name'         => $this->language->get('text_courier'),
				'description'  => $this->language->get('text_courier_desc'),
				'cost'         => $courier_cost,
				'tax_class_id' => $tax_class_id,
				'text'         => $this->formatCost($courier_cost, $tax_class_id)
			];
		}

		if ($this->config->get('shipping_novaposhta_locker_status')) {
			$locker_cost = $is_free ? 0.0 : (float)$this->config->get('shipping_novaposhta_locker_cost');

			$quote_data['locker'] = [
				'code'         => 'novaposhta.locker',
				'name'         => $this->language->get('text_locker'),
				'description'  => $this->language->get('text_locker_desc'),
				'cost'         => $locker_cost,
				'tax_class_id' => $tax_class_id,
				'text'         => $this->formatCost($locker_cost, $tax_class_id)
			];
		}

		if (!$quote_data) {
			return $method_data;
		}

		$method_data = [
			'code'       => 'novaposhta',
			'name'       => $this->language->get('heading_title'),
			'quote'      => $quote_data,
			'sort_order' => (int)$this->config->get('shipping_novaposhta_sort_order'),
			'error'      => false
		];

		return $method_data;
	}

	/**
	 * @param int $order_id
	 * @param array<string, mixed> $meta
	 *
	 * @return void
	 */
	public function saveOrderMeta(int $order_id, array $meta): void {
		if ($order_id <= 0) {
			return;
		}

		$this->ensureOrderMetaTable();

		$shipping_code = trim((string)($meta['shipping_code'] ?? ''));

		if ($shipping_code === '' || strpos($shipping_code, 'novaposhta.') !== 0) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "order_novaposhta` WHERE order_id = '" . (int)$order_id . "'");

			return;
		}

		$delivery_type = $this->extractDeliveryType($shipping_code);
		$city = trim((string)($meta['city'] ?? ''));
		$city_ref = trim((string)($meta['city_ref'] ?? ''));
		$address = trim((string)($meta['address'] ?? ''));
		$address_ref = trim((string)($meta['address_ref'] ?? ''));
		$zone_id = (int)($meta['zone_id'] ?? 0);
		$zone = trim((string)($meta['zone'] ?? ''));
		$country_id = (int)($meta['country_id'] ?? 0);
		$country = trim((string)($meta['country'] ?? ''));
		$payload = json_encode($meta, JSON_UNESCAPED_UNICODE);

		if (!is_string($payload)) {
			$payload = '{}';
		}

		$this->db->query(
			"INSERT INTO `" . DB_PREFIX . "order_novaposhta`
			SET order_id = '" . (int)$order_id . "',
				shipping_code = '" . $this->db->escape($shipping_code) . "',
				delivery_type = '" . $this->db->escape($delivery_type) . "',
				city = '" . $this->db->escape($city) . "',
				city_ref = '" . $this->db->escape($city_ref) . "',
				address = '" . $this->db->escape($address) . "',
				address_ref = '" . $this->db->escape($address_ref) . "',
				zone_id = '" . (int)$zone_id . "',
				zone = '" . $this->db->escape($zone) . "',
				country_id = '" . (int)$country_id . "',
				country = '" . $this->db->escape($country) . "',
				payload = '" . $this->db->escape($payload) . "',
				date_added = NOW(),
				date_modified = NOW()
			ON DUPLICATE KEY UPDATE
				shipping_code = VALUES(shipping_code),
				delivery_type = VALUES(delivery_type),
				city = VALUES(city),
				city_ref = VALUES(city_ref),
				address = VALUES(address),
				address_ref = VALUES(address_ref),
				zone_id = VALUES(zone_id),
				zone = VALUES(zone),
				country_id = VALUES(country_id),
				country = VALUES(country),
				payload = VALUES(payload),
				date_modified = NOW()"
		);
	}

	/**
	 * @param int $order_id
	 *
	 * @return array<string, mixed>
	 */
	public function getOrderMeta(int $order_id): array {
		if ($order_id <= 0) {
			return [];
		}

		$this->ensureOrderMetaTable();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_novaposhta` WHERE order_id = '" . (int)$order_id . "' LIMIT 1");

		if (!$query->num_rows) {
			return [];
		}

		$row = $query->row;

		if (!empty($row['payload']) && is_string($row['payload'])) {
			$decoded = json_decode($row['payload'], true);

			if (is_array($decoded)) {
				$row['payload'] = $decoded;
			}
		}

		return $row;
	}

	private function formatCost(float $cost, int $tax_class_id): string {
		if ($cost <= 0) {
			return (string)$this->language->get('text_free');
		}

		return $this->currency->format(
			$this->tax->calculate($cost, $tax_class_id, (bool)$this->config->get('config_tax')),
			$this->session->data['currency']
		);
	}

	private function extractDeliveryType(string $shipping_code): string {
		$parts = explode('.', $shipping_code);

		if (isset($parts[1]) && $parts[1] !== '') {
			return trim((string)$parts[1]);
		}

		return '';
	}

	private function ensureOrderMetaTable(): void {
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
				`ttn_ref` VARCHAR(64) NOT NULL DEFAULT '',
				`ttn_number` VARCHAR(32) NOT NULL DEFAULT '',
				`ttn_error` TEXT NULL,
				`ttn_payload` MEDIUMTEXT NULL,
				`ttn_date_created` DATETIME NULL,
				`ttn_date_modified` DATETIME NULL,
				`date_added` DATETIME NOT NULL,
				`date_modified` DATETIME NOT NULL,
				PRIMARY KEY (`order_id`),
				KEY `city_ref` (`city_ref`),
				KEY `address_ref` (`address_ref`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);
	}
}
