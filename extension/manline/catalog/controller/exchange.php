<?php
namespace Opencart\Catalog\Controller\Extension\Manline;

/**
 * 1C exchange endpoint (Manline) — CommerceML 2 protocol.
 *
 * Skeleton stage: authentication (checkauth) + session init are fully working;
 * file chunks are captured to storage; import/order modes are logged and
 * acknowledged but not processed yet. Reached via the compat shim
 * export/neoseo_exchange1c.php (kept only so 1C's configured URL still resolves).
 */
class Exchange extends \Opencart\System\Engine\Controller {
	private const COOKIE = 'MANLINE1C';
	private const FILE_LIMIT = 52428800; // 50 MB per chunk
	private const ORDER_STATUSES = '1,2'; // Ожидание, В обработке
	private const ORDER_EXPORT_FROM = '2026-06-01 00:00:00';
	private const ORDER_EXPORT_LIMIT = 3; // TEMP: small first batch; set to 0 for all after Iryna verifies matching

	public function index(): void {
		$log = new \Opencart\System\Library\Log('manline_1c.log');

		$type = (string)($this->request->get['type'] ?? '');
		$mode = (string)($this->request->get['mode'] ?? '');
		$filename = isset($this->request->get['filename']) ? basename((string)$this->request->get['filename']) : '';

		$log->write('type=' . $type . '&mode=' . $mode . ($filename ? '&filename=' . $filename : '') . ' from ' . ($this->request->server['REMOTE_ADDR'] ?? '?'));

		// text/html is the conventional 1C-exchange content type; the host CF cache is dodged by the "cart" in the cart1c.php entry URL, not by a cookie
		$this->response->addHeader('Content-Type: text/html; charset=utf-8');

		if ($this->setting('module_manline_exchange1c_status') !== '1') {
			// Still record the knock (with auth verdict) so you can confirm the
			// real 1C is reaching us — and that its credentials match — without
			// turning the exchange on.
			$log->write('exchange DISABLED — ignored ' . $type . '/' . $mode . ' [' . $this->authNote() . ']');
			$this->response->setOutput("failure\nОбмен отключён в админке магазина");
			return;
		}

		if ($mode === 'checkauth') {
			$this->modeCheckauth($log);
			return;
		}

		if (!$this->authed()) {
			$log->write('rejected: auth failed on mode=' . $mode);
			$this->response->setOutput("failure\nАвторизация не пройдена");
			return;
		}

		switch ($type . '/' . $mode) {
			case 'catalog/init':
			case 'sale/init':
				$this->modeInit();
				break;

			case 'catalog/file':
			case 'sale/file':
				$this->modeFile($filename, $log);
				break;

			case 'catalog/import':
			case 'sale/import':
				// Skeleton: capture only, real processing lands in a later phase.
				$log->write('import received (' . $filename . ') — not processed yet (skeleton)');
				$this->response->setOutput('success');
				break;

			case 'sale/query':
				$this->modeQuery($log);
				break;

			case 'sale/success':
			case 'catalog/export':
			case 'sale/status':
				$log->write($mode . ' — acknowledged (skeleton)');
				$this->response->setOutput('success');
				break;

			default:
				$log->write('unknown mode: ' . $type . '/' . $mode);
				$this->response->setOutput("failure\nНеизвестный режим");
		}
	}

	private function modeCheckauth(\Opencart\System\Library\Log $log): void {
		[$user, $pass] = $this->basicAuth();

		if ($user !== '' && $user === $this->setting('module_manline_exchange1c_username') && $pass === $this->setting('module_manline_exchange1c_password')) {
			$log->write('checkauth OK for user ' . $user);
			$this->response->setOutput("success\n" . self::COOKIE . "\n" . $this->token());
		} else {
			$log->write('checkauth FAILED for user ' . $user);
			$this->response->setOutput("failure\nНеверный логин или пароль");
		}
	}

	private function modeInit(): void {
		$this->response->setOutput("zip=no\nfile_limit=" . self::FILE_LIMIT);
	}

