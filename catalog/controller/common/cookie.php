<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class Cookie
 *
 * Can be called from $this->load->controller('common/cookie');
 *
 * @package Opencart\Catalog\Controller\Common
 */
class Cookie extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		if ($this->config->get('config_cookie_id') && !isset($this->request->cookie['policy'])) {
			// Information
			$this->load->model('catalog/information');

			$information_info = $this->model_catalog_information->getInformation((int)$this->config->get('config_cookie_id'));

			if ($information_info) {
				$this->load->language('common/cookie');

					$text_cookie = (string)$this->language->get('text_cookie');
					$cookie_link = $this->url->link('information/information', 'language=' . $this->config->get('config_language') . '&information_id=' . $information_info['information_id']);

					if (strpos($text_cookie, '%s') !== false) {
						$data['text_cookie'] = sprintf($text_cookie, $cookie_link);
					} else {
						$data['text_cookie'] = $text_cookie;
					}

				$data['agree'] = $this->url->link('common/cookie.confirm', 'language=' . $this->config->get('config_language') . '&agree=1');
				$data['disagree'] = $this->url->link('common/cookie.confirm', 'language=' . $this->config->get('config_language') . '&agree=0');

				return $this->load->view('common/cookie', $data);
			}
		}

		return '';
	}

	/**
	 * Confirm
	 *
	 * @return void
	 */
	public function confirm(): void {
		$json = [];

		if (isset($this->request->get['agree'])) {
			$agree = $this->request->get['agree'];
		} else {
			$agree = '0';
		}

		if ($this->config->get('config_cookie_id') && !isset($this->request->cookie['policy'])) {
			$this->load->language('common/cookie');

			// Cookie consent must persist across the whole storefront.
			// Using session_path here can accidentally scope the cookie to a sub-path and make the banner reappear on other pages.
			$cookie_path = (string)($this->config->get('session_path') ?? '');
			if ($cookie_path === '' || $cookie_path[0] !== '/') {
				$cookie_path = '/';
			}

			$option = [
				'expires'  => time() + 60 * 60 * 24 * 365,
				'path'     => $cookie_path,
				'secure'   => !empty($this->request->server['HTTPS']),
				'SameSite' => $this->config->get('config_session_samesite')
			];

			setcookie('policy', $agree, $option);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
