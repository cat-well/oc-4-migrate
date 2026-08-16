<?php
namespace Opencart\Admin\Controller\Extension\Manline\Module;

/**
 * Rozetka orders (Manline) — READ-ONLY viewer (Phase 3a).
 *
 * Shows the marketplace's current orders inside the OC4 admin and reports how
 * each line item matches an OC4 product. Makes only GET calls to Rozetka and
 * writes nothing to the OC4 order tables.
 */
class RozetkaOrders extends \Opencart\System\Engine\Controller {
	private const ROUTE = 'extension/manline/module/rozetka_orders';

	public function install(): void {
		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', self::ROUTE);
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', self::ROUTE);
	}

	public function uninstall(): void {
		// no-op
	}

	public function index(): void {
		$this->load->language(self::ROUTE);

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/manline/module/rozetka_orders');
		$this->load->model('setting/setting');

		$setting = $this->model_setting_setting->getSetting('module_manline_rozetka');

		$data['token_set'] = trim((string)($setting['module_manline_rozetka_token'] ?? '')) !== '';
		$data['token_hint'] = $data['token_set'] ? $this->mask((string)$setting['module_manline_rozetka_token']) : '';

		$data['breadcrumbs'] = [
			['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])],
			['text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')],
			['text' => $this->language->get('heading_title'), 'href' => $this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token'])],
		];

		$data['save'] = $this->url->link(self::ROUTE . '.save', 'user_token=' . $this->session->data['user_token']);
		$data['import'] = $this->url->link(self::ROUTE . '.import', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

		$data['orders'] = [];
		$data['summary'] = ['orders' => 0, 'items' => 0, 'matched' => 0, 'exact' => 0, 'ambiguous' => 0, 'unmatched' => 0];
		$data['api_error'] = '';
		$data['meta'] = [];

		$data['imported'] = [];
		foreach ($this->model_extension_manline_module_rozetka_orders->getImportedOrders() as $row) {
			$data['imported'][] = [
				'rozetka_order_id' => (int)$row['rozetka_order_id'],
				'order_id'         => (int)$row['order_id'],
				'customer'         => trim((string)$row['firstname'] . ' ' . (string)$row['lastname']),
				'total'            => (string)$row['total'] . ' ' . (string)$row['currency_code'],
				'status'           => (string)($row['status_name'] ?? ''),
				'imported_at'      => (string)$row['imported_at'],
				'order_url'        => $this->url->link('sale/order.info', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . (int)$row['order_id']),
			];
		}

		if ($data['token_set']) {
			$result = $this->model_extension_manline_module_rozetka_orders->getOrders();

			if (!$result['ok']) {
				$data['api_error'] = $result['error'];
			} else {
				$data['meta'] = $result['meta'];

				foreach ($result['orders'] as $order) {
					$detail = $this->model_extension_manline_module_rozetka_orders->getOrderDetail((int)$order['id']);
					$purchases = $detail['ok'] ? ($detail['order']['purchases'] ?? []) : [];

					$items = [];
					foreach ($purchases as $purchase) {
						$item = $purchase['item'] ?? [];
						$offer = (string)($item['uploader_offer_id'] ?? $item['price_offer_id'] ?? '');
						$article = (string)($item['article'] ?? '');
						$name = (string)($item['name'] ?? $purchase['item_name'] ?? '');

						$match = $this->model_extension_manline_module_rozetka_orders->matchProduct($offer, $name, $article);

						$data['summary']['items']++;
						if ($match['confidence'] === 'exact' || $match['confidence'] === 'article') { $data['summary']['exact']++; $data['summary']['matched']++; }
						elseif ($match['confidence'] === 'name') { $data['summary']['matched']++; }
						elseif ($match['confidence'] === 'ambiguous') { $data['summary']['ambiguous']++; }
						else { $data['summary']['unmatched']++; }

						$items[] = [
							'name'       => $name,
							'offer'      => $offer,
							'quantity'   => (int)($purchase['quantity'] ?? 0),
							'price'      => (string)($purchase['price'] ?? ''),
							'match'      => $match,
							'edit_url'   => $match['product_id'] ? $this->url->link('catalog/product.form', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $match['product_id']) : '',
						];
					}

					$recipient = $order['recipient_title'] ?? $order['user_title'] ?? [];

					$data['orders'][] = [
						'id'         => (int)$order['id'],
						'created'    => (string)($order['created'] ?? ''),
						'amount'     => (string)($order['amount_with_discount'] ?? $order['amount'] ?? ''),
						'status'     => (int)($order['status'] ?? 0),
						'ttn'        => (string)($order['ttn'] ?? ''),
						'customer'   => trim((string)($recipient['full_name'] ?? '')),
						'phone'      => (string)($order['recipient_phone'] ?? $order['user_phone'] ?? ''),
						'quantity'   => (int)($order['total_quantity'] ?? 0),
						'detail_ok'  => $detail['ok'],
						'items'      => $items,
					];

					$data['summary']['orders']++;
				}
			}
		}

		$data['success'] = $this->flash('success');
		$data['error_warning'] = $this->flash('error_warning');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/manline/module/rozetka_orders', $data));
	}

	public function save(): void {
		$this->load->language(self::ROUTE);

		if (!$this->user->hasPermission('modify', self::ROUTE)) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token']));
		}

		$token = trim((string)($this->request->post['token'] ?? ''));

		$this->load->model('setting/setting');

		// Keep the stored token if the field is left blank (so it isn't wiped on a re-save).
		if ($token === '') {
			$existing = $this->model_setting_setting->getSetting('module_manline_rozetka');
			$token = (string)($existing['module_manline_rozetka_token'] ?? '');
		}

		$this->model_setting_setting->editSetting('module_manline_rozetka', [
			'module_manline_rozetka_token' => $token,
		]);

		$this->session->data['success'] = $this->language->get('text_success');

		$this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token']));
	}

	public function import(): void {
		$this->load->language(self::ROUTE);

		if (!$this->user->hasPermission('modify', self::ROUTE)) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token']));
		}

		$this->load->model('extension/manline/module/rozetka_orders');
		$stats = $this->model_extension_manline_module_rozetka_orders->importNewOrders();

		if (!$stats['ok']) {
			$this->session->data['error_warning'] = 'Rozetka API: ' . $stats['error'];
		} else {
			$msg = 'Імпорт Rozetka: створено ' . $stats['imported'] . ', пропущено (вже імпортовані) ' . $stats['skipped'] . ', помилок ' . $stats['errors'];
			if ($stats['unmatched'] > 0) {
				$msg .= ', позицій без прив\'язки ' . $stats['unmatched'] . ' (потребують ручної прив\'язки)';
			}
			$this->session->data['success'] = $msg;
		}

		$this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token']));
	}

	private function mask(string $token): string {
		$len = strlen($token);

		if ($len <= 8) return str_repeat('•', $len);

		return substr($token, 0, 5) . str_repeat('•', max(4, $len - 8)) . substr($token, -3);
	}

	private function flash(string $key): string {
		$value = (string)($this->session->data[$key] ?? '');

		unset($this->session->data[$key]);

		return $value;
	}
}