	/**
	 * Export site orders to 1C as a CommerceML 2.07 order document.
	 * Read-only: statuses 1,2 since ORDER_EXPORT_FROM; 1C dedups by site number+date.
	 */
	private function modeQuery(\Opencart\System\Library\Log $log): void {
		$this->response->addHeader('Content-Type: application/xml; charset=utf-8');

		$header = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<КоммерческаяИнформация ВерсияСхемы="2.05" ДатаФормирования="' . date('Y-m-d\TH:i:s') . '"';

		$orders = $this->db->query("SELECT `order_id`, `firstname`, `lastname`, `email`, `telephone`, `payment_country`, `payment_zone`, `payment_city`, `payment_address_1`, `shipping_method`, `payment_method`, `currency_code`, `currency_value`, `total`, `date_added`, `comment` FROM `" . DB_PREFIX . "order` WHERE `order_status_id` IN (" . self::ORDER_STATUSES . ") AND `date_added` >= '" . $this->db->escape(self::ORDER_EXPORT_FROM) . "' ORDER BY `order_id`" . (self::ORDER_EXPORT_LIMIT > 0 ? " DESC LIMIT " . self::ORDER_EXPORT_LIMIT : ""))->rows;

		if (!$orders) {
			$log->write('query — 0 orders to export');
			$this->response->setOutput($header . '/>');
			return;
		}

		$in = implode(',', array_map('intval', array_column($orders, 'order_id')));

		$products = [];
		foreach ($this->db->query("SELECT `order_product_id`, `order_id`, `product_id`, `name`, `model`, `quantity`, `price` FROM `" . DB_PREFIX . "order_product` WHERE `order_id` IN (" . $in . ")")->rows as $p) {
			$products[(int)$p['order_id']][] = $p;
		}

		$options = [];
		foreach ($this->db->query("SELECT `order_product_id`, `product_option_value_id`, `name`, `value` FROM `" . DB_PREFIX . "order_option` WHERE `order_id` IN (" . $in . ")")->rows as $o) {
			$options[(int)$o['order_product_id']][] = $o;
		}

		// 1C nomenclature GUIDs (imported from the legacy shop): variant (product#characteristic) preferred, product-level as fallback.
		$pids = [];
		foreach ($products as $ps) {
			foreach ($ps as $p) {
				$pids[(int)$p['product_id']] = true;
			}
		}
		$pin = $pids ? implode(',', array_keys($pids)) : '0';

		$productGuid = [];
		foreach ($this->db->query("SELECT `product_id`, `1c_id` FROM `" . DB_PREFIX . "product_to_1c` WHERE `product_id` IN (" . $pin . ")")->rows as $r) {
			$productGuid[(int)$r['product_id']] = $r['1c_id'];
		}
		$variantGuid = [];
		foreach ($this->db->query("SELECT `product_id`, `product_option_value_id`, `1c_id` FROM `" . DB_PREFIX . "product_option_to_1c` WHERE `product_id` IN (" . $pin . ")")->rows as $r) {
			$variantGuid[(int)$r['product_id'] . '-' . (int)$r['product_option_value_id']] = $r['1c_id'];
		}

		$esc = static fn($v): string => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
		$money = static fn($v): string => number_format((float)$v, 2, '.', '');
		$method = static function ($json): string {
			$d = json_decode((string)$json, true);
			return is_array($d) && isset($d['name']) ? (string)$d['name'] : '';
		};

		$xml = $header . '>' . "\n";

		foreach ($orders as $o) {
			$oid = (int)$o['order_id'];
			$date = substr((string)$o['date_added'], 0, 10);
			$time = substr((string)$o['date_added'], 11, 8);
			$buyer = trim($o['firstname'] . ' ' . $o['lastname']);
			if ($buyer === '') {
				$buyer = 'Покупатель ' . $oid;
			}
			$address = trim(implode(', ', array_filter([$o['payment_country'], $o['payment_zone'], $o['payment_city'], $o['payment_address_1']])));

			$xml .= "\t<Документ>\n";
			$xml .= "\t\t<Ид>{$oid}</Ид>\n";
			$xml .= "\t\t<Номер>{$oid}</Номер>\n";
			$xml .= "\t\t<Дата>{$date}</Дата>\n";
			$xml .= "\t\t<Время>{$time}</Время>\n";
			$xml .= "\t\t<ХозОперация>Заказ товара</ХозОперация>\n";
			$xml .= "\t\t<Роль>Продавец</Роль>\n";
			$xml .= "\t\t<Валюта>" . $esc($o['currency_code']) . "</Валюта>\n";
			$xml .= "\t\t<Курс>" . rtrim(rtrim((string)$o['currency_value'], '0'), '.') . "</Курс>\n";
			$xml .= "\t\t<Сумма>" . $money($o['total']) . "</Сумма>\n";

			$xml .= "\t\t<Контрагенты>\n\t\t\t<Контрагент>\n";
			$xml .= "\t\t\t\t<Ид>{$oid}</Ид>\n";
			$xml .= "\t\t\t\t<Наименование>" . $esc($buyer) . "</Наименование>\n";
			$xml .= "\t\t\t\t<ПолноеНаименование>" . $esc($buyer) . "</ПолноеНаименование>\n";
			if ((string)$o['lastname'] !== '') {
				$xml .= "\t\t\t\t<Фамилия>" . $esc($o['lastname']) . "</Фамилия>\n";
			}
			if ((string)$o['firstname'] !== '') {
				$xml .= "\t\t\t\t<Имя>" . $esc($o['firstname']) . "</Имя>\n";
			}
			$xml .= "\t\t\t\t<Роль>Покупатель</Роль>\n";
			$xml .= "\t\t\t\t<Контакты>\n";
			if ((string)$o['email'] !== '') {
				$xml .= "\t\t\t\t\t<Контакт><Тип>Почта</Тип><Значение>" . $esc($o['email']) . "</Значение></Контакт>\n";
			}
			if ((string)$o['telephone'] !== '') {
				$xml .= "\t\t\t\t\t<Контакт><Тип>Телефон</Тип><Значение>" . $esc($o['telephone']) . "</Значение></Контакт>\n";
			}
			$xml .= "\t\t\t\t</Контакты>\n";
			if ($address !== '') {
				$xml .= "\t\t\t\t<АдресРегистрации><Представление>" . $esc($address) . "</Представление></АдресРегистрации>\n";
			}
			$xml .= "\t\t\t</Контрагент>\n\t\t</Контрагенты>\n";

			$xml .= "\t\t<Товары>\n";
			foreach ($products[$oid] ?? [] as $p) {
				$pid = (int)$p['product_id'];
				$id1c = (string)$pid;
				foreach ($options[(int)$p['order_product_id']] ?? [] as $opt) {
					if (isset($variantGuid[$pid . '-' . (int)$opt['product_option_value_id']])) {
						$id1c = $variantGuid[$pid . '-' . (int)$opt['product_option_value_id']];
						break;
					}
				}
				if ($id1c === (string)$pid && isset($productGuid[$pid])) {
					$id1c = $productGuid[$pid];
				}
				$xml .= "\t\t\t<Товар>\n";
				$xml .= "\t\t\t\t<Ид>" . $esc($id1c) . "</Ид>\n";
				if ((string)$p['model'] !== '') {
					$xml .= "\t\t\t\t<Артикул>" . $esc($p['model']) . "</Артикул>\n";
				}
				$xml .= "\t\t\t\t<Наименование>" . $esc($p['name']) . "</Наименование>\n";
				$xml .= "\t\t\t\t<БазоваяЕдиница Код=\"796\" НаименованиеПолное=\"Штука\" МеждународноеСокращение=\"PCE\">шт</БазоваяЕдиница>\n";
				$xml .= "\t\t\t\t<ЦенаЗаЕдиницу>" . $money($p['price']) . "</ЦенаЗаЕдиницу>\n";
				$xml .= "\t\t\t\t<Количество>" . (int)$p['quantity'] . "</Количество>\n";
				$xml .= "\t\t\t\t<Сумма>" . $money((float)$p['price'] * (int)$p['quantity']) . "</Сумма>\n";
				if (!empty($options[(int)$p['order_product_id']])) {
					$xml .= "\t\t\t\t<ХарактеристикиТовара>\n";
					foreach ($options[(int)$p['order_product_id']] as $opt) {
						$xml .= "\t\t\t\t\t<ХарактеристикаТовара><Наименование>" . $esc($opt['name']) . "</Наименование><Значение>" . $esc($opt['value']) . "</Значение></ХарактеристикаТовара>\n";
					}
					$xml .= "\t\t\t\t</ХарактеристикиТовара>\n";
				}
				$xml .= "\t\t\t</Товар>\n";
			}
			$xml .= "\t\t</Товары>\n";

			$xml .= "\t\t<ЗначенияРеквизитов>\n";
			$xml .= "\t\t\t<ЗначениеРеквизита><Наименование>Номер заказа на сайте</Наименование><Значение>{$oid}</Значение></ЗначениеРеквизита>\n";
			$xml .= "\t\t\t<ЗначениеРеквизита><Наименование>Дата заказа на сайте</Наименование><Значение>" . $esc($o['date_added']) . "</Значение></ЗначениеРеквизита>\n";
			if ((string)$o['telephone'] !== '') {
				$xml .= "\t\t\t<ЗначениеРеквизита><Наименование>Ф_ТелефонПартнера</Наименование><Значение>" . $esc($o['telephone']) . "</Значение></ЗначениеРеквизита>\n";
			}
			if ($address !== '') {
				$xml .= "\t\t\t<ЗначениеРеквизита><Наименование>Адрес доставки</Наименование><Значение>" . $esc($address) . "</Значение></ЗначениеРеквизита>\n";
				$xml .= "\t\t\t<ЗначениеРеквизита><Наименование>Грузополучатель</Наименование><Значение>" . $esc($buyer) . "</Значение></ЗначениеРеквизита>\n";
			}
			$ship = $method($o['shipping_method']);
			if ($ship !== '') {
				$xml .= "\t\t\t<ЗначениеРеквизита><Наименование>Способ доставки</Наименование><Значение>" . $esc($ship) . "</Значение></ЗначениеРеквизита>\n";
			}
			$pay = $method($o['payment_method']);
			if ($pay !== '') {
				$xml .= "\t\t\t<ЗначениеРеквизита><Наименование>Способ оплаты</Наименование><Значение>" . $esc($pay) . "</Значение></ЗначениеРеквизита>\n";
			}
			if (trim((string)$o['comment']) !== '') {
				$xml .= "\t\t\t<ЗначениеРеквизита><Наименование>Комментарий</Наименование><Значение>" . $esc(trim((string)$o['comment'])) . "</Значение></ЗначениеРеквизита>\n";
			}
			$xml .= "\t\t</ЗначенияРеквизитов>\n";

			$xml .= "\t</Документ>\n";
		}

		$xml .= '</КоммерческаяИнформация>';

		$log->write('query — exported ' . count($orders) . ' orders (read-only)');
		$this->response->setOutput($xml);
	}

