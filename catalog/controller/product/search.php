<?php
namespace Opencart\Catalog\Controller\Product;
/**
 * Class Search
 *
 * @package Opencart\Catalog\Controller\Product
 */
class Search extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('product/search');

		if (isset($this->request->get['search'])) {
			$filter_search = $this->request->get['search'];
		} else {
			$filter_search = '';
		}

		if (isset($this->request->get['description'])) {
			$filter_description = $this->request->get['description'];
		} else {
			$filter_description = '';
		}

		if (isset($this->request->get['tag'])) {
			$filter_tag = $this->request->get['tag'];
		} else {
			$filter_tag = '';
		}

		if (isset($this->request->get['category_id'])) {
			$filter_category_id = (int)$this->request->get['category_id'];
		} else {
			$filter_category_id = 0;
		}

		if (isset($this->request->get['sub_category'])) {
			$filter_sub_category = $this->request->get['sub_category'];
		} else {
			$filter_sub_category = 0;
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'p.sort_order';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		if (isset($this->request->get['limit']) && (int)$this->request->get['limit']) {
			$limit = (int)$this->request->get['limit'];
		} else {
			$limit = $this->config->get('config_pagination');
		}

		if (isset($this->request->get['search'])) {
			$this->document->setTitle($this->language->get('heading_title') . ' - ' . $this->request->get['search']);
		} elseif (isset($this->request->get['tag'])) {
			$this->document->setTitle($this->language->get('heading_title') . ' - ' . $this->language->get('heading_tag') . $this->request->get['tag']);
		} else {
			$this->document->setTitle($this->language->get('heading_title'));
		}

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
		];

		$url = '';

		if (isset($this->request->get['search'])) {
			$url .= '&search=' . urlencode(html_entity_decode($this->request->get['search'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['description'])) {
			$url .= '&description=' . $this->request->get['description'];
		}

		if (isset($this->request->get['tag'])) {
			$url .= '&tag=' . urlencode(html_entity_decode($this->request->get['tag'], ENT_QUOTES, 'UTF-8'));
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
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('product/search', 'language=' . $this->config->get('config_language') . $url)
		];

		if (isset($this->request->get['search'])) {
			$data['heading_title'] = $this->language->get('heading_title') . ' - ' . $this->request->get['search'];
		} else {
			$data['heading_title'] = $this->language->get('heading_title');
		}

		$data['text_compare'] = sprintf($this->language->get('text_compare'), isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0);

		$data['compare'] = $this->url->link('product/compare', 'language=' . $this->config->get('config_language'));

		// FilterPro-like sidebar for search page (Manline)
		$filterpro_setting = ['status' => 1];
		$this->load->model('setting/setting');
		$filterpro_module_id = (int)$this->model_setting_setting->getValue('manline_filterpro_module_id');
		if ($filterpro_module_id) {
			$this->load->model('setting/module');
			$module_info = $this->model_setting_module->getModule($filterpro_module_id);
			if (is_array($module_info)) {
				$filterpro_setting = $module_info;
			}
		}

		// 3 Level Category Search
		$data['categories'] = [];

		$this->load->model('catalog/category');

		$categories_1 = $this->model_catalog_category->getCategories(0);

		foreach ($categories_1 as $category_1) {
			$level_2_data = [];

			$categories_2 = $this->model_catalog_category->getCategories($category_1['category_id']);

			foreach ($categories_2 as $category_2) {
				$level_3_data = $this->model_catalog_category->getCategories($category_2['category_id']);

				$level_2_data[] = [
					'category_id' => $category_2['category_id'],
					'name'        => $category_2['name'],
					'children'    => $level_3_data
				];
			}

			$data['categories'][] = ['children' => $level_2_data] + $category_1;
		}

		$data['products'] = [];

		if ($filter_search || $filter_tag) {
			// Parse query-param based filters (stage 1) to match FilterPro-like sidebar on /search
			$manufacturer_ids = [];
			if (isset($this->request->get['manufacturer'])) {
				$m = $this->request->get['manufacturer'];
				if (is_array($m)) {
					foreach ($m as $vv) {
						foreach (explode(',', (string)$vv) as $id) $manufacturer_ids[] = (int)$id;
					}
				} elseif ((string)$m !== '') {
					foreach (explode(',', (string)$m) as $id) $manufacturer_ids[] = (int)$id;
				}
			}
			$manufacturer_ids = array_values(array_unique(array_filter($manufacturer_ids)));

			$min_price = isset($this->request->get['min_price']) ? (float)$this->request->get['min_price'] : null;
			$max_price = isset($this->request->get['max_price']) ? (float)$this->request->get['max_price'] : null;

			$attribute_slug = [];
			$option_value = [];
			foreach ($this->request->get as $k => $v) {
				$k = (string)$k;
				if (!str_starts_with($k, 'f_')) continue;
				$slug_key = substr($k, 2);
				if ($slug_key === '') continue;

				$vals = [];
				if (is_array($v)) {
					foreach ($v as $vv) $vals = array_merge($vals, explode(',', (string)$vv));
				} else {
					$vals = explode(',', (string)$v);
				}
				$vals = array_values(array_unique(array_filter(array_map('trim', $vals))));
				if (!$vals) continue;

				// OPTION by slug
				$opt_q = $this->db->query("SELECT option_id FROM `" . DB_PREFIX . "option_description` WHERE slug='" . $this->db->escape($slug_key) . "' ORDER BY language_id='" . (int)$this->config->get('config_language_id') . "' DESC LIMIT 1");
				if ($opt_q->num_rows) {
					$option_id = (int)$opt_q->row['option_id'];
					foreach ($vals as $vslug) {
						$ov_q = $this->db->query("SELECT option_value_id FROM `" . DB_PREFIX . "option_value_description` WHERE option_id='" . $option_id . "' AND slug='" . $this->db->escape((string)$vslug) . "' ORDER BY language_id='" . (int)$this->config->get('config_language_id') . "' DESC LIMIT 1");
						if ($ov_q->num_rows) {
							$option_value[$option_id][] = (int)$ov_q->row['option_value_id'];
						}
					}
					continue;
				}

				// ATTRIBUTE by slug
				$attr_q = $this->db->query("SELECT attribute_id FROM `" . DB_PREFIX . "attribute_description` WHERE slug='" . $this->db->escape($slug_key) . "' ORDER BY language_id='" . (int)$this->config->get('config_language_id') . "' DESC LIMIT 1");
				if ($attr_q->num_rows) {
					$attribute_id = (int)$attr_q->row['attribute_id'];
					foreach ($vals as $vslug) {
						$attribute_slug[$attribute_id][] = (string)$vslug;
					}
					continue;
				}
			}

			// normalize option_value
			foreach ($option_value as $oid => $ids) {
				$ids = array_values(array_unique(array_filter(array_map('intval', (array)$ids))));
				$option_value[(int)$oid] = $ids;
			}

			$filter_data = [
				'filter_search'       => $filter_search,
				'filter_description'  => $filter_description,
				'filter_tag'          => $filter_tag ? $filter_tag : $filter_search,
				'filter_category_id'  => $filter_category_id,
				'filter_sub_category' => $filter_sub_category,
				'filter_manufacturer_ids' => $manufacturer_ids,
				'filter_min_price' => $min_price,
				'filter_max_price' => $max_price,
				'filter_attribute_slug' => $attribute_slug,
				'filter_option_value' => $option_value,
				'filter_stock' => !empty($this->request->get['stock']) ? 1 : 0,
				'filter_special' => !empty($this->request->get['special']) ? 1 : 0,
				'filter_new' => !empty($this->request->get['new']) ? 1 : 0,
				'sort'                => $sort,
				'order'               => $order,
				'start'               => ($page - 1) * $limit,
				'limit'               => $limit
			];

			// Product
			$this->load->model('catalog/product');

			// Image
			$this->load->model('tool/image');

			$results = $this->model_catalog_product->getProducts($filter_data);

			foreach ($results as $result) {
				$description = trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8')));

				if (oc_strlen($description) > $this->config->get('config_product_description_length')) {
					$description = oc_substr($description, 0, $this->config->get('config_product_description_length')) . '..';
				}

				if ($result['image'] && is_file(DIR_IMAGE . html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'))) {
					$image = $result['image'];
				} else {
					$image = 'placeholder.png';
				}

				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$price = false;
				}

				if ((float)$result['special']) {
					$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$special = false;
				}

				if ($this->config->get('config_tax')) {
					$tax = $this->currency->format((float)$result['special'] ? $result['special'] : $result['price'], $this->session->data['currency']);
				} else {
					$tax = false;
				}

				$product_data = [
					'thumb'       => $this->model_tool_image->resize($image, $this->config->get('config_image_product_width'), $this->config->get('config_image_product_height')),
					'description' => $description,
					'price'       => $price,
					'special'     => $special,
					'tax'         => $tax,
					'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
					'href'        => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $result['product_id'] . $url)
				] + $result;

				$data['products'][] = $this->load->controller('product/thumb', $product_data);
			}

			$url = '';

			if (isset($this->request->get['search'])) {
				$url .= '&search=' . urlencode(html_entity_decode($this->request->get['search'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['description'])) {
				$url .= '&description=' . $this->request->get['description'];
			}

			if (isset($this->request->get['tag'])) {
				$url .= '&tag=' . urlencode(html_entity_decode($this->request->get['tag'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['category_id'])) {
				$url .= '&category_id=' . $this->request->get['category_id'];
			}

			if (isset($this->request->get['sub_category'])) {
				$url .= '&sub_category=' . $this->request->get['sub_category'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['sorts'] = [];

			$data['sorts'][] = [
				'text'  => $this->language->get('text_default'),
				'value' => 'p.sort_order-ASC',
				'href'  => $this->url->link('product/search', 'language=' . $this->config->get('config_language') . '&sort=p.sort_order&order=ASC' . $url)
			];

			$data['sorts'][] = [
				'text'  => $this->language->get('text_name_asc'),
				'value' => 'pd.name-ASC',
				'href'  => $this->url->link('product/search', 'language=' . $this->config->get('config_language') . '&sort=pd.name&order=ASC' . $url)
			];

			$data['sorts'][] = [
				'text'  => $this->language->get('text_name_desc'),
				'value' => 'pd.name-DESC',
				'href'  => $this->url->link('product/search', 'language=' . $this->config->get('config_language') . '&sort=pd.name&order=DESC' . $url)
			];

			$data['sorts'][] = [
				'text'  => $this->language->get('text_price_asc'),
				'value' => 'p.price-ASC',
				'href'  => $this->url->link('product/search', 'language=' . $this->config->get('config_language') . '&sort=p.price&order=ASC' . $url)
			];

			$data['sorts'][] = [
				'text'  => $this->language->get('text_price_desc'),
				'value' => 'p.price-DESC',
				'href'  => $this->url->link('product/search', 'language=' . $this->config->get('config_language') . '&sort=p.price&order=DESC' . $url)
			];

			if ($this->config->get('config_review_status')) {
				$data['sorts'][] = [
					'text'  => $this->language->get('text_rating_desc'),
					'value' => 'rating-DESC',
					'href'  => $this->url->link('product/search', 'language=' . $this->config->get('config_language') . '&sort=rating&order=DESC' . $url)
				];

				$data['sorts'][] = [
					'text'  => $this->language->get('text_rating_asc'),
					'value' => 'rating-ASC',
					'href'  => $this->url->link('product/search', 'language=' . $this->config->get('config_language') . '&sort=rating&order=ASC' . $url)
				];
			}

			$data['sorts'][] = [
				'text'  => $this->language->get('text_model_asc'),
				'value' => 'p.model-ASC',
				'href'  => $this->url->link('product/search', 'language=' . $this->config->get('config_language') . '&sort=p.model&order=ASC' . $url)
			];

			$data['sorts'][] = [
				'text'  => $this->language->get('text_model_desc'),
				'value' => 'p.model-DESC',
				'href'  => $this->url->link('product/search', 'language=' . $this->config->get('config_language') . '&sort=p.model&order=DESC' . $url)
			];

			$url = '';

			if (isset($this->request->get['search'])) {
				$url .= '&search=' . urlencode(html_entity_decode($this->request->get['search'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['description'])) {
				$url .= '&description=' . $this->request->get['description'];
			}

			if (isset($this->request->get['tag'])) {
				$url .= '&tag=' . urlencode(html_entity_decode($this->request->get['tag'], ENT_QUOTES, 'UTF-8'));
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

			$data['limits'] = [];

			$limits = array_unique([$this->config->get('config_pagination'), 25, 50, 75, 100]);

			sort($limits);

			foreach ($limits as $value) {
				$data['limits'][] = [
					'text'  => $value,
					'value' => $value,
					'href'  => $this->url->link('product/search', 'language=' . $this->config->get('config_language') . $url . '&limit=' . $value)
				];
			}

			$url = '';

			if (isset($this->request->get['search'])) {
				$url .= '&search=' . urlencode(html_entity_decode($this->request->get['search'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['description'])) {
				$url .= '&description=' . $this->request->get['description'];
			}

			if (isset($this->request->get['tag'])) {
				$url .= '&tag=' . urlencode(html_entity_decode($this->request->get['tag'], ENT_QUOTES, 'UTF-8'));
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

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$product_total = $this->model_catalog_product->getTotalProducts($filter_data);

			$data['pagination'] = $this->load->controller('common/pagination', [
				'total' => $product_total,
				'page'  => $page,
				'limit' => $limit,
				'url'   => $this->url->link('product/search', 'language=' . $this->config->get('config_language') . $url . '&page={page}')
			]);

			$data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($product_total - $limit)) ? $product_total : ((($page - 1) * $limit) + $limit), $product_total, ceil($product_total / $limit));

			if (isset($this->request->get['search']) && $this->config->get('config_customer_search')) {
				$this->load->model('account/search');

				if ($this->customer->isLogged()) {
					$customer_id = $this->customer->getId();
				} else {
					$customer_id = 0;
				}

				$search_data = [
					'keyword'      => $filter_tag ? $filter_tag : $filter_search,
					'description'  => $filter_description,
					'category_id'  => $filter_category_id,
					'sub_category' => $filter_sub_category,
					'products'     => $product_total,
					'customer_id'  => $customer_id,
					'ip'           => oc_get_ip()
				];

				$this->model_account_search->addSearch($search_data);
			}
		}

		$data['search'] = $filter_search;
		$data['description'] = $filter_description;
		$data['category_id'] = $filter_category_id;
		$data['sub_category'] = $filter_sub_category;

		$data['sort'] = $sort;
		$data['order'] = $order;
		$data['limit'] = $limit;

		$data['language'] = $this->config->get('config_language');

		$data['filterpro_like'] = $this->load->controller('extension/manline/module/filterpro_search_like', $filterpro_setting);
		$data['column_left'] = $this->load->controller('common/column_left');
		if (!empty($data['filterpro_like'])) {
			if (!empty($data['column_left']) && strpos($data['column_left'], '</aside>') !== false) {
				$data['column_left'] = str_replace('</aside>', $data['filterpro_like'] . "\n</aside>", $data['column_left']);
			} elseif (empty($data['column_left'])) {
				$data['column_left'] = '<aside id="column-left" class="col-md-3 hidden-xs hidden-sm">' . $data['filterpro_like'] . '</aside>';
			}
		}
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		// search.twig uses {% if lang == 'uk-ua' %} for the "no results"
		// empty-state copy. Without this assignment the conditional
		// always falls to the Russian branch on UA pages (same root
		// cause as product/related — header's $data['lang'] is not
		// inherited by independently-loaded child views).
		//
		// IMPORTANT: must be the full config_language code ('uk-ua' /
		// 'ru-ru'). $this->language->get('code') resolves to the SHORT
		// form ('uk' / 'ru') from the language file's $_['code'] key,
		// which never equals 'uk-ua' and silently breaks the ternary.
		$data['lang'] = (string)$this->config->get('config_language');

		// AJAX partial endpoint for FilterPro-like UX on search
		if (!empty($this->request->get['fp_partial']) && ($this->request->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
			$sort_options = '';
			foreach (($data['sorts'] ?? []) as $s) {
				if (!is_array($s)) continue;
				$selected = ((string)($s['value'] ?? '') === sprintf('%s-%s', $sort, $order)) ? ' selected' : '';
				$sort_options .= '<option value="' . htmlspecialchars((string)($s['href'] ?? ''), ENT_QUOTES) . '"' . $selected . '>' . htmlspecialchars((string)($s['text'] ?? ''), ENT_QUOTES) . '</option>';
			}

			$limit_options = '';
			foreach (($data['limits'] ?? []) as $l) {
				if (!is_array($l)) continue;
				$selected = ((string)($l['value'] ?? '') === (string)$limit) ? ' selected' : '';
				$limit_options .= '<option value="' . htmlspecialchars((string)($l['href'] ?? ''), ENT_QUOTES) . '"' . $selected . '>' . htmlspecialchars((string)($l['text'] ?? ''), ENT_QUOTES) . '</option>';
			}

			$this->response->addHeader('Content-Type: application/json; charset=utf-8');
			$this->response->setOutput(json_encode([
				'title' => (string)$this->document->getTitle(),
				'products_html' => implode('', $data['products'] ?? []),
				'pagination_html' => (string)($data['pagination'] ?? ''),
				'sort_options_html' => $sort_options,
				'limit_options_html' => $limit_options,
				'filter_html' => (string)($data['filterpro_like'] ?? ''),
			], JSON_UNESCAPED_UNICODE));
		} else {
			$this->response->setOutput($this->load->view('product/search', $data));
		}
	}
}
