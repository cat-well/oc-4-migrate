<?php
namespace Opencart\Catalog\Controller\Product;
/**
 * Class Category
 *
 * @package Opencart\Catalog\Controller\Product
 */
class Category extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return \Opencart\System\Engine\Action|null
	 */
	public function index(): ?\Opencart\System\Engine\Action {
		$this->load->language('product/category');

		if (isset($this->request->get['path'])) {
			$path = (string)$this->request->get['path'];
		} else {
			$path = '';
		}

		if (isset($this->request->get['filter'])) {
			$filter = $this->request->get['filter'];
		} else {
			$filter = '';
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

		// Category
		$parts = explode('_', $path);

		$category_id = (int)array_pop($parts);

		$this->load->model('catalog/category');

		$category_info = $this->model_catalog_category->getCategory($category_id);

		if ($category_info) {
			// FilterPro SEO landing overrides (legacy OC2 -> OC4)
			$filterpro_seo = $this->registry->has('filterpro_seo') ? $this->registry->get('filterpro_seo') : null;

			if (is_object($filterpro_seo) && !empty($filterpro_seo->title)) {
				$this->document->setTitle($filterpro_seo->title);
			} else {
				$this->document->setTitle($category_info['meta_title']);
			}

			if (is_object($filterpro_seo) && !empty($filterpro_seo->meta_description)) {
				$this->document->setDescription($filterpro_seo->meta_description);
			} else {
				$this->document->setDescription($category_info['meta_description']);
			}

			// OC2 stored meta_keywords; OC4 uses meta_keyword
			if (is_object($filterpro_seo) && !empty($filterpro_seo->meta_keywords)) {
				$this->document->setKeywords($filterpro_seo->meta_keywords);
			} else {
				$this->document->setKeywords($category_info['meta_keyword']);
			}

			$data['breadcrumbs'] = [];

			$data['breadcrumbs'][] = [
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
			];

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$path = '';

			foreach ($parts as $path_id) {
				if (!$path) {
					$path = (int)$path_id;
				} else {
					$path .= '_' . (int)$path_id;
				}

				$parent_info = $this->model_catalog_category->getCategory((int)$path_id);

				if ($parent_info) {
					$data['breadcrumbs'][] = [
						'text' => $parent_info['name'],
						'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $path . $url)
					];
				}
			}

			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
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

			// Set the last category breadcrumb
			$data['breadcrumbs'][] = [
				'text' => $category_info['name'],
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . $url)
			];

			// H1 override from FilterPro landing
			if (is_object($filterpro_seo) && !empty($filterpro_seo->h1)) {
				$data['heading_title'] = $filterpro_seo->h1;
			} else {
				$data['heading_title'] = $category_info['name'];
			}

			$data['text_compare'] = sprintf($this->language->get('text_compare'), isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0);

			// Image
			$this->load->model('tool/image');

			if (!empty($category_info['image']) && is_file(DIR_IMAGE . html_entity_decode($category_info['image'], ENT_QUOTES, 'UTF-8'))) {
				$data['image'] = $this->model_tool_image->resize($category_info['image'], $this->config->get('config_image_category_width'), $this->config->get('config_image_category_height'));
			} else {
				$data['image'] = '';
			}

			// SEO text override from FilterPro landing
			if (is_object($filterpro_seo) && !empty($filterpro_seo->description_html)) {
				$data['description'] = $filterpro_seo->description_html;
			} else {
				$data['description'] = html_entity_decode($category_info['description'], ENT_QUOTES, 'UTF-8');
			}
			$data['compare'] = $this->url->link('product/compare', 'language=' . $this->config->get('config_language'));

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
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

			$data['categories'] = [];

			// Product
			$this->load->model('catalog/product');

			$results = $this->model_catalog_category->getCategories($category_id);

			foreach ($results as $result) {
				$filter_data = [
					'filter_category_id'  => $result['category_id'],
					'filter_sub_category' => false
				];

				$data['categories'][] = [
					'name' => $result['name'] . ($this->config->get('config_product_count') ? ' (' . $this->model_catalog_product->getTotalProducts($filter_data) . ')' : ''),
					'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $this->request->get['path'] . '_' . $result['category_id'] . $url)
				];
			}

			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
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

			// Product
			$data['products'] = [];

			// FilterPro landing params (injected by startup/filterpro_seo)
			$manufacturer_ids = [];
			if (isset($this->request->get['manufacturer'])) {
				$m = $this->request->get['manufacturer'];
				if (is_array($m)) {
					foreach ($m as $id) {
						$manufacturer_ids[] = (int)$id;
					}
				} elseif (is_string($m) && $m !== '') {
					$manufacturer_ids[] = (int)$m;
				}
			}
			$manufacturer_ids = array_values(array_unique(array_filter($manufacturer_ids)));

			$min_price = isset($this->request->get['min_price']) ? (float)$this->request->get['min_price'] : null;
			$max_price = isset($this->request->get['max_price']) ? (float)$this->request->get['max_price'] : null;

			$attribute_value = [];
			if (isset($this->request->get['attribute_value']) && is_array($this->request->get['attribute_value'])) {
				foreach ($this->request->get['attribute_value'] as $attribute_id => $values) {
					$attribute_id = (int)$attribute_id;
					if ($attribute_id <= 0) continue;

					$vals = [];
					if (is_array($values)) {
						foreach ($values as $v) {
							$v = trim((string)$v);
							if ($v !== '') $vals[] = $v;
						}
					} else {
						$v = trim((string)$values);
						if ($v !== '') $vals[] = $v;
					}

					$vals = array_values(array_unique($vals));
					if ($vals) {
						$attribute_value[$attribute_id] = $vals;
					}
				}
			}

			$attribute_slug = [];
			if (isset($this->request->get['attribute_slug']) && is_array($this->request->get['attribute_slug'])) {
				foreach ($this->request->get['attribute_slug'] as $attribute_id => $values) {
					$attribute_id = (int)$attribute_id;
					if ($attribute_id <= 0) continue;
					$vals = [];
					if (is_array($values)) {
						foreach ($values as $v) {
							$v = trim((string)$v);
							if ($v !== '') $vals[] = $v;
						}
					} else {
						$v = trim((string)$values);
						if ($v !== '') $vals[] = $v;
					}
					$vals = array_values(array_unique($vals));
					if ($vals) {
						$attribute_slug[$attribute_id] = $vals;
					}
				}
			}

			$standard = [
				'stock' => !empty($this->request->get['stock']),
				'special' => !empty($this->request->get['special']),
				'new' => !empty($this->request->get['new']),
			];

			$option_value = [];
			if (isset($this->request->get['option_value']) && is_array($this->request->get['option_value'])) {
				foreach ($this->request->get['option_value'] as $option_id => $values) {
					$option_id = (int)$option_id;
					if ($option_id <= 0) continue;

					$vals = [];
					if (is_array($values)) {
						foreach ($values as $v) {
							$v = (int)$v;
							if ($v > 0) $vals[] = $v;
						}
					} else {
						$v = (int)$values;
						if ($v > 0) $vals[] = $v;
					}

					$vals = array_values(array_unique($vals));
					if ($vals) {
						$option_value[$option_id] = $vals;
					}
				}
			}

			// Build current /f/ selection map (slugs) to preserve in pagination/sorts/limits links
			$sel_slug = [];
			if ($manufacturer_ids) {
				$sel_slug['manufacturer'] = array_map('strval', $manufacturer_ids);
			}
			if (!empty($attribute_slug) && is_array($attribute_slug)) {
				// map attribute_id -> attribute_slug keyword
				foreach ($attribute_slug as $attr_id => $slugs) {
					$attr_id = (int)$attr_id;
					if ($attr_id <= 0 || !$slugs) continue;
					$q = $this->db->query("SELECT slug FROM `" . DB_PREFIX . "attribute_description` WHERE language_id='" . (int)$this->config->get('config_language_id') . "' AND attribute_id='" . $attr_id . "' LIMIT 1");
					if ($q->num_rows && $q->row['slug'] !== '') {
						$k = (string)$q->row['slug'];
						$sel_slug[$k] = array_values(array_unique(array_map('strval', (array)$slugs)));
					}
				}
			}
			if (!empty($option_value) && is_array($option_value)) {
				foreach ($option_value as $opt_id => $ids) {
					$opt_id = (int)$opt_id;
					if ($opt_id <= 0 || !$ids) continue;
					$opt_q = $this->db->query("SELECT slug FROM `" . DB_PREFIX . "option_description` WHERE language_id='" . (int)$this->config->get('config_language_id') . "' AND option_id='" . $opt_id . "' LIMIT 1");
					if (!$opt_q->num_rows || $opt_q->row['slug'] === '') continue;
					$opt_slug = (string)$opt_q->row['slug'];

					$vals = [];
					foreach ((array)$ids as $ov_id) {
						$ov_id = (int)$ov_id;
						if ($ov_id <= 0) continue;
						$ov_q = $this->db->query("SELECT slug FROM `" . DB_PREFIX . "option_value_description` WHERE language_id='" . (int)$this->config->get('config_language_id') . "' AND option_id='" . $opt_id . "' AND option_value_id='" . $ov_id . "' LIMIT 1");
						if ($ov_q->num_rows && $ov_q->row['slug'] !== '') {
							$vals[] = (string)$ov_q->row['slug'];
						}
					}
					$vals = array_values(array_unique(array_filter($vals)));
					if ($vals) {
						$sel_slug[$opt_slug] = $vals;
					}
				}
			}
			if (!empty($standard['stock'])) $sel_slug['stock'] = ['1'];
			if (!empty($standard['special'])) $sel_slug['special'] = ['1'];
			if (!empty($standard['new'])) $sel_slug['new'] = ['1'];

			$build_f_url = function (string $base_url) use ($sel_slug): string {
				if (!$sel_slug) return $base_url;
				ksort($sel_slug);
				$parts = [];
				foreach ($sel_slug as $k => $vals) {
					$vals = array_values(array_unique(array_filter(array_map('strval', (array)$vals), static fn($v) => $v !== '')));
					if (!$vals) continue;
					sort($vals);
					$parts[] = $k . '_' . implode(',', $vals);
				}
				if (!$parts) return $base_url;

				$base_path = $base_url;
				$base_query = '';
				$qpos = strpos($base_url, '?');
				if ($qpos !== false) {
					$base_path = substr($base_url, 0, $qpos);
					$base_query = substr($base_url, $qpos + 1);
				}

				$url = rtrim($base_path, '/') . '/f/' . implode('/', $parts);
				if ($base_query !== '') $url .= '?' . $base_query;
				return $url;
			};

			$filter_data = [
				'filter_category_id'    => $category_id,
				'filter_sub_category'   => false,
				'filter_filter'         => $filter,
				'filter_manufacturer_ids' => $manufacturer_ids,
				'filter_min_price'      => ($min_price !== null && $min_price > 0) ? $min_price : null,
				'filter_max_price'      => ($max_price !== null && $max_price > 0) ? $max_price : null,
				'filter_attribute_value' => $attribute_value,
				'filter_attribute_slug'  => $attribute_slug,
				'filter_option_value'    => $option_value,
				'filter_stock'           => $standard['stock'] ? 1 : 0,
				'filter_special'         => $standard['special'] ? 1 : 0,
				'filter_new'             => $standard['new'] ? 1 : 0,
				'sort'                  => $sort,
				'order'                 => $order,
				'start'                 => ($page - 1) * $limit,
				'limit'                 => $limit
			];

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

				// Additional images (for hover mini-gallery like OC2 theme)
				$images = [];

				foreach ($this->model_catalog_product->getImages((int)$result['product_id']) as $product_image) {
					if (!empty($product_image['image']) && is_file(DIR_IMAGE . html_entity_decode($product_image['image'], ENT_QUOTES, 'UTF-8'))) {
						$images[] = [
							'thumb' => $this->model_tool_image->resize($product_image['image'], 50, 50),
							'popup' => $this->model_tool_image->resize($product_image['image'], $this->config->get('config_image_product_width'), $this->config->get('config_image_product_height'))
						];
					}

					if (count($images) >= 4) {
						break;
					}
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

				// OC2-like "Новинка" badge: treat products added within last 60 days as new
				$is_new = false;
				if (!empty($result['date_added'])) {
					$date_added_ts = strtotime((string)$result['date_added']);
					if ($date_added_ts && (time() - $date_added_ts) <= (60 * 24 * 60 * 60)) {
						$is_new = true;
					}
				}

				$product_data = [
					'lang_code'   => $this->config->get('config_language'),
					'is_new'      => $is_new,
					'description' => $description,
					'thumb'       => $this->model_tool_image->resize($image, $this->config->get('config_image_product_width'), $this->config->get('config_image_product_height')),
					'images'      => $images,
					'price'       => $price,
					'special'     => $special,
					'tax'         => $tax,
					'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
					'href'        => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $result['product_id'] . $url)
				] + $result;

				$data['products'][] = $this->load->controller('product/thumb', $product_data);
			}

			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['sorts'] = [];

			$data['sorts'][] = [
				'text'  => $this->language->get('text_default'),
				'value' => 'p.sort_order-ASC',
				'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&sort=p.sort_order&order=ASC' . $url)
			];

			$data['sorts'][] = [
				'text'  => $this->language->get('text_name_asc'),
				'value' => 'pd.name-ASC',
				'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&sort=pd.name&order=ASC' . $url)
			];

			$data['sorts'][] = [
				'text'  => $this->language->get('text_name_desc'),
				'value' => 'pd.name-DESC',
				'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&sort=pd.name&order=DESC' . $url)
			];

			$data['sorts'][] = [
				'text'  => $this->language->get('text_price_asc'),
				'value' => 'p.price-ASC',
				'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&sort=p.price&order=ASC' . $url)
			];

			$data['sorts'][] = [
				'text'  => $this->language->get('text_price_desc'),
				'value' => 'p.price-DESC',
				'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&sort=p.price&order=DESC' . $url)
			];

			if ($this->config->get('config_review_status')) {
				$data['sorts'][] = [
					'text'  => $this->language->get('text_rating_desc'),
					'value' => 'rating-DESC',
					'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&sort=rating&order=DESC' . $url)
				];

				$data['sorts'][] = [
					'text'  => $this->language->get('text_rating_asc'),
					'value' => 'rating-ASC',
					'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&sort=rating&order=ASC' . $url)
				];
			}

			$data['sorts'][] = [
				'text'  => $this->language->get('text_model_asc'),
				'value' => 'p.model-ASC',
				'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&sort=p.model&order=ASC' . $url)
			];

			$data['sorts'][] = [
				'text'  => $this->language->get('text_model_desc'),
				'value' => 'p.model-DESC',
				'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&sort=p.model&order=DESC' . $url)
			];

			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
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
					'href'  => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . $url . '&limit=' . $value)
				];
			}

			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
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

			$pagination_base = $this->url->link('product/category', 'language=' . $this->config->get('config_language') . $url . '&page={page}');
			$data['pagination'] = $this->load->controller('common/pagination', [
				'total' => $product_total,
				'page'  => $page,
				'limit' => $limit,
				'url'   => $build_f_url($pagination_base)
			]);

			$data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($product_total - $limit)) ? $product_total : ((($page - 1) * $limit) + $limit), $product_total, ceil($product_total / $limit));

			// https://developers.google.com/search/blog/2011/09/pagination-with-relnext-and-relprev
			if ($page == 1) {
				$this->document->addLink($this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $this->request->get['path']), 'canonical');
			} else {
				$this->document->addLink($this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $this->request->get['path'] . '&page=' . $page), 'canonical');
			}

			if ($page > 1) {
				$this->document->addLink($this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $this->request->get['path'] . (($page - 2) ? '&page=' . ($page - 1) : '')), 'prev');
			}

			if ($limit && ceil($product_total / $limit) > $page) {
				$this->document->addLink($this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $this->request->get['path'] . '&page=' . ($page + 1)), 'next');
			}

			$data['sort'] = $sort;
			$data['order'] = $order;
			$data['limit'] = $limit;

			$data['continue'] = $this->url->link('common/home', 'language=' . $this->config->get('config_language'));

			// Manline FilterPro (custom) — if a module instance is bound globally, use its settings
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

			$data['filterpro_like'] = $this->load->controller('extension/manline/module/filterpro_like', $filterpro_setting);
			$data['column_left'] = $this->load->controller('common/column_left');

			// Inject our filter block into the left column (even when other modules already render there)
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

			$this->response->setOutput($this->load->view('product/category', $data));
		} else {
			return new \Opencart\System\Engine\Action('error/not_found');
		}

		return null;
	}
}