	private function modeFile(string $filename, \Opencart\System\Library\Log $log): void {
		if ($filename === '') {
			$this->response->setOutput("failure\nНе указано имя файла");
			return;
		}

		$dir = DIR_STORAGE . 'manline1c/';

		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		// 1C sends chunks; append so multi-part files reassemble correctly.
		$body = file_get_contents('php://input');
		file_put_contents($dir . $filename, $body, FILE_APPEND);

		$log->write('file saved: ' . $filename . ' (+' . strlen((string)$body) . ' bytes)');
		$this->response->setOutput('success');
	}

	/**
	 * Human-readable auth verdict for the log — lets you tell the real 1C
	 * (correct credentials) apart from random bots hitting the URL.
	 */
	private function authNote(): string {
		[$user, $pass] = $this->basicAuth();

		if ($user === '') {
			return 'no credentials';
		}

		if ($user === $this->setting('module_manline_exchange1c_username') && $pass === $this->setting('module_manline_exchange1c_password')) {
			return 'credentials OK — this is the real 1C';
		}

		return 'WRONG credentials (user=' . $user . ')';
	}

	private function authed(): bool {
		[$user, $pass] = $this->basicAuth();

		if ($user !== '' && $user === $this->setting('module_manline_exchange1c_username') && $pass === $this->setting('module_manline_exchange1c_password')) {
			return true;
		}

		$cookie = (string)($this->request->cookie[self::COOKIE] ?? ($_COOKIE[self::COOKIE] ?? ''));

		return $cookie !== '' && hash_equals($this->token(), $cookie);
	}

