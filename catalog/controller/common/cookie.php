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

				// IMPORTANT: this endpoint must bypass SEO URL rewriting; otherwise it can resolve to `/ua?...` and get cached (304),
				// preventing Set-Cookie from ever reaching the browser.
				$base = defined('HTTPS_SERVER') ? (string)HTTPS_SERVER : (string)$this->config->get('config_url');
				$base = rtrim($base, '/') . '/';
				$lang = (string)$this->config->get('config_language');

				// Add a cache-buster because some setups (e.g. Cloudflare Cache Everything) may cache this JSON endpoint and strip Set-Cookie.
				$cb = (string)time();

				$data['agree'] = $base . 'index.php?route=common/cookie.confirm&language=' . rawurlencode($lang) . '&agree=1&_cb=' . $cb;
				$data['disagree'] = $base . 'index.php?route=common/cookie.confirm&language=' . rawurlencode($lang) . '&agree=0&_cb=' . $cb;

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

			// Consent must persist across language paths.
			// Use site-wide path and keep domain default unless we explicitly need to share between www/non-www.
			$cookie_path = '/';

			$option = [
				'expires'  => time() + 60 * 60 * 24 * 365,
				'path'     => $cookie_path,
				'secure'   => !empty($this->request->server['HTTPS']),
				'httponly' => false,
				// PHP expects 'samesite' (lowercase) in the options array.
				'samesite' => $this->config->get('config_session_samesite')
			];

			// If host is www.*, set cookie domain to the apex so it survives host normalization.
			$host = (string)($this->request->server['HTTP_HOST'] ?? '');
			$host = preg_replace('~:\\d+$~', '', $host);
			if ($host && str_starts_with($host, 'www.')) {
				$option['domain'] = '.' . substr($host, 4);
			}

			// setcookie() can fail silently if headers are considered already sent in some hosting/CDN setups.
			// Use the OpenCart response header pipeline so headers are emitted together.
			$ttl = 60 * 60 * 24 * 365;
			$expires = gmdate('D, d M Y H:i:s \G\M\T', time() + $ttl);
			$cookie = 'policy=' . rawurlencode((string)$agree) . '; Expires=' . $expires . '; Max-Age=' . $ttl . '; Path=' . $option['path'];

			if (!empty($option['domain'])) {
				$cookie .= '; Domain=' . $option['domain'];
			}

			if (!empty($option['secure'])) {
				$cookie .= '; Secure';
			}

			if (!empty($option['samesite'])) {
				$cookie .= '; SameSite=' . $option['samesite'];
			}

			$this->response->addHeader('Set-Cookie: ' . $cookie);

			$json['success'] = $this->language->get('text_success');
		}

		// Do not allow caches/CDNs to store this response (it carries Set-Cookie).
		$this->response->addHeader('Content-Type: application/json');
		$this->response->addHeader('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		$this->response->addHeader('Pragma: no-cache');
		$this->response->addHeader('Expires: 0');
		$this->response->setOutput(json_encode($json));
	}
}
