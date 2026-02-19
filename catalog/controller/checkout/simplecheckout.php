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

		// Embed existing OC4 checkout controllers into SimpleCheckout page.
		$data['customer_logged'] = $this->customer->isLogged();

		if (!$data['customer_logged']) {
			$data['register'] = $this->load->controller('checkout/register');
		} else {
			$data['register'] = '';
		}

		if ($this->customer->isLogged() && $this->config->get('config_checkout_payment_address')) {
			$data['payment_address'] = $this->load->controller('checkout/payment_address');
		} else {
			$data['payment_address'] = '';
		}

		if ($this->customer->isLogged() && $this->cart->hasShipping()) {
			$data['shipping_address'] = $this->load->controller('checkout/shipping_address');
		} else {
			$data['shipping_address'] = '';
		}

		if ($this->cart->hasShipping()) {
			$data['shipping_method'] = $this->load->controller('checkout/shipping_method');
		} else {
			$data['shipping_method'] = '';
		}

		$data['payment_method'] = $this->load->controller('checkout/payment_method');
		$data['confirm'] = $this->load->controller('checkout/confirm');

		$data['header'] = $this->load->controller('common/header');
		$data['footer'] = $this->load->controller('common/footer');

		$data['action'] = $this->url->link('checkout/simplecheckout', 'language=' . $this->config->get('config_language'));

		$this->response->setOutput($this->load->view('checkout/simplecheckout', $data));
	}
}
