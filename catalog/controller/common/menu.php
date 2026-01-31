<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class Menu
 *
 * Can be called from $this->load->controller('common/menu');
 *
 * @package Opencart\Catalog\Controller\Common
 */
class Menu extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('common/menu');

		// Manline store uses a legacy CustomMenu module (editable from admin).
		// Header menu should use a specific module instance (selected in admin),
		// so we can control which items are displayed via in_module[].
		$data['custom_menu_module'] = '';
		$data['custom_menu'] = [];

		$header_module_id = (int)$this->config->get('manline_header_custom_menu_module_id');

		if ($header_module_id) {
			$this->load->model('setting/module');
			$module_info = $this->model_setting_module->getModule($header_module_id);

			if (!empty($module_info) && !empty($module_info['status'])) {
				$module_info['in_header'] = 1;
				$data['custom_menu_module'] = $this->load->controller('extension/manline/module/custom_menu', $module_info);
			}
		}

		// Fallback: if module is not bound/disabled, render full menu from legacy tables.
		// Also attach product tiles (3rd-level items with name == 'product' and link = product_id),
		// like in the legacy header.tpl.
		if (!$data['custom_menu_module']) {
			try {
				$exists = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "custom_menu'");
				if (!empty($exists->rows)) {
					$this->load->model('extension/manline/module/custom_menu');
					$this->load->model('catalog/product');
					$this->load->model('tool/image');

					$data['custom_menu'] = $this->model_extension_manline_module_custom_menu->getCustomMenu();

					// attach product cards
					$w = (int)$this->config->get('config_image_product_width');
					$h = (int)$this->config->get('config_image_product_height');
					if (!$w) { $w = 250; }
					if (!$h) { $h = 250; }

					foreach ($data['custom_menu'] as &$lvl1) {
						if (empty($lvl1['sub_menu']) || !is_array($lvl1['sub_menu'])) continue;
						foreach ($lvl1['sub_menu'] as &$lvl2) {
							if (empty($lvl2['sub_menu']) || !is_array($lvl2['sub_menu'])) continue;
							foreach ($lvl2['sub_menu'] as &$lvl3) {
								if (($lvl3['name'] ?? '') !== 'product') continue;
								$product_id = (int)($lvl3['link'] ?? 0);
								if (!$product_id) continue;
								$product = $this->model_catalog_product->getProduct($product_id);
								if (!$product) continue;

								$img = $product['image'] ? $this->model_tool_image->resize($product['image'], $w, $h) : $this->model_tool_image->resize('no_image.png', $w, $h);
								$href = $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product_id);

								$lvl3['product'] = [
									'product_id' => $product_id,
									'name'       => $product['name'],
									'name_short' => oc_substr($product['name'], 0, 30) . (oc_strlen($product['name']) > 30 ? '...' : ''),
									'image'      => $img,
									'href'       => $href,
									'price'      => (float)$product['price'],
									'special'    => (float)$product['special'],
								];
							}
						}
					}
					unset($lvl1, $lvl2, $lvl3);
				}
			} catch (\Throwable $e) {
				// ignore and fall back to standard OC menu
			}
		}

		// Fallback: standard category menu
		// Category
		$this->load->model('catalog/category');

		// Product
		$this->load->model('catalog/product');

		$data['categories'] = [];

		$categories = $this->model_catalog_category->getCategories(0);

		foreach ($categories as $category) {
			// Level 2
			$children_data = [];

			$children = $this->model_catalog_category->getCategories($category['category_id']);

			foreach ($children as $child) {
				$filter_data = [
					'filter_category_id'  => $child['category_id'],
					'filter_sub_category' => true
				];

				$children_data[] = [
					'name' => $child['name'] . ($this->config->get('config_product_count') ? ' (' . $this->model_catalog_product->getTotalProducts($filter_data) . ')' : ''),
					'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $category['category_id'] . '_' . $child['category_id'])
				];
			}

			// Level 1
			$data['categories'][] = [
				'children' => $children_data,
				'href'     => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $category['category_id'])
			] + $category;
		}

		return $this->load->view('common/menu', $data);
	}
}
