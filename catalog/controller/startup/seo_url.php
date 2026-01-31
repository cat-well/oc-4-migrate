<?php
namespace Opencart\Catalog\Controller\Startup;
/**
 * Class SeoUrl
 *
 * @package Opencart\Catalog\Controller\Startup
 */
class SeoUrl extends \Opencart\System\Engine\Controller {
	/**
	 * @var array<string, string>
	 */
	private array $data = [];

	/**
	 * Index
	 *
	 * @return null
	 */
	public function index() {
		// Add rewrite to URL class
		if ($this->config->get('config_seo_url')) {
			$this->url->addRewrite($this);

			$this->load->model('design/seo_url');

			// Decode URL
			if (isset($this->request->get['_route_'])) {
				$parts = explode('/', $this->request->get['_route_']);

				// remove any empty arrays from trailing
				if (oc_strlen(end($parts)) == 0) {
					array_pop($parts);
				}

				foreach ($parts as $key => $value) {
					$seo_url_info = $this->model_design_seo_url->getSeoUrlByKeyword($value);

					if ($seo_url_info) {
						$this->request->get[$seo_url_info['key']] = html_entity_decode($seo_url_info['value'], ENT_QUOTES, 'UTF-8');

						// OC4 category controller expects `path` (not category_id). If legacy SEO gives us category_id,
						// map it to path when missing.
						if ($seo_url_info['key'] === 'category_id' && !isset($this->request->get['path'])) {
							$this->request->get['path'] = (string)$this->request->get['category_id'];
						}

						// Infer route from entity key when route is not explicitly set
						if (!isset($this->request->get['route'])) {
							switch ($seo_url_info['key']) {
								case 'product_id':
									$this->request->get['route'] = 'product/product';
									break;
								case 'path':
								case 'category_id':
									$this->request->get['route'] = 'product/category';
									break;
								case 'manufacturer_id':
									$this->request->get['route'] = 'product/manufacturer.info';
									break;
								case 'information_id':
									$this->request->get['route'] = 'information/information';
									break;
							}
						}

						unset($parts[$key]);
					}
				}

				if (!isset($this->request->get['route'])) {
					$this->request->get['route'] = $this->config->get('action_default');
				}

				if ($parts) {
					$this->request->get['route'] = $this->config->get('action_error');
				}
			}
		}

		return null;
	}

	/**
	 * Rewrite
	 *
	 * @param string $link
	 *
	 * @return string
	 */
	public function rewrite(string $link): string {
		$url_info = parse_url(str_replace('&amp;', '&', $link));

		// Build the url
		$url = '';

		if (isset($url_info['scheme']) && $url_info['scheme']) {
			$url .= $url_info['scheme'];
		}

		$url .= '://';

		if (isset($url_info['host']) && $url_info['host']) {
			$url .= $url_info['host'];
		}

		if (isset($url_info['port'])) {
			$url .= ':' . $url_info['port'];
		}

		parse_str($url_info['query'], $query);

		$language_id = $this->config->get('config_language_id');

		// Home route cleanup (common/home should be the base URL)
		$is_home = isset($query['route']) && $query['route'] === 'common/home';

		// Start changing the URL query into a path
		$paths = [];

		// Track entity flags so we can drop redundant query params like route/path
		$has_product = false;
		$has_category = false;
		$has_manufacturer = false;
		$has_information = false;

		// Parse the query into its separate parts
		$parts = explode('&', $url_info['query']);

		// First pass: detect which entity type this URL is for (order independent)
		foreach ($parts as $part) {
			$pair = explode('=', $part, 2);

			$key = $pair[0] ?? '';
			$value = $pair[1] ?? '';

			if ($key === 'product_id' && $value !== '') {
				$has_product = true;
			} elseif (($key === 'path' || $key === 'category_id') && $value !== '') {
				$has_category = true;
			} elseif ($key === 'manufacturer_id' && $value !== '') {
				$has_manufacturer = true;
			} elseif ($key === 'information_id' && $value !== '') {
				$has_information = true;
			}
		}

		// Second pass: build SEO path
		foreach ($parts as $part) {
			$pair = explode('=', $part, 2);

			$key = (string)($pair[0] ?? '');
			$value = (string)($pair[1] ?? '');

			// Prevent nested URLs like /product/category by skipping category context on product pages
			if ($has_product && ($key === 'path' || $key === 'category_id')) {
				unset($query[$key]);
				continue;
			}

			$index = $key . '=' . $value;

			if (!isset($this->data[$language_id][$index])) {
				$this->data[$language_id][$index] = $this->model_design_seo_url->getSeoUrlByKeyValue((string)$key, (string)$value);
			}

			if ($this->data[$language_id][$index]) {
				$paths[] = $this->data[$language_id][$index];

				unset($query[$key]);
			}
		}

		$sort_order = [];

		foreach ($paths as $key => $value) {
			$sort_order[$key] = $value['sort_order'];
		}

		array_multisort($sort_order, SORT_ASC, $paths);

		// Drop redundant query params for cleaner URLs
		// - route is already implied by the entity keyword we add
		// - path is not needed on product pages (it only provides breadcrumb context)
		if ($has_product) {
			unset($query['route'], $query['path']);
		}

		// Manufacturer pages: /brand should not include route
		if ($has_manufacturer) {
			unset($query['route']);
		}

		// Category pages: /category should not include route or path
		if ($has_category) {
			unset($query['route'], $query['path']);
		}

		// Information pages: /info-page should not include route
		if ($has_information) {
			unset($query['route']);
		}

		// Home: / should not include route=common/home
		if ($is_home) {
			unset($query['route']);
		}

		// Build the path
		$url .= str_replace('/index.php', '', $url_info['path']);

		foreach ($paths as $result) {
			$url .= '/' . $result['keyword'];
		}

		// Rebuild the URL query
		if ($query) {
			$url .= '?' . str_replace(['%2F'], ['/'], http_build_query($query));
		}

		return $url;
	}
}
