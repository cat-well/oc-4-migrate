<?php
namespace Opencart\Catalog\Controller\Checkout;

class Simplecheckout extends \Opencart\System\Engine\Controller {
	public function index(): void {
		// Manline: SimpleCheckout-style one-page checkout.
		// Phase 1: reuse OC4 checkout blocks inside SimpleCheckout DOM scaffold.
		// Phase 2: port OC2 SimpleCheckout AJAX reload + custom blocks (NP, totals, comment, agreement, etc).

		// Validate cart to see if it has products and has stock.
		if (!$this->cart->hasProducts() || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) || !$this->cart->hasMinimum()) {
			$this->response->redirect($this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true));
		}

		$this->load->language('checkout/checkout');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_cart'),
			'href' => $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'))
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('checkout/simplecheckout', 'language=' . $this->config->get('config_language'))
		];

		// Phase 1 rendering: use OC2-like markup with OC4 cart/totals data.
		$data['customer_logged'] = $this->customer->isLogged();

		$this->load->language('checkout/cart');
		$this->load->language('checkout/simplecheckout');

		// Cart + totals
		$totals = [];
		$taxes = $this->cart->getTaxes();
		$total = 0;

		$this->load->model('checkout/cart');
		$this->load->model('tool/image');

		// Totals calculation (same approach as common/cart)
		($this->model_checkout_cart->getTotals)($totals, $taxes, $total);

		$data['products'] = [];

		foreach ($this->model_checkout_cart->getProducts() as $product) {
			if ($product['option']) {
				foreach ($product['option'] as $key => $option) {
					$value = $option['value'] ?? '';
					$product['option'][$key]['value'] = (oc_strlen($value) > 40 ? oc_substr($value, 0, 40) . '..' : $value);
				}
			}

			$data['products'][] = [
				'key'      => !empty($product['cart_id']) ? $product['cart_id'] : $product['key'],
				'product_id' => $product['product_id'],
				'thumb'    => $this->model_tool_image->resize($product['image'], 80, 80),
				'name'     => $product['name'],
				'model'    => $product['model'],
				'option'   => $product['option'],
				'quantity' => $product['quantity'],
				'stock'    => $product['stock'],
				'price'    => $product['price_text'] ?? '',
				'total'    => $product['total_text'] ?? '',
				'href'     => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product['product_id'])
			];
		}

		$data['totals'] = [];
		foreach ($totals as $t) {
			$data['totals'][] = ['code' => $t['code'], 'title' => $t['title'], 'text' => $this->currency->format($t['value'], $this->session->data['currency'])];
		}

		// Labels
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_customer'] = $this->language->get('text_customer');
		$data['text_shipping_method'] = $this->language->get('text_shipping_method');
		$data['text_shipping_address'] = $this->language->get('text_shipping_address');
		$data['text_payment_method'] = $this->language->get('text_payment_method');
		$data['button_order'] = $this->language->get('button_order');

		$data['column_image'] = $this->language->get('column_image');
		$data['column_name'] = $this->language->get('column_name');
		$data['column_quantity'] = $this->language->get('column_quantity');
		$data['column_price'] = $this->language->get('column_price');
		$data['column_total'] = $this->language->get('column_total');

		// Shipping/payment placeholders (temporary; will be replaced by NP module)
		$data['shipping_options'] = [
			['code' => 'np_branch', 'title' => $this->language->get('text_shipping_np_branch'), 'desc' => $this->language->get('text_shipping_np_branch_desc')],
			['code' => 'np_courier', 'title' => $this->language->get('text_shipping_np_courier'), 'desc' => $this->language->get('text_shipping_np_courier_desc')],
			['code' => 'np_locker', 'title' => $this->language->get('text_shipping_np_locker'), 'desc' => $this->language->get('text_shipping_np_locker_desc')],
		];

		$data['payment_options'] = [
			['code' => 'cod', 'title' => $this->language->get('text_payment_cod')]
		];

		$data['header'] = $this->load->controller('common/header');
		$data['footer'] = $this->load->controller('common/footer');

		$data['action'] = $this->url->link('checkout/simplecheckout', 'language=' . $this->config->get('config_language'));

		$this->response->setOutput($this->load->view('checkout/simplecheckout', $data));
	}
}
