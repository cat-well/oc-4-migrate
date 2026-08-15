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
	// Catalog import (offers → prices/stock)
	private const WH_MAIN    = '31e9d33b-0701-11eb-917b-2c4d545a248c'; // «Основной склад» — только его остаток
	private const PT_BASE    = '67f65848-1cf1-11eb-97b9-2c4d545a24e4'; // «Интернет соглашения» → базовая цена
	private const PT_SPECIAL = 'cfd5bb8a-1af7-11ec-bf74-bc5ff47122f4'; // «Акция (для сайта)» → спец-цена
	private const ORDER_EXPORT_LIMIT = 0; // 0 = all matching orders

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
				$this->clearStorage(); // fresh session — старые файлы не дописываем
				$this->modeInit();
				break;

			case 'sale/init':
				$this->modeInit();
				break;

			case 'catalog/file':
			case 'sale/file':
				$this->modeFile($filename, $log);
				break;

			case 'catalog/import':
				$this->modeCatalogImport($filename, $log);
				break;

			case 'sale/import':
				$log->write('import received (' . $filename . ') — not processed (sale skeleton)');
				$this->response->setOutput('success');
				break;

			case 'sale/query':
				$this->modeQuery($log);
				break;

			case 'sale/success':
				$this->modeSaleSuccess($log);
				break;

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

		// Nova Poshta delivery details (city, warehouse address + its NP Ref = 1C's IDОтделения).
		$np = [];
		foreach ($this->db->query("SELECT `order_id`, `delivery_type`, `city`, `address`, `address_ref`, `ttn_number`, `ttn_ref` FROM `" . DB_PREFIX . "order_novaposhta` WHERE `order_id` IN (" . $in . ")")->rows as $r) {
			$np[(int)$r['order_id']] = $r;
		}
		// 1C matches the delivery method by an exact string; map our codes to what ОбменССайтом expects.
		$shipName = static function (string $type): string {
			switch ($type) {
				case 'courier': return 'Курьером Новой Почты на адрес';
				case 'branch':
				case 'locker':
				default:       return 'Доставка в отделение Новой Почты';
			}
		};

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
				$xml .= "\t\t\t\t\t<Контакт><Тип>ТелефонРабочий</Тип><Значение>" . $esc($o['telephone']) . "</Значение></Контакт>\n";
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
			// Delivery — the names ОбменССайтом reads: "Способ доставки" (exact match), "Адрес доставки", "address_ref" (NP warehouse Ref = IDОтделения).
			if (isset($np[$oid])) {
				$n = $np[$oid];
				$xml .= "\t\t\t<ЗначениеРеквизита><Наименование>Способ доставки</Наименование><Значение>" . $esc($shipName((string)$n['delivery_type'])) . "</Значение></ЗначениеРеквизита>\n";
				$deliveryAddress = trim($n['city'] . ', ' . $n['address'], ', ');
				if ($deliveryAddress !== '') {
					$xml .= "\t\t\t<ЗначениеРеквизита><Наименование>Адрес доставки</Наименование><Значение>" . $esc($deliveryAddress) . "</Значение></ЗначениеРеквизита>\n";
				}
				if ((string)$n['address_ref'] !== '') {
					$xml .= "\t\t\t<ЗначениеРеквизита><Наименование>address_ref</Наименование><Значение>" . $esc($n['address_ref']) . "</Значение></ЗначениеРеквизита>\n";
				}
				// Nova Poshta waybill (TTN) — 1C stores it in ф_НоваяПочтаТТН (needs the small 1C-side addition to ОбновитьКСпоНП).
				if ((string)$n['ttn_number'] !== '') {
					$xml .= "\t\t\t<ЗначениеРеквизита><Наименование>ТТН</Наименование><Значение>" . $esc($n['ttn_number']) . "</Значение></ЗначениеРеквизита>\n";
				}
				if ((string)$n['ttn_ref'] !== '') {
					$xml .= "\t\t\t<ЗначениеРеквизита><Наименование>RefTTN</Наименование><Значение>" . $esc($n['ttn_ref']) . "</Значение></ЗначениеРеквизита>\n";
				}
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

		// Mark handed-over orders "Передан в 1С" (16) right here, before responding:
		// 1C may query several times in one session, so returning an order twice would
		// duplicate it. Flipping the status now makes any repeat query return nothing.
		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_history` (`order_id`, `order_status_id`, `notify`, `comment`, `date_added`) SELECT `order_id`, 16, 0, 'Передан в 1С', NOW() FROM `" . DB_PREFIX . "order` WHERE `order_id` IN (" . $in . ")");
		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET `order_status_id` = 16, `date_modified` = NOW() WHERE `order_id` IN (" . $in . ")");

		$log->write('query — exported ' . count($orders) . ' orders, marked Передан в 1С (16)');
		$this->response->setOutput($xml);
	}

	private function modeSaleSuccess(\Opencart\System\Library\Log $log): void {
		// Orders are already marked at query time; just acknowledge.
		$log->write('success — acknowledged');
		$this->response->setOutput('success');
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

	/** Wipe last session's catalog files so a fresh upload isn't appended to stale data. */
	private function clearStorage(): void {
		$dir = DIR_STORAGE . 'manline1c/';
		if (is_dir($dir)) {
			foreach (glob($dir . '*.xml') as $f) {
				@unlink($f);
			}
		}
	}

	/** Catalog import: offers*.xml carry prices+stock and we apply them; import*.xml (full catalog) is not enabled yet. */
	private function modeCatalogImport(string $filename, \Opencart\System\Library\Log $log): void {
		if (stripos($filename, 'offers') === 0) {
			$this->importOffers($filename, $log);
		} else {
			$log->write('import ' . $filename . ' — acknowledged (catalog sync not enabled)');
			$this->response->setOutput('success');
		}
	}

	/**
	 * Parse one offers file and update OC4 prices + stock. String+regex so it
	 * tolerates concatenated docs; last value per offer Ид wins (most recent).
	 */
	private function importOffers(string $filename, \Opencart\System\Library\Log $log): void {
		@set_time_limit(0);

		$path = DIR_STORAGE . 'manline1c/' . basename($filename);
		if (!is_file($path)) {
			$log->write('offers file missing: ' . $filename);
			$this->response->setOutput('success');
			return;
		}
		$s = (string)file_get_contents($path);

		$variant = [];
		foreach ($this->db->query("SELECT `product_id`, `product_option_value_id`, `1c_id` FROM `" . DB_PREFIX . "product_option_to_1c`")->rows as $x) {
			$variant[$x['1c_id']] = [(int)$x['product_id'], (int)$x['product_option_value_id']];
		}
		$product = [];
		foreach ($this->db->query("SELECT `product_id`, `1c_id` FROM `" . DB_PREFIX . "product_to_1c`")->rows as $x) {
			$product[$x['1c_id']] = (int)$x['product_id'];
		}

		$povQty = []; $povProd = []; $simpleQty = []; $base = []; $special = [];
		$offers = 0; $mapped = 0; $unmapped = 0;

		if (preg_match_all('~<Предложение>(.*?)</Предложение>~us', $s, $blocks)) {
			foreach ($blocks[1] as $node) {
				$offers++;
				if (!preg_match('~<Ид>([^<]+)</Ид>~u', $node, $mm)) {
					continue;
				}
				$id = $mm[1];

				$qty = 0;
				if (preg_match('~<Склад\s+ИдСклада="' . preg_quote(self::WH_MAIN, '~') . '"\s+КоличествоНаСкладе="([^"]*)"~u', $node, $q)) {
					$qty = (int)$q[1];
				}

				$pb = null; $ps = null;
				if (preg_match_all('~<ИдТипаЦены>([^<]+)</ИдТипаЦены>\s*<ЦенаЗаЕдиницу>([^<]+)</ЦенаЗаЕдиницу>~u', $node, $pm, PREG_SET_ORDER)) {
					foreach ($pm as $p) {
						if ($p[1] === self::PT_BASE)    { $pb = (float)$p[2]; }
						if ($p[1] === self::PT_SPECIAL) { $ps = (float)$p[2]; }
					}
				}

				if (isset($variant[$id])) {
					[$pid, $pov] = $variant[$id];
					$povQty[$pov] = $qty;
					$povProd[$pov] = $pid;
					$mapped++;
				} elseif (isset($product[$id])) {
					$pid = $product[$id];
					$simpleQty[$pid] = $qty;
					$mapped++;
				} else {
					$unmapped++;
					continue;
				}
				if ($pb !== null) { $base[$pid] = $pb; }
				if ($ps !== null) { $special[$pid] = $ps; }
			}
		}

		$this->applyQtyCase(DB_PREFIX . 'product_option_value', 'product_option_value_id', $povQty);
		$this->applyQtyCase(DB_PREFIX . 'product', 'product_id', $simpleQty, true);
		$this->applyPriceCase($base);
		$this->applySpecial($special);
		$this->recomputeProductQty(array_values(array_unique($povProd)));

		$log->write('import ' . $filename . ' — offers=' . $offers . ' mapped=' . $mapped . ' unmapped=' . $unmapped . ' (variants=' . count($povQty) . ', prices=' . count($base) . ')');
		$this->response->setOutput('success');
	}

	/** Batched CASE update of an int quantity column keyed by an id column. */
	private function applyQtyCase(string $table, string $idCol, array $map, bool $touch = false): void {
		foreach (array_chunk($map, 500, true) as $chunk) {
			$ids = array_map('intval', array_keys($chunk));
			$case = '';
			foreach ($chunk as $id => $q) {
				$case .= ' WHEN ' . (int)$id . ' THEN ' . (int)$q;
			}
			$set = '`quantity` = CASE `' . $idCol . '`' . $case . ' END' . ($touch ? ', `date_modified` = NOW()' : '');
			$this->db->query("UPDATE `" . $table . "` SET " . $set . " WHERE `" . $idCol . "` IN (" . implode(',', $ids) . ")");
		}
	}

	/** Batched CASE update of product base price. */
	private function applyPriceCase(array $base): void {
		foreach (array_chunk($base, 500, true) as $chunk) {
			$ids = array_map('intval', array_keys($chunk));
			$case = '';
			foreach ($chunk as $pid => $p) {
				$case .= ' WHEN ' . (int)$pid . ' THEN ' . (float)$p;
			}
			$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `price` = CASE `product_id`" . $case . " END, `date_modified` = NOW() WHERE `product_id` IN (" . implode(',', $ids) . ")");
		}
	}

	/** Update existing special prices (customer_group 1) and insert missing ones. */
	private function applySpecial(array $special): void {
		if (!$special) {
			return;
		}
		$ids = array_map('intval', array_keys($special));

		$existing = [];
		foreach (array_chunk($ids, 1000) as $chunk) {
			foreach ($this->db->query("SELECT `product_id` FROM `" . DB_PREFIX . "product_special` WHERE `customer_group_id` = 1 AND `product_id` IN (" . implode(',', $chunk) . ")")->rows as $r) {
				$existing[(int)$r['product_id']] = true;
			}
		}

		$update = array_intersect_key($special, $existing);
		foreach (array_chunk($update, 500, true) as $chunk) {
			$cids = array_map('intval', array_keys($chunk));
			$case = '';
			foreach ($chunk as $pid => $p) {
				$case .= ' WHEN ' . (int)$pid . ' THEN ' . (float)$p;
			}
			$this->db->query("UPDATE `" . DB_PREFIX . "product_special` SET `price` = CASE `product_id`" . $case . " END WHERE `customer_group_id` = 1 AND `product_id` IN (" . implode(',', $cids) . ")");
		}

		$insert = array_diff_key($special, $existing);
		$values = [];
		foreach ($insert as $pid => $p) {
			$values[] = '(' . (int)$pid . ", 1, 0, " . (float)$p . ", '0000-00-00', '0000-00-00')";
		}
		foreach (array_chunk($values, 500) as $chunk) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "product_special` (`product_id`, `customer_group_id`, `priority`, `price`, `date_start`, `date_end`) VALUES " . implode(',', $chunk));
		}
	}

	/** Product-level quantity for variant products = sum of their option quantities. */
	private function recomputeProductQty(array $pids): void {
		$pids = array_values(array_filter(array_map('intval', $pids)));
		foreach (array_chunk($pids, 500) as $chunk) {
			$in = implode(',', $chunk);
			$this->db->query("UPDATE `" . DB_PREFIX . "product` p SET p.`quantity` = (SELECT COALESCE(SUM(pov.`quantity`), 0) FROM `" . DB_PREFIX . "product_option_value` pov WHERE pov.`product_id` = p.`product_id`), p.`date_modified` = NOW() WHERE p.`product_id` IN (" . $in . ")");
		}
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
