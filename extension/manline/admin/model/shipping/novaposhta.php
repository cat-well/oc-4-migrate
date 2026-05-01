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

		if (!empty($row['ttn_status_payload']) && is_string($row['ttn_status_payload'])) {
			$ttn_status_payload = json_decode($row['ttn_status_payload'], true);

			if (is_array($ttn_status_payload)) {
				$row['ttn_status_payload'] = $ttn_status_payload;
			}
		}

		return $row;
	}

	public function createTtnForOrder(int $order_id, bool $force = false): array {
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
		$meta = $this->getOrderMeta($order_id);

		if (strpos($shipping_code, 'novaposhta.') !== 0 && !empty($meta['shipping_code'])) {
			$shipping_code = (string)$meta['shipping_code'];
		}

		if (!$shipping_code || strpos($shipping_code, 'novaposhta.') !== 0) {
			return ['success' => false, 'error' => 'This order does not use Nova Poshta shipping.'];
		}

		if (!$meta) {
			$this->createFallbackMeta($order_id, $order_info, $shipping_code);
			$meta = $this->getOrderMeta($order_id);
		}

		if (!$meta) {
			return ['success' => false, 'error' => 'Nova Poshta delivery data is missing for this order.'];
		}

		$credentials = $this->getApiCredentials();

		if (empty($credentials['success'])) {
			return ['success' => false, 'error' => (string)($credentials['error'] ?? 'Nova Poshta API credentials are not configured.')];
		}

		$api_key = (string)$credentials['api_key'];
		$api_url = (string)$credentials['api_url'];

		$existing_ttn_number = trim((string)($meta['ttn_number'] ?? ''));
		$existing_ttn_ref = trim((string)($meta['ttn_ref'] ?? ''));

		if ($existing_ttn_number !== '' || $existing_ttn_ref !== '') {
			if (!$force) {
				return ['success' => false, 'error' => 'TTN already exists for this order.'];
			}

			$delete_result = $this->deleteTtnInNovaPoshta($api_key, $api_url, $existing_ttn_ref, $existing_ttn_number, true);

			if (empty($delete_result['success'])) {
				$error = 'Unable to cancel existing TTN in Nova Poshta: ' . (string)($delete_result['error'] ?? 'Unknown API error.');
				$this->updateTtnError($order_id, $error, (array)($delete_result['response'] ?? []));

				return ['success' => false, 'error' => $error];
			}

			$this->clearTtnData($order_id);
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
				'RecipientsPhone' => $recipient['phone'],
				'OptionsSeat' => $order_data['options_seat'],
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
				'ttn_date_created' => date('Y-m-d H:i:s'),
				'print_url' => $this->buildPrintUrl($ttn_ref)
			];
		} catch (\Throwable $e) {
			$this->updateTtnError($order_id, $e->getMessage(), []);

			return ['success' => false, 'error' => $e->getMessage()];
		}
	}

	public function deleteTtnForOrder(int $order_id): array {
		if ($order_id <= 0) {
			return ['success' => false, 'error' => 'Order ID is missing.'];
		}

		$this->ensureOrderMetaTable();
		$this->ensureTtnColumns();

		$meta = $this->getOrderMeta($order_id);

		if (!$meta) {
			return ['success' => false, 'error' => 'Nova Poshta delivery data is missing for this order.'];
		}

		$ttn_number = trim((string)($meta['ttn_number'] ?? ''));
		$ttn_ref = trim((string)($meta['ttn_ref'] ?? ''));

		if ($ttn_number === '' && $ttn_ref === '') {
			return ['success' => false, 'error' => 'TTN is not assigned to this order.'];
		}

		$credentials = $this->getApiCredentials();

		if (empty($credentials['success'])) {
			return ['success' => false, 'error' => (string)($credentials['error'] ?? 'Nova Poshta API credentials are not configured.')];
		}

		$delete_result = $this->deleteTtnInNovaPoshta(
			(string)$credentials['api_key'],
			(string)$credentials['api_url'],
			$ttn_ref,
			$ttn_number,
			true
		);

		if (empty($delete_result['success'])) {
			$error = 'Nova Poshta API did not cancel TTN: ' . (string)($delete_result['error'] ?? 'Unknown API error.');
			$this->updateTtnError($order_id, $error, (array)($delete_result['response'] ?? []));

			return ['success' => false, 'error' => $error];
		}

		$this->clearTtnData($order_id);

		return [
			'success' => true,
			'deleted_ttn_number' => $ttn_number,
			'deleted_ttn_ref' => $ttn_ref,
			'remote_deleted' => empty($delete_result['already_missing']),
			'remote_already_missing' => !empty($delete_result['already_missing'])
		];
	}

	public function getPrintUrlByOrderId(int $order_id): string {
		if ($order_id <= 0) {
			return '';
		}

		$meta = $this->getOrderMeta($order_id);

		if (!$meta) {
			return '';
		}

		$ttn_ref = trim((string)($meta['ttn_ref'] ?? ''));

		return $this->buildPrintUrl($ttn_ref);
	}

	public function refreshTtnStatusForOrder(int $order_id): array {
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

		$meta = $this->getOrderMeta($order_id);

		if (!$meta) {
			return ['success' => false, 'error' => 'Nova Poshta delivery data is missing for this order.'];
		}

		$ttn_number = trim((string)($meta['ttn_number'] ?? ''));

		if ($ttn_number === '') {
			return ['success' => false, 'error' => 'TTN is not assigned to this order.'];
		}

		$credentials = $this->getApiCredentials();

		if (empty($credentials['success'])) {
			return ['success' => false, 'error' => (string)($credentials['error'] ?? 'Nova Poshta API credentials are not configured.')];
		}

		$api_key = (string)$credentials['api_key'];
		$api_url = (string)$credentials['api_url'];
		$phones = $this->getTrackingPhones((string)($order_info['telephone'] ?? ''), $meta);

		if (!$phones) {
			return ['success' => false, 'error' => 'Recipient phone is required for Nova Poshta status request.'];
		}

		$response = [];
		$status_data = [];

		foreach ($phones as $phone) {
			try {
				$response = $this->callApi(
					$api_key,
					$api_url,
					'TrackingDocument',
					'getStatusDocuments',
					[
						'Documents' => [
							[
								'DocumentNumber' => $ttn_number,
								'Phone' => $phone
							]
						]
					]
				);
			} catch (\Throwable $e) {
				return ['success' => false, 'error' => $e->getMessage()];
			}

			if (!empty($response['success']) && !empty($response['data'][0]) && is_array($response['data'][0])) {
				$status_data = $response['data'][0];
				break;
			}
		}

		if (!$status_data) {
			$error = $this->getApiError($response, 'Nova Poshta API did not return TTN status.');

			return ['success' => false, 'error' => $error];
		}

		$status_code = trim((string)($status_data['StatusCode'] ?? ''));
		$status_text = trim((string)($status_data['Status'] ?? ''));
		$status_date = trim((string)($status_data['TrackingUpdateDate'] ?? ''));

		if ($status_date === '') {
			$status_date = trim((string)($status_data['DateScan'] ?? ''));
		}

		if ($status_date === '') {
			$status_date = trim((string)($status_data['RecipientDateTime'] ?? ''));
		}

		if ($status_date === '') {
			$status_date = trim((string)($status_data['DateCreated'] ?? ''));
		}

		if ($status_text === '') {
			return ['success' => false, 'error' => 'Nova Poshta API did not return status text.'];
		}

		$previous_code = trim((string)($meta['ttn_status_code'] ?? ''));
		$previous_text = trim((string)($meta['ttn_status_text'] ?? ''));
		$previous_date = trim((string)($meta['ttn_status_date'] ?? ''));

		$this->updateTtnStatus($order_id, $status_code, $status_text, $status_date, $response);

		return [
			'success' => true,
			'ttn_number' => $ttn_number,
			'ttn_status_code' => $status_code,
			'ttn_status_text' => $status_text,
			'ttn_status_date' => $status_date,
			'changed' => $previous_code !== $status_code || $previous_text !== $status_text || $previous_date !== $status_date
		];
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

		// NP's InternetDocument.save now rejects payloads without OptionsSeat
		// even for CargoType=Cargo ("OptionsSeat is empty" / errorCode
		// 20000200226). Build a per-seat array from seats_amount, distributing
		// the total weight evenly. Dimensions are a sensible underwear-shop
		// default (20×10×10 cm ≈ 0.002 m³ per package); NP recalculates
		// volumetric weight server-side anyway.
		$seats = max($seats_amount, 1);
		$weight_total = 1.0;
		$weight_per_seat = round($weight_total / $seats, 3);
		if ($weight_per_seat <= 0) {
			$weight_per_seat = 0.1;
		}

		$options_seat = [];
		for ($i = 0; $i < $seats; $i++) {
			$options_seat[] = [
				'volumetricVolume' => '0.002',
				'volumetricWidth'  => 10,
				'volumetricLength' => 20,
				'volumetricHeight' => 10,
				'weight'           => $weight_per_seat,
			];
		}

		return [
			'description'  => $description,
			'seats_amount' => (string)$seats_amount,
			'weight'       => (string)$weight_total,
			'cost'         => number_format($cost, 2, '.', ''),
			'options_seat' => $options_seat,
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

		// NP's Counterparty.save validator now rejects Latin characters with
		// "FirstName/LastName has invalid characters". Transliterate Latin
		// → Ukrainian Cyrillic when the input has no Cyrillic letters of its
		// own. Cyrillic-mixed input is left alone (already valid for NP).
		$firstname = $this->normalizeNameForNp($firstname);
		$lastname  = $this->normalizeNameForNp($lastname);

		if ($firstname === '' || $lastname === '') {
			throw new \RuntimeException('Recipient first name and last name became empty after sanitisation. Please re-enter using letters only.');
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

	private function getApiCredentials(): array {
		$api_key = trim((string)$this->config->get('shipping_novaposhta_api_key'));
		$api_url = trim((string)$this->config->get('shipping_novaposhta_api_url'));

		if ($api_url === '') {
			$api_url = 'https://api.novaposhta.ua/v2.0/json/';
		}

		if ($api_key === '') {
			return ['success' => false, 'error' => 'Nova Poshta API key is not configured.'];
		}

		return [
			'success' => true,
			'api_key' => $api_key,
			'api_url' => $api_url
		];
	}

	private function getTrackingPhones(string $order_phone, array $meta): array {
		$phones = [];

		$add = function(string $phone) use (&$phones): void {
			$normalized = $this->normalizePhone($phone);

			if ($normalized !== '') {
				$phones[] = $normalized;
				$phones[] = '38' . substr($normalized, 1);
			}

			$digits = preg_replace('/\D+/', '', $phone);

			if (is_string($digits)) {
				if (strlen($digits) === 12 && strpos($digits, '380') === 0) {
					$phones[] = $digits;
				}

				if (strlen($digits) === 10 && strpos($digits, '0') === 0) {
					$phones[] = $digits;
					$phones[] = '38' . substr($digits, 1);
				}
			}
		};

		$add($order_phone);
		$add((string)($meta['phone'] ?? ''));

		$payload = $meta['payload'] ?? [];
		if (is_array($payload)) {
			$add((string)($payload['RecipientsPhone'] ?? ''));
		}

		$ttn_payload = $meta['ttn_payload'] ?? [];
		if (is_array($ttn_payload)) {
			$add((string)($ttn_payload['data'][0]['RecipientsPhone'] ?? ''));
		}

		$ttn_status_payload = $meta['ttn_status_payload'] ?? [];
		if (is_array($ttn_status_payload)) {
			$add((string)($ttn_status_payload['data'][0]['PhoneRecipient'] ?? ''));
		}

		$phones = array_values(array_unique(array_filter($phones, static function($phone) {
			return is_string($phone) && $phone !== '';
		})));

		return $phones;
	}

	private function deleteTtnInNovaPoshta(string $api_key, string $api_url, string $ttn_ref, string $ttn_number, bool $allow_missing = false): array {
		$ttn_ref = trim($ttn_ref);
		$ttn_number = trim($ttn_number);

		if ($ttn_ref === '' && $ttn_number === '') {
			return ['success' => false, 'error' => 'TTN ref and number are missing for delete request.'];
		}

		$properties = [];

		if ($ttn_ref !== '') {
			$properties['DocumentRefs'] = [$ttn_ref];
		} else {
			$properties['DocumentBarcodes'] = [$ttn_number];
		}

		try {
			$response = $this->callApi($api_key, $api_url, 'InternetDocument', 'delete', $properties);
		} catch (\Throwable $e) {
			return [
				'success' => false,
				'error' => $e->getMessage(),
				'response' => []
			];
		}

		if (!empty($response['success'])) {
			return [
				'success' => true,
				'already_missing' => false,
				'response' => $response
			];
		}

		$error = $this->getApiError($response, 'Nova Poshta API did not delete TTN.');

		if ($allow_missing && $this->isTtnMissingOnNovaPoshta($response, $error)) {
			return [
				'success' => true,
				'already_missing' => true,
				'error' => $error,
				'response' => $response
			];
		}

		return [
			'success' => false,
			'error' => $error,
			'response' => $response
		];
	}

	private function isTtnMissingOnNovaPoshta(array $response, string $error): bool {
		$missing_codes = ['20000200564'];
		$error_codes = $response['errorCodes'] ?? [];

		if (is_array($error_codes)) {
			foreach ($error_codes as $code) {
				if (in_array((string)$code, $missing_codes, true)) {
					return true;
				}
			}
		}

		$error_lower = mb_strtolower($error);
		$needles = [
			'invalid documentbarcodes',
			'invalid documentrefs',
			'not found',
			'не знайден',
			'не знайш',
			'не існує'
		];

		foreach ($needles as $needle) {
			if (mb_stripos($error_lower, $needle) !== false) {
				return true;
			}
		}

		return false;
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

	private function updateTtnStatus(int $order_id, string $status_code, string $status_text, string $status_date, array $payload): void {
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "order_novaposhta`
			SET ttn_status_code = '" . $this->db->escape($status_code) . "',
				ttn_status_text = '" . $this->db->escape($status_text) . "',
				ttn_status_date = '" . $this->db->escape($status_date) . "',
				ttn_status_payload = '" . $this->db->escape($this->encodeJson($payload)) . "',
				ttn_date_modified = NOW(),
				date_modified = NOW()
			WHERE order_id = '" . (int)$order_id . "'"
		);
	}

	private function clearTtnData(int $order_id): void {
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "order_novaposhta`
			SET ttn_ref = '',
				ttn_number = '',
				ttn_error = '',
				ttn_payload = NULL,
				ttn_status_code = '',
				ttn_status_text = '',
				ttn_status_date = '',
				ttn_status_payload = NULL,
				ttn_date_created = NULL,
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

	/**
	 * Sanitise a recipient name for the Nova Poshta Counterparty.save endpoint.
	 *
	 * NP's validator rejects names that contain anything other than Cyrillic
	 * letters / spaces / hyphens / apostrophes — Latin alphabet, digits and
	 * punctuation get bounced with "FirstName has invalid characters".
	 *
	 * Behaviour:
	 *   - Transliterate Latin → Ukrainian Cyrillic unconditionally. The map
	 *     only touches Latin letters, so existing Cyrillic input passes through
	 *     unchanged; mixed input (e.g. "Petro Іваненко") gets both halves in
	 *     Cyrillic instead of losing the Latin half to the strip step.
	 *   - Strip everything that isn't a Cyrillic letter / space / hyphen /
	 *     apostrophe — digits, brackets, punctuation, emoji.
	 *   - Collapse whitespace and trim.
	 */
	private function normalizeNameForNp(string $name): string {
		$name = trim($name);

		if ($name === '') {
			return '';
		}

		$name = $this->transliterateLatinToUkrainian($name);

		// Allow Cyrillic letters, ASCII space, hyphen, apostrophe (both ASCII
		// ' and Unicode ’). Drop everything else — digits, brackets, dots, etc.
		$name = preg_replace('/[^\p{Cyrillic}\s\-\'’]/u', '', $name) ?? '';
		$name = preg_replace('/\s+/u', ' ', $name) ?? '';

		return trim($name);
	}

	/**
	 * Latin → Ukrainian Cyrillic transliteration. Order matters: digraphs
	 * (Shch, Yo, Zh, Ch, Sh, Kh, Ts, Ya, Yu, Yi) must be replaced before the
	 * single letters they start with. PHP's strtr() handles longest-key-first
	 * automatically when given an array, so the order in the source is for
	 * readability only.
	 */
	private function transliterateLatinToUkrainian(string $s): string {
		$map = [
			'Shch' => 'Щ', 'shch' => 'щ',
			'Yo' => 'Йо', 'yo' => 'йо',
			'Ya' => 'Я',  'ya' => 'я',
			'Yu' => 'Ю',  'yu' => 'ю',
			'Ye' => 'Є',  'ye' => 'є',
			'Yi' => 'Ї',  'yi' => 'ї',
			'Zh' => 'Ж',  'zh' => 'ж',
			'Ch' => 'Ч',  'ch' => 'ч',
			'Sh' => 'Ш',  'sh' => 'ш',
			'Kh' => 'Х',  'kh' => 'х',
			'Ts' => 'Ц',  'ts' => 'ц',
			'A' => 'А', 'a' => 'а',
			'B' => 'Б', 'b' => 'б',
			'V' => 'В', 'v' => 'в',
			'H' => 'Г', 'h' => 'г',
			'G' => 'Ґ', 'g' => 'ґ',
			'D' => 'Д', 'd' => 'д',
			'E' => 'Е', 'e' => 'е',
			'Z' => 'З', 'z' => 'з',
			'I' => 'І', 'i' => 'і',
			'Y' => 'И', 'y' => 'и',
			'K' => 'К', 'k' => 'к',
			'L' => 'Л', 'l' => 'л',
			'M' => 'М', 'm' => 'м',
			'N' => 'Н', 'n' => 'н',
			'O' => 'О', 'o' => 'о',
			'P' => 'П', 'p' => 'п',
			'R' => 'Р', 'r' => 'р',
			'S' => 'С', 's' => 'с',
			'T' => 'Т', 't' => 'т',
			'U' => 'У', 'u' => 'у',
			'F' => 'Ф', 'f' => 'ф',
			'C' => 'Ц', 'c' => 'ц',
			'J' => 'Й', 'j' => 'й',
			'X' => 'Кс', 'x' => 'кс',
			'Q' => 'К', 'q' => 'к',
			'W' => 'В', 'w' => 'в',
		];

		return strtr($s, $map);
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

	private function buildPrintUrl(string $ttn_ref): string {
		$ttn_ref = trim($ttn_ref);

		if ($ttn_ref === '') {
			return '';
		}

		$api_key = trim((string)$this->config->get('shipping_novaposhta_api_key'));

		if ($api_key === '') {
			return '';
		}

		return 'https://my.novaposhta.ua/orders/printDocument/orders[]/' . rawurlencode($ttn_ref) . '/type/pdf/apiKey/' . rawurlencode($api_key);
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

		if (!$this->hasColumn($table, 'ttn_status_code')) {
			$this->db->query("ALTER TABLE `" . $table . "` ADD `ttn_status_code` VARCHAR(32) NOT NULL DEFAULT '' AFTER `ttn_date_modified`");
		}

		if (!$this->hasColumn($table, 'ttn_status_text')) {
			$this->db->query("ALTER TABLE `" . $table . "` ADD `ttn_status_text` VARCHAR(255) NOT NULL DEFAULT '' AFTER `ttn_status_code`");
		}

		if (!$this->hasColumn($table, 'ttn_status_date')) {
			$this->db->query("ALTER TABLE `" . $table . "` ADD `ttn_status_date` VARCHAR(64) NOT NULL DEFAULT '' AFTER `ttn_status_text`");
		}

		if (!$this->hasColumn($table, 'ttn_status_payload')) {
			$this->db->query("ALTER TABLE `" . $table . "` ADD `ttn_status_payload` MEDIUMTEXT NULL AFTER `ttn_status_date`");
		}
	}

	private function hasColumn(string $table, string $column): bool {
		$query = $this->db->query("SHOW COLUMNS FROM `" . $this->db->escape($table) . "` LIKE '" . $this->db->escape($column) . "'");

		return (bool)$query->num_rows;
	}
}
