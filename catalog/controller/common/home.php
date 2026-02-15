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

		// Manline: homepage content (OC2-like, hardcoded slider + tiles)
		$data['lang'] = (string)$this->config->get('config_language');

		$home_cfg_path = DIR_APPLICATION . 'view/theme/manline/data/home.json';
		$home_cfg = [];

		if (is_file($home_cfg_path)) {
			$raw = file_get_contents($home_cfg_path);
			$home_cfg = $raw ? (json_decode($raw, true) ?: []) : [];
		}

		$data['home_slider'] = $home_cfg['slider'] ?? [];
		$data['home_features'] = $home_cfg['features'] ?? [];
		$data['home_category_tiles'] = $home_cfg['category_tiles'] ?? [];
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

		if (is_array($product_ids) && $product_ids) {
			$this->load->model('catalog/product');
			$this->load->model('tool/image');

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

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('common/home', $data));
	}
}
