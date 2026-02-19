<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class Home
 *
 * Can be called from $this->load->controller('common/home');
 *
 * @package Opencart\Catalog\Controller\Common
 */
class Home extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$description = $this->config->get('config_description');
		$language_id = $this->config->get('config_language_id');

		if (isset($description[$language_id])) {
			$this->document->setTitle($description[$language_id]['meta_title']);
			$this->document->setDescription($description[$language_id]['meta_description']);
			$this->document->setKeywords($description[$language_id]['meta_keyword']);
		}

		// Manline: homepage content (OC2-like)
		$data['lang'] = (string)$this->config->get('config_language');

		$home_cfg_path = DIR_APPLICATION . 'view/theme/manline/data/home.json';
		$home_cfg = [];

		if (is_file($home_cfg_path)) {
			$raw = file_get_contents($home_cfg_path);
			$home_cfg = $raw ? (json_decode($raw, true) ?: []) : [];
		}

		$data['home_slider'] = $home_cfg['slider'] ?? [];
		$data['home_features'] = $home_cfg['features'] ?? [];

		// Category tiles (OC2-like). Allow JSON to specify either:
		// - keyword: legacy SEO keyword (will be resolved to actual route via seo_url table)
		// - category_id: category id (href will be generated via product/category)
		// - href: explicit href fallback
		$data['home_category_tiles'] = [];
		$raw_tiles = $home_cfg['category_tiles'] ?? [];

		if (is_array($raw_tiles) && $raw_tiles) {
			try {
				$this->load->model('design/seo_url');
			} catch (\Throwable $e) {
				// ignore
			}

			foreach ($raw_tiles as $t) {
				if (!is_array($t)) continue;

				$href = (string)($t['href'] ?? '');

				// Resolve by category_id
				if (!empty($t['category_id'])) {
					$href = $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . (int)$t['category_id']);
				}

				// Resolve by seo keyword (preferred; lets you keep OC2 keywords in JSON)
				if (!empty($t['keyword'])) {
					// Always have a non-empty fallback
					$href = '/' . ltrim((string)$t['keyword'], '/') . '/';

					// If OC4 SEO table knows this keyword, generate route-based link
					if (isset($this->model_design_seo_url)) {
						$seo = $this->model_design_seo_url->getSeoUrlByKeyword((string)$t['keyword']);
						if ($seo && !empty($seo['key']) && isset($seo['value'])) {
							$key = (string)$seo['key'];
							$value = (string)$seo['value'];

							// Category keyword usually maps to key=path, value=<category_id>
							if ($key === 'path' && ctype_digit($value)) {
								$href = $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . (int)$value);
							}
						}
					}
				}

				$t['href'] = $href;
				$data['home_category_tiles'][] = $t;
			}
		}

		// Top categories grid (OC2 style: categories where top=1 and has image)
		$data['home_top_categories'] = [];
		try {
			$this->load->model('catalog/category');
			$this->load->model('tool/image');

			$categories = $this->model_catalog_category->getCategories(0);

			foreach ($categories as $category) {
				if (empty($category['status']) || empty($category['top']) || empty($category['image'])) {
					continue;
				}

				$image_path = html_entity_decode((string)$category['image'], ENT_QUOTES, 'UTF-8');
				if (!is_file(DIR_IMAGE . $image_path)) {
					continue;
				}

				// Keep aspect ratio similar to OC2 home.tpl logic
				[$w, $h] = @getimagesize(DIR_IMAGE . $image_path) ?: [0, 0];
				if (!$w || !$h) {
					$w = 400;
					$h = 400;
				}

				$ratio = $w / $h;
				$thumb_w = 400;
				$thumb_h = (int)round($thumb_w / $ratio);

				$data['home_top_categories'][] = [
					'name' => $category['name'] ?? '',
					'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . (int)$category['category_id']),
					'image' => $this->model_tool_image->resize($image_path, $thumb_w, $thumb_h)
				];
			}
		} catch (\Throwable $e) {
			// ignore
		}

		// Popular brands carousel (OC2-style static list)
		$data['home_brands'] = [];
		try {
			$this->load->model('tool/image');

			$brand_src = [
				['name' => 'Аtlantic', 'href' => '/muzhskie-trusy/atlantic/', 'image' => 'data/home_manuf/atlantic.jpg'],
				['name' => 'Calvin Klein', 'href' => '/muzhskie-trusy/calvin-klein/', 'image' => 'data/home_manuf/calvin_klein.png'],
				['name' => 'Jiber', 'href' => '/muzhskie-trusy/jiber/', 'image' => 'data/home_manuf/jiber.jpg'],
				['name' => 'DIM', 'href' => '/muzhskie-trusy/dim/', 'image' => 'data/home_manuf/dim.png'],
				['name' => 'Кey', 'href' => '/muzhskie-trusy/key/', 'image' => 'data/home_manuf/key.png'],
				['name' => 'Doreanse', 'href' => '/muzhskie-trusy/doreanse/', 'image' => 'data/home_manuf/doreanse.png'],
				['name' => 'Thermoform', 'href' => '/termobele/thermoform/', 'image' => 'data/home_manuf/thermoform.png'],
				['name' => 'Lacost', 'href' => '/muzhskie-trusy/f/manufacturer_102', 'image' => 'data/home_manuf/lacost.jpg'],
			];

			foreach ($brand_src as $b) {
				$image_path = (string)$b['image'];
				if (is_file(DIR_IMAGE . $image_path)) {
					$b['image'] = $this->model_tool_image->resize($image_path, 100, 100);
				} else {
					$b['image'] = '';
				}
				$data['home_brands'][] = $b;
			}
		} catch (\Throwable $e) {
			// fallback (raw images)
			$data['home_brands'] = [
				['name' => 'Аtlantic', 'href' => '/muzhskie-trusy/atlantic/', 'image' => 'data/home_manuf/atlantic.jpg'],
				['name' => 'Calvin Klein', 'href' => '/muzhskie-trusy/calvin-klein/', 'image' => 'data/home_manuf/calvin_klein.png'],
				['name' => 'Jiber', 'href' => '/muzhskie-trusy/jiber/', 'image' => 'data/home_manuf/jiber.jpg'],
				['name' => 'DIM', 'href' => '/muzhskie-trusy/dim/', 'image' => 'data/home_manuf/dim.png'],
				['name' => 'Кey', 'href' => '/muzhskie-trusy/key/', 'image' => 'data/home_manuf/key.png'],
				['name' => 'Doreanse', 'href' => '/muzhskie-trusy/doreanse/', 'image' => 'data/home_manuf/doreanse.png'],
				['name' => 'Thermoform', 'href' => '/termobele/thermoform/', 'image' => 'data/home_manuf/thermoform.png'],
				['name' => 'Lacost', 'href' => '/muzhskie-trusy/f/manufacturer_102', 'image' => 'data/home_manuf/lacost.jpg'],
			];
		}
		$data['home_featured_title_ua'] = $home_cfg['featured_title_ua'] ?? 'Рекомендуємо';
		$data['home_featured_title_ru'] = $home_cfg['featured_title_ru'] ?? 'Рекомендуем';
		$data['home_blog_title_ua'] = $home_cfg['blog_title_ua'] ?? 'Останнє з блогу';
		$data['home_blog_title_ru'] = $home_cfg['blog_title_ru'] ?? 'Последнее из блога';

		// Blog teaser: prefer OC4 CMS articles (migrated from manline_src). Fallback to home.json.
		$data['home_blog_posts'] = [];
		try {
			$this->load->model('cms/article');
			$this->load->model('tool/image');

			$articles = $this->model_cms_article->getArticles([
				'filter_search'   => '',
				'filter_topic_id' => 0,
				'filter_author'   => '',
				'filter_tag'      => '',
				'sort'            => 'date_added',
				'order'           => 'DESC',
				'start'           => 0,
				'limit'           => 3
			]);

			foreach ($articles as $a) {
				$image = '';
				if (!empty($a['image']) && is_file(DIR_IMAGE . html_entity_decode((string)$a['image'], ENT_QUOTES, 'UTF-8'))) {
					$image = $this->model_tool_image->resize((string)$a['image'], 380, 260);
				}

				$data['home_blog_posts'][] = [
					'href' => $this->url->link('cms/blog.info', 'language=' . $this->config->get('config_language') . '&article_id=' . (int)$a['article_id']),
					'image' => $image,
					'title_ru' => $a['name'] ?? '',
					'title_ua' => $a['name'] ?? '',
					'desc_ru' => '',
					'desc_ua' => '',
					'date_day' => date('d', strtotime((string)($a['date_added'] ?? 'now'))),
					'date_month_ru' => '',
					'date_month_ua' => '',
					'date_year' => date('Y', strtotime((string)($a['date_added'] ?? 'now'))),
					'read_more_ru' => 'Читать статью',
					'read_more_ua' => 'Читати статтю'
				];
			}
		} catch (\Throwable $e) {
			// ignore (fallback below)
		}

		if (!$data['home_blog_posts']) {
			$data['home_blog_posts'] = $home_cfg['blog_posts'] ?? [];
		}

		// Featured products carousel (best-effort; can be replaced by OC4 modules later)
		$data['home_featured_products'] = [];
		$product_ids = $home_cfg['featured_product_ids'] ?? [];

		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		if (is_array($product_ids) && $product_ids) {
			foreach ($product_ids as $pid) {
				$pid = (int)$pid;
				if (!$pid) continue;

				$product_info = $this->model_catalog_product->getProduct($pid);
				if (!$product_info) continue;

				$thumb = '';
				if (!empty($product_info['image']) && is_file(DIR_IMAGE . html_entity_decode((string)$product_info['image'], ENT_QUOTES, 'UTF-8'))) {
					$thumb = $this->model_tool_image->resize((string)$product_info['image'], (int)$this->config->get('config_image_product_width'), (int)$this->config->get('config_image_product_height'));
				}

				$data['home_featured_products'][] = $this->load->controller('product/thumb', [
					'product_id' => $pid,
					'images' => [],
					'thumb' => $thumb,
					'name' => $product_info['name'] ?? '',
					'quantity' => (int)($product_info['quantity'] ?? 0),
					'price' => !empty($product_info['price']) ? $this->currency->format((float)$product_info['price'], $this->session->data['currency']) : '',
					'special' => !empty($product_info['special']) ? $this->currency->format((float)$product_info['special'], $this->session->data['currency']) : '',
					'lang_code' => $data['lang'],
					'model' => $product_info['model'] ?? '',
					'href' => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $pid)
				]);
			}
		}

		// Home carousels (OC2-like): Specials (Sale) and Bestseller (Popular)
		$data['home_special_products'] = [];
		$data['home_bestseller_products'] = [];
		$data['home_special_title_ru'] = $home_cfg['special_title_ru'] ?? 'Распродажа';
		$data['home_special_title_ua'] = $home_cfg['special_title_ua'] ?? 'Розпродаж';
		$data['home_bestseller_title_ru'] = $home_cfg['bestseller_title_ru'] ?? 'Популярные товары';
		$data['home_bestseller_title_ua'] = $home_cfg['bestseller_title_ua'] ?? 'Популярні товари';

		$limit_special = (int)($home_cfg['special_limit'] ?? 10);
		$limit_best = (int)($home_cfg['bestseller_limit'] ?? 10);
		if ($limit_special <= 0) $limit_special = 10;
		if ($limit_best <= 0) $limit_best = 10;

		try {
			// Prefer dedicated specials query; fallback to getProducts(filter_special).
			// If the store has no specials configured, allow a manual fallback list from home.json:
			// - special_product_ids: [4637, 3315, ...]
			$specials = $this->model_catalog_product->getSpecials([
				'sort' => 'p.sort_order',
				'order' => 'ASC',
				'start' => 0,
				'limit' => $limit_special
			]);

			if (!$specials) {
				$specials = $this->model_catalog_product->getProducts([
					'filter_special' => 1,
					'sort' => 'p.sort_order',
					'order' => 'ASC',
					'start' => 0,
					'limit' => $limit_special
				]);
			}

			if (!$specials) {
				$specialIds = $home_cfg['special_product_ids'] ?? [];
				if (is_array($specialIds) && $specialIds) {
					$specials = [];
					foreach ($specialIds as $pid) {
						$pid = (int)$pid;
						if (!$pid) continue;
						$p = $this->model_catalog_product->getProduct($pid);
						if ($p) $specials[] = $p;
					}
				}
			}

			foreach ($specials as $result) {
				$pid = (int)($result['product_id'] ?? 0);
				if (!$pid) continue;

				$thumb = '';
				if (!empty($result['image']) && is_file(DIR_IMAGE . html_entity_decode((string)$result['image'], ENT_QUOTES, 'UTF-8'))) {
					$thumb = $this->model_tool_image->resize((string)$result['image'], (int)$this->config->get('config_image_product_width'), (int)$this->config->get('config_image_product_height'));
				}

				$data['home_special_products'][] = $this->load->controller('product/thumb', [
					'product_id' => $pid,
					'images' => [],
					'thumb' => $thumb,
					'name' => $result['name'] ?? '',
					'quantity' => (int)($result['quantity'] ?? 0),
					'price' => isset($result['price']) ? $this->currency->format((float)$result['price'], $this->session->data['currency']) : '',
					'special' => isset($result['special']) ? $this->currency->format((float)$result['special'], $this->session->data['currency']) : '',
					'lang_code' => $data['lang'],
					'model' => $result['model'] ?? '',
					'href' => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $pid)
				]);
			}
		} catch (\Throwable $e) {
			// ignore
		}

		try {
			$this->load->model('extension/opencart/module/bestseller');
			$bests = $this->model_extension_opencart_module_bestseller->getBestSellers($limit_best);

			// If bestseller table is empty, allow a manual fallback list from home.json:
			// - bestseller_product_ids: [4637, 3315, ...]
			if (!$bests) {
				$bestIds = $home_cfg['bestseller_product_ids'] ?? [];
				if (is_array($bestIds) && $bestIds) {
					$bests = [];
					foreach ($bestIds as $pid) {
						$pid = (int)$pid;
						if (!$pid) continue;
						$p = $this->model_catalog_product->getProduct($pid);
						if ($p) $bests[] = $p;
					}
				}
			}

			// Fallback: use most viewed products if bestseller table is empty
			if (!$bests) {
				$q = $this->db->query("SELECT p.product_id
					FROM `" . DB_PREFIX . "product` p
					LEFT JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p2s.product_id = p.product_id AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "')
					WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.product_id IS NOT NULL
					ORDER BY p.viewed DESC, p.sort_order ASC
					LIMIT " . (int)$limit_best);

				$bests = [];
				foreach ($q->rows as $row) {
					$pid = (int)($row['product_id'] ?? 0);
					if ($pid) {
						$bests[] = $this->model_catalog_product->getProduct($pid);
					}
				}
			}

			foreach ($bests as $result) {
				$pid = (int)($result['product_id'] ?? 0);
				if (!$pid) continue;

				$thumb = '';
				if (!empty($result['image']) && is_file(DIR_IMAGE . html_entity_decode((string)$result['image'], ENT_QUOTES, 'UTF-8'))) {
					$thumb = $this->model_tool_image->resize((string)$result['image'], (int)$this->config->get('config_image_product_width'), (int)$this->config->get('config_image_product_height'));
				}

				$data['home_bestseller_products'][] = $this->load->controller('product/thumb', [
					'product_id' => $pid,
					'images' => [],
					'thumb' => $thumb,
					'name' => $result['name'] ?? '',
					'quantity' => (int)($result['quantity'] ?? 0),
					'price' => isset($result['price']) ? $this->currency->format((float)$result['price'], $this->session->data['currency']) : '',
					'special' => !empty($result['special']) ? $this->currency->format((float)$result['special'], $this->session->data['currency']) : '',
					'lang_code' => $data['lang'],
					'model' => $result['model'] ?? '',
					'href' => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $pid)
				]);
			}
		} catch (\Throwable $e) {
			// ignore
		}

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('common/home', $data));
	}
}
