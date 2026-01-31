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

		$data['head'] = '';
		$lang = $this->config->get('config_language');
		if (!empty($setting['head']) && !empty($setting['head'][$lang])) {
			$data['head'] = $setting['head'][$lang];
		}

		$data['style'] = (int)($setting['style'] ?? 0);
		$in_module = $setting['in_module'] ?? [];
		$in_module = is_array($in_module) ? array_map('intval', $in_module) : [];

		$all = $this->model_extension_manline_module_custom_menu->getCustomMenu();

		// Filter by selected IDs but keep tree.
		$data['custom_menu'] = $this->filterTree($all, array_flip($in_module));

		if (!$data['custom_menu']) {
			return '';
		}

		return $this->load->view('extension/manline/module/custom_menu', $data);
	}

	private function filterTree(array $tree, array $allowed): array {
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
