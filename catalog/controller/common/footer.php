<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class Footer
 *
 * Can be called from $this->load->controller('common/footer');
 *
 * @package Opencart\Catalog\Controller\Common
 */
class Footer extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('common/footer');
		// Manline theme additions (subscribe/footer texts)
		$this->load->language('extension/manline/common/footer');

		// Article
		$this->load->model('cms/article');

		$article_total = $this->model_cms_article->getTotalArticles();

		if ($article_total) {
			$data['blog'] = $this->url->link('cms/blog', 'language=' . $this->config->get('config_language'));
		} else {
			$data['blog'] = '';
		}

		// Information
		$data['informations'] = [];

		// Manline theme: explicit information links used in footer
		$data['information_4'] = $this->url->link('information/information', 'language=' . $this->config->get('config_language') . '&information_id=4'); // about
		$data['information_7'] = $this->url->link('information/information', 'language=' . $this->config->get('config_language') . '&information_id=7'); // track
		$data['information_9'] = $this->url->link('information/information', 'language=' . $this->config->get('config_language') . '&information_id=9'); // size (used as help)
		$data['information_10'] = $this->url->link('information/information', 'language=' . $this->config->get('config_language') . '&information_id=10'); // delivery
		$data['information_16'] = $this->url->link('information/information', 'language=' . $this->config->get('config_language') . '&information_id=16'); // reviews
		$data['information_17'] = $this->url->link('information/information', 'language=' . $this->config->get('config_language') . '&information_id=17'); // cooperation
		$data['information_19'] = $this->url->link('information/information', 'language=' . $this->config->get('config_language') . '&information_id=19'); // models

		$data['simplecheckout_url'] = $this->url->link('checkout/simplecheckout', 'language=' . $this->config->get('config_language'));

		$this->load->model('catalog/information');

		$results = $this->model_catalog_information->getInformations();

		foreach ($results as $result) {
			$data['informations'][] = ['href' => $this->url->link('information/information', 'language=' . $this->config->get('config_language') . '&information_id=' . $result['information_id'])] + $result;
		}

		$data['contact'] = $this->url->link('information/contact', 'language=' . $this->config->get('config_language'));
		$data['return'] = $this->url->link('account/returns.add', 'language=' . $this->config->get('config_language'));

		if ($this->config->get('config_gdpr_id')) {
			$data['gdpr'] = $this->url->link('information/gdpr', 'language=' . $this->config->get('config_language'));
		} else {
			$data['gdpr'] = '';
		}

		$data['sitemap'] = $this->url->link('information/sitemap', 'language=' . $this->config->get('config_language'));
		$data['manufacturer'] = $this->url->link('product/manufacturer', 'language=' . $this->config->get('config_language'));

		// Manline theme: simple UA/RU switch (avoid bootstrap dropdown dependency)
		$route = $this->request->get['route'] ?? 'common/home';
		$params = $this->request->get;
		unset($params['route'], $params['language'], $params['_route_']);
		$query_ru = http_build_query(['language' => 'ru-ru'] + $params);
		$query_ua = http_build_query(['language' => 'uk-ua'] + $params);
		$data['lang_switch_ru'] = $this->url->link($route, $query_ru);
		$data['lang_switch_ua'] = $this->url->link($route, $query_ua);

		if ($this->config->get('config_affiliate_status')) {
			$data['affiliate'] = $this->url->link('account/affiliate', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		} else {
			$data['affiliate'] = '';
		}

		$data['special'] = $this->url->link('product/special', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['account'] = $this->url->link('account/account', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['order'] = $this->url->link('account/order', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['wishlist'] = $this->url->link('account/wishlist', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['newsletter'] = $this->url->link('account/newsletter', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));

		$data['powered'] = sprintf($this->language->get('text_powered'), $this->config->get('config_name'), date('Y', time()));

		// Who's Online
		if ($this->config->get('config_customer_online')) {
			$this->load->model('tool/online');

			if (isset($this->request->server['HTTP_HOST']) && isset($this->request->server['REQUEST_URI'])) {
				$url = ($this->request->server['HTTPS'] ? 'https://' : 'http://') . $this->request->server['HTTP_HOST'] . $this->request->server['REQUEST_URI'];
			} else {
				$url = '';
			}

			if (isset($this->request->server['HTTP_REFERER'])) {
				$referer = $this->request->server['HTTP_REFERER'];
			} else {
				$referer = '';
			}

			$this->model_tool_online->addOnline(oc_get_ip(), $this->customer->getId(), $url, $referer);
		}

		$data['bootstrap'] = 'catalog/view/javascript/bootstrap/js/bootstrap.bundle.min.js';
		$data['scripts'] = $this->document->getScripts('footer');
		$data['cookie'] = $this->load->controller('common/cookie');

		return $this->load->view('common/footer', $data);
	}
}
