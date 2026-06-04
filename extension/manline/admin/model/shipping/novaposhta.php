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

	public function createTtnForOrder(int $order_id, bool $force = false, string $sender_address_ref = '', array $changes = []): array {
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
			if (!empty($changes['delivery_type'])) {
				$delivery_type = (string)$changes['delivery_type'];
			}

			$city_ref = '';
			$address_ref = '';

			if (!empty($changes['recipient_city_ref']) && !empty($changes['recipient_address_ref'])) {
				$city_ref = trim((string)$changes['recipient_city_ref']);
				$address_ref = trim((string)$changes['recipient_address_ref']);
			} else {
				$city_ref = $this->resolveCityRef($meta, $order_info, $api_key, $api_url);
				$address_ref = $this->resolveAddressRef($meta, $delivery_type, $city_ref, $api_key, $api_url);
			}

			if ($city_ref === '' || $address_ref === '') {
				throw new \RuntimeException('City or destination address ref is missing for TTN creation.');
			}

			// If operator overrode recipient address in the create modal, persist
			// it back into the order so admin/order shows the factual shipping
			// address that was used for the TTN.
			if (!empty($changes['recipient_city_ref']) && !empty($changes['recipient_address_ref'])) {
				$wh = $this->lookupWarehouseByRef($address_ref);
				if (!empty($wh[0])) {
					$city_name = trim((string)($wh[0]['city'] ?? ''));
					$addr_name = trim((string)($wh[0]['description'] ?? $wh[0]['value'] ?? ''));
					$this->persistRecipientOverrideToOrder($order_id, $delivery_type, $city_name, $city_ref, $addr_name, $address_ref);
				}
			}

			// Operator-chosen sender warehouse (from the modal dropdown) is
			// preferred over the auto-picked "first Warehouse, else first"
			// fallback inside fetchSenderData. Empty string keeps the
			// legacy auto-pick behaviour.
			$sender = $this->fetchSenderData($api_key, $api_url, $sender_address_ref);
			$recipient = $this->fetchOrCreateRecipientData($order_info, $city_ref, $api_key, $api_url);
			$order_data = $this->getOrderData($order_id, $order_info, $delivery_type);
			if (!empty($changes['Weight'])) $order_data['weight'] = (float)$changes['Weight'];
			if (!empty($changes['SeatsAmount'])) $order_data['seats'] = (int)$changes['SeatsAmount'];
			if (!empty($changes['Cost'])) $order_data['cost'] = (float)$changes['Cost'];
			if (!empty($changes['Description'])) $order_data['description'] = (string)$changes['Description'];

			// Locker delivery has its own validator at NP API and the
			// payload shape differs from branch/courier. Specifically:
			//   • CargoType must be Parcel (not Cargo)
			//   • SeatsAmount is hard-capped at 1
			//   • OptionsSeat must contain exactly one entry
			//   • Cost is capped at 10 000 UAH
			//   • ServiceType only accepts WarehouseWarehouse or DoorsWarehouse
			// getOrderData() already handles the seats/options_seat split.
			$is_locker = $delivery_type === 'locker';

			if ($is_locker && (float)$order_data['cost'] > 10000.0) {
				throw new \RuntimeException(
					sprintf('Cost %s UAH exceeds Nova Poshta locker limit of 10000 UAH. Use branch delivery for this order.', $order_data['cost'])
				);
			}

			// DateTime is the planned ship-out date. NP server runs in Europe/Kyiv
			// timezone; when our PHP server is in UTC the day rolls over earlier
			// for NP than for us, so date('d.m.Y') here can hand NP a string they
			// consider "in the past" (returns "DateTime cannot be less then now").
			// The price-hint flow already uses tomorrow for the same reason — see
			// commit 9a229e5 in catalog/controller/checkout/simplecheckout.php.
			// For TTN creation we follow the same trick: tomorrow is always
			// "future" for both timezones, and operationally operators batch
			// drop-offs next morning anyway.
			$ship_date = date('d.m.Y', time() + 86400);

			$payer_type = 'Recipient';
			$payment_method = 'Cash';
			if (!empty($changes['PayerType'])) $payer_type = (string)$changes['PayerType'];

			// Additional services: COD vs payment control are mutually exclusive.
			//
			// Auto-derive default is intentionally 'none' so the model
			// matches the original 6e524a1 behaviour — a minimal NP payload
			// with no BackwardDelivery attached. Two earlier commits drifted
			// from that:
			//   - b5ab4e2 set the default to 'control' for any non-Liq/
			//     non-PayPal payment, which silently attached "Послуга
			//     післяплати" (NonCash) to every COD order and got
			//     "Передана послуга Післяплата недоступна" back from NP
			//     because the merchant's account does not have it active.
			//   - The follow-up cf2743c tried to route COD payments through
			//     the 'cod' branch (Cash + BackwardDelivery), but the same
			//     account-side service-availability rule applies.
			// The right behaviour is operator-explicit: only attach a
			// BackwardDelivery service when the modal dropdown explicitly
			// asks for 'cod' or 'control'. Bare "Оплата при доставці" still
			// works as before — recipient pays cash at the warehouse, sender
			// handles the cash return out-of-band.
			$additional = trim((string)($changes['additional_service'] ?? ''));
			if ($additional === '') {
				$additional = 'none';
			}
			if ($additional === 'control') {
				// Nova Poshta cabinet represents "Контроль оплати" as
				// AfterpaymentOnGoodsCost with the regular delivery payment
				// fields left intact. Sending it as NonCash + BackwardDelivery
				// makes NP reject the document for this FOP contract.
				$payment_method = 'Cash';
			} elseif ($additional === 'cod') {
				$payment_method = 'Cash';
			} elseif (!empty($changes['PaymentMethod'])) {
				$payment_method = (string)$changes['PaymentMethod'];
				if ($payment_method === 'NonCash' && $payer_type === 'Recipient') {
					$payer_type = 'Sender';
				}
			}

			// CargoType selection:
			//   - Lockers (поштомати) are Parcel-only.
			//   - NP cabinet creates payment-control documents as Parcel, and
			//     COD BackwardDelivery is also only reliable on Parcel-class
			//     shipments. Sending Parcel from the start avoids NP's cargo
			//     auto-coercion warnings and service rejection edge cases.
			//   - Otherwise default to Cargo (legacy WarehouseWarehouse
			//     freight flow for non-COD large items).
			$has_additional_payment_service = ($additional === 'cod' || $additional === 'control');
			$cargo_type = ($is_locker || $has_additional_payment_service) ? 'Parcel' : 'Cargo';

			$payload = [
				'PayerType' => $payer_type,
				'PaymentMethod' => $payment_method,
				'DateTime' => $ship_date,
				'CargoType' => $cargo_type,
				// Explicit RecipientType silences the "RecipientType is set to
				// PrivatePerson" advisory that NP returns when we omit it and
				// it has to infer from the recipient counterparty.
				'RecipientType' => 'PrivatePerson',
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
				'RecipientsPhone' => !empty($changes['recipient_phone']) ? (string)$changes['recipient_phone'] : $recipient['phone'],
				'OptionsSeat' => $order_data['options_seat'],
			];

			// Additional services: COD / payment control.
			if ($additional === 'cod' || $additional === 'control') {
				$cod_total = trim((string)($changes['cod_total'] ?? ''));
				if ($cod_total === '' || (float)$cod_total <= 0) {
					$cod_total = (string)$order_data['cost'];
				}

				if ($additional === 'control') {
					$payload['AfterpaymentOnGoodsCost'] = number_format((float)$cod_total, 2, '.', '');
				} else {
					$cod_payer = trim((string)($changes['cod_payer'] ?? 'Recipient'));
					if ($cod_payer === '') $cod_payer = 'Recipient';

					$payload['BackwardDeliveryData'] = [[
						'PayerType' => $cod_payer,
						'CargoType' => 'Money',
						'RedeliveryString' => $cod_total,
					]];
				}
			}

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

			// Persist the REQUEST payload (what we sent to NP), not the API
			// response. The response only contains the assigned identifiers
			// and cost-on-site — none of the shipment fields we need as the
			// baseline for in-place edits (updateTtnForOrder reads back this
			// column when the operator opens the edit form). The earlier
			// version stored $response here, which is why the edit form
			// showed blank pre-fills for Weight / Cost / Description / etc.
			$this->updateTtnSuccess($order_id, $ttn_number, $ttn_ref, $payload);

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

	/**
	 * In-place edit of an existing TTN via Nova Poshta `InternetDocument.update`.
	 *
	 * Keeps the same TTN number / Ref — only the payload (weight / seats /
	 * cost / cargo type / description / payer / payment method) is updated
	 * server-side at NP. This is the operationally-correct path when the
	 * old TTN number has already been printed, given to the customer or
	 * sent to NP — recreate would invalidate the printout, in-place update
	 * does not.
	 *
	 * NP allows updates only while the document is in pre-pickup statuses;
	 * once it has been scanned at the first NP warehouse it becomes
	 * immutable and this call returns NP's "Document is not editable"
	 * error verbatim. Callers should offer Recreate as fallback in that case.
	 *
	 * Edits are atomic: on NP-side success the new payload is persisted to
	 * `order_novaposhta.ttn_payload` and `ttn_date_modified` is bumped;
	 * `ttn_date_created` and the TTN identifiers stay as they were. On
	 * NP-side failure nothing is written back to MySQL — the operator can
	 * retry without our DB getting into a half-state.
	 *
	 * @param int $order_id
	 * @param array<string, mixed> $changes Whitelisted fields only — see $allowed below.
	 * @return array{success: bool, error?: string, ttn_number?: string, ttn_ref?: string, changed_fields?: list<string>, old_values?: array<string, mixed>, new_values?: array<string, mixed>}
	 */
	public function updateTtnForOrder(int $order_id, array $changes): array {
		if ($order_id <= 0) {
			return ['success' => false, 'error' => 'Order ID is missing.'];
		}

		$this->ensureOrderMetaTable();
		$this->ensureTtnColumns();

		$meta = $this->getOrderMeta($order_id);

		if (!$meta) {
			return ['success' => false, 'error' => 'Nova Poshta delivery data is missing for this order.'];
		}

		$existing_ttn_ref = trim((string)($meta['ttn_ref'] ?? ''));
		$existing_ttn_number = trim((string)($meta['ttn_number'] ?? ''));

		if ($existing_ttn_ref === '' || $existing_ttn_number === '') {
			return ['success' => false, 'error' => 'No existing TTN to edit. Create one first.'];
		}

		$current_payload = $meta['ttn_payload'] ?? null;

		if (!is_array($current_payload) || !$current_payload) {
			return [
				'success' => false,
				'error' => 'Original TTN payload is not stored for this order — in-place edit cannot be performed. Use Recreate instead.'
			];
		}

		// Whitelist of NP-shape fields. Anything outside this set is silently
		// dropped on the scalar branch below; structured fields (dimensions,
		// COD, address) live in their own translation blocks further down
		// and reach NP under their proper API names regardless of what the
		// form called them.
		$allowed = [
			'Weight', 'SeatsAmount', 'Cost', 'CargoType', 'Description',
			'PayerType', 'PaymentMethod', 'VolumeGeneral', 'RecipientsPhone',
			'AfterpaymentOnGoodsCost',
		];

		$normalized_changes = [];

		foreach ($changes as $key => $value) {
			if (!in_array($key, $allowed, true)) {
				continue;
			}

			if ($value === '' || $value === null) {
				continue;
			}

			// Cast to the same types NP expects (numeric strings for Weight/Cost/Volume,
			// integer string for SeatsAmount, plain string for the rest).
			switch ($key) {
				case 'Weight':
				case 'Cost':
					$normalized_changes[$key] = number_format((float)$value, 2, '.', '');
					break;

				case 'AfterpaymentOnGoodsCost':
					$normalized_changes[$key] = number_format((float)$value, 2, '.', '');
					break;

				case 'VolumeGeneral':
					$normalized_changes[$key] = number_format((float)$value, 4, '.', '');
					break;

				case 'SeatsAmount':
					$normalized_changes[$key] = (string)max(1, (int)$value);
					break;

				default:
					$normalized_changes[$key] = (string)$value;
			}
		}

		// High-level field: COD / payment control. The create form uses
		// additional_service; older edit requests may still send cod_enabled.
		if (array_key_exists('additional_service', $changes) || array_key_exists('cod_enabled', $changes)) {
			$additional_service = trim((string)($changes['additional_service'] ?? ''));

			if ($additional_service === '') {
				$cod_enabled = $changes['cod_enabled'] ?? null;
				$additional_service = ($cod_enabled === '1' || $cod_enabled === 1 || $cod_enabled === true) ? 'cod' : 'none';
			}

			if ($additional_service === 'control') {
				$cod_total = number_format((float)($changes['cod_total'] ?? 0), 2, '.', '');
				$normalized_changes['AfterpaymentOnGoodsCost'] = $cod_total;
				$normalized_changes['PaymentMethod'] = 'Cash';
				$normalized_changes['BackwardDeliveryData'] = [];
			} elseif ($additional_service === 'cod') {
				$cod_total = number_format((float)($changes['cod_total'] ?? 0), 2, '.', '');
				$cod_payer = in_array($changes['cod_payer'] ?? '', ['Sender', 'Recipient', 'ThirdPerson'], true)
					? $changes['cod_payer']
					: 'Recipient';

				$normalized_changes['BackwardDeliveryData'] = [[
					'PayerType' => $cod_payer,
					'CargoType' => 'Money',
					'RedeliveryString' => $cod_total,
				]];
				$normalized_changes['AfterpaymentOnGoodsCost'] = '0.00';
			} else {
				// Empty array tells NP "drop any existing COD"; zero clears
				// payment-control amount in the stored payload.
				$normalized_changes['BackwardDeliveryData'] = [];
				$normalized_changes['AfterpaymentOnGoodsCost'] = '0.00';
			}
		}

		// High-level field: internal shipment number → InfoRegClientBarcodes.
		// NP accepts a single string or comma-separated list; we forward whatever
		// the operator typed.
		if (array_key_exists('internal_number', $changes)) {
			$value = trim((string)$changes['internal_number']);

			$normalized_changes['InfoRegClientBarcodes'] = $value;
		}

		// High-level fields: dimensions (volume_width / volume_length /
		// volume_height in cm). When any of them is present we rebuild the
		// per-seat dimensions across all seats — NP requires OptionsSeat to
		// stay consistent. VolumeGeneral is then derived from the cube unless
		// the operator passed an explicit VolumeGeneral override above.
		$has_dim_change = isset($changes['volume_width']) || isset($changes['volume_length']) || isset($changes['volume_height']);

		if ($has_dim_change) {
			$first_existing = $current_payload['OptionsSeat'][0] ?? [];

			$width = isset($changes['volume_width']) && $changes['volume_width'] !== ''
				? max(1, (int)$changes['volume_width'])
				: (int)($first_existing['volumetricWidth'] ?? 10);
			$length = isset($changes['volume_length']) && $changes['volume_length'] !== ''
				? max(1, (int)$changes['volume_length'])
				: (int)($first_existing['volumetricLength'] ?? 20);
			$height = isset($changes['volume_height']) && $changes['volume_height'] !== ''
				? max(1, (int)$changes['volume_height'])
				: (int)($first_existing['volumetricHeight'] ?? 10);

			// cm × cm × cm → m³ (NP wants volumetricVolume in m³).
			$volume_per_seat = max(0.0001, round(($width * $length * $height) / 1000000, 4));

			$normalized_changes['__rebuild_options_seat_dims'] = [
				'width' => $width,
				'length' => $length,
				'height' => $height,
				'volumetricVolume' => number_format($volume_per_seat, 4, '.', ''),
			];
		}

		// High-level fields: recipient address. The form sends free-text
		// city / warehouse / address along with the delivery_type radio
		// (branch / locker / courier). We resolve refs via the existing
		// helpers — if NP cannot find a match we surface that error and
		// don't attempt the update at all (better than sending a half-baked
		// payload that NP rejects with an obscure message).
		// recipient_city_ref / recipient_address_ref come pre-resolved from the
		// admin dropdown picker when the operator picks from autocomplete —
		// that skips the findCityRef / findWarehouseRef round-trip and avoids
		// ambiguity errors (e.g. two cities with the same name in different
		// areas). Free-text fallback is preserved for the rare case where the
		// operator types without picking.
		$has_address_change = isset($changes['recipient_city_name']) || isset($changes['recipient_address_name'])
			|| isset($changes['recipient_city_ref']) || isset($changes['recipient_address_ref'])
			|| isset($changes['delivery_type']);

		if ($has_address_change) {
			$new_delivery_type = isset($changes['delivery_type']) ? (string)$changes['delivery_type'] : (string)($meta['delivery_type'] ?? '');
			$city_name = trim((string)($changes['recipient_city_name'] ?? $meta['city'] ?? ''));
			$address_name = trim((string)($changes['recipient_address_name'] ?? $meta['address'] ?? ''));
			$city_ref = trim((string)($changes['recipient_city_ref'] ?? ''));
			$address_ref = trim((string)($changes['recipient_address_ref'] ?? ''));

			$needs_lookup = ($city_ref === '') || ($address_ref === '' && in_array($new_delivery_type, ['branch', 'locker', ''], true) && $address_name !== '');

			if ($needs_lookup) {
				$credentials_early = $this->getApiCredentials();

				if (empty($credentials_early['success'])) {
					return ['success' => false, 'error' => (string)($credentials_early['error'] ?? 'Nova Poshta API credentials are not configured.')];
				}

				$api_key_addr = (string)$credentials_early['api_key'];
				$api_url_addr = (string)$credentials_early['api_url'];

				if ($city_ref === '' && $city_name !== '') {
					$city_ref = $this->findCityRef($city_name, (string)($meta['zone'] ?? ''), $api_key_addr, $api_url_addr);
				}

				if ($city_ref === '') {
					return ['success' => false, 'error' => 'Could not resolve recipient city "' . $city_name . '" against Nova Poshta. Check spelling and try again, or use Recreate.'];
				}

				if ($address_ref === '' && $address_name !== '' && ($new_delivery_type === 'branch' || $new_delivery_type === 'locker' || $new_delivery_type === '')) {
					$address_ref = $this->findWarehouseRef($city_ref, $address_name, $api_key_addr, $api_url_addr);

					if ($address_ref === '') {
						return ['success' => false, 'error' => 'Could not resolve recipient warehouse "' . $address_name . '" in the chosen city. Check the branch / locker name and try again, or use Recreate.'];
					}
				}
			}

			if ($city_ref === '') {
				return ['success' => false, 'error' => 'Could not resolve recipient city. Check spelling and try again, or use Recreate.'];
			}

			$normalized_changes['CityRecipient'] = $city_ref;

			if ($address_ref !== '') {
				$normalized_changes['RecipientAddress'] = $address_ref;
			}

			if ($new_delivery_type !== '') {
				$normalized_changes['ServiceType'] = $this->mapServiceType($new_delivery_type);
			}
		}

		// High-level field: sender address. The form sends `sender_address_ref`
		// = the chosen warehouse's NP Ref from the dropdown. We re-fetch the
		// full sender block with that override so Sender / SenderAddress /
		// CitySender / ContactSender / SendersPhone all move together as a
		// consistent set — half-updating those refs (e.g. swap address but
		// leave city) is what makes NP issue silent courier-pickup orders.
		if (array_key_exists('sender_address_ref', $changes)) {
			$override_ref = trim((string)$changes['sender_address_ref']);

			if ($override_ref !== '') {
				$credentials_sender = $this->getApiCredentials();

				if (empty($credentials_sender['success'])) {
					return ['success' => false, 'error' => (string)($credentials_sender['error'] ?? 'Nova Poshta API credentials are not configured.')];
				}

				try {
					$sender = $this->fetchSenderData(
						(string)$credentials_sender['api_key'],
						(string)$credentials_sender['api_url'],
						$override_ref
					);
				} catch (\Throwable $e) {
					return ['success' => false, 'error' => 'Sender address lookup failed: ' . $e->getMessage()];
				}

				if (trim((string)$sender['address_ref']) !== $override_ref) {
					return ['success' => false, 'error' => 'Selected sender address is no longer available in Nova Poshta.'];
				}

				$normalized_changes['Sender'] = $sender['ref'];
				$normalized_changes['SenderAddress'] = $sender['address_ref'];
				$normalized_changes['CitySender'] = $sender['city_ref'];
				$normalized_changes['ContactSender'] = $sender['contact_ref'];
				$normalized_changes['SendersPhone'] = $sender['phone'];
			}
		}

		// Pull out the internal "rebuild dims" marker before we diff — it's
		// a hint to the OptionsSeat rebuild block below, not an NP-shape field.
		$dim_rebuild = null;

		if (isset($normalized_changes['__rebuild_options_seat_dims'])) {
			$dim_rebuild = $normalized_changes['__rebuild_options_seat_dims'];
			unset($normalized_changes['__rebuild_options_seat_dims']);
		}

		if (!$normalized_changes && $dim_rebuild === null) {
			return ['success' => false, 'error' => 'No editable fields were provided.'];
		}

		// Skip the call if nothing actually differs — protects NP from no-op
		// edits and keeps the audit log clean. Arrays (BackwardDeliveryData)
		// are compared via JSON to avoid the "Array" string cast trap.
		$diff_changes = [];

		foreach ($normalized_changes as $key => $value) {
			$old_raw = $current_payload[$key] ?? null;

			if (is_array($value) || is_array($old_raw)) {
				if (json_encode($old_raw) !== json_encode($value)) {
					$diff_changes[$key] = $value;
				}
			} else {
				$old = isset($current_payload[$key]) ? (string)$current_payload[$key] : '';

				if ($old !== (string)$value) {
					$diff_changes[$key] = $value;
				}
			}
		}

		if (!$diff_changes && $dim_rebuild === null) {
			return ['success' => false, 'error' => 'No changes detected — values match the existing TTN.'];
		}

		// Merge changes onto the last-known-good payload from create.
		$new_payload = array_merge($current_payload, $diff_changes);

		// Weight / SeatsAmount / dimensions drive the OptionsSeat array (NP
		// requires a per-seat breakdown — see createTtnForOrder note around
		// line 422). When any of those change we rebuild OptionsSeat from
		// scratch, preserving any sides the operator did NOT override.
		$needs_seats_rebuild = isset($diff_changes['Weight']) || isset($diff_changes['SeatsAmount']) || $dim_rebuild !== null;

		if ($needs_seats_rebuild) {
			$weight_total = (float)$new_payload['Weight'];
			$seats = max(1, (int)$new_payload['SeatsAmount']);
			$weight_per_seat = round($weight_total / $seats, 3);

			if ($weight_per_seat <= 0) {
				$weight_per_seat = 0.1;
			}

			$first_existing = $current_payload['OptionsSeat'][0] ?? [];

			if ($dim_rebuild !== null) {
				$width = (int)$dim_rebuild['width'];
				$length = (int)$dim_rebuild['length'];
				$height = (int)$dim_rebuild['height'];
				$vol = (string)$dim_rebuild['volumetricVolume'];
			} else {
				$vol = $first_existing['volumetricVolume'] ?? '0.002';
				$width = $first_existing['volumetricWidth'] ?? 10;
				$length = $first_existing['volumetricLength'] ?? 20;
				$height = $first_existing['volumetricHeight'] ?? 10;
			}

			$options_seat = [];

			for ($i = 0; $i < $seats; $i++) {
				$options_seat[] = [
					'volumetricVolume' => $vol,
					'volumetricWidth'  => $width,
					'volumetricLength' => $length,
					'volumetricHeight' => $height,
					'weight'           => $weight_per_seat,
				];
			}

			$new_payload['OptionsSeat'] = $options_seat;

			// Record OptionsSeat as a changed field for the audit log / diff
			// summary, even though the operator entered individual dimensions
			// or seats — they should see "dimensions changed" in the history.
			// Skip the marker when the rebuild produced the same per-seat
			// breakdown that was already stored: that happens when the
			// operator reopens the edit modal and saves without changing
			// dimensions, and the audit log shouldn't log a no-op.
			if ($dim_rebuild !== null) {
				$old_options_seat = $current_payload['OptionsSeat'] ?? null;

				if (json_encode($old_options_seat) !== json_encode($options_seat)) {
					$diff_changes['OptionsSeat'] = $options_seat;
				}
			}
		}

		// NP needs Ref to identify which document to mutate; without it
		// the API would treat the payload as a create attempt and fail.
		$new_payload['Ref'] = $existing_ttn_ref;

		$credentials = $this->getApiCredentials();

		if (empty($credentials['success'])) {
			return ['success' => false, 'error' => (string)($credentials['error'] ?? 'Nova Poshta API credentials are not configured.')];
		}

		$api_key = (string)$credentials['api_key'];
		$api_url = (string)$credentials['api_url'];

		try {
			$response = $this->callApi($api_key, $api_url, 'InternetDocument', 'update', $new_payload);
		} catch (\Throwable $e) {
			return ['success' => false, 'error' => $e->getMessage()];
		}

		if (empty($response['success'])) {
			$error = $this->getApiError($response, 'Nova Poshta API did not update TTN.');

			// Intentionally do NOT persist the failed payload — leaving the DB
			// row in its last-known-good state means the operator can retry
			// the same (or a corrected) edit without us having silently
			// shifted the baseline.
			return ['success' => false, 'error' => $error];
		}

		$this->persistTtnUpdate($order_id, $new_payload);

		return [
			'success' => true,
			'ttn_number' => $existing_ttn_number,
			'ttn_ref' => $existing_ttn_ref,
			'changed_fields' => array_keys($diff_changes),
			'old_values' => array_intersect_key($current_payload, $diff_changes),
			'new_values' => $diff_changes,
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

	/**
	 * Live-search Nova Poshta cities — back-end for the admin TTN edit modal
	 * picker. Uses Address.searchSettlements (NP's autocomplete endpoint, the
	 * same one the catalog uses), NOT Address.getCities — the latter takes
	 * AreaRef + pagination and is not a free-text search.
	 *
	 * Returns DeliveryCity ref (the field accepted by Address.getWarehouses).
	 *
	 * @return array<int, array<string, string>> rows of {description, value, label, ref}
	 */
	public function lookupCities(string $search): array {
		$credentials = $this->getApiCredentials();

		if (empty($credentials['success'])) {
			return [];
		}

		$search = trim($search);

		// NP searchSettlements requires at least 2 chars; on empty/short input
		// we just return nothing rather than spam the API with bad queries.
		if (mb_strlen($search, 'UTF-8') < 2) {
			return [];
		}

		$response = $this->callApi(
			$credentials['api_key'],
			$credentials['api_url'],
			'Address',
			'searchSettlements',
			['CityName' => $search, 'Limit' => '20', 'Page' => '1']
		);

		if (empty($response['success']) || empty($response['data'][0]['Addresses'])) {
			return [];
		}

		$data = [];

		foreach ($response['data'][0]['Addresses'] as $row) {
			$ref = trim((string)($row['DeliveryCity'] ?? $row['Ref'] ?? ''));
			$description = trim((string)($row['MainDescription'] ?? ''));

			if ($ref === '' || $description === '') {
				continue;
			}

			$present = trim((string)($row['Present'] ?? ''));
			$label = $present !== '' ? $present : $description;

			$data[] = [
				'description' => $description,
				'value'       => $description,
				'label'       => $label,
				'ref'         => $ref
			];
		}

		return $data;
	}

	/**
	 * Live-search NP warehouses (branches / postomats) for a given city ref.
	 * Filters by delivery_type so the postomat dropdown shows only postomats
	 * and the branch dropdown shows only branches. delivery_type='courier'
	 * returns nothing — courier deliveries do not pick from a warehouse list.
	 *
	 * @return array<int, array<string, string>> rows of {description, value, label, ref}
	 */
	public function lookupWarehouses(string $city_ref, string $search, string $delivery_type): array {
		$city_ref = trim($city_ref);

		if ($city_ref === '' || $delivery_type === 'courier') {
			return [];
		}

		$credentials = $this->getApiCredentials();

		if (empty($credentials['success'])) {
			return [];
		}

		$properties = ['CityRef' => $city_ref, 'Limit' => '50', 'Page' => '1'];
		$search = trim($search);

		if ($search !== '') {
			$properties['FindByString'] = $search;
		}

		// AddressGeneral.getWarehouses is the newer endpoint with broader
		// coverage (includes some lockers / temp branches missing from the
		// legacy Address.getWarehouses). Mirror the catalog fallback chain.
		$response = $this->callApi($credentials['api_key'], $credentials['api_url'], 'AddressGeneral', 'getWarehouses', $properties);

		if (empty($response['success']) || empty($response['data'])) {
			$response = $this->callApi($credentials['api_key'], $credentials['api_url'], 'Address', 'getWarehouses', $properties);
		}

		if (empty($response['success']) || empty($response['data'])) {
			return [];
		}

		$need_locker = $delivery_type === 'locker';
		$data = [];

		foreach ($response['data'] as $row) {
			$ref = trim((string)($row['Ref'] ?? ''));
			$description = trim((string)($row['Description'] ?? ''));

			if ($ref === '' || $description === '') {
				continue;
			}

			$category = trim((string)($row['CategoryOfWarehouse'] ?? ''));
			$category_lower = mb_strtolower($category, 'UTF-8');
			$is_locker = $category !== '' && (
				$category === 'Postomat' ||
				mb_strpos($category_lower, 'поштомат') !== false ||
				mb_strpos($category_lower, 'postomat') !== false
			);

			if ($need_locker && !$is_locker) {
				continue;
			}

			if (!$need_locker && $is_locker) {
				continue;
			}

			// Write the full warehouse description into the form ("Відділення №14
			// (до 30 кг на одне місце): вул. Благовісна, 372/Юрія Іллєнка, 59")
			// — operator needs both the branch number AND the street address
			// visible at a glance, especially when reviewing the TTN before
			// sending. ShortAddress alone ("Черкаси, Благовісна, 269/4") drops
			// the "Відділення №X" prefix and makes branch numbers ambiguous.
			$data[] = [
				'description' => $description,
				'value'       => $description,
				'label'       => $description,
				'ref'         => $ref
			];
		}

		return $data;
	}

	/**
	 * Resolve a single warehouse by its Ref.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function lookupWarehouseByRef(string $ref): array {
		$ref = trim($ref);
		if ($ref === '') {
			return [];
		}

		$credentials = $this->getApiCredentials();

		if (empty($credentials['success'])) {
			return [];
		}

		$response = $this->callApi($credentials['api_key'], $credentials['api_url'], 'AddressGeneral', 'getWarehouses', ['Ref' => $ref]);

		if (empty($response['success']) || empty($response['data'][0])) {
			$response = $this->callApi($credentials['api_key'], $credentials['api_url'], 'Address', 'getWarehouses', ['Ref' => $ref]);
		}

		if (empty($response['success']) || empty($response['data'][0])) {
			return [];
		}

		$row = $response['data'][0];
		$description = trim((string)($row['Description'] ?? ''));
		$city_description = trim((string)($row['CityDescription'] ?? ''));
		$city_ref = trim((string)($row['CityRef'] ?? ''));

		$label_parts = [];
		if ($city_description !== '') $label_parts[] = $city_description;
		if ($description !== '') $label_parts[] = $description;
		$label = implode(': ', $label_parts);

		return [[
			'description' => $description !== '' ? $description : $ref,
			'value' => $description !== '' ? $description : $ref,
			'label' => $label !== '' ? $label : $ref,
			'ref' => $ref,
			'city_ref' => $city_ref,
			'city' => $city_description,
		]];
	}

	/**
	 * Live-list of the configured sender's warehouse/door addresses pulled
	 * straight from Nova Poshta. Drives the "Адреса відправника" picker in
	 * the TTN modal — operator picks the actual ship-from warehouse instead
	 * of relying on fetchSenderData()'s "first Warehouse, else first"
	 * fallback, which has historically picked the wrong city when the NP
	 * account holds more than one address (the symptom that prompted this
	 * change: TTNs auto-generating a courier pickup at a Lviv address while
	 * the operator ships from a different one).
	 *
	 * @return array<int, array<string, string>>
	 */
	public function lookupSenderAddresses(): array {
		$credentials = $this->getApiCredentials();

		if (empty($credentials['success'])) {
			return [];
		}

		$api_key = (string) $credentials['api_key'];
		$api_url = (string) $credentials['api_url'];

		// NP does not expose a separate API method that returns the
		// "user-picked sender warehouses" list from the business cabinet.
		// Counterparty*.getCounterpartyAddresses returns street/door addresses
		// and can include multiple cities.
		//
		// What the operator actually needs here is the set of sender warehouse
		// Refs that are used in real shipments (ServiceType Warehouse*). Those
		// SenderAddress Refs are returned by InternetDocument.getDocumentList.
		// We build a unique list from the recent documents window (NP limits
		// the range to 3 months) and then resolve each Ref via
		// AddressGeneral.getWarehouses({Ref}).
		$from = date('d.m.Y', strtotime('-80 days'));
		$to = date('d.m.Y');

		$sender_address_refs = [];
		$sender_address_service_types = [];
		for ($page = 1; $page <= 20; $page++) {
			$list_response = $this->callApi(
				$api_key,
				$api_url,
				'InternetDocument',
				'getDocumentList',
				['DateTimeFrom' => $from, 'DateTimeTo' => $to, 'Page' => $page]
			);

			if (empty($list_response['success']) || empty($list_response['data'])) {
				break;
			}

			foreach ($list_response['data'] as $row) {
				$service_type = trim((string)($row['ServiceType'] ?? ''));
				$sender_address_ref = trim((string)($row['SenderAddress'] ?? ''));

				if ($sender_address_ref === '' || $service_type === '') {
					continue;
				}

				// Only warehouse-based sender addresses are relevant.
				if (strpos($service_type, 'Warehouse') === false) {
					continue;
				}

				$sender_address_refs[$sender_address_ref] = true;
				$sender_address_service_types[$sender_address_ref] = $service_type;
			}
		}

		if (!$sender_address_refs) {
			return [];
		}

		// Preload counterparty door/street addresses (used when ServiceType is
		// Doors* and SenderAddress points to a counterparty address ref rather
		// than a warehouse ref).
		$counterparty_address_by_ref = [];
		$counterparties_response = $this->callApi(
			$api_key,
			$api_url,
			'Counterparty',
			'getCounterparties',
			['CounterpartyProperty' => 'Sender', 'Page' => 1]
		);
		if (!empty($counterparties_response['success']) && !empty($counterparties_response['data'][0]['Ref'])) {
			$sender_ref = trim((string)$counterparties_response['data'][0]['Ref']);
			for ($page = 1; $page <= 20; $page++) {
				$addresses_response = $this->callApi(
					$api_key,
					$api_url,
					'Counterparty',
					'getCounterpartyAddresses',
					['Ref' => $sender_ref, 'Page' => $page]
				);
				if (empty($addresses_response['success']) || empty($addresses_response['data'])) break;
				foreach ($addresses_response['data'] as $row) {
					$aref = trim((string)($row['Ref'] ?? ''));
					if ($aref !== '') $counterparty_address_by_ref[$aref] = $row;
				}
			}
		}

		$data = [];

		foreach (array_keys($sender_address_refs) as $ref) {
			$service_type = (string)($sender_address_service_types[$ref] ?? '');

			// 1) Try resolve as a warehouse ref.
			$warehouse_response = $this->callApi(
				$api_key,
				$api_url,
				'AddressGeneral',
				'getWarehouses',
				['Ref' => $ref]
			);

			if (!empty($warehouse_response['success']) && !empty($warehouse_response['data'][0])) {
				$row = $warehouse_response['data'][0];
				$description = trim((string)($row['Description'] ?? ''));
				$city_description = trim((string)($row['CityDescription'] ?? ''));

				$label_parts = [];
				if ($city_description !== '') $label_parts[] = $city_description;
				if ($description !== '') $label_parts[] = $description;
				$label = implode(': ', $label_parts);

				$data[] = [
					'ref' => $ref,
					'value' => $ref,
					'description' => $description,
					'city' => $city_description,
					'address_type' => 'Warehouse',
					'label' => $label !== '' ? $label : $ref,
				];
				continue;
			}

			// 2) Fallback: resolve as a counterparty (door/street) address ref.
			if (!empty($counterparty_address_by_ref[$ref])) {
				$row = $counterparty_address_by_ref[$ref];
				$description = trim((string)($row['Description'] ?? ''));
				$city_description = trim((string)($row['CityDescription'] ?? ''));

				$label_parts = [];
				if ($city_description !== '') $label_parts[] = $city_description;
				if ($description !== '') $label_parts[] = $description;
				$label = implode(': ', $label_parts);

				$data[] = [
					'ref' => $ref,
					'value' => $ref,
					'description' => $description,
					'city' => $city_description,
					'address_type' => 'Doors',
					'label' => $label !== '' ? $label : $ref,
				];
				continue;
			}

			// Unknown ref type.
			continue;
		}

		// Sort for stable UX (avoids "random" perceived order).
		usort($data, function($a, $b) {
			$ac = mb_strtolower((string)($a['city'] ?? ''), 'UTF-8');
			$bc = mb_strtolower((string)($b['city'] ?? ''), 'UTF-8');
			if ($ac !== $bc) return $ac <=> $bc;
			$al = mb_strtolower((string)($a['label'] ?? ''), 'UTF-8');
			$bl = mb_strtolower((string)($b['label'] ?? ''), 'UTF-8');
			return $al <=> $bl;
		});

		return $data;
	}

	private function getOrderData(int $order_id, array $order_info, string $delivery_type = ''): array {
		$this->load->model('sale/order');

		$products = $this->model_sale_order->getProducts($order_id);

		// NP cargo description is hard-set to a generic category string per
		// shipper policy — the actual product line-up is irrelevant to NP and
		// keeping it generic avoids accidental category-mismatch holds.
		$description = 'Одяг';
		$seats_amount = 1;

		if ($products) {
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

		// Locker shipments are hard-limited by NP to a single seat per
		// document, regardless of how many items the customer ordered.
		// Doc says: "Під час створення відправлення на поштомат можна
		// вказувати лише одне місце на одне відправлення".
		$is_locker = $delivery_type === 'locker';
		$seats = $is_locker ? 1 : max($seats_amount, 1);

		// NP's InternetDocument.save now rejects payloads without OptionsSeat
		// even for CargoType=Cargo ("OptionsSeat is empty" / errorCode
		// 20000200226). Build a per-seat array. For non-locker the total
		// weight is split evenly across seats; for locker everything goes
		// into the single seat. Dimensions are a sensible underwear-shop
		// default (20×10×10 cm ≈ 0.002 m³); locker limits are W≤40,
		// L≤60, H≤30 so we are well within the envelope.
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
			'seats_amount' => (string)$seats,
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

	private function fetchSenderData(string $api_key, string $api_url, string $override_address_ref = ''): array {
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
		$override_address_ref = trim($override_address_ref);

		// 1) If the operator picked an explicit address from the UI dropdown,
		//    honour that — but only if it actually belongs to this sender's
		//    address list (defence-in-depth against a stale/forged ref).
		if ($override_address_ref !== '') {
			foreach ($addresses_response['data'] as $address) {
				if (trim((string)($address['Ref'] ?? '')) === $override_address_ref) {
					$sender_address = $address;
					break;
				}
			}
		}

		// 2) Otherwise, prefer the first Warehouse-type address.
		if (!$sender_address) {
			foreach ($addresses_response['data'] as $address) {
				if (($address['AddressType'] ?? '') === 'Warehouse' && !empty($address['Ref'])) {
					$sender_address = $address;
					break;
				}
			}
		}

		// 3) Last-resort fallback: whatever NP returned first.
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

	/**
	 * Persist an in-place edit of an existing TTN — payload + modified
	 * timestamp only. Identifiers (ttn_number / ttn_ref) and the original
	 * creation timestamp are intentionally NOT touched here, that's what
	 * distinguishes an edit from a recreate.
	 */
	private function persistTtnUpdate(int $order_id, array $payload): void {
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "order_novaposhta`
			SET ttn_payload = '" . $this->db->escape($this->encodeJson($payload)) . "',
				ttn_error = '',
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

	public function clearTtnError(int $order_id): void {
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "order_novaposhta`
			SET ttn_error = ''
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

	private function persistRecipientOverrideToOrder(int $order_id, string $delivery_type, string $city, string $city_ref, string $address, string $address_ref): void {
		$city = trim($city);
		$address = trim($address);
		$delivery_type = trim($delivery_type);

		// 1) Update order_novaposhta meta (used to pre-fill modals)
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "order_novaposhta`
			SET delivery_type = '" . $this->db->escape($delivery_type) . "',
				city = '" . $this->db->escape($city) . "',
				city_ref = '" . $this->db->escape($city_ref) . "',
				address = '" . $this->db->escape($address) . "',
				address_ref = '" . $this->db->escape($address_ref) . "',
				date_modified = NOW()
			WHERE order_id = '" . (int)$order_id . "'"
		);

		// 2) Update the order shipping address (what the admin UI shows)
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "order`
			SET shipping_city = '" . $this->db->escape($city) . "',
				shipping_address_1 = '" . $this->db->escape($address) . "',
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
				// NP's locker-specific InternetDocument.save validator only
				// accepts WarehouseWarehouse or DoorsWarehouse. The legacy
				// "WarehousePostomat" value used to work but is not in the
				// current API contract and risks rejection.
				return 'WarehouseWarehouse';
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
