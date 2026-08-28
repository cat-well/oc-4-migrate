<?php
namespace Opencart\Catalog\Controller\Product;
/**
 * Class Product
 *
 * @package Opencart\Catalog\Controller\Product
 */
class Product extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return ?\Opencart\System\Engine\Action
	 */
	public function index(): ?\Opencart\System\Engine\Action {
		$this->load->language('product/product');

		if (isset($this->request->get['product_id'])) {
			$product_id = (int)$this->request->get['product_id'];
		} else {
			$product_id = 0;
		}

		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if ($product_info) {
			$this->document->setTitle($product_info['meta_title']);
			$this->document->setDescription($product_info['meta_description']);
			$this->document->setKeywords($product_info['meta_keyword']);
			$this->document->addLink($this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product_id), 'canonical');

			$data['breadcrumbs'] = [];

			$data['breadcrumbs'][] = [
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
			];

			// Category
			$this->load->model('catalog/category');

			if (isset($this->request->get['path'])) {
				$path = '';

				$parts = explode('_', (string)$this->request->get['path']);

				$category_id = (int)array_pop($parts);

				foreach ($parts as $path_id) {
					if (!$path) {
						$path = $path_id;
					} else {
						$path .= '_' . $path_id;
					}

					$category_info = $this->model_catalog_category->getCategory((int)$path_id);

					if ($category_info) {
						$data['breadcrumbs'][] = [
							'text' => $category_info['name'],
							'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $path)
						];
					}
				}

				// Set the last category breadcrumb
				$category_info = $this->model_catalog_category->getCategory($category_id);

				if ($category_info) {
					$url = '';

					if (isset($this->request->get['sort'])) {
						$url .= '&sort=' . $this->request->get['sort'];
					}

					if (isset($this->request->get['order'])) {
						$url .= '&order=' . $this->request->get['order'];
					}

					if (isset($this->request->get['page'])) {
						$url .= '&page=' . $this->request->get['page'];
					}

					if (isset($this->request->get['limit'])) {
						$url .= '&limit=' . $this->request->get['limit'];
					}

					$data['breadcrumbs'][] = [
						'text' => $category_info['name'],
						'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $this->request->get['path'] . $url)
					];
				}
			}

			// Manufacturer
			$this->load->model('catalog/manufacturer');

			if (isset($this->request->get['manufacturer_id'])) {
				$data['breadcrumbs'][] = [
					'text' => $this->language->get('text_brand'),
					'href' => $this->url->link('product/manufacturer', 'language=' . $this->config->get('config_language'))
				];

				$url = '';

				if (isset($this->request->get['sort'])) {
					$url .= '&sort=' . $this->request->get['sort'];
				}

				if (isset($this->request->get['order'])) {
					$url .= '&order=' . $this->request->get['order'];
				}

				if (isset($this->request->get['page'])) {
					$url .= '&page=' . $this->request->get['page'];
				}

				if (isset($this->request->get['limit'])) {
					$url .= '&limit=' . $this->request->get['limit'];
				}

				$manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($this->request->get['manufacturer_id']);

				if ($manufacturer_info) {
					$data['breadcrumbs'][] = [
						'text' => $manufacturer_info['name'],
						'href' => $this->url->link('product/manufacturer.info', 'language=' . $this->config->get('config_language') . '&manufacturer_id=' . $this->request->get['manufacturer_id'] . $url)
					];
				}
			}

			if (isset($this->request->get['search']) || isset($this->request->get['tag'])) {
				$url = '';

				if (isset($this->request->get['search'])) {
					$url .= '&search=' . $this->request->get['search'];
				}

				if (isset($this->request->get['tag'])) {
					$url .= '&tag=' . $this->request->get['tag'];
				}

				if (isset($this->request->get['description'])) {
					$url .= '&description=' . $this->request->get['description'];
				}

				if (isset($this->request->get['category_id'])) {
					$url .= '&category_id=' . $this->request->get['category_id'];
				}

				if (isset($this->request->get['sub_category'])) {
					$url .= '&sub_category=' . $this->request->get['sub_category'];
				}

				if (isset($this->request->get['sort'])) {
					$url .= '&sort=' . $this->request->get['sort'];
				}

				if (isset($this->request->get['order'])) {
					$url .= '&order=' . $this->request->get['order'];
				}

				if (isset($this->request->get['page'])) {
					$url .= '&page=' . $this->request->get['page'];
				}

				if (isset($this->request->get['limit'])) {
					$url .= '&limit=' . $this->request->get['limit'];
				}

				$data['breadcrumbs'][] = [
					'text' => $this->language->get('text_search'),
					'href' => $this->url->link('product/search', 'language=' . $this->config->get('config_language') . $url)
				];
			}

			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['manufacturer_id'])) {
				$url .= '&manufacturer_id=' . $this->request->get['manufacturer_id'];
			}

			if (isset($this->request->get['search'])) {
				$url .= '&search=' . $this->request->get['search'];
			}

			if (isset($this->request->get['tag'])) {
				$url .= '&tag=' . $this->request->get['tag'];
			}

			if (isset($this->request->get['description'])) {
				$url .= '&description=' . $this->request->get['description'];
			}

			if (isset($this->request->get['category_id'])) {
				$url .= '&category_id=' . $this->request->get['category_id'];
			}

			if (isset($this->request->get['sub_category'])) {
				$url .= '&sub_category=' . $this->request->get['sub_category'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['breadcrumbs'][] = [
				'text' => $product_info['name'],
				'href' => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . $url . '&product_id=' . $product_id)
			];

			$this->document->setTitle($product_info['meta_title']);
			$this->document->setDescription($product_info['meta_description']);
			$this->document->setKeywords($product_info['meta_keyword']);
			$this->document->addLink($this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product_id), 'canonical');

			$this->document->addScript('catalog/view/javascript/jquery/magnific/jquery.magnific-popup.min.js');
			$this->document->addStyle('catalog/view/javascript/jquery/magnific/magnific-popup.css');

			$data['heading_title'] = $product_info['name'];

			$data['text_minimum'] = sprintf($this->language->get('text_minimum'), $product_info['minimum']);
			$data['text_login'] = sprintf($this->language->get('text_login'), $this->url->link('account/login', 'language=' . $this->config->get('config_language')), $this->url->link('account/register', 'language=' . $this->config->get('config_language')));
			$data['text_reviews'] = sprintf($this->language->get('text_reviews'), (int)$product_info['reviews']);

			$data['tab_review'] = sprintf($this->language->get('tab_review'), $product_info['reviews']);

			$data['error_upload_size'] = sprintf($this->language->get('error_upload_size'), $this->config->get('config_file_max_size'));

			$data['config_file_max_size'] = ((int)$this->config->get('config_file_max_size') * 1024 * 1024);

			$this->session->data['upload_token'] = oc_token(32);

			$data['upload'] = $this->url->link('tool/upload', 'language=' . $this->config->get('config_language') . '&upload_token=' . $this->session->data['upload_token']);

			$data['product_id'] = $product_id;

			$manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($product_info['manufacturer_id']);

			if ($manufacturer_info) {
				$data['manufacturer'] = $manufacturer_info['name'];
			} else {
				$data['manufacturer'] = '';
			}

			$data['manufacturers'] = $this->url->link('product/manufacturer.info', 'language=' . $this->config->get('config_language') . '&manufacturer_id=' . $product_info['manufacturer_id']);
			$data['model'] = $product_info['model'];

			$data['product_codes'] = [];

			$results = $this->model_catalog_product->getCodes($product_id);

			foreach ($results as $result) {
				if ($result['status']) {
					$data['product_codes'][] = $result;
				}
			}

			$data['reward'] = $product_info['reward'];
			$data['points'] = $product_info['points'];
			$data['description'] = html_entity_decode($product_info['description'], ENT_QUOTES, 'UTF-8');
			// Manline template expects numeric quantity for availability/Buy button.
			$data['quantity'] = (int)$product_info['quantity'];

			// Stock Status
			if ($product_info['quantity'] <= 0) {
				$stock_status_id = $product_info['stock_status_id'];
			} elseif (!$this->config->get('config_stock_display')) {
				$stock_status_id = (int)$this->config->get('config_stock_status_id');
			} else {
				$stock_status_id = 0;
			}

			// Stock Status
			$this->load->model('localisation/stock_status');

			$stock_status_info = $this->model_localisation_stock_status->getStockStatus($stock_status_id);

			if ($stock_status_info) {
				$data['stock'] = $stock_status_info['name'];
			} else {
				$data['stock'] = $product_info['quantity'];
			}

			$data['rating'] = (int)$product_info['rating'];
			$data['review_status'] = (int)$this->config->get('config_review_status');
			$data['review'] = $this->load->controller('product/review');

			$data['wishlist_add'] = $this->url->link('account/wishlist.add', 'language=' . $this->config->get('config_language'));
			$data['compare_add'] = $this->url->link('product/compare.add', 'language=' . $this->config->get('config_language'));

			// Manline: helpers used by OC2-like product template
			$data['lang'] = (string)$this->config->get('config_language');
			$data['current_url'] = $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product_id);
			$data['is_new'] = !empty($product_info['date_added']) ? ((time() - strtotime((string)$product_info['date_added'])) / (60 * 60 * 24)) <= 60 : false;
			$data['dost_oplata_info_url'] = $this->url->link('information/information', 'language=' . $this->config->get('config_language') . '&information_id=10');
			$data['set_block'] = '';
			$data['colors_cfg'] = [];
			$data['colors'] = [];

			// Manline: OC2-like color switcher based on product variants (master_id)
			// NOTE: This is best-effort. We detect the "Color" option by name and build tiles from variants.
			$color_product_option_id = 0;
			$color_value_map = [];
			$color_opt_names = ['цвет', 'колір', 'color'];

			// Manline: Style attribute (attribute_id=23, slug key=stil)
			// 1) Breadcrumb injection (legacy OC2 behavior; keep for RU)
			// 2) Render colon-separated styles as clickable tags linking to /f/ filter
			$data['style_breadcrumb'] = null;
			$data['style_tags'] = [];
			try {
				$this->load->model('catalog/product');

				// Fetch raw style value + stored slug from product_attribute (our migration keeps slug)
				$q = $this->db->query("SELECT `text`, `slug` FROM `" . DB_PREFIX . "product_attribute` WHERE product_id='" . (int)$product_id . "' AND attribute_id='23' AND language_id='" . (int)$this->config->get('config_language_id') . "' LIMIT 1");
				$style_text = $q->num_rows ? trim((string)$q->row['text']) : '';
				$style_slug = $q->num_rows ? trim((string)$q->row['slug']) : '';

				$style_parts = $style_text !== '' ? array_map('trim', explode(':', $style_text)) : [];
				$slug_parts = $style_slug !== '' ? array_map('trim', explode(':', $style_slug)) : [];

				// Build base category URL (best-effort):
				// 1) if coming from category page, OC passes path=...; use it
				// 2) otherwise take one of product categories (lowest sort_order)
				// 3) fallback to legacy /muzhskie-trusy
				$base_cat_href = '';

				$req_path = $this->request->get['path'] ?? '';
				if (is_string($req_path) && $req_path !== '') {
					$base_cat_href = $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $req_path);
				}

				if ($base_cat_href === '') {
					$cat_q = $this->db->query("SELECT c.category_id
						FROM `" . DB_PREFIX . "product_to_category` p2c
						LEFT JOIN `" . DB_PREFIX . "category` c ON (c.category_id = p2c.category_id)
						WHERE p2c.product_id='" . (int)$product_id . "'
						ORDER BY c.sort_order ASC, c.category_id ASC
						LIMIT 1");
					if ($cat_q->num_rows) {
						$cid = (int)$cat_q->row['category_id'];
						$base_cat_href = $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $cid);
					}
				}

				if ($base_cat_href === '' && isset($data['breadcrumbs']) && is_array($data['breadcrumbs']) && count($data['breadcrumbs']) >= 2) {
					// fallback to breadcrumb before product
					$base_cat_href = (string)($data['breadcrumbs'][count($data['breadcrumbs']) - 2]['href'] ?? '');
				}

				// Normalize to path-only (no query/hash)
				$base_path = $base_cat_href;
				$hpos = strpos($base_path, '#');
				if ($hpos !== false) $base_path = substr($base_path, 0, $hpos);
				$qpos = strpos($base_path, '?');
				if ($qpos !== false) $base_path = substr($base_path, 0, $qpos);
				$base_path = rtrim($base_path, '/');
				if ($base_path === '') $base_path = '/muzhskie-trusy';

				$slugify = function(string $s): string {
					$s = oc_strtolower(trim($s));
					$s = preg_replace('/[^a-z0-9\p{Cyrillic}]+/u', '-', $s);
					$s = trim((string)$s, '-');
					// basic translit for UA/RU to ascii-ish slugs (fallback)
					$map = [
						'а'=>'a','б'=>'b','в'=>'v','г'=>'g','ґ'=>'g','д'=>'d','е'=>'e','є'=>'e','ж'=>'zh','з'=>'z','и'=>'y','і'=>'i','ї'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ь'=>'','ы'=>'y','ъ'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
					];
					$s = strtr($s, $map);
					$s = preg_replace('/[^a-z0-9-]+/', '-', $s);
					$s = trim((string)$s, '-');
					return $s;
				};

				foreach ($style_parts as $idx => $part) {
					if ($part === '') continue;
					$sp = $slug_parts[$idx] ?? '';
					if ($sp === '') $sp = $slugify($part);
					if ($sp === '') continue;

					$data['style_tags'][] = [
						'text' => $part,
						'slug' => $sp,
						'href' => $base_path . '/f/stil_' . $sp,
					];
				}

				// Breadcrumb injection uses the first (main) style part and a legacy map (RU only)
				if ($data['lang'] === 'ru-ru' && !empty($style_parts[0])) {
					$style_main = (string)$style_parts[0];
					$map = [
						'Свободные боксеры' => '/muzhskie-trusy/svobodnye-boksery/',
						'Слипы' => '/muzhskie-trusy/slipy/',
						'Трусы боксеры' => '/muzhskie-trusy/trusy-boksery/',
						'Майка' => '/majki-futbolki/mayki/',
						'Футболка' => '/majki-futbolki/futbolki/',
					];
					if ($style_main !== '' && isset($map[$style_main])) {
						$data['style_breadcrumb'] = [
							'text' => $style_main,
							'href' => $map[$style_main],
						];
					}
				}
			} catch (\Throwable $e) {
				// ignore
			}

			// Image
			$this->load->model('tool/image');

			if ($product_info['image'] && is_file(DIR_IMAGE . html_entity_decode($product_info['image'], ENT_QUOTES, 'UTF-8'))) {
				$data['popup'] = $this->model_tool_image->resize($product_info['image'], $this->config->get('config_image_popup_width'), $this->config->get('config_image_popup_height'));
				$data['thumb'] = $this->model_tool_image->resize($product_info['image'], $this->config->get('config_image_thumb_width'), $this->config->get('config_image_thumb_height'));
			} else {
				$data['popup'] = '';
				$data['thumb'] = '';
			}

			$data['images'] = [];

			$results = $this->model_catalog_product->getImages($product_id);

			// OC2-like additional thumb for the first image (used in thumbnail strip)
			$data['thumb_thumb'] = $data['thumb'] ? $this->model_tool_image->resize($product_info['image'], $this->config->get('config_image_additional_width'), $this->config->get('config_image_additional_height')) : '';

			foreach ($results as $result) {
				if ($result['image'] && is_file(DIR_IMAGE . html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'))) {
					$data['images'][] = [
						'popup' => $this->model_tool_image->resize($result['image'], $this->config->get('config_image_popup_width'), $this->config->get('config_image_popup_height')),
						'thumb' => $this->model_tool_image->resize($result['image'], $this->config->get('config_image_additional_width'), $this->config->get('config_image_additional_height')),
						// hover image for lazy loading in main slider (use same as thumb as fallback)
						'hover' => $this->model_tool_image->resize($result['image'], $this->config->get('config_image_thumb_width'), $this->config->get('config_image_thumb_height'))
					];
				}
			}

			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$data['price'] = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$data['price'] = false;
			}

			if ((float)$product_info['special']) {
				$data['special'] = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$data['special'] = false;
			}

			if ($this->config->get('config_tax')) {
				$data['tax'] = $this->currency->format((float)$product_info['special'] ? $product_info['special'] : $product_info['price'], $this->session->data['currency']);
			} else {
				$data['tax'] = false;
			}

			// Product JSON-LD (schema.org) — restores structured data lost in the OC2->OC4 migration.
			$jsonld = [
				'@context' => 'https://schema.org',
				'@type'    => 'Product',
				'name'     => (string)$product_info['name'],
				'url'      => (string)$data['current_url'],
			];
			if (!empty($product_info['model'])) {
				$jsonld['sku'] = (string)$product_info['model'];
			}
			if (!empty($data['popup'])) {
				$jsonld['image'] = [(string)$data['popup']];
			}
			$jsonld_desc = trim(strip_tags(html_entity_decode((string)($product_info['meta_description'] ?: $product_info['description']), ENT_QUOTES, 'UTF-8')));
			if ($jsonld_desc !== '') {
				$jsonld['description'] = function_exists('mb_substr') ? mb_substr($jsonld_desc, 0, 500) : substr($jsonld_desc, 0, 500);
			}
			if (!empty($data['manufacturer'])) {
				$jsonld['brand'] = ['@type' => 'Brand', 'name' => (string)$data['manufacturer']];
			}
			$jsonld_price = $this->tax->calculate((float)$product_info['special'] ?: (float)$product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax'));
			$jsonld['offers'] = [
				'@type'         => 'Offer',
				'url'           => (string)$data['current_url'],
				'priceCurrency' => 'UAH',
				'price'         => number_format((float)$jsonld_price, 2, '.', ''),
				'priceValidUntil' => date('Y-m-d', strtotime('+1 year')),
				'availability'  => ((int)$product_info['quantity'] > 0) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'itemCondition' => 'https://schema.org/NewCondition',
			];
			$data['product_jsonld'] = '<script type="application/ld+json">' . json_encode($jsonld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

			$discounts = $this->model_catalog_product->getDiscounts($product_id);

			$data['discounts'] = [];

			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				foreach ($discounts as $discount) {
					$data['discounts'][] = ['price' => $this->currency->format($this->tax->calculate($discount['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency'])] + $discount;
				}
			}

			$data['options'] = [];

			// Check if product is variant
			if ($product_info['master_id']) {
				$master_id = (int)$product_info['master_id'];
			} else {
				$master_id = (int)$this->request->get['product_id'];
			}

			$product_options = $this->model_catalog_product->getOptions($master_id);

			foreach ($product_options as $option) {
				if ((int)$this->request->get['product_id'] && !isset($product_info['override']['variant'][$option['product_option_id']])) {
					$product_option_value_data = [];

					foreach ($option['product_option_value'] as $option_value) {
						if (!$option_value['subtract'] || ($option_value['quantity'] > 0)) {
							if ((($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) && (float)$option_value['price']) {
								$price = $this->currency->format($this->tax->calculate($option_value['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
							} else {
								$price = false;
							}

							if ($option_value['image'] && is_file(DIR_IMAGE . html_entity_decode($option_value['image'], ENT_QUOTES, 'UTF-8'))) {
								$image = $option_value['image'];
							} else {
								$image = '';
							}

							$product_option_value_data[] = [
								'image' => $this->model_tool_image->resize($image, 50, 50),
								'price' => $price
							] + $option_value;
						}
					}

					$data['options'][] = ['product_option_value' => $product_option_value_data] + $option;
				}
			}

			// Build Manline color switcher (by attribute "Цвет/Колір")
			// OC2 convention: color is stored as a product attribute (not an option).
			// Variants are inferred by shared model prefix (before '-') e.g. 2664-xxx.
			try {
				$current_pid = (int)($this->request->get['product_id'] ?? 0);
				$language_id = (int)$this->config->get('config_language_id');

				$model = (string)($product_info['model'] ?? '');
				$prefix = trim(explode('-', $model)[0]);

				if ($prefix !== '' && preg_match('/^\d+$/', $prefix)) {
					// Attribute id for Color/Колір in current dataset
					$color_attribute_id = 17;

					$group_products = $this->model_catalog_product->getProductsByModelPrefix($prefix);
					$product_ids = array_map(fn($r) => (int)$r['product_id'], $group_products);
					$color_map = $this->model_catalog_product->getAttributeTextMap($product_ids, $color_attribute_id, $language_id);

					// Fallback to the other language if current language doesn't have color filled (data can be mixed)
					$fallback_language_id = ($language_id === 3) ? 2 : (($language_id === 2) ? 3 : 0);
					$color_map_fallback = $fallback_language_id ? $this->model_catalog_product->getAttributeTextMap($product_ids, $color_attribute_id, $fallback_language_id) : [];

					$normalizeColor = function(string $name, string $lang): string {
						$name = trim($name);
						if ($name === '') return '';

						// Keep first token ("черный, синий" → "черный")
						if (str_contains($name, ',')) {
							$name = trim(explode(',', $name)[0]);
						}

						$name_l = mb_strtolower($name);

						if ($lang === 'uk-ua') {
							$map = [
								'черный' => 'чорний',
								'белый' => 'білий',
								'синий' => 'синій',
								'голубой' => 'блакитний',
								'красный' => 'червоний',
								'желтый' => 'жовтий',
								'оранжевый' => 'помаранчевий',
								'зелёный' => 'зелений',
								'зеленый' => 'зелений',
								'серый' => 'сірий',
								'коричневый' => 'коричневий',
								'бежевый' => 'бежевий',
								'розовый' => 'рожевий',
								'фиолетовый' => 'фіолетовий',
								'фіолетовий' => 'фіолетовий',
								'чорний' => 'чорний',
								'білий' => 'білий',
								'синій' => 'синій',
								'блакитний' => 'блакитний',
								'червоний' => 'червоний',
								'жовтий' => 'жовтий',
								'помаранчевий' => 'помаранчевий',
								'зелений' => 'зелений',
								'сірий' => 'сірий',
								'коричневий' => 'коричневий',
								'бежевий' => 'бежевий',
								'рожевий' => 'рожевий',
							];
							if (isset($map[$name_l])) {
								return $map[$name_l];
							}
						}

						return $name;
					};

					if (count($group_products) > 1) {
						$data['colors_cfg'] = ['name' => 1];

						$colors = [];

						foreach ($group_products as $gp) {
							$pid = (int)($gp['product_id'] ?? 0);
							if (!$pid) continue;

							$qty = (int)($gp['quantity'] ?? 0);
							$is_disabled = $qty <= 0;

							$ico_photo = '';
							if (!empty($gp['image']) && is_file(DIR_IMAGE . html_entity_decode((string)$gp['image'], ENT_QUOTES, 'UTF-8'))) {
								$ico_photo = $this->model_tool_image->resize((string)$gp['image'], 40, 40);
							}

							$color_text = (string)($color_map[$pid] ?? '');
							if ($color_text === '' && isset($color_map_fallback[$pid])) {
								$color_text = (string)$color_map_fallback[$pid];
							}

							$color_name = $normalizeColor($color_text, (string)$data['lang']);

							$colors[] = [
								'product_id' => $pid,
								'href' => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $pid),
								'tpl' => $ico_photo ? 'photos' : 'img',
								'quantity' => $is_disabled ? 'disabled' : '',
								'color' => '',
								'ico_photo' => $ico_photo,
								'ico_color' => $ico_photo,
								'color_name' => $color_name,
								'_model' => (string)($gp['model'] ?? ''),
								'_disabled' => $is_disabled,
							];
						}

						// Sort (OC2-like): in-stock first, then by model; keep current product first within its stock group.
						usort($colors, function($a, $b) use ($current_pid) {
							if ($a['_disabled'] !== $b['_disabled']) return $a['_disabled'] <=> $b['_disabled'];

							if ($a['product_id'] === $current_pid) return -1;
							if ($b['product_id'] === $current_pid) return 1;

							$am = $a['_model'] ?? '';
							$bm = $b['_model'] ?? '';
							if ($am !== '' && $bm !== '') return strcmp($am, $bm);
							return $a['product_id'] <=> $b['product_id'];
						});

						// Deduplicate by normalized color name: keep 1 tile per color.
						// Priority: current product > in-stock > lowest model/product_id.
						$by_color = [];
						foreach ($colors as $c) {
							$key = trim((string)($c['color_name'] ?? ''));
							if ($key === '') {
								$key = '__unknown__';
							}

							$is_current = ((int)$c['product_id'] === $current_pid);
							$is_disabled = !empty($c['_disabled']);

							$score = 0;
							if ($is_current) $score += 1000;
							if (!$is_disabled) $score += 100;

							// prefer more informative tile (with photo)
							if (!empty($c['ico_photo'])) $score += 10;

							$existing = $by_color[$key] ?? null;
							if ($existing === null || ($score > ($existing['_score'] ?? -1))) {
								$c['_score'] = $score;
								$by_color[$key] = $c;
							}
						}

						$colors = array_values($by_color);
						usort($colors, function($a, $b) use ($current_pid) {
							$ad = !empty($a['_disabled']);
							$bd = !empty($b['_disabled']);
							if ($ad !== $bd) return $ad <=> $bd;
							if ((int)$a['product_id'] === $current_pid) return -1;
							if ((int)$b['product_id'] === $current_pid) return 1;
							return strcmp((string)($a['color_name'] ?? ''), (string)($b['color_name'] ?? ''));
						});

						// Remove internal helper fields
						foreach ($colors as &$c) {
							unset($c['_model'], $c['_disabled'], $c['_score']);
						}
						unset($c);

						$data['colors'] = $colors;
					}
				}
			} catch (\Throwable $e) {
				// fail silently; colors are optional
			}

			// Subscriptions
			$data['subscription_plans'] = [];

			$results = $this->model_catalog_product->getSubscriptions($product_id);

			foreach ($results as $result) {
				$description = '';

				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					if ($result['duration']) {
						$price = ($product_info['special'] ?: $product_info['price']) / $result['duration'];
					} else {
						$price = ($product_info['special'] ?: $product_info['price']);
					}

					$price = $this->currency->format($this->tax->calculate($price, $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
					$cycle = $result['cycle'];
					$frequency = $this->language->get('text_' . $result['frequency']);
					$duration = $result['duration'];

					if ($duration) {
						$description = sprintf($this->language->get('text_subscription_duration'), $price, $cycle, $frequency, $duration);
					} else {
						$description = sprintf($this->language->get('text_subscription_cancel'), $price, $cycle, $frequency);
					}
				}

				$data['subscription_plans'][] = ['description' => $description] + $result;
			}

			if ($product_info['minimum']) {
				$data['minimum'] = $product_info['minimum'];
			} else {
				$data['minimum'] = 1;
			}

			$data['share'] = $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . (int)$this->request->get['product_id']);

			$data['attribute_groups'] = $this->model_catalog_product->getAttributes($product_id);

			$data['related'] = $this->load->controller('product/related');

			$data['tags'] = [];

			if ($product_info['tag']) {
				$tags = explode(',', $product_info['tag']);

				foreach ($tags as $tag) {
					$data['tags'][] = [
						'tag'  => trim($tag),
						'href' => $this->url->link('product/search', 'language=' . $this->config->get('config_language') . '&tag=' . trim($tag))
					];
				}
			}

			if ($this->config->get('config_product_report_status')) {
				$this->model_catalog_product->addReport($this->request->get['product_id'], oc_get_ip());
			}

			$data['language'] = $this->config->get('config_language');

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('product/product', $data));
		} else {
			return new \Opencart\System\Engine\Action('error/not_found');
		}

		return null;
	}
}
