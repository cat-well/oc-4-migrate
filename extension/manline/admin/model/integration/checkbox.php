<?php
namespace Opencart\Admin\Model\Extension\Manline\Integration;

class Checkbox extends \Opencart\System\Engine\Model {
	public function ensureTable(): void {
		$this->db->query(
			"CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "order_checkbox` (
				`order_id` INT(11) NOT NULL,
				`module_id` INT(11) NOT NULL DEFAULT 0,
				`receipt_id` VARCHAR(64) NOT NULL DEFAULT '',
				`return_receipt_id` VARCHAR(64) NOT NULL DEFAULT '',
				`return_receipt_status` VARCHAR(64) NOT NULL DEFAULT '',
				`return_sms_sent` TINYINT(1) NOT NULL DEFAULT 0,
				`receipt_status` VARCHAR(64) NOT NULL DEFAULT '',
				`sms_phone` VARCHAR(32) NOT NULL DEFAULT '',
				`sms_sent` TINYINT(1) NOT NULL DEFAULT 0,
				`payload` MEDIUMTEXT NULL,
				`response` MEDIUMTEXT NULL,
				`error` TEXT NULL,
				`date_added` DATETIME NOT NULL,
				`date_modified` DATETIME NOT NULL,
				PRIMARY KEY (`order_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);
	}

