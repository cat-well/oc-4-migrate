<?php
namespace Opencart\Catalog\Controller\Checkout;
/**
 * Class Success
 *
 * @package Opencart\Catalog\Controller\Checkout
 */
class Success extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('checkout/success');

		if (isset($this->session->data['order_id'])) {
			$this->cart->clear();

			unset($this->session->data['order_id']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['comment']);
			unset($this->session->data['agree']);
			unset($this->session->data['coupon']);
			unset($this->session->data['reward']);
		}

		// Manline: custom success copy (OC2-like)
		$this->document->setTitle($this->language->get('heading_title'));

		$data['lang'] = (string)$this->config->get('config_language');
		$data['heading_title_oc2'] = ($data['lang'] === 'uk-ua') ? 'Ваше замовлення сформовано!' : 'Ваш заказ сформирован!';
		$data['success_subtitle_oc2'] = ($data['lang'] === 'uk-ua') ? 'Дякуємо за замовлення' : 'Благодарим за заказ';
		$data['success_text_oc2'] = ($data['lang'] === 'uk-ua')
			? 'Ваше замовлення прийняте. Очікуйте повідомлення про відправлення замовлення на Viber або E-Mail найближчим часом'
			: 'Ваш заказ принят в обработку. Ожидайте уведомление об отправке заказа на Viber или E-Mail в ближайшее время';


		$data['breadcrumbs'] = [];

		$language = (string)($this->request->get['language'] ?? ($this->session->data['language'] ?? $this->config->get('config_language')));

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home', 'language=' . $language)
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_basket'),
			'href' => $this->url->link('checkout/cart', 'language=' . $language)
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_checkout'),
			'href' => $this->url->link('checkout/checkout', 'language=' . $language)
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_success'),
			'href' => $this->url->link('checkout/success', 'language=' . $language)
		];

		if ($this->customer->isLogged()) {
			$data['text_message'] = sprintf(
				$this->language->get('text_customer'),
				$this->url->link('account/account', 'language=' . $language . '&customer_token=' . $this->session->data['customer_token']),
				$this->url->link('account/order', 'language=' . $language . '&customer_token=' . $this->session->data['customer_token']),
				$this->url->link('account/download', 'language=' . $language . '&customer_token=' . $this->session->data['customer_token']),
				$this->url->link('information/contact', 'language=' . $language)
			);
		} else {
			$data['text_message'] = sprintf($this->language->get('text_guest'), $this->url->link('information/contact', 'language=' . $language));
		}

		$data['continue'] = $this->url->link('common/home', 'language=' . $language);

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('common/success', $data));
	}
}
