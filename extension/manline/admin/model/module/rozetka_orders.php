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
		return $this->setting('module_manline_rozetka_token');
	}

	public function hasToken(): bool {
		return $this->token() !== '';
	}

	/** Read a module setting straight from the table (works from any bootstrap, incl. the cron endpoint). */
	private function setting(string $key): string {
		$q = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `code` = 'module_manline_rozetka' AND `key` = '" . $this->db->escape($key) . "' LIMIT 1");

		return $q->num_rows ? trim((string)$q->row['value']) : '';
	}

	/** Secret key for the cron endpoint — generated once and stored, so the URL can't be guessed. */
	public function cronKey(): string {
		$key = $this->setting('module_manline_rozetka_cron_key');

		if ($key === '') {
			$key = substr(bin2hex(random_bytes(16)), 0, 24);
			$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0, `code` = 'module_manline_rozetka', `key` = 'module_manline_rozetka_cron_key', `value` = '" . $this->db->escape($key) . "', `serialized` = 0");
		}

		return $key;
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
	public function matchProduct(string $offer, string $item_name, string $article = ''): array {
		$none = ['product_id' => 0, 'name' => '', 'model' => '', 'confidence' => 'none', 'candidates' => 0];

		// 1) offer id shaped like "{product_id}-{option}" — exact and unambiguous.
		if (preg_match('/^(\d{1,6})(?:-|$)/', $offer, $m)) {
			$product = $this->productById((int)$m[1]);
			if ($product) {
				return ['product_id' => (int)$product['product_id'], 'name' => (string)$product['name'], 'model' => (string)$product['model'], 'confidence' => 'exact', 'candidates' => 1];
			}
		}

		// 2) seller article == OC4 model (reliable when Rozetka listing carries it).
		if ($article !== '') {
			$rows = $this->productsByModel($article);
			if (count($rows) === 1) {
				return ['product_id' => (int)$rows[0]['product_id'], 'name' => (string)$rows[0]['name'], 'model' => $article, 'confidence' => 'article', 'candidates' => 1];
			}
		}

		// 3) fallback: a model token embedded in the item name.
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

	// ---- Phase 3b: import Rozetka orders into OC4 (creates real orders → they flow to 1C) ----

	/**
	 * Pull all Rozetka orders and create OC4 orders for the ones not imported yet.
	 *
	 * @return array{ok:bool,pulled:int,imported:int,skipped:int,errors:int,unmatched:int,created:list<array{rozetka:int,oc:int}>,error:string}
	 */
	public function importNewOrders(): array {
		$stats = ['ok' => true, 'pulled' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => 0, 'unmatched' => 0, 'created' => [], 'error' => ''];

		$page = 1;
		$pages = 1;

		do {
			$res = $this->getOrders($page);
			if (!$res['ok']) {
				$stats['ok'] = false;
				$stats['error'] = $res['error'];
				break;
			}
			$pages = max(1, (int)($res['meta']['pageCount'] ?? 1));

			foreach ($res['orders'] as $order) {
				$stats['pulled']++;
				$rid = (int)$order['id'];

				if ($this->alreadyImported($rid)) {
					$stats['skipped']++;
					continue;
				}

				$detail = $this->getOrderDetail($rid);
				if (!$detail['ok']) {
					$stats['errors']++;
					continue;
				}

				$oid = $this->createOcOrder($order, $detail['order'], $stats);
				if ($oid) {
					$this->db->query("INSERT INTO `" . DB_PREFIX . "rozetka_order` SET `rozetka_order_id` = '" . $rid . "', `order_id` = '" . (int)$oid . "', `date_added` = NOW()");
					$stats['imported']++;
					$stats['created'][] = ['rozetka' => $rid, 'oc' => (int)$oid];
				} else {
					$stats['errors']++;
				}
			}

			$page++;
		} while ($page <= $pages);

		return $stats;
	}

	/**
	 * Imported Rozetka orders (dedup table joined to the created OC4 orders).
	 *
	 * @return list<array<string,mixed>>
	 */
	public function getImportedOrders(int $limit = 200): array {
		$lang = (int)$this->config->get('config_language_id');

		return $this->db->query(
			"SELECT ro.`rozetka_order_id`, ro.`order_id`, ro.`date_added` AS imported_at,
					o.`firstname`, o.`lastname`, o.`total`, o.`currency_code`, o.`order_status_id`,
					os.`name` AS status_name
			FROM `" . DB_PREFIX . "rozetka_order` ro
			JOIN `" . DB_PREFIX . "order` o ON o.`order_id` = ro.`order_id`
			LEFT JOIN `" . DB_PREFIX . "order_status` os ON os.`order_status_id` = o.`order_status_id` AND os.`language_id` = '" . $lang . "'
			ORDER BY ro.`date_added` DESC
			LIMIT " . (int)$limit
		)->rows;
	}

	private function alreadyImported(int $rid): bool {
		return (int)$this->db->query("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "rozetka_order` WHERE `rozetka_order_id` = '" . $rid . "'")->row['c'] > 0;
	}

	private function createOcOrder(array $order, array $detail, array &$stats): int {
		$e = fn($v): string => $this->db->escape((string)$v);

		$delivery = $detail['delivery'] ?? [];
		$purchases = $detail['purchases'] ?? [];

		$rt = $order['recipient_title'] ?? $order['user_title'] ?? [];
		$first = (string)($delivery['recipient_first_name'] ?? $rt['first_name'] ?? '');
		$last  = (string)($delivery['recipient_last_name'] ?? $rt['last_name'] ?? '');
		$phone = (string)($delivery['recipient_phone'] ?? $order['recipient_phone'] ?? $order['user_phone'] ?? '');

		$city = (string)($delivery['city']['name_ua'] ?? $delivery['city']['name'] ?? '');
		$zone = (string)($delivery['city']['region_title'] ?? '');
		$ref  = (string)($delivery['ref_id'] ?? '');
		// 1C binds the NP warehouse by parsing "Відділення №N" out of the address, so build it that way.
		$branch = (string)($delivery['place_number'] ?? '');
		$street = trim(implode(', ', array_filter([(string)($delivery['place_street'] ?? ''), (string)($delivery['place_house'] ?? '')])));
		$addr = $branch !== '' ? ('Відділення №' . $branch . ($street !== '' ? ': ' . $street : '')) : $street;
		if ($addr === '') {
			$addr = $city;
		}

		$ship = json_encode(['code' => 'novaposhta.branch', 'name' => 'Нова Пошта'], JSON_UNESCAPED_UNICODE);
		$pay  = json_encode(['code' => 'cod.cod', 'name' => 'Оплата при доставці'], JSON_UNESCAPED_UNICODE);

		$total = (float)($order['amount_with_discount'] ?? $order['amount'] ?? $order['cost'] ?? 0);
		$created = (string)($order['created'] ?? '');
		$comment = 'Rozetka #' . (int)$order['id'] . (empty($order['comment']) ? '' : ' | ' . $order['comment']);

		$this->db->query("INSERT INTO `" . DB_PREFIX . "order` SET
			`store_id` = 0, `store_name` = 'Rozetka', `store_url` = '',
			`customer_id` = 0, `customer_group_id` = 1,
			`firstname` = '" . $e($first) . "', `lastname` = '" . $e($last) . "', `email` = '', `telephone` = '" . $e($phone) . "',
			`payment_firstname` = '" . $e($first) . "', `payment_lastname` = '" . $e($last) . "', `payment_address_1` = '" . $e($addr) . "', `payment_city` = '" . $e($city) . "', `payment_zone` = '" . $e($zone) . "', `payment_country` = 'Україна', `payment_country_id` = 220, `payment_method` = '" . $e($pay) . "',
			`shipping_firstname` = '" . $e($first) . "', `shipping_lastname` = '" . $e($last) . "', `shipping_address_1` = '" . $e($addr) . "', `shipping_city` = '" . $e($city) . "', `shipping_zone` = '" . $e($zone) . "', `shipping_country` = 'Україна', `shipping_country_id` = 220, `shipping_method` = '" . $e($ship) . "',
			`comment` = '" . $e($comment) . "', `total` = " . $total . ", `order_status_id` = 1, `invoice_prefix` = 'RZ-',
			`language_id` = 3, `language_code` = 'uk-ua', `currency_id` = 9, `currency_code` = 'UAH', `currency_value` = 1, `ip` = '',
			`date_added` = " . ($created !== '' ? "'" . $e($created) . "'" : 'NOW()') . ", `date_modified` = NOW()");
		$oid = (int)$this->db->getLastId();

		$sub = 0.0;
		foreach ($purchases as $p) {
			$item = $p['item'] ?? [];
			$offer = (string)($item['uploader_offer_id'] ?? $p['uploader_offer_id'] ?? $item['price_offer_id'] ?? '');
			$article = (string)($item['article'] ?? '');
			$name = (string)($p['item_name'] ?? $item['name'] ?? '');
			$qty = max(1, (int)($p['quantity'] ?? 1));
			$price = (float)($p['price'] ?? 0);
			$line = $price * $qty;
			$sub += $line;

			$match = $this->matchProduct($offer, $name, $article);
			$pid = (int)$match['product_id'];
			if (!$pid) {
				$stats['unmatched']++;
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "order_product` SET `order_id` = " . $oid . ", `product_id` = " . $pid . ", `name` = '" . $e($name) . "', `model` = '" . $e($match['model']) . "', `quantity` = " . $qty . ", `price` = " . $price . ", `total` = " . $line . ", `tax` = 0, `reward` = 0");
			$opid = (int)$this->db->getLastId();

			$ov = preg_match('/^\d{1,6}-(\d{1,7})$/', $offer, $mm) ? (int)$mm[1] : 0;
			if ($pid && $ov) {
				$opt = $this->resolveOption($pid, $ov);
				if ($opt) {
					$this->db->query("INSERT INTO `" . DB_PREFIX . "order_option` SET `order_id` = " . $oid . ", `order_product_id` = " . $opid . ", `product_option_id` = " . (int)$opt['product_option_id'] . ", `product_option_value_id` = " . (int)$opt['product_option_value_id'] . ", `name` = '" . $e($opt['opt_name']) . "', `value` = '" . $e($opt['val_name']) . "', `type` = 'radio'");
				}
			}
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_total` SET `order_id` = " . $oid . ", `extension` = '', `code` = 'sub_total', `title` = 'Сума', `value` = " . $sub . ", `sort_order` = 1");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_total` SET `order_id` = " . $oid . ", `extension` = '', `code` = 'total', `title` = 'Разом', `value` = " . $total . ", `sort_order` = 9");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_history` SET `order_id` = " . $oid . ", `order_status_id` = 1, `notify` = 0, `comment` = 'Імпортовано з Rozetka #" . (int)$order['id'] . "', `date_added` = NOW()");

		$ttn = (string)($order['ttn'] ?? '');
		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_novaposhta` SET `order_id` = " . $oid . ", `shipping_code` = 'novaposhta.branch', `delivery_type` = 'branch', `city` = '" . $e($city) . "', `city_ref` = '', `address` = '" . $e($addr) . "', `address_ref` = '" . $e($ref) . "', `zone_id` = 0, `zone` = '" . $e($zone) . "', `country_id` = 220, `country` = 'Україна', `ttn_ref` = '', `ttn_number` = '" . $e($ttn) . "', `ttn_status_code` = '', `ttn_status_text` = '', `ttn_status_date` = '', `date_added` = NOW(), `date_modified` = NOW()");

		return $oid;
	}

	/**
	 * @return array{product_option_id:int,product_option_value_id:int,opt_name:string,val_name:string}|array{}
	 */
	private function resolveOption(int $product_id, int $option_value_id): array {
		$row = $this->db->query("SELECT `product_option_id`, `product_option_value_id`, `option_id`, `option_value_id` FROM `" . DB_PREFIX . "product_option_value` WHERE `product_id` = " . $product_id . " AND `option_value_id` = " . $option_value_id . " LIMIT 1")->row;
		if (!$row) {
			return [];
		}

		$lang = (int)$this->config->get('config_language_id');
		$on = $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "option_description` WHERE `option_id` = " . (int)$row['option_id'] . " AND `language_id` = " . $lang . " LIMIT 1")->row;
		$vn = $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "option_value_description` WHERE `option_value_id` = " . (int)$row['option_value_id'] . " AND `language_id` = " . $lang . " LIMIT 1")->row;

		return [
			'product_option_id'       => (int)$row['product_option_id'],
			'product_option_value_id' => (int)$row['product_option_value_id'],
			'opt_name'                => (string)($on['name'] ?? 'Розмір'),
			'val_name'                => (string)($vn['name'] ?? ''),
		];
	}
}