	/**
	 * Stateless session token derived from the credentials — no session store
	 * needed (catalog runs with session_autostart=false).
	 */
	private function token(): string {
		return substr(hash('sha256', $this->setting('module_manline_exchange1c_username') . ':' . $this->setting('module_manline_exchange1c_password') . ':manline1c'), 0, 32);
	}

	/**
	 * @return array{0:string,1:string} [user, pass]
	 */
	private function basicAuth(): array {
		$user = (string)($this->request->server['PHP_AUTH_USER'] ?? '');
		$pass = (string)($this->request->server['PHP_AUTH_PW'] ?? '');

		if ($user === '') {
			$header = (string)($this->request->server['HTTP_AUTHORIZATION'] ?? $this->request->server['REDIRECT_HTTP_AUTHORIZATION'] ?? '');

			if (stripos($header, 'Basic ') === 0) {
				$decoded = base64_decode(substr($header, 6));
				if ($decoded !== false && str_contains($decoded, ':')) {
					[$user, $pass] = explode(':', $decoded, 2);
				}
			}
		}

		return [$user, $pass];
	}

	private function setting(string $key): string {
		$query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `key` = '" . $this->db->escape($key) . "' LIMIT 1");

		return $query->num_rows ? (string)$query->row['value'] : '';
	}
}
