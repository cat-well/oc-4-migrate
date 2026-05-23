<?php
namespace Opencart\Catalog\Controller\Product;
/**
 * Class Related
 *
 * Can be loaded using $this->load->controller('product/related');
 *
 * @package Opencart\Catalog\Controller\Product
 */
class Related extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return ?\Opencart\System\Engine\Action
	 */
	public function index(): string {
		$this->load->language('product/related');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		if (isset($this->request->get['product_id'])) {
			$product_id = (int)$this->request->get['product_id'];
		} else {
			$product_id = 0;
		}

		$data['products'] = [];

		$results = $this->model_catalog_product->getRelated($product_id);

		// Manline migration: product_related table may be empty; use fallback to show something.
		if (!$results) {
			// Try simple fallback: same manufacturer, random-ish selection
			$man_q = $this->db->query("SELECT manufacturer_id FROM `" . DB_PREFIX . "product` WHERE product_id='" . (int)$product_id . "' LIMIT 1");
			$manufacturer_id = $man_q->num_rows ? (int)$man_q->row['manufacturer_id'] : 0;
			if ($manufacturer_id) {
				$q = $this->db->query(
					"SELECT p.product_id, pd.name, pd.description, p.image, p.price, p.tax_class_id, p.model, p.date_added, p.minimum, p.quantity, p.status, p.sort_order, 0 special " .
					"FROM `" . DB_PREFIX . "product` p " .
					"JOIN `" . DB_PREFIX . "product_description` pd ON pd.product_id=p.product_id AND pd.language_id='" . (int)$this->config->get('config_language_id') . "' " .
					"WHERE p.product_id != '" . (int)$product_id . "' AND p.manufacturer_id='" . (int)$manufacturer_id . "' AND p.status='1' AND p.date_available<=NOW() " .
					"ORDER BY p.date_added DESC LIMIT 12"
				);
				$results = $q->rows;
			}
		}

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

			// Extend for OC2-like related carousel (Manline)
			$date_added_num = 0;
			if (!empty($result['date_added'])) {
				$date_added_num = strtotime((string)$result['date_added']);
			}
			$is_new = false;
			if ($date_added_num) {
				$is_new = ((time() - $date_added_num) / (60 * 60 * 24)) <= 60;
			}

			// In-stock sizes list for option_id=14
			$product_polt_options = '';
			$size_q = $this->db->query(
				"SELECT ovd.name " .
				"FROM `" . DB_PREFIX . "product_option_value` pov " .
				"JOIN `" . DB_PREFIX . "option_value_description` ovd ON ovd.option_value_id=pov.option_value_id AND ovd.option_id='14' AND ovd.language_id='" . (int)$this->config->get('config_language_id') . "' " .
				"WHERE pov.product_id='" . (int)$result['product_id'] . "' AND pov.option_id='14' AND pov.quantity > 0 " .
				"ORDER BY ovd.name"
			);
			$has_in_stock_size = !empty($size_q->rows);
			if ($has_in_stock_size) {
				$names = [];
				foreach ($size_q->rows as $r) {
					if (!empty($r['name'])) $names[] = (string)$r['name'];
				}
				$names = array_values(array_unique($names));
				$product_polt_options = implode(', ', $names);
			}

			// Stock badge for the carousel card. A product is "in stock" when:
			//   - at least one size (option_id=14) has quantity > 0, OR
			//   - the product does not track stock at all (subtract=0), OR
			//   - the bare product row has quantity > 0 (non-size products).
			// Without this, related.twig used to render "Є в наявності" on
			// every card regardless of real stock — clicking through landed
			// on a product detail page that correctly said "Немає в наявності",
			// which is exactly the mismatch a user reported.
			$product_quantity = (int)($result['quantity'] ?? 0);
			$product_subtract = (int)($result['subtract'] ?? 1);
			$in_stock = $has_in_stock_size
				|| ($product_subtract === 0)
				|| ($product_subtract === 1 && $product_quantity > 0);

			$product_data = [
				'thumb'       => $this->model_tool_image->resize($image, $this->config->get('config_image_related_width'), $this->config->get('config_image_related_height')),
				'description' => $description,
				'price'       => $price,
				'special'     => $special,
				'tax'         => $tax,
				'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
				'href'        => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $result['product_id']),
				'model'       => $result['model'] ?? '',
				'date_added_num' => $date_added_num,
				'is_new'      => $is_new,
				'product_polt_options' => $product_polt_options,
				'in_stock'    => $in_stock
			] + $result;

			// For Manline theme related carousel we pass raw product data into twig
			$data['products'][] = $product_data;
		}

		// Manline twig (product/related.twig) uses {% if lang == 'uk-ua' %}
		// for inline UA/RU strings ("Схожі товари" / "Похожие товары" and
		// 4 others). Independently-loaded views (this one is invoked via
		// $this->load->controller('product/related') from product.twig)
		// do NOT inherit $data['lang'] from the parent header controller,
		// so without this assignment the ternary always falls to the
		// Russian branch on UA pages.
		//
		// IMPORTANT: must be the full config_language code ('uk-ua' /
		// 'ru-ru'), not $this->language->get('code') which resolves to
		// the SHORT form ('uk' / 'ru') from the language file's $_['code']
		// key. The Twig conditionals compare against the full form, so
		// language->get('code') silently fails every condition.
		$data['lang'] = (string)$this->config->get('config_language');

		return $this->load->view('product/related', $data);

	}
}
