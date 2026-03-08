<?php
namespace Opencart\Catalog\Controller\Checkout;

class Simplecheckout extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->loadSimpleLanguages();
		unset($this->session->data['simplecheckout_show_errors']);
		$this->applyPostedState($this->request->post);

		$state = $this->buildState(false);
		$this->loadSimpleLanguages();

		$this->document->setTitle($this->language->get('heading_title'));

		$data = $this->getViewData($state);

		$data['header'] = $this->load->controller('common/header');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('checkout/simplecheckout', $data));
	}

	public function reload(): void {
		$this->loadSimpleLanguages();

		$json = [];

		$this->applyPostedState($this->request->post);

		$create_order = !empty($this->request->post['create_order']);

		if ($create_order) {
			$this->session->data['simplecheckout_show_errors'] = 1;
		}

		$state = $this->buildState($this->shouldShowErrors());
		$this->loadSimpleLanguages();

		if ($create_order && !$state['errors']) {
			$payment_form = $this->load->controller('checkout/confirm.confirm');

			if ($payment_form) {
				$state['payment_form'] = $payment_form;
				$json['order_created'] = true;
				unset($this->session->data['simplecheckout_show_errors']);
			} else {
				$state['errors']['warning'] = $this->language->get('error_confirm');
				$json['order_created'] = false;
			}
		}

		$blocks = $this->renderBlocks($state);

		$json['blocks'] = $blocks;
		$json['errors'] = $state['errors'];
		$json['order_ready'] = empty($state['errors']) && !$state['cart_empty'] && !empty($state['payment_code']);

		$this->outputJson($json);
	}

	public function cart(): void {
		$this->outputSingleBlock('cart');
	}

	public function customer(): void {
		$this->outputSingleBlock('customer');
	}

	public function shipping_address(): void {
		$this->outputSingleBlock('shipping_address');
	}

	public function shipping(): void {
		$this->outputSingleBlock('shipping');
	}

	public function payment(): void {
		$this->outputSingleBlock('payment');
	}

	public function comment(): void {
		$this->outputSingleBlock('comment');
	}

	public function novaposhta(): void {
		$json = [];

		$action = trim((string)($this->request->post['action'] ?? ''));
		$search = trim((string)($this->request->post['search'] ?? ''));
		$method = trim((string)($this->request->post['shipping_method'] ?? ''));
		$zone_id = (int)($this->request->post['zone_id'] ?? 0);
		$city_ref = trim((string)($this->request->post['city_ref'] ?? ''));
		$city = trim((string)($this->request->post['city'] ?? ''));

		$is_np_enabled = (bool)$this->config->get('shipping_novaposhta_status');
		$api_key = trim((string)$this->config->get('shipping_novaposhta_api_key'));

		if (!$is_np_enabled || $api_key === '') {
			$this->outputJson($json);

			return;
		}

		if ($action === 'getCities') {
			$json = $this->getNovaPoshtaCities($search, $zone_id);
		} elseif ($action === 'getWarehouses') {
			if ($city_ref === '' && $city !== '') {
				$city_ref = $this->resolveNovaPoshtaCityRef($city, $zone_id);
			}

			$json = $this->getNovaPoshtaWarehouses($city_ref, $search, $method);
		}

		$this->outputJson($json);
	}

	private function outputSingleBlock(string $block): void {
		$this->loadSimpleLanguages();

		$json = [];

		$this->applyPostedState($this->request->post);

		$state = $this->buildState($this->shouldShowErrors());
		$this->loadSimpleLanguages();
		$blocks = $this->renderBlocks($state);

		$json['block'] = $blocks[$block] ?? '';
		$json['errors'] = $state['errors'];

		$this->outputJson($json);
	}

	private function loadSimpleLanguages(): void {
		$this->load->language('checkout/checkout');
		$this->load->language('checkout/cart');
		$this->load->language('checkout/simplecheckout');
	}

	private function outputJson(array $json): void {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function getViewData(array $state): array {
		$data = $this->getBlockData($state);

		$data['breadcrumbs'] = [
			[
				'text' => $this->config->get('config_name'),
				'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
			],
			[
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('checkout/simplecheckout', 'language=' . $this->config->get('config_language'))
			]
		];

		$data['lang'] = $this->config->get('config_language');
		$data['heading_title'] = $this->language->get('heading_title');
		$data['customer_logged'] = $this->customer->isLogged();
		$data['action'] = $this->url->link('checkout/simplecheckout', 'language=' . $this->config->get('config_language'));
		$data['reload_action'] = $this->url->link('checkout/simplecheckout.reload', 'language=' . $this->config->get('config_language'));
		$data['simple_blocks'] = $this->renderBlocks($state);

		return $data;
	}

	private function buildState(bool $validate = false): array {
		$this->ensureSimpleSession();
		$this->syncCoreSessionFromSimple();

		$simple = $this->session->data['simplecheckout'];

		$this->load->model('checkout/cart');
		$this->load->model('tool/image');
		$this->load->model('localisation/country');
		$this->load->model('localisation/zone');

		$products = [];

		foreach ($this->model_checkout_cart->getProducts() as $product) {
			if ($product['option']) {
				foreach ($product['option'] as $key => $option) {
					$value = $option['value'] ?? '';
					$product['option'][$key]['value'] = oc_strlen($value) > 40 ? oc_substr($value, 0, 40) . '..' : $value;
				}
			}

			$image = $product['image'] ?: 'placeholder.png';

			$products[] = [
				'key'        => (int)($product['cart_id'] ?? 0),
				'product_id' => $product['product_id'],
				'thumb'      => $this->model_tool_image->resize($image, 80, 80),
				'name'       => $product['name'],
				'model'      => $product['model'],
				'option'     => $product['option'],
				'quantity'   => (int)$product['quantity'],
				'stock'      => $product['stock'],
				'price'      => $product['price_text'] ?? '',
				'total'      => $product['total_text'] ?? '',
				'href'       => $this->url->link(
					'product/product',
					'language=' . $this->config->get('config_language') . '&product_id=' . (int)$product['product_id']
				)
			];
		}

		$totals = [];
		$taxes = $this->cart->getTaxes();
		$total = 0;

		($this->model_checkout_cart->getTotals)($totals, $taxes, $total);

		$totals_data = [];
		$cart_total_text = '';

		foreach ($totals as $item) {
			$text = $this->currency->format($item['value'], $this->session->data['currency']);

			$totals_data[] = [
				'code'  => $item['code'],
				'title' => $item['title'],
				'text'  => $text
			];

			if ($item['code'] === 'total') {
				$cart_total_text = $text;
			}
		}

		if ($cart_total_text === '' && $totals_data) {
			$last_total = end($totals_data);
			$cart_total_text = $last_total['text'] ?? '';
		}

		$countries = $this->getCountriesWithFallback();
		$shipping_country_id = (int)$simple['shipping_address']['country_id'];
		$zones = $this->getZonesByCountryIdWithFallback($shipping_country_id);

		$shipping_methods = [];
		$shipping_code = '';

		if ($this->cart->hasShipping()) {
			$this->load->model('checkout/shipping_method');

			$shipping_methods = $this->model_checkout_shipping_method->getMethods($this->session->data['shipping_address']);
			$shipping_methods = $this->normalizeShippingMethods($shipping_methods);
			$this->session->data['shipping_methods'] = $shipping_methods;

			$preferred_shipping_code = $simple['shipping_method'];
			$selected_shipping = $this->findShippingByCode($shipping_methods, $preferred_shipping_code);

			if (!$selected_shipping && !empty($this->session->data['shipping_method']['code'])) {
				$selected_shipping = $this->findShippingByCode($shipping_methods, $this->session->data['shipping_method']['code']);
			}

			if (!$selected_shipping) {
				$selected_shipping = $this->findFirstShipping($shipping_methods);
			}

			if ($selected_shipping) {
				$shipping_code = $selected_shipping['code'];
				$this->session->data['shipping_method'] = $selected_shipping;
				$this->session->data['simplecheckout']['shipping_method'] = $shipping_code;
			} else {
				unset($this->session->data['shipping_method']);
				$this->session->data['simplecheckout']['shipping_method'] = '';
			}
		} else {
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			$this->session->data['simplecheckout']['shipping_method'] = '';
		}

		$this->load->model('checkout/payment_method');

		$payment_address = $this->config->get('config_checkout_payment_address')
			? $this->session->data['payment_address']
			: ($this->session->data['shipping_address'] ?? []);

		$payment_methods = $this->model_checkout_payment_method->getMethods($payment_address);
		$this->session->data['payment_methods'] = $payment_methods;

		$preferred_payment_code = $simple['payment_method'];
		$selected_payment = $this->findPaymentByCode($payment_methods, $preferred_payment_code);

		if (!$selected_payment && !empty($this->session->data['payment_method']['code'])) {
			$selected_payment = $this->findPaymentByCode($payment_methods, $this->session->data['payment_method']['code']);
		}

		if (!$selected_payment) {
			$selected_payment = $this->findFirstPayment($payment_methods);
		}

		$payment_code = '';

		if ($selected_payment) {
			$payment_code = $selected_payment['code'];
			$this->session->data['payment_method'] = $selected_payment;
			$this->session->data['simplecheckout']['payment_method'] = $payment_code;
		} else {
			unset($this->session->data['payment_method']);
			$this->session->data['simplecheckout']['payment_method'] = '';
		}

		$state = [
			'customer'          => $this->session->data['simplecheckout']['customer'],
			'shipping_address'  => $this->session->data['simplecheckout']['shipping_address'],
			'payment_address'   => $this->session->data['simplecheckout']['payment_address'],
			'comment'           => $this->session->data['simplecheckout']['comment'],
			'coupon'            => (string)($this->session->data['coupon'] ?? ''),
			'coupon_enabled'    => true,
			'agree'             => !empty($this->session->data['agree']),
			'countries'         => $countries,
			'zones'             => $zones,
			'shipping_methods'  => $shipping_methods,
			'payment_methods'   => $payment_methods,
			'shipping_code'     => $shipping_code,
			'payment_code'      => $payment_code,
			'products'          => $products,
			'totals'            => $totals_data,
			'cart_total'        => $cart_total_text,
			'cart_has_shipping' => $this->cart->hasShipping(),
			'cart_empty'        => empty($products),
			'payment_form'      => ''
		];

		$state['errors'] = $validate ? $this->validateState($state) : [];
		$this->applyCartWarnings($state);

		return $state;
	}

	private function shouldShowErrors(): bool {
		return !empty($this->session->data['simplecheckout_show_errors']);
	}

	private function renderBlocks(array $state): array {
		$data = $this->getBlockData($state);

		return [
			'cart'             => $this->load->view('checkout/simplecheckout_cart', $data),
			'customer'         => $this->load->view('checkout/simplecheckout_customer', $data),
			'shipping_address' => $state['cart_has_shipping'] ? $this->load->view('checkout/simplecheckout_shipping_address', $data) : '',
			'shipping'         => $state['cart_has_shipping'] ? $this->load->view('checkout/simplecheckout_shipping', $data) : '',
			'comment'          => $this->load->view('checkout/simplecheckout_comment', $data),
			'payment'          => $this->load->view('checkout/simplecheckout_payment', $data),
			'payment_form'     => $state['payment_form']
		];
	}

	private function getBlockData(array $state): array {
		$data = $state;

		$data['column_image'] = $this->language->get('column_image');
		$data['column_name'] = $this->language->get('column_name');
		$column_model = $this->language->get('column_model');

		if ($column_model === 'column_model' || stripos($column_model, 'column_model') !== false) {
			$column_model = '';
		}

		$data['column_model'] = $column_model;
		$data['column_quantity'] = $this->language->get('column_quantity');
		$data['column_price'] = $this->language->get('column_price');
		$data['column_total'] = $this->language->get('column_total');
		$data['button_order'] = $this->language->get('button_order');
		$data['button_update'] = $this->language->get('button_update');
		$data['text_customer'] = $this->language->get('text_customer');
		$data['text_shipping_method'] = $this->language->get('text_shipping_method');
		$data['text_shipping_address'] = $this->language->get('text_shipping_address');
		$data['text_payment_method'] = $this->language->get('text_payment_method');
		$data['text_select'] = $this->language->get('text_select');
		$data['text_comment'] = $this->language->get('entry_comment');
		$data['text_free_delivery_prefix'] = $this->language->get('text_free_delivery_prefix');
		$data['text_free_delivery'] = $this->language->get('text_free_delivery');
		$data['text_dozak_word'] = $this->language->get('text_dozak_word');
		$data['text_dozak_after'] = $this->language->get('text_dozak_after');
		$data['text_dozak_and'] = $this->language->get('text_dozak_and');
		$data['text_dozak_free'] = $this->language->get('text_dozak_free');
		$data['free_delivery_total'] = $this->getFreeDeliveryThreshold();
		$data['novaposhta_endpoint'] = $this->url->link(
			'checkout/simplecheckout.novaposhta',
			'language=' . $this->config->get('config_language')
		);
		$data['entry_coupon'] = $this->language->get('entry_coupon');
		$data['entry_firstname'] = $this->language->get('entry_firstname');
		$data['entry_lastname'] = $this->language->get('entry_lastname');
		$data['entry_telephone'] = $this->language->get('entry_telephone');
		$data['entry_email'] = $this->language->get('entry_email');
		$data['placeholder_firstname'] = $this->language->get('placeholder_firstname');
		$data['placeholder_lastname'] = $this->language->get('placeholder_lastname');
		$data['placeholder_telephone'] = $this->language->get('placeholder_telephone');
		$data['placeholder_email'] = $this->language->get('placeholder_email');
		$data['placeholder_comment'] = $this->language->get('placeholder_comment');
		$data['entry_country'] = $this->language->get('entry_country');
		$data['entry_zone'] = $this->language->get('entry_zone');
		$data['entry_city'] = $this->language->get('entry_city');
		$data['entry_address_1'] = $this->language->get('entry_address_1');
		$data['entry_postcode'] = $this->language->get('entry_postcode');
		$data['shipping_city_ref'] = (string)($state['shipping_address']['city_ref'] ?? '');
		$data['shipping_address_ref'] = (string)($state['shipping_address']['address_ref'] ?? '');
		$data['display_model'] = false;

		$this->load->model('catalog/information');

		$information_id = (int)$this->config->get('config_checkout_id');
		$information_info = $this->model_catalog_information->getInformation($information_id);

		if ($information_info) {
			$data['text_agree'] = sprintf(
				$this->language->get('text_agree'),
				$this->url->link(
					'information/information.info',
					'language=' . $this->config->get('config_language') . '&information_id=' . $information_id
				),
				$information_info['title']
			);
		} else {
			$data['text_agree'] = '';
		}

		return $data;
	}

	private function applyPostedState(array $post): void {
		$this->ensureSimpleSession();

		if (!$post) {
			$this->syncCoreSessionFromSimple();

			return;
		}

		if (!empty($post['remove'])) {
			$this->cart->remove((int)$post['remove']);
		}

		if (!empty($post['quantity']) && is_array($post['quantity'])) {
			foreach ($post['quantity'] as $cart_id => $quantity) {
				$cart_id = (int)$cart_id;
				$quantity = (int)$quantity;

				if ($quantity > 0) {
					$this->cart->update($cart_id, $quantity);
				} else {
					$this->cart->remove($cart_id);
				}
			}
		}

		$simple = &$this->session->data['simplecheckout'];
		$previous_country_id = (int)($simple['shipping_address']['country_id'] ?? 0);
		$previous_zone_id = (int)($simple['shipping_address']['zone_id'] ?? 0);
		$is_create_order_request = !empty($post['create_order']);

		$customer_map = [
			'firstname' => 'firstname',
			'lastname'  => 'lastname',
			'telephone' => 'telephone',
			'email'     => 'email'
		];

		foreach ($customer_map as $post_key => $customer_key) {
			if (array_key_exists($post_key, $post)) {
				$simple['customer'][$customer_key] = trim((string)$post[$post_key]);
			}
		}

		$shipping_map = [
			'shipping_country_id' => 'country_id',
			'shipping_zone_id'    => 'zone_id',
			'shipping_city'       => 'city',
			'shipping_address_1'  => 'address_1',
			'shipping_postcode'   => 'postcode'
		];

		foreach ($shipping_map as $post_key => $address_key) {
			if (array_key_exists($post_key, $post)) {
				$value = is_numeric($post[$post_key]) ? (int)$post[$post_key] : trim((string)$post[$post_key]);
				$simple['shipping_address'][$address_key] = $value;
				$simple['payment_address'][$address_key] = $value;
			}
		}

		$country_changed = array_key_exists('shipping_country_id', $post) && (int)$simple['shipping_address']['country_id'] !== $previous_country_id;
		$zone_changed = array_key_exists('shipping_zone_id', $post) && (int)$simple['shipping_address']['zone_id'] !== $previous_zone_id;

		if ($country_changed || $zone_changed) {
			$simple['shipping_address']['city_ref'] = '';
			$simple['shipping_address']['address_ref'] = '';

			$simple['payment_address']['city_ref'] = '';
			$simple['payment_address']['address_ref'] = '';

			// On normal country/zone switch we reset dependent address fields.
			// On final submit (create_order) we keep posted user input to avoid destructive reset.
			if (!$is_create_order_request) {
				$simple['shipping_address']['city'] = '';
				$simple['shipping_address']['address_1'] = '';
				$simple['shipping_address']['postcode'] = '';

				$simple['payment_address']['city'] = '';
				$simple['payment_address']['address_1'] = '';
				$simple['payment_address']['postcode'] = '';
			}
		}

		$shipping_ref_map = [
			'shipping_city_ref' => 'city_ref',
			'shipping_address_ref' => 'address_ref'
		];

		foreach ($shipping_ref_map as $post_key => $address_key) {
			if (array_key_exists($post_key, $post)) {
				$simple['shipping_address'][$address_key] = trim((string)$post[$post_key]);
			}
		}

		if (array_key_exists('comment', $post)) {
			$simple['comment'] = trim((string)$post['comment']);
		}

		if (array_key_exists('coupon', $post)) {
			$coupon = trim((string)$post['coupon']);

			if ($coupon === '') {
				unset($this->session->data['coupon']);
			} else {
				$this->session->data['coupon'] = $coupon;
			}
		}

		if (array_key_exists('shipping_method', $post)) {
			$simple['shipping_method'] = trim((string)$post['shipping_method']);
		}

		if (array_key_exists('payment_method', $post)) {
			$simple['payment_method'] = trim((string)$post['payment_method']);
		}

		if (array_key_exists('agree', $post)) {
			if ((int)$post['agree']) {
				$this->session->data['agree'] = 1;
			} else {
				unset($this->session->data['agree']);
			}
		}

			$this->syncCoreSessionFromSimple();
		}

	private function ensureSimpleSession(): void {
		if (!isset($this->session->data['simplecheckout']) || !is_array($this->session->data['simplecheckout'])) {
			$this->session->data['simplecheckout'] = [];
		}

		$defaults = $this->getInitialSimpleData();
		$simple = &$this->session->data['simplecheckout'];

		foreach (['customer', 'payment_address', 'shipping_address'] as $key) {
			if (!isset($simple[$key]) || !is_array($simple[$key])) {
				$simple[$key] = $defaults[$key];
			} else {
				$simple[$key] = array_replace($defaults[$key], $simple[$key]);
			}
		}

		foreach (['comment', 'shipping_method', 'payment_method'] as $key) {
			if (!isset($simple[$key])) {
				$simple[$key] = $defaults[$key];
			}
		}
	}

	private function getInitialSimpleData(): array {
		$default_address = $this->getDefaultAddress();
		$customer_group_id = (int)$this->config->get('config_customer_group_id');

		$customer = [
			'customer_id'       => 0,
			'customer_group_id' => $customer_group_id,
			'firstname'         => '',
			'lastname'          => '',
			'email'             => '',
			'telephone'         => '',
			'custom_field'      => []
		];

		if ($this->customer->isLogged()) {
			$customer = [
				'customer_id'       => (int)$this->customer->getId(),
				'customer_group_id' => (int)$this->customer->getGroupId(),
				'firstname'         => $this->customer->getFirstName(),
				'lastname'          => $this->customer->getLastName(),
				'email'             => $this->customer->getEmail(),
				'telephone'         => $this->customer->getTelephone(),
				'custom_field'      => []
			];

			$this->load->model('account/address');

			$address_id = $this->customer->getAddressId();

			if ($address_id) {
				$address_info = $this->model_account_address->getAddress($this->customer->getId(), $address_id);

				if ($address_info) {
					$default_address = $this->normalizeAddress($address_info, $default_address);
				}
			}
		}

		$payment_address = $this->normalizeAddress($this->session->data['payment_address'] ?? [], $default_address);
		$shipping_address = $this->normalizeAddress($this->session->data['shipping_address'] ?? $payment_address, $payment_address);

		return [
			'customer'        => $customer,
			'payment_address' => $payment_address,
			'shipping_address'=> $shipping_address,
			'comment'         => (string)($this->session->data['comment'] ?? ''),
			'shipping_method' => (string)($this->session->data['shipping_method']['code'] ?? ''),
			'payment_method'  => (string)($this->session->data['payment_method']['code'] ?? '')
		];
	}

	private function getDefaultAddress(): array {
		$country_id = $this->getDefaultCountryIdForCheckout();
		$zone_id = $this->getDefaultZoneIdForCountry($country_id);

		$country_info = $this->getCountryByIdWithFallback($country_id);
		$zone_info = $zone_id ? $this->getZoneByIdWithFallback($zone_id) : [];

		return [
			'address_id'      => 0,
			'firstname'       => '',
			'lastname'        => '',
			'company'         => '',
			'address_1'       => '',
			'address_2'       => '',
			'city'            => '',
			'city_ref'        => '',
			'address_ref'     => '',
			'postcode'        => '',
			'country_id'      => $country_id,
			'country'         => $country_info['name'] ?? '',
			'zone_id'         => $zone_id,
			'zone'            => $zone_info['name'] ?? '',
			'address_format'  => '',
			'custom_field'    => []
		];
	}

	private function getDefaultCountryIdForCheckout(): int {
		$country_id = (int)$this->config->get('config_country_id');

		// Prefer Ukraine as checkout default when NP is enabled.
		if ((bool)$this->config->get('shipping_novaposhta_status')) {
			return 220;
		}

		return $country_id > 0 ? $country_id : 0;
	}

	private function getDefaultZoneIdForCountry(int $country_id): int {
		$zone_id = (int)$this->config->get('config_zone_id');

		if ($zone_id <= 0) {
			return 0;
		}

		$zone_info = $this->getZoneByIdWithFallback($zone_id);

		if (!$zone_info || (int)($zone_info['country_id'] ?? 0) !== $country_id) {
			return 0;
		}

		return $zone_id;
	}

	private function normalizeAddress(array $address, array $fallback): array {
		$result = array_replace($fallback, $address);

		$result['address_id'] = (int)($result['address_id'] ?? 0);
		$result['country_id'] = (int)($result['country_id'] ?? 0);
		$result['zone_id'] = (int)($result['zone_id'] ?? 0);
		$result['firstname'] = trim((string)($result['firstname'] ?? ''));
		$result['lastname'] = trim((string)($result['lastname'] ?? ''));
		$result['address_1'] = trim((string)($result['address_1'] ?? ''));
		$result['city'] = trim((string)($result['city'] ?? ''));
		$result['city_ref'] = trim((string)($result['city_ref'] ?? ''));
		$result['address_ref'] = trim((string)($result['address_ref'] ?? ''));
		$result['postcode'] = trim((string)($result['postcode'] ?? ''));

		$this->load->model('localisation/country');
		$this->load->model('localisation/zone');

		if ($result['country_id']) {
			$country_info = $this->getCountryByIdWithFallback($result['country_id']);
			$result['country'] = $country_info['name'] ?? '';
		}

		if ($result['zone_id']) {
			$zone_info = $this->getZoneByIdWithFallback($result['zone_id']);

			if ($zone_info && (int)($zone_info['country_id'] ?? 0) === (int)$result['country_id']) {
				$result['zone'] = $zone_info['name'] ?? '';
			} else {
				$result['zone_id'] = 0;
				$result['zone'] = '';
			}
		}

		return $result;
	}

	private function syncCoreSessionFromSimple(): void {
		$this->ensureSimpleSession();

		$simple = &$this->session->data['simplecheckout'];
		$default_address = $this->getDefaultAddress();

		$customer = $simple['customer'];

		if ($this->customer->isLogged()) {
			$customer['customer_id'] = (int)$this->customer->getId();
			$customer['customer_group_id'] = (int)$this->customer->getGroupId();
		} else {
			$customer['customer_id'] = 0;
			if (empty($customer['customer_group_id'])) {
				$customer['customer_group_id'] = (int)$this->config->get('config_customer_group_id');
			}
		}

		$simple['customer'] = $customer;

		$payment_address = $this->normalizeAddress($simple['payment_address'], $default_address);
		$shipping_address = $this->normalizeAddress($simple['shipping_address'], $payment_address);

		if (empty($payment_address['firstname'])) {
			$payment_address['firstname'] = $customer['firstname'];
		}

		if (empty($payment_address['lastname'])) {
			$payment_address['lastname'] = $customer['lastname'];
		}

		if (empty($shipping_address['firstname'])) {
			$shipping_address['firstname'] = $customer['firstname'];
		}

		if (empty($shipping_address['lastname'])) {
			$shipping_address['lastname'] = $customer['lastname'];
		}

		$simple['payment_address'] = $payment_address;
		$simple['shipping_address'] = $shipping_address;

		$this->session->data['customer'] = [
			'customer_id'       => (int)$customer['customer_id'],
			'customer_group_id' => (int)$customer['customer_group_id'],
			'firstname'         => $customer['firstname'],
			'lastname'          => $customer['lastname'],
			'email'             => $customer['email'],
			'telephone'         => $customer['telephone'],
			'custom_field'      => $customer['custom_field'] ?? []
		];

		$this->session->data['payment_address'] = $payment_address;

		if ($this->cart->hasShipping()) {
			$this->session->data['shipping_address'] = $shipping_address;
			$this->tax->setShippingAddress((int)$shipping_address['country_id'], (int)$shipping_address['zone_id']);
		} else {
			unset($this->session->data['shipping_address']);
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
		}

		$this->tax->setPaymentAddress((int)$payment_address['country_id'], (int)$payment_address['zone_id']);
		$this->session->data['comment'] = (string)$simple['comment'];
	}

	private function findShippingByCode(array $shipping_methods, string $code): array {
		foreach ($shipping_methods as $shipping_method) {
			if (empty($shipping_method['quote']) || !is_array($shipping_method['quote'])) {
				continue;
			}

			foreach ($shipping_method['quote'] as $quote) {
				if (!empty($quote['code']) && $quote['code'] === $code) {
					return $quote;
				}
			}
		}

		return [];
	}

	private function normalizeShippingMethods(array $shipping_methods): array {
		if (!$shipping_methods) {
			return $shipping_methods;
		}

		$np_enabled = (bool)$this->config->get('shipping_novaposhta_status');
		$replace_flat = (bool)$this->config->get('shipping_novaposhta_replace_flat');

		if (!$np_enabled || !$replace_flat) {
			return $shipping_methods;
		}

		$filtered = $shipping_methods;
		unset($filtered['flat']);

		return $filtered ?: $shipping_methods;
	}

	private function getFreeDeliveryThreshold(): int {
		$threshold = (float)$this->config->get('shipping_novaposhta_free_total');

		if ($threshold <= 0) {
			$threshold = 1500;
		}

		return (int)round($threshold);
	}

	private function findFirstShipping(array $shipping_methods): array {
		foreach ($shipping_methods as $shipping_method) {
			if (empty($shipping_method['quote']) || !is_array($shipping_method['quote'])) {
				continue;
			}

			foreach ($shipping_method['quote'] as $quote) {
				if (!empty($quote['code'])) {
					return $quote;
				}
			}
		}

		return [];
	}

	private function findPaymentByCode(array $payment_methods, string $code): array {
		foreach ($payment_methods as $payment_method) {
			if (empty($payment_method['option']) || !is_array($payment_method['option'])) {
				continue;
			}

			foreach ($payment_method['option'] as $option) {
				if (!empty($option['code']) && $option['code'] === $code) {
					return $option;
				}
			}
		}

		return [];
	}

	private function findFirstPayment(array $payment_methods): array {
		foreach ($payment_methods as $payment_method) {
			if (empty($payment_method['option']) || !is_array($payment_method['option'])) {
				continue;
			}

			foreach ($payment_method['option'] as $option) {
				if (!empty($option['code'])) {
					return $option;
				}
			}
		}

		return [];
	}

	private function validateState(array $state): array {
		$errors = [];

		if ($state['cart_empty']) {
			$errors['cart'] = $this->language->get('error_cart_empty');
		}

		if (!oc_validate_length($state['customer']['firstname'], 1, 32)) {
			$errors['firstname'] = $this->language->get('error_firstname');
		}

		if (!oc_validate_length($state['customer']['lastname'], 1, 32)) {
			$errors['lastname'] = $this->language->get('error_lastname');
		}

		if (!oc_validate_email($state['customer']['email'])) {
			$errors['email'] = $this->language->get('error_email');
		}

		if ($this->config->get('config_telephone_required') && !oc_validate_length($state['customer']['telephone'], 3, 32)) {
			$errors['telephone'] = $this->language->get('error_telephone');
		}

		if ($state['cart_has_shipping']) {
			$is_novaposhta = strpos((string)$state['shipping_code'], 'novaposhta.') === 0;
			$address_min = $is_novaposhta ? 1 : 3;
			$city_min = $is_novaposhta ? 1 : 2;

			if (!oc_validate_length($state['shipping_address']['address_1'], $address_min, 128)) {
				$errors['shipping_address_1'] = $this->language->get('error_address_1');
			}

			if (!oc_validate_length($state['shipping_address']['city'], $city_min, 128)) {
				$errors['shipping_city'] = $this->language->get('error_city');
			}

			$country_info = $this->getCountryByIdWithFallback((int)$state['shipping_address']['country_id']);

			if (!$country_info) {
				$errors['shipping_country'] = $this->language->get('error_country');
			} elseif ($country_info['postcode_required'] && !oc_validate_length($state['shipping_address']['postcode'], 2, 10)) {
				$errors['shipping_postcode'] = $this->language->get('error_postcode');
			}

			$this->load->model('localisation/zone');
			$zone_total = $this->model_localisation_zone->getTotalZonesByCountryId((int)$state['shipping_address']['country_id']);

			if ($zone_total && !(int)$state['shipping_address']['zone_id']) {
				$errors['shipping_zone'] = $this->language->get('error_zone');
			}

			if (empty($state['shipping_methods'])) {
				$errors['shipping_method'] = $this->language->get('error_shipping_method');
			}

			if (!$state['shipping_code']) {
				$errors['shipping_method'] = $this->language->get('error_shipping_method');
			}
		}

		if (empty($state['payment_methods'])) {
			$errors['payment_method'] = $this->language->get('error_payment_method');
		}

		if (!$state['payment_code']) {
			$errors['payment_method'] = $this->language->get('error_payment_method');
		}

		if ($this->config->get('config_checkout_id') && empty($this->session->data['agree'])) {
			$errors['agree'] = $this->language->get('error_agree');
		}

		return $errors;
	}

	private function applyCartWarnings(array &$state): void {
		if ($state['cart_empty']) {
			$state['errors']['cart'] = $this->language->get('error_cart_empty');
			return;
		}

		if (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) {
			$state['errors']['cart'] = $this->language->get('error_stock');
		}
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function getNovaPoshtaCities(string $search, int $zone_id = 0): array {
		$search = trim($search);
		$area_ref = $this->getNovaPoshtaAreaRefByZoneId($zone_id);

		if ($area_ref === '') {
			return [];
		}

		$cities = $this->getNovaPoshtaCitiesCatalogByArea($area_ref);

		if (!$cities) {
			return [];
		}

		if ($search === '') {
			return $cities;
		}

		if (oc_strlen($search) < 2) {
			return [];
		}

		$needle = function_exists('mb_strtolower') ? mb_strtolower($search, 'UTF-8') : strtolower($search);
		$filtered = [];

		foreach ($cities as $city) {
			$name = trim((string)($city['description'] ?? ''));

			if ($name === '') {
				continue;
			}

			$name_lower = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);

			if (strpos($name_lower, $needle) === 0) {
				$filtered[] = $city;
			}
		}

		return $filtered;
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function getNovaPoshtaWarehouses(string $city_ref, string $search, string $shipping_method): array {
		if ($city_ref === '') {
			return [];
		}

		$properties = [
			'CityRef' => $city_ref,
			'Limit'   => '50',
			'Page'    => '1'
		];

		$search = trim($search);

		if ($search !== '') {
			$properties['FindByString'] = $search;
		}

		$rows = $this->requestNovaPoshta('AddressGeneral', 'getWarehouses', $properties);

		if (!$rows) {
			$rows = $this->requestNovaPoshta('Address', 'getWarehouses', $properties);
		}

		if (!$rows) {
			return [];
		}

		$need_locker = $shipping_method === 'novaposhta.locker';
		$data = [];

		foreach ($rows as $warehouse) {
			$category = trim((string)($warehouse['CategoryOfWarehouse'] ?? ''));
			$category_lower = function_exists('mb_strtolower') ? mb_strtolower($category, 'UTF-8') : strtolower($category);
			$is_locker = $category !== '' && (
				$category === 'Postomat' ||
				strpos($category_lower, 'поштомат') !== false ||
				strpos($category_lower, 'postomat') !== false
			);

			if ($need_locker && !$is_locker) {
				continue;
			}

			if (!$need_locker && $is_locker) {
				continue;
			}

			$description = trim((string)($warehouse['ShortAddress'] ?? $warehouse['Description'] ?? ''));
			$label = trim((string)($warehouse['Description'] ?? $description));
			$ref = trim((string)($warehouse['Ref'] ?? ''));

			if ($description === '' || $ref === '') {
				continue;
			}

			$data[] = [
				'description' => $description,
				'value'       => $description,
				'label'       => $label !== '' ? $label : $description,
				'ref'         => $ref
			];
		}

		return $data;
	}

	private function resolveNovaPoshtaCityRef(string $city, int $zone_id = 0): string {
		$city = trim($city);

		if ($city === '') {
			return '';
		}

		$area_ref = $this->getNovaPoshtaAreaRefByZoneId($zone_id);

		if ($area_ref !== '') {
			$cities = $this->getNovaPoshtaCitiesCatalogByArea($area_ref);
			$needle = function_exists('mb_strtolower') ? mb_strtolower($city, 'UTF-8') : strtolower($city);

			foreach ($cities as $item) {
				$name = trim((string)($item['description'] ?? ''));
				$ref = trim((string)($item['ref'] ?? ''));

				if ($name === '' || $ref === '') {
					continue;
				}

				$name_lower = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);

				if ($name_lower === $needle) {
					return $ref;
				}
			}

			foreach ($cities as $item) {
				$name = trim((string)($item['description'] ?? ''));
				$ref = trim((string)($item['ref'] ?? ''));

				if ($name === '' || $ref === '') {
					continue;
				}

				$name_lower = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);

				if (strpos($name_lower, $needle) === 0) {
					return $ref;
				}
			}
		}

		$rows = $this->requestNovaPoshta('Address', 'searchSettlements', [
			'CityName' => $city,
			'Limit'    => '20',
			'Page'     => '1'
		]);

		if (!$rows || empty($rows[0]['Addresses'])) {
			return '';
		}

		$addresses = $rows[0]['Addresses'];
		$candidates = $this->getAreaCandidatesByZoneId($zone_id);

		foreach ($addresses as $address) {
			$ref = trim((string)($address['DeliveryCity'] ?? $address['Ref'] ?? ''));

			if ($ref === '') {
				continue;
			}

			if ($area_ref !== '') {
				$row_area = trim((string)($address['Area'] ?? $address['AreaDescription'] ?? $address['AreaDescriptionRu'] ?? ''));

				if ($row_area !== '') {
					if ($this->isUuid($row_area)) {
						if (strcasecmp($row_area, $area_ref) !== 0) {
							continue;
						}
					} elseif (!$this->areaMatchesCandidates($row_area, $candidates)) {
						continue;
					}
				}
			}

			return $ref;
		}

		return '';
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function getNovaPoshtaCitiesCatalogByArea(string $area_ref): array {
		$area_ref = trim($area_ref);

		if ($area_ref === '') {
			return [];
		}

		if (!isset($this->session->data['simplecheckout_np_cities_cache']) || !is_array($this->session->data['simplecheckout_np_cities_cache'])) {
			$this->session->data['simplecheckout_np_cities_cache'] = [];
		}

		$language = (string)$this->config->get('config_language');
		$cache_key = $area_ref . '|' . $language;
		$cache = &$this->session->data['simplecheckout_np_cities_cache'];

		if (isset($cache[$cache_key]) && is_array($cache[$cache_key])) {
			return $cache[$cache_key];
		}

		$is_ru = strpos($language, 'ru') === 0;
		$city_map = [];

		for ($page = 1; $page <= 20; $page++) {
			$rows = $this->requestNovaPoshta('Address', 'getCities', [
				'AreaRef' => $area_ref,
				'Limit'   => '500',
				'Page'    => (string)$page
			]);

			if (!$rows) {
				break;
			}

			foreach ($rows as $city) {
				$row_area = trim((string)($city['Area'] ?? $city['AreaRef'] ?? ''));

				if ($row_area !== '' && $this->isUuid($row_area) && strcasecmp($row_area, $area_ref) !== 0) {
					continue;
				}

				$ref = trim((string)($city['Ref'] ?? ''));
				$name = trim((string)($is_ru ? ($city['DescriptionRu'] ?? $city['Description'] ?? '') : ($city['Description'] ?? $city['DescriptionRu'] ?? '')));

				if ($ref === '' || $name === '') {
					continue;
				}

				$city_map[$ref] = [
					'description' => $name,
					'value'       => $name,
					'label'       => $name,
					'ref'         => $ref
				];
			}

			if (count($rows) < 500) {
				break;
			}
		}

		$cities = array_values($city_map);
		$this->sortAutocompleteItems($cities);
		$cache[$cache_key] = $cities;

		return $cities;
	}

	private function getNovaPoshtaAreaRefByZoneId(int $zone_id): string {
		if ($zone_id <= 0) {
			return '';
		}

		$zone_info = $this->getZoneByIdWithFallback($zone_id);
		$zone_code = trim((string)($zone_info['code'] ?? ''));

		if ($zone_code === '') {
			return '';
		}

		$direct_ref = $this->getAreaRefByZoneCode($zone_code);

		if ($direct_ref !== '') {
			return $direct_ref;
		}

		$area_candidates = $this->getAreaCandidatesByZoneCode($zone_code);
		$zone_name = trim((string)($zone_info['name'] ?? ''));

		if ($zone_name !== '') {
			$area_candidates[] = $zone_name;
		}

		if ($zone_code === '26') {
			$area_candidates[] = 'Київська';
			$area_candidates[] = 'Киевская';
			$area_candidates[] = 'Kyivska';
		}

		$area_candidates = array_values(array_unique(array_filter(array_map('trim', $area_candidates))));

		if (!$area_candidates) {
			return '';
		}

		$area_map = $this->getNovaPoshtaAreasMap();

		foreach ($area_candidates as $candidate) {
			$normalized = $this->normalizeNpName($candidate);

			if ($normalized !== '' && isset($area_map[$normalized])) {
				return $area_map[$normalized];
			}
		}

		return '';
	}

	private function getAreaRefByZoneCode(string $zone_code): string {
		$zone_code = trim($zone_code);

		if ($zone_code === '') {
			return '';
		}

		$zone_code = strtoupper($zone_code);

		$map = [
			'02' => '71508129-9b87-11de-822f-000c2965ae0e', // Vinnytska
			'03' => '7150812a-9b87-11de-822f-000c2965ae0e', // Volynska
			'04' => '7150812b-9b87-11de-822f-000c2965ae0e', // Dnipropetrovska
			'05' => '7150812c-9b87-11de-822f-000c2965ae0e', // Donetska
			'06' => '7150812d-9b87-11de-822f-000c2965ae0e', // Zhytomyrska
			'07' => '7150812e-9b87-11de-822f-000c2965ae0e', // Zakarpatska
			'08' => '7150812f-9b87-11de-822f-000c2965ae0e', // Zaporizka
			'09' => '71508130-9b87-11de-822f-000c2965ae0e', // Ivano-Frankivska
			'10' => '71508131-9b87-11de-822f-000c2965ae0e', // Kyivska
			'12' => '71508133-9b87-11de-822f-000c2965ae0e', // Luhanska
			'13' => '71508134-9b87-11de-822f-000c2965ae0e', // Lvivska
			'14' => '71508135-9b87-11de-822f-000c2965ae0e', // Mykolaivska
			'15' => '71508136-9b87-11de-822f-000c2965ae0e', // Odeska
			'16' => '71508137-9b87-11de-822f-000c2965ae0e', // Poltavska
			'17' => '71508138-9b87-11de-822f-000c2965ae0e', // Rivnenska
			'18' => '71508139-9b87-11de-822f-000c2965ae0e', // Sumska
			'19' => '7150813a-9b87-11de-822f-000c2965ae0e', // Ternopilska
			'21' => '7150813c-9b87-11de-822f-000c2965ae0e', // Khersonska
			'22' => '7150813d-9b87-11de-822f-000c2965ae0e', // Khmelnytska
			'23' => '7150813e-9b87-11de-822f-000c2965ae0e', // Cherkaska
			'24' => '7150813f-9b87-11de-822f-000c2965ae0e', // Chernivetska
			'25' => '71508140-9b87-11de-822f-000c2965ae0e', // Chernihivska
			'26' => '71508131-9b87-11de-822f-000c2965ae0e', // Kyiv city -> Kyivska area
			'27' => '71508128-9b87-11de-822f-000c2965ae0e', // ARK
			'35' => '71508132-9b87-11de-822f-000c2965ae0e', // Kirovohradska
			'63' => '7150813b-9b87-11de-822f-000c2965ae0e'  // Kharkivska
		];

		if (isset($map[$zone_code])) {
			return $map[$zone_code];
		}

		if (ctype_digit($zone_code)) {
			$normalized = str_pad((string)(int)$zone_code, 2, '0', STR_PAD_LEFT);

			if (isset($map[$normalized])) {
				return $map[$normalized];
			}
		}

		return '';
	}

	/**
	 * @return array<int, string>
	 */
	private function getAreaCandidatesByZoneCode(string $zone_code): array {
		$map = [
			'23' => ['Черкаська', 'Черкасская', 'Cherkaska'],
			'25' => ['Чернігівська', 'Черниговская', 'Chernihivska'],
			'24' => ['Чернівецька', 'Черновицкая', 'Chernivetska'],
			'04' => ['Дніпропетровська', 'Днепропетровская', 'Dnipropetrovska'],
			'05' => ['Донецька', 'Донецкая', 'Donetska'],
			'09' => ['Івано-Франківська', 'Ивано-Франковская', 'Ivano-Frankivska'],
			'21' => ['Херсонська', 'Херсонская', 'Khersonska'],
			'22' => ['Хмельницька', 'Хмельницкая', 'Khmelnytska'],
			'35' => ['Кіровоградська', 'Кировоградская', 'Kirovohradska'],
			'26' => ['Київська', 'Киевская', 'м. Київ', 'Kyivska', 'Kyiv'],
			'10' => ['Київська', 'Киевская', 'Kyivska'],
			'12' => ['Луганська', 'Луганская', 'Luhanska'],
			'13' => ['Львівська', 'Львовская', 'Lvivska'],
			'14' => ['Миколаївська', 'Николаевская', 'Mykolaivska'],
			'15' => ['Одеська', 'Одесская', 'Odeska'],
			'16' => ['Полтавська', 'Полтавская', 'Poltavska'],
			'17' => ['Рівненська', 'Ровенская', 'Rivnenska'],
			'18' => ['Сумська', 'Сумская', 'Sumska'],
			'19' => ['Тернопільська', 'Тернопольская', 'Ternopilska'],
			'02' => ['Вінницька', 'Винницкая', 'Vinnytska'],
			'03' => ['Волинська', 'Волынская', 'Volynska'],
			'07' => ['Закарпатська', 'Закарпатская', 'Zakarpatska'],
			'08' => ['Запорізька', 'Запорожская', 'Zaporizka'],
			'06' => ['Житомирська', 'Житомирская', 'Zhytomyrska'],
			'63' => ['Харківська', 'Харьковская', 'Kharkivska'],
			'27' => ['АРК', 'Крим', 'Крым', 'Avtonomna Respublika Krym', 'ARK']
		];

		return $map[$zone_code] ?? [];
	}

	/**
	 * @return array<string, string>
	 */
	private function getNovaPoshtaAreasMap(): array {
		if (!empty($this->session->data['simplecheckout_np_areas_map']) && is_array($this->session->data['simplecheckout_np_areas_map'])) {
			return $this->session->data['simplecheckout_np_areas_map'];
		}

		$rows = $this->requestNovaPoshta('Address', 'getAreas', []);
		$map = [];

		foreach ($rows as $area) {
			$ref = trim((string)($area['Ref'] ?? ''));

			if ($ref === '') {
				continue;
			}

			foreach (['Description', 'DescriptionRu'] as $field) {
				$value = trim((string)($area[$field] ?? ''));
				$key = $this->normalizeNpName($value);

				if ($key !== '') {
					$map[$key] = $ref;
				}
			}
		}

		$this->session->data['simplecheckout_np_areas_map'] = $map;

		return $map;
	}

	private function normalizeNpName(string $value): string {
		$value = trim($value);

		if ($value === '') {
			return '';
		}

		$value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
		$value = str_replace(['`', '\'', '"', '.', ',', 'область', 'обл', 'р-н', 'район'], '', $value);
		$value = preg_replace('/\s+/', ' ', $value);

		return trim((string)$value);
	}

	/**
	 * @return array<int, string>
	 */
	private function getAreaCandidatesByZoneId(int $zone_id): array {
		if ($zone_id <= 0) {
			return [];
		}

		$zone_info = $this->getZoneByIdWithFallback($zone_id);
		$zone_code = trim((string)($zone_info['code'] ?? ''));

		if ($zone_code === '') {
			return [];
		}

		return $this->getAreaCandidatesByZoneCode($zone_code);
	}

	/**
	 * @param array<int, string> $area_candidates
	 */
	private function areaMatchesCandidates(string $area_value, array $area_candidates): bool {
		if (!$area_candidates) {
			return true;
		}

		$area = $this->normalizeNpName($area_value);

		if ($area === '') {
			return true;
		}

		foreach ($area_candidates as $candidate) {
			$candidate_normalized = $this->normalizeNpName($candidate);

			if ($candidate_normalized === '') {
				continue;
			}

			if (
				$area === $candidate_normalized ||
				strpos($area, $candidate_normalized) !== false ||
				strpos($candidate_normalized, $area) !== false
			) {
				return true;
			}
		}

		return false;
	}

	private function isUuid(string $value): bool {
		return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($value));
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function requestNovaPoshta(string $model_name, string $called_method, array $method_properties): array {
		$api_key = trim((string)$this->config->get('shipping_novaposhta_api_key'));

		if ($api_key === '') {
			return [];
		}

		$url = trim((string)$this->config->get('shipping_novaposhta_api_url'));

		if ($url === '') {
			$url = 'https://api.novaposhta.ua/v2.0/json/';
		}

		$payload = [
			'apiKey'           => $api_key,
			'modelName'        => $model_name,
			'calledMethod'     => $called_method,
			'methodProperties' => $method_properties
		];

		$body = json_encode($payload, JSON_UNESCAPED_UNICODE);

		if ($body === false) {
			return [];
		}

		$response_body = '';

		if (function_exists('curl_init')) {
			$ch = curl_init($url);

			if ($ch !== false) {
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
				curl_setopt($ch, CURLOPT_TIMEOUT, 12);

				$response = curl_exec($ch);

				if (is_string($response)) {
					$response_body = $response;
				}

				curl_close($ch);
			}
		}

		if ($response_body === '') {
			$context = stream_context_create([
				'http' => [
					'method'  => 'POST',
					'header'  => "Content-Type: application/json\r\n",
					'content' => $body,
					'timeout' => 12
				]
			]);

			$response = @file_get_contents($url, false, $context);

			if (is_string($response)) {
				$response_body = $response;
			}
		}

		if ($response_body === '') {
			return [];
		}

		$decoded = json_decode($response_body, true);

		if (!is_array($decoded) || empty($decoded['success']) || empty($decoded['data']) || !is_array($decoded['data'])) {
			return [];
		}

		return $decoded['data'];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function getCountriesWithFallback(): array {
		$this->load->model('localisation/country');

		$countries = $this->model_localisation_country->getCountries();

		if ($countries) {
			foreach ($countries as &$country) {
				$this->localizeCountryName($country);
			}
			unset($country);
			$this->sortRowsByName($countries);

			return $countries;
		}

		$language_id = $this->getFallbackDescriptionLanguageId('country_description');

		$sql = "SELECT c.country_id, c.iso_code_2, c.iso_code_3, cd.name
			FROM `" . DB_PREFIX . "country` c
			LEFT JOIN `" . DB_PREFIX . "country_description` cd
				ON (cd.country_id = c.country_id AND cd.language_id = '" . (int)$language_id . "')
			WHERE c.status = '1'
			ORDER BY cd.name";

		$countries = $this->db->query($sql)->rows;

		foreach ($countries as &$country) {
			$this->localizeCountryName($country);
		}
		unset($country);
		$this->sortRowsByName($countries);

		return $countries;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function getZonesByCountryIdWithFallback(int $country_id): array {
		$this->load->model('localisation/zone');

		$zones = $this->model_localisation_zone->getZonesByCountryId($country_id);

		if ($zones || $country_id <= 0) {
			foreach ($zones as &$zone) {
				$this->localizeUkraineZoneName($zone);
			}
			unset($zone);
			$this->sortRowsByName($zones);

			return $zones;
		}

		$language_id = $this->getFallbackDescriptionLanguageId('zone_description');

		$sql = "SELECT z.zone_id, z.country_id, z.code, zd.name
			FROM `" . DB_PREFIX . "zone` z
			LEFT JOIN `" . DB_PREFIX . "zone_description` zd
				ON (zd.zone_id = z.zone_id AND zd.language_id = '" . (int)$language_id . "')
			WHERE z.country_id = '" . (int)$country_id . "'
				AND z.status = '1'
			ORDER BY zd.name";

		$zones = $this->db->query($sql)->rows;

		foreach ($zones as &$zone) {
			$this->localizeUkraineZoneName($zone);
		}
		unset($zone);
		$this->sortRowsByName($zones);

		return $zones;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getCountryByIdWithFallback(int $country_id): array {
		$this->load->model('localisation/country');

		$country = $this->model_localisation_country->getCountry($country_id);

		if ($country) {
			$this->localizeCountryName($country);

			return $country;
		}

		$language_id = $this->getFallbackDescriptionLanguageId('country_description');

		$sql = "SELECT c.*, cd.name
			FROM `" . DB_PREFIX . "country` c
			LEFT JOIN `" . DB_PREFIX . "country_description` cd
				ON (cd.country_id = c.country_id AND cd.language_id = '" . (int)$language_id . "')
			WHERE c.country_id = '" . (int)$country_id . "'
			LIMIT 1";

		$row = $this->db->query($sql)->row;

		if (!is_array($row)) {
			return [];
		}

		$this->localizeCountryName($row);

		return $row;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getZoneByIdWithFallback(int $zone_id): array {
		$this->load->model('localisation/zone');

		$zone = $this->model_localisation_zone->getZone($zone_id);

		if ($zone) {
			$this->localizeUkraineZoneName($zone);

			return $zone;
		}

		$language_id = $this->getFallbackDescriptionLanguageId('zone_description');

		$sql = "SELECT z.zone_id, z.country_id, z.code, z.status, zd.name
			FROM `" . DB_PREFIX . "zone` z
			LEFT JOIN `" . DB_PREFIX . "zone_description` zd
				ON (zd.zone_id = z.zone_id AND zd.language_id = '" . (int)$language_id . "')
			WHERE z.zone_id = '" . (int)$zone_id . "'
			LIMIT 1";

		$row = $this->db->query($sql)->row;

		if (!is_array($row)) {
			return [];
		}

		$this->localizeUkraineZoneName($row);

		return $row;
	}

	private function localizeCountryName(array &$country): void {
		$language = (string)$this->config->get('config_language');
		$lang_prefix = strpos($language, 'uk-') === 0 ? 'uk' : (strpos($language, 'ru-') === 0 ? 'ru' : '');
		$country_id = (int)($country['country_id'] ?? 0);

		if ($lang_prefix === '') {
			return;
		}

		if ($country_id === 220) {
			$country['name'] = $lang_prefix === 'uk' ? 'Україна' : 'Украина';
			return;
		}

		$iso_code_2 = strtoupper(trim((string)($country['iso_code_2'] ?? '')));

		if ($iso_code_2 === '' || !class_exists('\Locale')) {
			return;
		}

		$translated = (string)\Locale::getDisplayRegion('-' . $iso_code_2, $this->getLanguageLocale($language));

		if ($translated === '' || strtoupper($translated) === $iso_code_2) {
			return;
		}

		$country['name'] = $translated;
	}

	private function localizeUkraineZoneName(array &$zone): void {
		$country_id = (int)($zone['country_id'] ?? 0);

		if ($country_id !== 220) {
			return;
		}

		$zone_code = trim((string)($zone['code'] ?? ''));

		if ($zone_code === '') {
			return;
		}

		$map = [
			'02' => ['uk' => 'Вінницька', 'ru' => 'Винницкая'],
			'03' => ['uk' => 'Волинська', 'ru' => 'Волынская'],
			'04' => ['uk' => 'Дніпропетровська', 'ru' => 'Днепропетровская'],
			'05' => ['uk' => 'Донецька', 'ru' => 'Донецкая'],
			'06' => ['uk' => 'Житомирська', 'ru' => 'Житомирская'],
			'07' => ['uk' => 'Закарпатська', 'ru' => 'Закарпатская'],
			'08' => ['uk' => 'Запорізька', 'ru' => 'Запорожская'],
			'09' => ['uk' => 'Івано-Франківська', 'ru' => 'Ивано-Франковская'],
			'10' => ['uk' => 'Київська', 'ru' => 'Киевская'],
			'12' => ['uk' => 'Луганська', 'ru' => 'Луганская'],
			'13' => ['uk' => 'Львівська', 'ru' => 'Львовская'],
			'14' => ['uk' => 'Миколаївська', 'ru' => 'Николаевская'],
			'15' => ['uk' => 'Одеська', 'ru' => 'Одесская'],
			'16' => ['uk' => 'Полтавська', 'ru' => 'Полтавская'],
			'17' => ['uk' => 'Рівненська', 'ru' => 'Ровенская'],
			'18' => ['uk' => 'Сумська', 'ru' => 'Сумская'],
			'19' => ['uk' => 'Тернопільська', 'ru' => 'Тернопольская'],
			'21' => ['uk' => 'Херсонська', 'ru' => 'Херсонская'],
			'22' => ['uk' => 'Хмельницька', 'ru' => 'Хмельницкая'],
			'23' => ['uk' => 'Черкаська', 'ru' => 'Черкасская'],
			'24' => ['uk' => 'Чернівецька', 'ru' => 'Черновицкая'],
			'25' => ['uk' => 'Чернігівська', 'ru' => 'Черниговская'],
			'26' => ['uk' => 'м. Київ', 'ru' => 'г. Киев'],
			'27' => ['uk' => 'Автономна Республіка Крим', 'ru' => 'Автономная Республика Крым'],
			'28' => ['uk' => 'Севастополь', 'ru' => 'Севастополь'],
			'35' => ['uk' => 'Кіровоградська', 'ru' => 'Кировоградская'],
			'63' => ['uk' => 'Харківська', 'ru' => 'Харьковская']
		];

		$language = (string)$this->config->get('config_language');
		$lang_key = strpos($language, 'uk-') === 0 ? 'uk' : (strpos($language, 'ru-') === 0 ? 'ru' : '');

		if ($lang_key === '') {
			return;
		}

		if (isset($map[$zone_code][$lang_key])) {
			$zone['name'] = $map[$zone_code][$lang_key];
			return;
		}

		if (ctype_digit($zone_code)) {
			$normalized = str_pad((string)(int)$zone_code, 2, '0', STR_PAD_LEFT);

			if (isset($map[$normalized][$lang_key])) {
				$zone['name'] = $map[$normalized][$lang_key];
			}
		}
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 */
	private function sortRowsByName(array &$rows): void {
		if (count($rows) < 2) {
			return;
		}

		usort($rows, function(array $a, array $b): int {
			$name_a = trim((string)($a['name'] ?? ''));
			$name_b = trim((string)($b['name'] ?? ''));

			return $this->compareLocalizedStrings($name_a, $name_b);
		});
	}

	/**
	 * @param array<int, array<string, string>> $rows
	 */
	private function sortAutocompleteItems(array &$rows): void {
		if (count($rows) < 2) {
			return;
		}

		usort($rows, function(array $a, array $b): int {
			$label_a = trim((string)($a['label'] ?? $a['value'] ?? ''));
			$label_b = trim((string)($b['label'] ?? $b['value'] ?? ''));

			return $this->compareLocalizedStrings($label_a, $label_b);
		});
	}

	private function compareLocalizedStrings(string $a, string $b): int {
		$locale = $this->getLanguageLocale((string)$this->config->get('config_language'));

		if (class_exists('\Collator')) {
			static $collators = [];

			if (!isset($collators[$locale])) {
				$collator = new \Collator($locale);
				$collator->setStrength(\Collator::PRIMARY);
				$collators[$locale] = $collator;
			}

			$result = $collators[$locale]->compare($a, $b);

			if ($result !== false) {
				return (int)$result;
			}
		}

		$a = function_exists('mb_strtolower') ? mb_strtolower($a, 'UTF-8') : strtolower($a);
		$b = function_exists('mb_strtolower') ? mb_strtolower($b, 'UTF-8') : strtolower($b);

		return strcmp($a, $b);
	}

	private function getLanguageLocale(string $language): string {
		if (strpos($language, 'uk-') === 0) {
			return 'uk_UA';
		}

		if (strpos($language, 'ru-') === 0) {
			return 'ru_RU';
		}

		return 'en_US';
	}

	private function getFallbackDescriptionLanguageId(string $description_table): int {
		$sql = "SELECT language_id
			FROM `" . DB_PREFIX . $this->db->escape($description_table) . "`
			ORDER BY language_id
			LIMIT 1";

		$result = $this->db->query($sql)->row;

		if (!empty($result['language_id'])) {
			return (int)$result['language_id'];
		}

		return (int)$this->config->get('config_language_id');
	}
}