	public function getOrderMeta(int $order_id): array {
		if ($order_id <= 0) {
			return [];
		}

		$this->ensureTable();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_checkbox` WHERE order_id = '" . (int)$order_id . "' LIMIT 1");

		if (!$query->num_rows) {
			return [];
		}

		$row = $query->row;

		foreach (['payload', 'response'] as $field) {
			if (!empty($row[$field]) && is_string($row[$field])) {
				$decoded = json_decode($row[$field], true);
				if (is_array($decoded)) {
					$row[$field] = $decoded;
				}
			}
		}

		return $row;
	}

	public function saveOrderMeta(int $order_id, array $meta): void {
		if ($order_id <= 0) {
			return;
		}

		$this->ensureTable();

		$module_id = (int)($meta['module_id'] ?? 0);
		$receipt_id = trim((string)($meta['receipt_id'] ?? ''));
		$return_receipt_id = trim((string)($meta['return_receipt_id'] ?? ''));
		$return_receipt_status = trim((string)($meta['return_receipt_status'] ?? ''));
		$return_sms_sent = !empty($meta['return_sms_sent']) ? 1 : 0;
		$receipt_status = trim((string)($meta['receipt_status'] ?? ''));
		$sms_phone = trim((string)($meta['sms_phone'] ?? ''));
		$sms_sent = !empty($meta['sms_sent']) ? 1 : 0;
		$payload = isset($meta['payload']) ? json_encode($meta['payload'], JSON_UNESCAPED_UNICODE) : null;
		$response = isset($meta['response']) ? json_encode($meta['response'], JSON_UNESCAPED_UNICODE) : null;
		$error = isset($meta['error']) ? (string)$meta['error'] : '';

		$this->db->query(
			"INSERT INTO `" . DB_PREFIX . "order_checkbox`
			SET order_id = '" . (int)$order_id . "',
				receipt_id = '" . $this->db->escape($receipt_id) . "',
				receipt_status = '" . $this->db->escape($receipt_status) . "',
				sms_phone = '" . $this->db->escape($sms_phone) . "',
				sms_sent = '" . (int)$sms_sent . "',
				payload = " . ($payload === null ? "NULL" : "'" . $this->db->escape($payload) . "'") . ",
				response = " . ($response === null ? "NULL" : "'" . $this->db->escape($response) . "'") . ",
				error = " . ($error === '' ? "NULL" : "'" . $this->db->escape($error) . "'") . ",
				date_added = NOW(),
				date_modified = NOW()
			ON DUPLICATE KEY UPDATE
				receipt_id = VALUES(receipt_id),
				receipt_status = VALUES(receipt_status),
				sms_phone = VALUES(sms_phone),
				sms_sent = VALUES(sms_sent),
				payload = VALUES(payload),
				response = VALUES(response),
				error = VALUES(error),
				date_modified = NOW()"
		);
	}

	public function normalizePhoneTo380(string $phone): string {
		$digits = preg_replace('/\D+/', '', $phone);
		$digits = is_string($digits) ? $digits : '';

		if ($digits === '') {
			return '';
		}

		// Already 380XXXXXXXXX
		if (preg_match('/^380\d{9}$/', $digits)) {
			return $digits;
		}

		// +380XXXXXXXXX or 380XXXXXXXXX in digits handled above; handle 0XXXXXXXXX
		if (preg_match('/^0\d{9}$/', $digits)) {
			return '38' . $digits;
		}

		// Handle 80XXXXXXXXX (missing leading 3)
		if (preg_match('/^80\d{9}$/', $digits)) {
			return '3' . $digits;
		}

		return '';
	}

	private function request(string $method, string $url, array $headers = [], string $body = ''): array {
		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

		if ($body !== '') {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		}

		$response = curl_exec($ch);
		$err = curl_error($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return ['code' => $code, 'body' => is_string($response) ? $response : '', 'error' => $err];
	}

	public function cashierSignIn(array $config): array {
		$api_url = rtrim((string)($config['api_url'] ?? ''), '/');
		$login = (string)($config['cashier_login'] ?? '');
		$password = (string)($config['cashier_password'] ?? '');

		if ($api_url === '' || $login === '' || $password === '') {
			return ['success' => false, 'error' => 'Checkbox credentials are not configured.'];
		}

		$url = $api_url . '/api/v1/cashier/signin';
		$payload = json_encode(['login' => $login, 'password' => $password], JSON_UNESCAPED_UNICODE);
		if (!is_string($payload)) {
			$payload = '{}';
		}

		$headers = [
			'Content-Type: application/json'
		];

		if (!empty($config['client_name'])) {
			$headers[] = 'X-Client-Name: ' . (string)$config['client_name'];
		}
		if (!empty($config['client_version'])) {
			$headers[] = 'X-Client-Version: ' . (string)$config['client_version'];
		}

		$res = $this->request('POST', $url, $headers, $payload);

		if (!empty($res['error'])) {
			return ['success' => false, 'error' => (string)$res['error']];
		}

		$data = json_decode((string)$res['body'], true);
		if (!is_array($data)) {
			return ['success' => false, 'error' => 'Invalid response from Checkbox sign-in.'];
		}

		$token = (string)($data['access_token'] ?? '');
		if ($token === '') {
			return ['success' => false, 'error' => (string)($data['message'] ?? 'Unable to sign in to Checkbox.')];
		}

		return ['success' => true, 'token' => $token, 'raw' => $data];
	}

	public function createSellReceipt(array $config, array $receipt_payload): array {
		$sign = $this->cashierSignIn($config);
		if (empty($sign['success'])) {
			return $sign;
		}

		$token = (string)$sign['token'];
		$api_url = rtrim((string)($config['api_url'] ?? ''), '/');
		$url = $api_url . '/api/v1/receipts/sell';
		$payload = json_encode($receipt_payload, JSON_UNESCAPED_UNICODE);
		if (!is_string($payload)) {
			$payload = '{}';
		}

		$headers = [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $token
		];

		if (!empty($config['client_name'])) {
			$headers[] = 'X-Client-Name: ' . (string)$config['client_name'];
		}
		if (!empty($config['client_version'])) {
			$headers[] = 'X-Client-Version: ' . (string)$config['client_version'];
		}

		$res = $this->request('POST', $url, $headers, $payload);

		$data = json_decode((string)$res['body'], true);
		if (!is_array($data)) {
			return ['success' => false, 'error' => 'Invalid response from Checkbox receipt create.', 'http_code' => (int)$res['code'], 'response' => $res['body']];
		}

		if ((int)$res['code'] >= 400) {
			return ['success' => false, 'error' => (string)($data['message'] ?? 'Checkbox API error.'), 'http_code' => (int)$res['code'], 'response' => $data];
		}

		$receipt_id = (string)($data['id'] ?? '');
		return ['success' => true, 'receipt_id' => $receipt_id, 'response' => $data];
	}

	/**
	 * Create return receipt.
	 *
	 * Checkbox uses the same endpoint as SELL: POST /api/v1/receipts/sell
	 * The payload must include related_receipt_id (original sell receipt id) and usually negative payments.
	 */
	public function createReturnReceipt(array $config, array $return_payload): array {
		return $this->createSellReceipt($config, $return_payload);
	}

	public function sendReceiptSms(array $config, string $receipt_id, string $phone380): array {
		$sign = $this->cashierSignIn($config);
		if (empty($sign['success'])) {
			return $sign;
		}

		$token = (string)$sign['token'];
		$api_url = rtrim((string)($config['api_url'] ?? ''), '/');
		$url = $api_url . '/api/v1/receipts/' . rawurlencode($receipt_id) . '/sms';
		$payload = json_encode(['phone' => $phone380], JSON_UNESCAPED_UNICODE);
		if (!is_string($payload)) {
			$payload = '{}';
		}

		$headers = [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $token
		];

		if (!empty($config['client_name'])) {
			$headers[] = 'X-Client-Name: ' . (string)$config['client_name'];
		}
		if (!empty($config['client_version'])) {
			$headers[] = 'X-Client-Version: ' . (string)$config['client_version'];
		}

		$res = $this->request('POST', $url, $headers, $payload);

		if ((int)$res['code'] >= 400) {
			$data = json_decode((string)$res['body'], true);
			if (!is_array($data)) {
				$data = ['message' => (string)$res['body']];
			}

			return ['success' => false, 'error' => (string)($data['message'] ?? 'Checkbox API error.'), 'http_code' => (int)$res['code'], 'response' => $data];
		}

		return ['success' => true];
	}
}
