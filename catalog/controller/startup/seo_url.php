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
				$route_string = (string)$this->request->get['_route_'];

				// Language short prefixes: /ua/... and /ru/...
				// Normalize them into ?language=uk-ua / ru-ru so the standard language controller can handle it.
				$route_string = ltrim($route_string, '/');
				if ($route_string === 'ua' || str_starts_with($route_string, 'ua/')) {
					$this->request->get['language'] = 'uk-ua';
					$route_string = substr($route_string, 2);
					$route_string = ltrim($route_string, '/');
				} elseif ($route_string === 'ru' || str_starts_with($route_string, 'ru/')) {
					$this->request->get['language'] = 'ru-ru';
					$route_string = substr($route_string, 2);
					$route_string = ltrim($route_string, '/');
				}

				// Legacy FilterPro selection URLs look like: /category-slug/f/stil_plavki-boksery
				// Split /f/... suffix and parse it after normal SEO decode.
				$filterpro_parts = [];
				if (strpos($route_string, '/f/') !== false) {
					[$base, $fpart] = explode('/f/', $route_string, 2);
					$route_string = $base;
					$filterpro_parts = array_values(array_filter(explode('/', $fpart), fn($v) => $v !== ''));
				}

				$parts = explode('/', $route_string);

				// remove any empty arrays from trailing
				if (oc_strlen(end($parts)) == 0) {
					array_pop($parts);
				}

				foreach ($parts as $key => $value) {
					// Legacy OC2 SEOPro-style URLs often end with .html
					$lookup = (string)$value;
					if (str_ends_with($lookup, '.html')) {
						$lookup = substr($lookup, 0, -5);
					}

					// Prefer FilterPro SEO landing slugs (route=filter_id=NNN) only for *non-first* segments,
					// and only when we already have a category context. This avoids breaking plain category URLs
					// if a filter landing slug happens to equal a category slug (keyword collision).
					$seo_url_info = [];
					if ($key > 0 && isset($this->request->get['path']) && $this->request->get['path'] !== '') {
						$pref = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE `keyword` = '" . $this->db->escape($lookup) . "' AND `store_id` = '" . (int)$this->config->get('config_store_id') . "' AND `key` = 'route' AND `value` LIKE 'filter_id=%' ORDER BY sort_order DESC, seo_url_id ASC LIMIT 1");
						if ($pref->num_rows) {
							$seo_url_info = $pref->row;
						}
					}

					if (!$seo_url_info) {
						$seo_url_info = $this->model_design_seo_url->getSeoUrlByKeyword($lookup);
					}

					if ($seo_url_info) {
						$this->request->get[$seo_url_info['key']] = html_entity_decode($seo_url_info['value'], ENT_QUOTES, 'UTF-8');

						// OC4 category controller expects `path` (parent_child chain).
						// If SEO table gives us category_id for multiple segments, build the chain.
						if ($seo_url_info['key'] === 'category_id') {
							$cid = (string)$this->request->get['category_id'];
							if (isset($this->request->get['path']) && $this->request->get['path'] !== '') {
								$this->request->get['path'] = (string)$this->request->get['path'] . '_' . $cid;
							} else {
								$this->request->get['path'] = $cid;
							}
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

				// Parse legacy FilterPro /f/... filters (uses slugs from custom columns)
				if ($filterpro_parts) {
					// Determine language_id for slug lookups.
					$lang_id = (int)$this->config->get('config_language_id');
					if (isset($this->request->get['language']) && is_string($this->request->get['language']) && $this->request->get['language'] !== '') {
						$code = $this->db->escape($this->request->get['language']);
						$lang_q = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE code='" . $code . "' LIMIT 1");
						if ($lang_q->num_rows) {
							$lang_id = (int)$lang_q->row['language_id'];
						}
					}

					foreach ($filterpro_parts as $seg) {
						$seg = trim((string)$seg);
						if ($seg === '' || strpos($seg, '_') === false) continue;

						[$k, $raw] = explode('_', $seg, 2);
						$k = trim($k);
						$raw = trim($raw);
						if ($k === '' || $raw === '') continue;

						$vals = array_values(array_filter(explode(',', $raw), fn($v) => trim($v) !== ''));
						$vals = array_map(function ($v) {
							$v = trim((string)$v);
							// Legacy links sometimes have trailing dashes
							return rtrim($v, '-');
						}, $vals);
						$vals = array_values(array_filter($vals, fn($v) => $v !== ''));
						if (!$vals) continue;

						// Manufacturer uses numeric ids
						if ($k === 'manufacturer') {
							$this->request->get['manufacturer'] = $this->request->get['manufacturer'] ?? [];
							if (!is_array($this->request->get['manufacturer'])) {
								$this->request->get['manufacturer'] = [$this->request->get['manufacturer']];
							}
							foreach ($vals as $v) {
								$id = (int)$v;
								if ($id > 0) $this->request->get['manufacturer'][] = $id;
							}
							continue;
						}

						// Standard filters (checkboxes)
						if ($k === 'stock') {
							$this->request->get['stock'] = 1;
							continue;
						}

						if ($k === 'special') {
							$this->request->get['special'] = 1;
							continue;
						}

						if ($k === 'new') {
							$this->request->get['new'] = 1;
							continue;
						}

						// Try OPTION by slug: option_description.slug == $k
						$option_q = $this->db->query("SELECT option_id FROM `" . DB_PREFIX . "option_description` WHERE slug='" . $this->db->escape($k) . "' ORDER BY language_id='" . $lang_id . "' DESC LIMIT 1");
						if ($option_q->num_rows) {
							$option_id = (int)$option_q->row['option_id'];
							foreach ($vals as $vslug) {
								$ov_q = $this->db->query("SELECT option_value_id FROM `" . DB_PREFIX . "option_value_description` WHERE option_id='" . $option_id . "' AND slug='" . $this->db->escape($vslug) . "' ORDER BY language_id='" . $lang_id . "' DESC LIMIT 1");
								if ($ov_q->num_rows) {
									$this->request->get['option_value'] = $this->request->get['option_value'] ?? [];
									if (!isset($this->request->get['option_value'][$option_id]) || !is_array($this->request->get['option_value'][$option_id])) {
										$this->request->get['option_value'][$option_id] = [];
									}
									$this->request->get['option_value'][$option_id][] = (int)$ov_q->row['option_value_id'];
								}
							}
							continue;
						}

						// Try ATTRIBUTE by slug: attribute_description.slug == $k
						$attr_q = $this->db->query("SELECT attribute_id FROM `" . DB_PREFIX . "attribute_description` WHERE slug='" . $this->db->escape($k) . "' ORDER BY language_id='" . $lang_id . "' DESC LIMIT 1");
						if ($attr_q->num_rows) {
							$attribute_id = (int)$attr_q->row['attribute_id'];
							foreach ($vals as $vslug) {
								// Always store slugs for reliable filtering across languages
								$this->request->get['attribute_slug'] = $this->request->get['attribute_slug'] ?? [];
								if (!isset($this->request->get['attribute_slug'][$attribute_id]) || !is_array($this->request->get['attribute_slug'][$attribute_id])) {
									$this->request->get['attribute_slug'][$attribute_id] = [];
								}
								$this->request->get['attribute_slug'][$attribute_id][] = (string)$vslug;

								// Also keep legacy text (best-effort) for UI
								$text_q = $this->db->query("SELECT text FROM `" . DB_PREFIX . "product_attribute` WHERE attribute_id='" . $attribute_id . "' AND language_id='" . $lang_id . "' AND slug='" . $this->db->escape($vslug) . "' LIMIT 1");
								if (!$text_q->num_rows) {
									$text_q = $this->db->query("SELECT text FROM `" . DB_PREFIX . "product_attribute` WHERE attribute_id='" . $attribute_id . "' AND slug='" . $this->db->escape($vslug) . "' LIMIT 1");
								}
								if ($text_q->num_rows) {
									$this->request->get['attribute_value'] = $this->request->get['attribute_value'] ?? [];
									if (!isset($this->request->get['attribute_value'][$attribute_id]) || !is_array($this->request->get['attribute_value'][$attribute_id])) {
										$this->request->get['attribute_value'][$attribute_id] = [];
									}
									$this->request->get['attribute_value'][$attribute_id][] = (string)$text_q->row['text'];
								}
							}
							continue;
						}
					}
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

		// Language short prefixes: render as /ua and /ru instead of ?language=
		$lang_prefix = '';
		if (isset($query['language']) && is_string($query['language'])) {
			$lc = (string)$query['language'];
			if ($lc === 'uk-ua' || $lc === 'ua') {
				$lang_prefix = 'ua';
				unset($query['language']);
			} elseif ($lc === 'ru-ru' || $lc === 'ru') {
				$lang_prefix = 'ru';
				unset($query['language']);
			}
		}

		// Build the path
		$url .= str_replace('/index.php', '', $url_info['path']);

		if ($lang_prefix !== '') {
			$url .= '/' . $lang_prefix;
		}

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
