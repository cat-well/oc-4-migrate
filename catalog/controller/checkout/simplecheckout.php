<?php
namespace Opencart\Catalog\Controller\Checkout;

class Simplecheckout extends \Opencart\System\Engine\Controller {
	public function index(): void {
		// TEMP: one-page checkout entrypoint scaffold.
		// Next: port Manline OC2 simplecheckout blocks + AJAX reload logic.
		$this->load->language('checkout/checkout');

		$this->document->setTitle('Checkout');

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
		];
		$data['breadcrumbs'][] = [
			'text' => 'Checkout',
			'href' => $this->url->link('checkout/simplecheckout', 'language=' . $this->config->get('config_language'))
		];

		// Common controllers
		$data['header'] = $this->load->controller('common/header');
		$data['footer'] = $this->load->controller('common/footer');

		// Basic URLs used by legacy JS/CSS
		$data['action'] = $this->url->link('checkout/simplecheckout', 'language=' . $this->config->get('config_language'));

		$this->response->setOutput($this->load->view('checkout/simplecheckout', $data));
	}
}
