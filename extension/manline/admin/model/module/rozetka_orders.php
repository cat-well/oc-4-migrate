<?php
namespace Opencart\Admin\Model\Extension\Manline\Module;

/**
 * Rozetka orders (Manline) — READ-ONLY connector.
 *
 * Pulls orders from the Rozetka Seller API and matches each line item back to an
 * OC4 product. Writes NOTHING to Rozetka and NOTHING to the OC4 order tables —
 * this is a viewer / diagnostics stage (Phase 3a). Only GET requests are made.
 */
class RozetkaOrders extends \Opencart\System\Engine\Model {
	private const BASE = 'https://api-seller.rozetka.com.ua';

	private function token(): string {
		$this->load->model('setting/setting');
		$setting = $this->model_setting_setting->getSetting('module_manline_rozetka');

		return trim((string)($setting['module_manline_rozetka_token'] ?? ''));
	}

	public function hasToken(): bool {
		return $this->token() !== '';
	}

	/**
	 * @return array{ok:bool,status:int,data:array,error:string}
	 */
	private function get(string $path): array {
		$token = $this->token();

		if ($token === '') {
			return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'no_token'];
		}

		$ch = curl_init(self::BASE . $path);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPGET        => true,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_HTTPHEADER     => [
				'Authorization: Bearer ' . $token,
				'Content-Type: application/json',
			],
		]);

		$body = curl_exec($ch);
		$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
		curl_close($ch);

		if ($body === false) {
			return ['ok' => false, 'status' => $status, 'data' => [], 'error' => $err ?: 'request_failed'];
		}

		$json = json_decode((string)$body, true);

		if (!is_array($json) || empty($json['success'])) {
			$message = $json['errors']['description'] ?? $json['errors']['message'] ?? ('HTTP ' . $status);

			return ['ok' => false, 'status' => $status, 'data' => [], 'error' => (string)$message];
		}

		return ['ok' => true, 'status' => $status, 'data' => $json['content'] ?? [], 'error' => ''];
	}

	/**
	 * @return array{ok:bool,orders:array,meta:array,error:string}
	 */
	public function getOrders(int $page = 1): array {
		$res = $this->get('/orders/search?page=' . max(1, $page));

		if (!$res['ok']) {
			return ['ok' => false, 'orders' => [], 'meta' => [], 'error' => $res['error']];
		}

		return [
			'ok'     => true,
			'orders' => $res['data']['orders'] ?? [],
			'meta'   => $res['data']['_meta'] ?? [],
			'error'  => '',
		];
	}

	/**
	 * @return array{ok:bool,order:array,error:string}
	 */
	public function getOrderDetail(int $order_id): array {
		$res = $this->get('/orders/' . $order_id . '?expand=purchases,delivery');

		if (!$res['ok']) {
			return ['ok' => false, 'order' => [], 'error' => $res['error']];
		}

		return ['ok' => true, 'order' => $res['data'], 'error' => ''];
	}

	/**
	 * Match a Rozetka line item to an OC4 product.
	 *
	 * @return array{product_id:int,name:string,model:string,confidence:string,candidates:int}
	 */
	public function matchProduct(string $offer, string $item_name): array {
		$none = ['product_id' => 0, 'name' => '', 'model' => '', 'confidence' => 'none', 'candidates' => 0];

		// 1) offer id shaped like "{product_id}-{option}" — exact and unambiguous.
		if (preg_match('/^(\d{1,6})(?:-|$)/', $offer, $m)) {
			$product = $this->productById((int)$m[1]);
			if ($product) {
				return ['product_id' => (int)$product['product_id'], 'name' => (string)$product['name'], 'model' => (string)$product['model'], 'confidence' => 'exact', 'candidates' => 1];
			}
		}

		// 2) fallback: a model token embedded in the item name.
		foreach ($this->modelTokens($item_name) as $token) {
			$rows = $this->productsByModel($token);
			if ($rows) {
				return [
					'product_id' => (int)$rows[0]['product_id'],
					'name'       => (string)$rows[0]['name'],
					'model'      => $token,
					'confidence' => count($rows) === 1 ? 'name' : 'ambiguous',
					'candidates' => count($rows),
				];
			}
		}

		return $none;
	}

	/**
	 * @return list<string>
	 */
	private function modelTokens(string $name): array {
		preg_match_all('/\b[A-Za-z]{0,4}\d{3,}\b/u', $name, $m);

		return array_values(array_unique($m[0]));
	}

	private function productById(int $product_id): array {
		$query = $this->db->query(
			"SELECT p.product_id, p.model, pd.name
			FROM `" . DB_PREFIX . "product` p
			LEFT JOIN `" . DB_PREFIX . "product_description` pd
			  ON pd.product_id = p.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
			WHERE p.product_id = '" . (int)$product_id . "'"
		);

		return $query->row;
	}

	/**
	 * @return list<array{product_id:int,name:string}>
	 */
	private function productsByModel(string $model): array {
		$query = $this->db->query(
			"SELECT p.product_id, pd.name
			FROM `" . DB_PREFIX . "product` p
			LEFT JOIN `" . DB_PREFIX . "product_description` pd
			  ON pd.product_id = p.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
			WHERE p.model = '" . $this->db->escape($model) . "'"
		);

		return $query->rows;
	}
}
