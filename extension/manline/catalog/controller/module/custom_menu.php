<?php
namespace Opencart\Catalog\Controller\Extension\Manline\Module;

class CustomMenu extends \Opencart\System\Engine\Controller {
	/**
	 * Render module output.
	 *
	 * @param array<string,mixed> $setting
	 */
	public function index(array $setting): string {
		$this->load->language('extension/manline/module/custom_menu');

		if (empty($setting['status'])) {
			return '';
		}

		$this->load->model('extension/manline/module/custom_menu');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$data['head'] = '';
		$lang = $this->config->get('config_language');
		if (!empty($setting['head']) && !empty($setting['head'][$lang])) {
			$data['head'] = $setting['head'][$lang];
		}

		$data['style'] = (int)($setting['style'] ?? 0);
		$data['in_header'] = !empty($setting['in_header']);

		$in_module = $setting['in_module'] ?? [];
		$in_module = is_array($in_module) ? array_map('intval', $in_module) : [];

		$all = $this->model_extension_manline_module_custom_menu->getCustomMenu();

		// Attach product cards for menu items where name == 'product' and link holds product_id
		$all = $this->attachProducts($all);

		// Filter by selected IDs but keep tree.
		$data['custom_menu'] = $this->filterTree($all, array_flip($in_module));

		if (!$data['custom_menu']) {
			return '';
		}

		return $this->load->view('extension/manline/module/custom_menu', $data);
	}

	private function attachProducts(array $tree): array {
		$w = (int)$this->config->get('config_image_product_width');
		$h = (int)$this->config->get('config_image_product_height');
		if (!$w) { $w = 200; }
		if (!$h) { $h = 200; }

		foreach ($tree as &$lvl1) {
			if (empty($lvl1['sub_menu']) || !is_array($lvl1['sub_menu'])) {
				continue;
			}

			foreach ($lvl1['sub_menu'] as &$lvl2) {
				if (empty($lvl2['sub_menu']) || !is_array($lvl2['sub_menu'])) {
					continue;
				}

				foreach ($lvl2['sub_menu'] as &$lvl3) {
					if (($lvl3['name'] ?? '') !== 'product') {
						continue;
					}

					$product_id = (int)($lvl3['link'] ?? 0);
					if (!$product_id) {
						continue;
					}

					$product = $this->model_catalog_product->getProduct($product_id);
					if (!$product) {
						continue;
					}

					$img = $product['image'] ? $this->model_tool_image->resize($product['image'], $w, $h) : $this->model_tool_image->resize('no_image.png', $w, $h);

					// old theme printed raw numbers + 'грн.'
					$price = (float)$product['price'];
					$special = (float)$product['special'];

					$href = $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product_id);

					$lvl3['product'] = [
						'product_id' => $product_id,
						'name'       => $product['name'],
						'name_short' => oc_substr($product['name'], 0, 30) . (oc_strlen($product['name']) > 30 ? '...' : ''),
						'image'      => $img,
						'href'       => $href,
						'price'      => $price,
						'special'    => $special
					];
				}
			}
		}

		return $tree;
	}

	private function filterTree(array $tree, array $allowed): array {
		// If nothing selected, behave like legacy (show everything)
		if (!$allowed) {
			return $tree;
		}

		$out = [];
		foreach ($tree as $node) {
			$id = (int)($node['id'] ?? 0);

			$children = [];
			if (!empty($node['sub_menu']) && is_array($node['sub_menu'])) {
				foreach ($node['sub_menu'] as $sub) {
					$sub_id = (int)($sub['id'] ?? 0);
					$sub_children = [];

					if (!empty($sub['sub_menu']) && is_array($sub['sub_menu'])) {
						foreach ($sub['sub_menu'] as $c) {
							$c_id = (int)($c['id'] ?? 0);
							if (isset($allowed[$c_id])) {
								$sub_children[] = $c;
							}
						}
					}

					if (isset($allowed[$sub_id]) || $sub_children) {
						$sub['sub_menu'] = $sub_children;
						$children[] = $sub;
					}
				}
			}

			if (isset($allowed[$id]) || $children) {
				$node['sub_menu'] = $children;
				$out[] = $node;
			}
		}

		return $out;
	}
}
