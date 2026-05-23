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
		// Note: there is no separate language file for manline footer in this repo; texts are set below.

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
		// For footer legal links we reuse labels from SimpleCheckout language pack
		$this->load->language('checkout/simplecheckout');

		$results = $this->model_catalog_information->getInformations();

		foreach ($results as $result) {
			$data['informations'][] = ['href' => $this->url->link('information/information', 'language=' . $this->config->get('config_language') . '&information_id=' . $result['information_id'])] + $result;
		}

		// --- Manline footer: legal links (Rozetka-like block at the very bottom) ---
		$all_informations = $results;

		$titleMatches = static function (string $title, string $type): bool {
			$title = trim($title);
			if ($title === '') return false;
			$norm = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);

			if ($type === 'checkout') {
				return strpos($norm, 'оферт') !== false
					|| strpos($norm, 'договор') !== false
					|| strpos($norm, 'догов') !== false
					|| strpos($norm, 'offer') !== false;
			}
			if ($type === 'gdpr') {
				return strpos($norm, 'конфиденц') !== false
					|| strpos($norm, 'конфіденц') !== false
					|| strpos($norm, 'privacy') !== false
					|| strpos($norm, 'персональн') !== false;
			}
			if ($type === 'returns') {
				return strpos($norm, 'обмен') !== false
					|| strpos($norm, 'возврат') !== false
					|| strpos($norm, 'повернен') !== false
					|| strpos($norm, 'обмін') !== false
					|| strpos($norm, 'return') !== false;
			}
			return false;
		};

		$resolveInfoId = function (string $type) use ($all_informations, $titleMatches): int {
			$configured_id = 0;
			if ($type === 'checkout') {
				$configured_id = (int)$this->config->get('config_checkout_id');
			} elseif ($type === 'gdpr') {
				$configured_id = (int)$this->config->get('config_gdpr_id');
			} elseif ($type === 'returns') {
				$configured_id = (int)$this->config->get('config_return_id');
			}

			if ($configured_id > 0) {
				$info = $this->model_catalog_information->getInformation($configured_id);
				if ($info) return $configured_id;
			}

			foreach ($all_informations as $info) {
				$iid = (int)($info['information_id'] ?? 0);
				if ($iid <= 0) continue;
				$t = (string)($info['title'] ?? '');
				if ($titleMatches($t, $type)) return $iid;
			}

			return 0;
		};

		$legal = [];
		$offer_id = $resolveInfoId('checkout');
		$privacy_id = $resolveInfoId('gdpr');
		$returns_id = $resolveInfoId('returns');

		$offer_label = trim((string)$this->language->get('text_agree_offer')) ?: 'Публичный договор (оферта)';
		$privacy_label = trim((string)$this->language->get('text_agree_privacy')) ?: 'Политика конфиденциальности';
		$returns_label = trim((string)$this->language->get('text_agree_returns')) ?: 'Обмен и возврат';

		if ($offer_id > 0) {
			$legal[] = [
				'key' => 'offer',
				'label' => $offer_label,
				'href' => $this->url->link('information/information', 'language=' . $this->config->get('config_language') . '&information_id=' . $offer_id)
			];
		}
		if ($privacy_id > 0) {
			$legal[] = [
				'key' => 'privacy',
				'label' => $privacy_label,
				'href' => $this->url->link('information/information', 'language=' . $this->config->get('config_language') . '&information_id=' . $privacy_id)
			];
		}
		if ($returns_id > 0) {
			$legal[] = [
				'key' => 'returns',
				'label' => $returns_label,
				'href' => $this->url->link('information/information', 'language=' . $this->config->get('config_language') . '&information_id=' . $returns_id)
			];
		}

		if (!$returns_id) {
			$legal[] = [
				'key' => 'returns',
				'label' => $returns_label,
				'href' => rtrim((string)$this->config->get('config_url'), '/') . '/obmenvozvrat?language=' . $this->config->get('config_language')
			];
		}

		$data['legal_links'] = $legal;

		$data['contact'] = $this->url->link('information/contact', 'language=' . $this->config->get('config_language'));
		$data['return'] = $this->url->link('account/returns.add', 'language=' . $this->config->get('config_language'));

		if ($this->config->get('config_gdpr_id')) {
			$data['gdpr'] = $this->url->link('information/gdpr', 'language=' . $this->config->get('config_language'));
		} else {
			$data['gdpr'] = '';
		}

		$data['sitemap'] = $this->url->link('information/sitemap', 'language=' . $this->config->get('config_language'));
		$data['manufacturer'] = $this->url->link('product/manufacturer', 'language=' . $this->config->get('config_language'));

		// --- Manline footer: contact info block (requested custom text) ---
		$lang = (string)$this->config->get('config_language');
		if ($lang === 'uk-ua') {
			$data['text_contact_info'] = 'Контактна інформація';
			$data['text_contact_address'] = "ФОП Науменко П. П.<br>вул. Володимирська, 18";
		} else {
			$data['text_contact_info'] = 'Контактная информация';
			$data['text_contact_address'] = "ФОП Науменко П. П.<br>ул. Владимирская, 18";
		}

		// Manline theme: simple UA/RU switch.
		// Requirement: use short prefixes /ua and /ru (no ?language=...)
		$build_lang_switch = function (string $lang_code): string {
			$uri = (string)($this->request->server['REQUEST_URI'] ?? '/');
			$info = parse_url($uri);
			$path = $info['path'] ?? '/';
			if ($path === '') $path = '/';
			if ($path[0] !== '/') $path = '/' . $path;

			// Strip existing /ua or /ru prefix
			$path2 = preg_replace('#^/(ua|ru)(/|$)#', '/', $path);
			$path2 = $path2 ?: '/';
			$path2 = '/' . ltrim($path2, '/');
			if ($path2 !== '/' && str_ends_with($path2, '/')) {
				$path2 = rtrim($path2, '/');
			}

			$prefix = ($lang_code === 'uk-ua') ? 'ua' : 'ru';
			$base = '/' . $prefix;
			return $base . ($path2 === '/' ? '' : $path2);
		};

		$data['lang_switch_ru'] = $build_lang_switch('ru-ru');
		$data['lang_switch_ua'] = $build_lang_switch('uk-ua');

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

		// SEOShield footer block (ТОП категории / города / теги / товары / предложения)
		//
		// Legacy parity: never displayed on Ukrainian pages — see
		// legacy seoshield-client/core/modules/footers_block.php
		// ::is_valid_url() returning false for any REQUEST_URI matching
		// '/ua/'. We can't use the URI shape here because the OC4 home
		// page on UA can be served from any of `/ua/`, `/ua` (no
		// trailing slash), or even `/` (when the language is resolved
		// from cookie/session). The URI-only check missed every shape
		// except the explicit subfolder, so the block kept rendering on
		// the homepage. Gating on the active config_language is the
		// canonical way the rest of this codebase makes language
		// decisions (product.php:314, home.php:27, …).
		$data['seoshield_footer_html'] = '';
		$req_uri = (string)($this->request->server['REQUEST_URI'] ?? '/');
		$lookup_seoshield = ((string)$this->config->get('config_language') !== 'uk-ua');

		try {
			if ($lookup_seoshield) {
			$info = parse_url($req_uri);
			$path = (string)($info['path'] ?? '/');
			if ($path === '') $path = '/';
			if ($path[0] !== '/') $path = '/' . $path;

			// SEOShield cache keys use //host/path (no scheme)
			$http_host = (string)($this->request->server['HTTP_HOST'] ?? 'manline.com.ua');
			$cache_host = $http_host;

			$try_hosts = array_values(array_unique([
				$cache_host,
				'manline.com.ua'
			]));

			$try_paths = array_values(array_unique([
				$path,
				rtrim($path, '/'),
				rtrim($path, '/') . '/',
				'/'
			]));

			$cache_root = rtrim(DIR_OPENCART, '/\\') . '/seoshield-client/data/footers_cache';

			foreach ($try_hosts as $h) {
				foreach ($try_paths as $p) {
					if ($p === '') $p = '/';
					$key = '//' . $h . $p;

					$hash = md5($key);
					$dir = substr($hash, -2);
					$file = $cache_root . '/' . $dir . '/' . $hash . '.cache.php';

					if (is_file($file)) {
						$map = include $file;
						if (is_array($map) && !empty($map[$key])) {
							$data['seoshield_footer_html'] = (string)$map[$key];
							break 2;
						}
					}
				}
			}

			// Fallback: some cache dumps may not contain homepage ("//manline.com.ua/") entry.
			// In that case, render any available footers_block so layout is 1:1.
			if (!$data['seoshield_footer_html']) {
				$files = glob($cache_root . '/*/*.cache.php');
				if ($files) {
					$map = include $files[0];
					if (is_array($map) && $map) {
						$first = reset($map);
						if (is_string($first) && $first !== '') {
							$data['seoshield_footer_html'] = $first;
						}
					}
				}
			}
			} // end if ($lookup_seoshield)
		} catch (\Throwable $e) {
			// ignore
		}

		$data['bootstrap'] = 'catalog/view/javascript/bootstrap/js/bootstrap.bundle.min.js';
		$data['scripts'] = $this->document->getScripts('footer');
		$data['cookie'] = $this->load->controller('common/cookie');

		return $this->load->view('common/footer', $data);
	}
}
