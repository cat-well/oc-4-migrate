<?php
namespace Opencart\Admin\Model\Extension\Manline\Shipping;

class Novaposhta extends \Opencart\System\Engine\Model {
	public function getOrderMeta(int $order_id): array {
		if ($order_id <= 0) {
			return [];
		}

		$this->ensureOrderMetaTable();
		$this->ensureTtnColumns();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_novaposhta` WHERE order_id = '" . (int)$order_id . "' LIMIT 1");

		if (!$query->num_rows) {
			return [];
		}

		$row = $query->row;

		if (!empty($row['payload']) && is_string($row['payload'])) {
			$payload = json_decode($row['payload'], true);

			if (is_array($payload)) {
				$row['payload'] = $payload;
			}
		}

		if (!empty($row['ttn_payload']) && is_string($row['ttn_payload'])) {
			$ttn_payload = json_decode($row['ttn_payload'], true);

			if (is_array($ttn_payload)) {
				$row['ttn_payload'] = $ttn_payload;
			}
		}

		return $row;
	}

	public function createTtnForOrder(int $order_id): array {
		if ($order_id <= 0) {
			return ['success' => false, 'error' => 'Order ID is missing.'];
		}

		$this->ensureOrderMetaTable();
		$this->ensureTtnColumns();

		$this->load->model('sale/order');

		$order_info = $this->model_sale_order->getOrder($order_id);

		if (!$order_info) {
			return ['success' => false, 'error' => 'Order was not found.'];
		}

		$shipping_code = (string)($order_info['shipping_method']['code'] ?? '');

		if (!$shipping_code || strpos($shipping_code, 'novaposhta.') !== 0) {
			return ['success' => false, 'error' => 'This order does not use Nova Poshta shipping.'];
		}

		$meta = $this->getOrderMeta($order_id);

		if (!$meta) {
			$this->createFallbackMeta($order_id, $order_info, $shipping_code);
			$meta = $this->getOrderMeta($order_id);
		}

		if (!$meta) {
			return ['success' => false, 'error' => 'Nova Poshta delivery data is missing for this order.'];
		}

		if (!empty($meta['ttn_number'])) {
			return [
				'success' => true,
				'ttn_number' => (string)$meta['ttn_number'],
				'ttn_ref' => (string)($meta['ttn_ref'] ?? ''),
				'ttn_date_created' => (string)($meta['ttn_date_created'] ?? ''),
				'message' => 'TTN already exists for this order.'
			];
		}

		$api_key = trim((string)$this->config->get('shipping_novaposhta_api_key'));
		$api_url = trim((string)$this->config->get('shipping_novaposhta_api_url'));

		if ($api_url === '') {
			$api_url = 'https://api.novaposhta.ua/v2.0/json/';
		}

		if ($api_key === '') {
			return ['success' => false, 'error' => 'Nova Poshta API key is not configured.'];
		}

		try {
			$delivery_type = (string)($meta['delivery_type'] ?? $this->extractDeliveryType($shipping_code));
			$city_ref = $this->resolveCityRef($meta, $order_info, $api_key, $api_url);
			$address_ref = $this->resolveAddressRef($meta, $delivery_type, $city_ref, $api_key, $api_url);

			if ($city_ref === '' || $address_ref === '') {
				throw new \RuntimeException('City or destination address ref is missing for TTN creation.');
			}

			$sender = $this->fetchSenderData($api_key, $api_url);
			$recipient = $this->fetchOrCreateRecipientData($order_info, $city_ref, $api_key, $api_url);
			$order_data = $this->getOrderData($order_id, $order_info);

			$payload = [
				'PayerType' => 'Recipient',
				'PaymentMethod' => 'Cash',
				'DateTime' => date('d.m.Y'),
				'CargoType' => 'Cargo',
				'VolumeGeneral' => '0.0004',
				'Weight' => $order_data['weight'],
				'ServiceType' => $this->mapServiceType($delivery_type),
				'SeatsAmount' => $order_data['seats_amount'],
				'Description' => $order_data['description'],
				'Cost' => $order_data['cost'],
				'CitySender' => $sender['city_ref'],
				'Sender' => $sender['ref'],
				'SenderAddress' => $sender['address_ref'],
				'ContactSender' => $sender['contact_ref'],
				'SendersPhone' => $sender['phone'],
				'CityRecipient' => $city_ref,
				'Recipient' => $recipient['ref'],
				'RecipientAddress' => $address_ref,
				'ContactRecipient' => $recipient['contact_ref'],
				'RecipientsPhone' => $recipient['phone']
			];

			$response = $this->callApi($api_key, $api_url, 'InternetDocument', 'save', $payload);

			if (empty($response['success'])) {
				$error = $this->getApiError($response, 'Nova Poshta API did not create TTN.');
				$this->updateTtnError($order_id, $error, $response);

				return ['success' => false, 'error' => $error];
			}

			$data = $response['data'][0] ?? [];
			$ttn_number = trim((string)($data['IntDocNumber'] ?? ''));
			$ttn_ref = trim((string)($data['Ref'] ?? ''));

			if ($ttn_number === '' || $ttn_ref === '') {
				$error = 'Nova Poshta API returned success without TTN identifiers.';
				$this->updateTtnError($order_id, $error, $response);

				return ['success' => false, 'error' => $error];
			}

			$this->updateTtnSuccess($order_id, $ttn_number, $ttn_ref, $response);

			return [
				'success' => true,
				'ttn_number' => $ttn_number,
				'ttn_ref' => $ttn_ref,
				'ttn_date_created' => date('Y-m-d H:i:s')
			];
		} catch (\Throwable $e) {
			$this->updateTtnError($order_id, $e->getMessage(), []);

			return ['success' => false, 'error' => $e->getMessage()];
		}
	}

	private function getOrderData(int $order_id, array $order_info): array {
		$this->load->model('sale/order');

		$products = $this->model_sale_order->getProducts($order_id);

		$description = 'Order #' . $order_id;
		$seats_amount = 1;

		if ($products) {
			$description = trim((string)$products[0]['name']);

			if (count($products) > 1) {
				$description .= ' +' . (count($products) - 1);
			}

			$quantity_total = 0;

			foreach ($products as $product) {
				$quantity_total += (int)($product['quantity'] ?? 0);
			}

			if ($quantity_total > 0) {
				$seats_amount = $quantity_total;
			}
		}

		$description = oc_substr($description, 0, 90);

		$cost = (float)($order_info['total'] ?? 0.0);
		$cost = $cost > 0 ? $cost : 1.0;

		return [
			'description' => $description,
			'seats_amount' => (string)$seats_amount,
			'weight' => '1',
			'cost' => number_format($cost, 2, '.', '')
		];
	}

	private function fetchOrCreateRecipientData(array $order_info, string $city_ref, string $api_key, string $api_url): array {
		$firstname = trim((string)($order_info['shipping_firstname'] ?? ''));
		$lastname = trim((string)($order_info['shipping_lastname'] ?? ''));

		if ($firstname === '') {
			$firstname = trim((string)($order_info['firstname'] ?? ''));
		}

		if ($lastname === '') {
			$lastname = trim((string)($order_info['lastname'] ?? ''));
		}

		if ($firstname === '' || $lastname === '') {
			throw new \RuntimeException('Recipient first name and last name are required for TTN creation.');
		}

		$phone = $this->normalizePhone((string)($order_info['telephone'] ?? ''));

		if ($phone === '') {
			throw new \RuntimeException('Recipient phone is required for TTN creation.');
		}

		$recipient_response = $this->callApi(
			$api_key,
			$api_url,
			'Counterparty',
			'save',
			[
				'FirstName' => $firstname,
				'LastName' => $lastname,
				'Phone' => $phone,
				'Email' => trim((string)($order_info['email'] ?? '')),
				'CounterpartyType' => 'PrivatePerson',
				'CounterpartyProperty' => 'Recipient',
				'CityRef' => $city_ref
			]
		);

		if (empty($recipient_response['success'])) {
			throw new \RuntimeException($this->getApiError($recipient_response, 'Unable to create recipient in Nova Poshta API.'));
		}

		$recipient_data = $recipient_response['data'][0] ?? [];
		$recipient_ref = trim((string)($recipient_data['Ref'] ?? ''));
		$contact_ref = '';

		if (!empty($recipient_data['ContactPerson']['data'][0]['Ref'])) {
			$contact_ref = trim((string)$recipient_data['ContactPerson']['data'][0]['Ref']);
		}

		if ($recipient_ref === '') {
			throw new \RuntimeException('Nova Poshta API did not return recipient ref.');
		}

		if ($contact_ref === '') {
			$contacts_response = $this->callApi($api_key, $api_url, 'Counterparty', 'getCounterpartyContactPersons', ['Ref' => $recipient_ref]);

			if (!empty($contacts_response['success']) && !empty($contacts_response['data'][0]['Ref'])) {
				$contact_ref = trim((string)$contacts_response['data'][0]['Ref']);
			}
		}

		if ($contact_ref === '') {
			throw new \RuntimeException('Nova Poshta API did not return recipient contact ref.');
		}

		return [
			'ref' => $recipient_ref,
			'contact_ref' => $contact_ref,
			'phone' => $phone
		];
	}

	private function fetchSenderData(string $api_key, string $api_url): array {
		$counterparties_response = $this->callApi(
			$api_key,
			$api_url,
			'Counterparty',
			'getCounterparties',
			[
				'CounterpartyProperty' => 'Sender',
				'Page' => 1
			]
		);

		if (empty($counterparties_response['success']) || empty($counterparties_response['data'][0]['Ref'])) {
			throw new \RuntimeException($this->getApiError($counterparties_response, 'Nova Poshta sender counterparties are not available.'));
		}

		$sender = $counterparties_response['data'][0];
		$sender_ref = trim((string)($sender['Ref'] ?? ''));

		$contacts_response = $this->callApi($api_key, $api_url, 'Counterparty', 'getCounterpartyContactPersons', ['Ref' => $sender_ref]);

		if (empty($contacts_response['success']) || empty($contacts_response['data'][0]['Ref'])) {
			throw new \RuntimeException($this->getApiError($contacts_response, 'Nova Poshta sender contacts are not available.'));
		}

		$sender_contact = $contacts_response['data'][0];
		$sender_contact_ref = trim((string)($sender_contact['Ref'] ?? ''));
		$sender_phone = $this->normalizePhone($this->extractPhone((string)($sender_contact['Phones'] ?? '')));

		if ($sender_phone === '') {
			throw new \RuntimeException('Nova Poshta sender contact phone is missing.');
		}

		$addresses_response = $this->callApi($api_key, $api_url, 'Counterparty', 'getCounterpartyAddresses', ['Ref' => $sender_ref, 'Page' => 1]);

		if (empty($addresses_response['success']) || empty($addresses_response['data'])) {
			throw new \RuntimeException($this->getApiError($addresses_response, 'Nova Poshta sender addresses are not available.'));
		}

		$sender_address = [];

		foreach ($addresses_response['data'] as $address) {
			if (($address['AddressType'] ?? '') === 'Warehouse' && !empty($address['Ref'])) {
				$sender_address = $address;
				break;
			}
		}

		if (!$sender_address) {
			$sender_address = $addresses_response['data'][0];
		}

		$sender_address_ref = trim((string)($sender_address['Ref'] ?? ''));
		$sender_city_ref = trim((string)($sender_address['CityRef'] ?? ($sender['CityRef'] ?? '')));

		if ($sender_ref === '' || $sender_contact_ref === '' || $sender_address_ref === '' || $sender_city_ref === '') {
			throw new \RuntimeException('Nova Poshta sender profile is incomplete (missing refs).');
		}

		return [
			'ref' => $sender_ref,
			'contact_ref' => $sender_contact_ref,
			'address_ref' => $sender_address_ref,
			'city_ref' => $sender_city_ref,
			'phone' => $sender_phone
		];
	}

	private function resolveAddressRef(array $meta, string $delivery_type, string $city_ref, string $api_key, string $api_url): string {
		$address_ref = trim((string)($meta['address_ref'] ?? ''));

		if ($address_ref !== '') {
			return $address_ref;
		}

		$address_name = trim((string)($meta['address'] ?? ''));

		if (in_array($delivery_type, ['branch', 'locker'], true) && $city_ref !== '' && $address_name !== '') {
			return $this->findWarehouseRef($city_ref, $address_name, $api_key, $api_url);
		}

		return '';
	}

	private function resolveCityRef(array $meta, array $order_info, string $api_key, string $api_url): string {
		$city_ref = trim((string)($meta['city_ref'] ?? ''));

		if ($city_ref !== '') {
			return $city_ref;
		}

		$city = trim((string)($meta['city'] ?? ($order_info['shipping_city'] ?? '')));
		$zone = trim((string)($meta['zone'] ?? ($order_info['shipping_zone'] ?? '')));

		if ($city === '') {
			return '';
		}

		return $this->findCityRef($city, $zone, $api_key, $api_url);
	}

	private function findCityRef(string $city, string $zone, string $api_key, string $api_url): string {
		$response = $this->callApi($api_key, $api_url, 'Address', 'getCities', ['FindByString' => $city, 'Limit' => 100]);

		if (empty($response['success']) || empty($response['data'])) {
			throw new \RuntimeException($this->getApiError($response, 'Unable to resolve city ref in Nova Poshta API.'));
		}

		$city_norm = mb_strtolower(trim($city));
		$zone_norm = mb_strtolower(trim($zone));

		$first_ref = '';

		foreach ($response['data'] as $row) {
			$ref = trim((string)($row['Ref'] ?? ''));

			if ($ref === '') {
				continue;
			}

			if ($first_ref === '') {
				$first_ref = $ref;
			}

			$names = [
				mb_strtolower(trim((string)($row['Description'] ?? ''))),
				mb_strtolower(trim((string)($row['DescriptionRu'] ?? '')))
			];

			if (!in_array($city_norm, $names, true)) {
				continue;
			}

			if ($zone_norm === '') {
				return $ref;
			}

			$areas = [
				mb_strtolower(trim((string)($row['AreaDescription'] ?? ''))),
				mb_strtolower(trim((string)($row['AreaDescriptionRu'] ?? '')))
			];

			foreach ($areas as $area) {
				if ($area !== '' && mb_stripos($zone_norm, $area) !== false) {
					return $ref;
				}

				if ($area !== '' && mb_stripos($area, $zone_norm) !== false) {
					return $ref;
				}
			}
		}

		return $first_ref;
	}

	private function findWarehouseRef(string $city_ref, string $address_name, string $api_key, string $api_url): string {
		$response = $this->callApi($api_key, $api_url, 'Address', 'getWarehouses', ['CityRef' => $city_ref, 'Limit' => 200]);

		if (empty($response['success']) || empty($response['data'])) {
			throw new \RuntimeException($this->getApiError($response, 'Unable to resolve Nova Poshta warehouse ref.'));
		}

		$needle = mb_strtolower(trim($address_name));
		$first_ref = '';

		foreach ($response['data'] as $row) {
			$ref = trim((string)($row['Ref'] ?? ''));

			if ($ref === '') {
				continue;
			}

			if ($first_ref === '') {
				$first_ref = $ref;
			}

			$haystacks = [
				mb_strtolower(trim((string)($row['Description'] ?? ''))),
				mb_strtolower(trim((string)($row['ShortAddress'] ?? ''))),
				mb_strtolower(trim((string)($row['DescriptionRu'] ?? '')))
			];

			foreach ($haystacks as $haystack) {
				if ($haystack !== '' && ($haystack === $needle || mb_stripos($haystack, $needle) !== false || mb_stripos($needle, $haystack) !== false)) {
					return $ref;
				}
			}
		}

		return $first_ref;
	}

	private function callApi(string $api_key, string $api_url, string $model, string $method, array $properties): array {
		$endpoint = rtrim($api_url, '/') . '/';

		$payload = json_encode([
			'apiKey' => $api_key,
			'modelName' => $model,
			'calledMethod' => $method,
			'methodProperties' => $properties
		], JSON_UNESCAPED_UNICODE);

		if (!is_string($payload)) {
			throw new \RuntimeException('Unable to encode Nova Poshta API payload.');
		}

		if (function_exists('curl_init')) {
			$curl = curl_init($endpoint);

			if ($curl === false) {
				throw new \RuntimeException('Unable to initialize cURL for Nova Poshta API.');
			}

			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
			curl_setopt($curl, CURLOPT_TIMEOUT, 30);

			$response_raw = curl_exec($curl);

			if ($response_raw === false) {
				$error = curl_error($curl);
				curl_close($curl);

				throw new \RuntimeException('Nova Poshta cURL error: ' . $error);
			}

			$status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
			curl_close($curl);

			if ($status >= 400) {
				throw new \RuntimeException('Nova Poshta API HTTP error: ' . $status);
			}
		} else {
			$context = stream_context_create([
				'http' => [
					'method' => 'POST',
					'header' => "Content-Type: application/json\r\n",
					'content' => $payload,
					'timeout' => 30
				]
			]);

			$response_raw = @file_get_contents($endpoint, false, $context);

			if (!is_string($response_raw) || $response_raw === '') {
				throw new \RuntimeException('Nova Poshta API request failed.');
			}
		}

		$response = json_decode($response_raw, true);

		if (!is_array($response)) {
			throw new \RuntimeException('Nova Poshta API returned non-JSON response.');
		}

		return $response;
	}

	private function getApiError(array $response, string $fallback): string {
		$messages = [];

		foreach (['errors', 'warnings', 'info'] as $key) {
			if (!isset($response[$key])) {
				continue;
			}

			if (is_array($response[$key])) {
				foreach ($response[$key] as $message) {
					$message = trim((string)$message);

					if ($message !== '') {
						$messages[] = $message;
					}
				}
			} elseif (is_string($response[$key])) {
				$message = trim($response[$key]);

				if ($message !== '') {
					$messages[] = $message;
				}
			}
		}

		if ($messages) {
			return implode(' ', array_unique($messages));
		}

		return $fallback;
	}

	private function updateTtnSuccess(int $order_id, string $ttn_number, string $ttn_ref, array $payload): void {
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "order_novaposhta`
			SET ttn_number = '" . $this->db->escape($ttn_number) . "',
				ttn_ref = '" . $this->db->escape($ttn_ref) . "',
				ttn_error = '',
				ttn_payload = '" . $this->db->escape($this->encodeJson($payload)) . "',
				ttn_date_created = NOW(),
				ttn_date_modified = NOW(),
				date_modified = NOW()
			WHERE order_id = '" . (int)$order_id . "'"
		);
	}

	private function updateTtnError(int $order_id, string $error, array $payload): void {
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "order_novaposhta`
			SET ttn_error = '" . $this->db->escape($error) . "',
				ttn_payload = '" . $this->db->escape($this->encodeJson($payload)) . "',
				ttn_date_modified = NOW(),
				date_modified = NOW()
			WHERE order_id = '" . (int)$order_id . "'"
		);
	}

	private function encodeJson(array $data): string {
		$json = json_encode($data, JSON_UNESCAPED_UNICODE);

		if (!is_string($json)) {
			return '{}';
		}

		return $json;
	}

	private function createFallbackMeta(int $order_id, array $order_info, string $shipping_code): void {
		$delivery_type = $this->extractDeliveryType($shipping_code);

		$this->db->query(
			"INSERT INTO `" . DB_PREFIX . "order_novaposhta`
			SET order_id = '" . (int)$order_id . "',
				shipping_code = '" . $this->db->escape($shipping_code) . "',
				delivery_type = '" . $this->db->escape($delivery_type) . "',
				city = '" . $this->db->escape((string)($order_info['shipping_city'] ?? '')) . "',
				city_ref = '',
				address = '" . $this->db->escape((string)($order_info['shipping_address_1'] ?? '')) . "',
				address_ref = '',
				zone_id = '" . (int)($order_info['shipping_zone_id'] ?? 0) . "',
				zone = '" . $this->db->escape((string)($order_info['shipping_zone'] ?? '')) . "',
				country_id = '" . (int)($order_info['shipping_country_id'] ?? 0) . "',
				country = '" . $this->db->escape((string)($order_info['shipping_country'] ?? '')) . "',
				payload = '{}',
				date_added = NOW(),
				date_modified = NOW()
			ON DUPLICATE KEY UPDATE
				shipping_code = VALUES(shipping_code),
				delivery_type = VALUES(delivery_type),
				city = VALUES(city),
				address = VALUES(address),
				zone_id = VALUES(zone_id),
				zone = VALUES(zone),
				country_id = VALUES(country_id),
				country = VALUES(country),
				date_modified = NOW()"
		);
	}

	private function normalizePhone(string $phone): string {
		$digits = preg_replace('/\D+/', '', $phone);

		if (!is_string($digits)) {
			return '';
		}

		if (strpos($digits, '380') === 0) {
			$digits = '0' . substr($digits, 3);
		}

		if (strlen($digits) === 9) {
			$digits = '0' . $digits;
		}

		if (strlen($digits) > 10) {
			$digits = substr($digits, -10);
		}

		if (strlen($digits) !== 10) {
			return '';
		}

		return $digits;
	}

	private function extractPhone(string $phone_value): string {
		if ($phone_value === '') {
			return '';
		}

		$parts = explode(',', $phone_value);

		return trim((string)($parts[0] ?? ''));
	}

	private function mapServiceType(string $delivery_type): string {
		switch ($delivery_type) {
			case 'courier':
				return 'WarehouseDoors';
			case 'locker':
				return 'WarehousePostomat';
			case 'branch':
			default:
				return 'WarehouseWarehouse';
		}
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
				`date_added` DATETIME NOT NULL,
				`date_modified` DATETIME NOT NULL,
				PRIMARY KEY (`order_id`),
				KEY `city_ref` (`city_ref`),
				KEY `address_ref` (`address_ref`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);
	}

	private function ensureTtnColumns(): void {
		$table = DB_PREFIX . 'order_novaposhta';

		if (!$this->hasColumn($table, 'ttn_ref')) {
			$this->db->query("ALTER TABLE `" . $table . "` ADD `ttn_ref` VARCHAR(64) NOT NULL DEFAULT '' AFTER `payload`");
		}

		if (!$this->hasColumn($table, 'ttn_number')) {
			$this->db->query("ALTER TABLE `" . $table . "` ADD `ttn_number` VARCHAR(32) NOT NULL DEFAULT '' AFTER `ttn_ref`");
		}

		if (!$this->hasColumn($table, 'ttn_error')) {
			$this->db->query("ALTER TABLE `" . $table . "` ADD `ttn_error` TEXT NULL AFTER `ttn_number`");
		}

		if (!$this->hasColumn($table, 'ttn_payload')) {
			$this->db->query("ALTER TABLE `" . $table . "` ADD `ttn_payload` MEDIUMTEXT NULL AFTER `ttn_error`");
		}

		if (!$this->hasColumn($table, 'ttn_date_created')) {
			$this->db->query("ALTER TABLE `" . $table . "` ADD `ttn_date_created` DATETIME NULL AFTER `ttn_payload`");
		}

		if (!$this->hasColumn($table, 'ttn_date_modified')) {
			$this->db->query("ALTER TABLE `" . $table . "` ADD `ttn_date_modified` DATETIME NULL AFTER `ttn_date_created`");
		}
	}

	private function hasColumn(string $table, string $column): bool {
		$query = $this->db->query("SHOW COLUMNS FROM `" . $this->db->escape($table) . "` LIKE '" . $this->db->escape($column) . "'");

		return (bool)$query->num_rows;
	}
}
