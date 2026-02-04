<?php
namespace Opencart\Admin\Controller\Extension\Manline\Module;

/**
 * FilterPro (Manline) — OC4 custom filter module (admin settings scaffold)
 *
 * Stage 1: admin UI to configure blocks (type/expanded/tooltip) + bind one module instance
 *          as the global category sidebar filter (like OC2 FilterPro behavior).
 */
class FilterPro extends \Opencart\System\Engine\Controller {
	private array $error = [];

	public function install(): void {
		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/manline/module/filterpro');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/manline/module/filterpro');
	}

	public function uninstall(): void {
		// no-op
	}

	public function index(): void {
		$this->load->language('extension/manline/module/filterpro');
		$this->document->setTitle($this->language->get('heading_title'));

		$module_info = [];
		if (isset($this->request->get['module_id'])) {
			$this->load->model('setting/module');
			$module_info = $this->model_setting_module->getModule((int)$this->request->get['module_id']);
		}

		$data['module_id'] = (int)($this->request->get['module_id'] ?? 0);

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/manline/module/filterpro', 'user_token=' . $this->session->data['user_token'] . ($data['module_id'] ? '&module_id=' . $data['module_id'] : ''))
		];

		$data['save'] = $this->url->link('extension/manline/module/filterpro.save', 'user_token=' . $this->session->data['user_token'] . ($data['module_id'] ? '&module_id=' . $data['module_id'] : ''));
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

		$data['name'] = $module_info['name'] ?? '';
		$data['status'] = $module_info['status'] ?? 0;

		// Default blocks (based on OC2 FilterPro UI)
		$default_blocks = [
			[
				'key' => 'price',
				'label' => $this->language->get('text_block_price'),
				'display' => 'slider',
				'expanded' => 1,
				'sort_order' => 10,
				'tooltip' => []
			],
			[
				'key' => 'manufacturer',
				'label' => $this->language->get('text_block_manufacturer'),
				'display' => 'checkbox',
				'expanded' => 1,
				'sort_order' => 20,
				'tooltip' => []
			],
			[
				'key' => 'size',
				'label' => $this->language->get('text_block_size'),
				'display' => 'checkbox',
				'expanded' => 1,
				'sort_order' => 30,
				'tooltip' => []
			],
			[
				'key' => 'color',
				'label' => $this->language->get('text_block_color'),
				'display' => 'image',
				'expanded' => 1,
				'sort_order' => 40,
				'tooltip' => []
			],
			[
				'key' => 'style',
				'label' => $this->language->get('text_block_style'),
				'display' => 'checkbox',
				'expanded' => 0,
				'sort_order' => 50,
				'tooltip' => []
			],
		];

		$data['display_options'] = [
			['value' => 'hide', 'text' => $this->language->get('text_display_hide')],
			['value' => 'checkbox', 'text' => $this->language->get('text_display_checkbox')],
			['value' => 'list', 'text' => $this->language->get('text_display_list')],
			['value' => 'image', 'text' => $this->language->get('text_display_image')],
			['value' => 'slider', 'text' => $this->language->get('text_display_slider')],
		];

		$blocks = $module_info['blocks'] ?? $default_blocks;

		// Backward-compat: if blocks are stored as associative array, convert to list
		if (is_array($blocks) && $blocks) {
			$first_key = array_key_first($blocks);
			if ($first_key !== null && !is_int($first_key)) {
				$list = [];
				$sort = 10;
				foreach ($blocks as $k => $b) {
					if (!is_array($b)) continue;
					$list[] = [
						'key' => (string)$k,
						'label' => (string)($b['label'] ?? (string)$k),
						'display' => (string)($b['display'] ?? 'checkbox'),
						'expanded' => !empty($b['expanded']) ? 1 : 0,
						'sort_order' => (int)($b['sort_order'] ?? $sort),
						'tooltip' => (is_array($b['tooltip'] ?? null) ? $b['tooltip'] : [])
					];
					$sort += 10;
				}
				$blocks = $list;
			}
		}

		// Ensure stable ordering
		usort($blocks, static function ($a, $b) {
			$sa = (int)($a['sort_order'] ?? 0);
			$sb = (int)($b['sort_order'] ?? 0);
			return $sa <=> $sb;
		});

		$data['blocks'] = $blocks;

		// Languages
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		// Global binding (use one module instance on category pages)
		$this->load->model('setting/setting');
		$current_id = (int)$this->model_setting_setting->getValue('manline_filterpro_module_id');
		$data['use_globally'] = $data['module_id'] && $current_id === $data['module_id'];

		$data['user_token'] = $this->session->data['user_token'];
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		// Use dedicated admin template path
		$this->response->setOutput($this->load->view('extension/manline/module/filterpro', $data));
	}

	public function save(): void {
		$this->load->language('extension/manline/module/filterpro');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/manline/module/filterpro')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$required = [
			'name' => '',
			'status' => 0,
			'blocks' => [],
			'use_globally' => 0
		];

		$post_info = $this->request->post + $required;

		if (!oc_validate_length($post_info['name'], 3, 64)) {
			$json['error']['name'] = $this->language->get('error_name');
		}

		// Normalize blocks (store as ordered list)
		$norm = [];
		if (is_array($post_info['blocks'])) {
			foreach ($post_info['blocks'] as $i => $b) {
				if (!is_array($b)) continue;
				$key = (string)($b['key'] ?? $i);
				$key = trim($key);
				// allow keys like option:14 / attribute:23 / manufacturer / price etc.
				$key = preg_replace('/[^a-z0-9_\-:]/i', '', $key);
				if ($key === '') continue;

				$tooltip = $b['tooltip'] ?? [];
				if (!is_array($tooltip)) {
					$tooltip = ['' => (string)$tooltip];
				}

				$norm[] = [
					'key' => $key,
					'label' => (string)($b['label'] ?? $key),
					'display' => (string)($b['display'] ?? 'checkbox'),
					'expanded' => !empty($b['expanded']) ? 1 : 0,
					'sort_order' => (int)($b['sort_order'] ?? ($i * 10)),
					'tooltip' => $tooltip
				];
			}
		}

		usort($norm, static function ($a, $b) {
			return (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0);
		});

		$post_info['blocks'] = $norm;

		if (!$json) {
			$this->load->model('setting/module');

			if (empty($this->request->get['module_id'])) {
				$json['module_id'] = $this->model_setting_module->addModule('manline.filterpro', $post_info);
			} else {
				$this->model_setting_module->editModule((int)$this->request->get['module_id'], $post_info);
				$json['module_id'] = (int)$this->request->get['module_id'];
			}

			// Persist global binding
			$this->load->model('setting/setting');
			$current = $this->model_setting_setting->getSetting('manline');
			$selected_id = (int)$json['module_id'];
			$current_id = (int)$this->model_setting_setting->getValue('manline_filterpro_module_id');

			if (!empty($post_info['use_globally'])) {
				$current['manline_filterpro_module_id'] = $selected_id;
				$this->model_setting_setting->editSetting('manline', $current);
			} else {
				if ($current_id === $selected_id) {
					$current['manline_filterpro_module_id'] = 0;
					$this->model_setting_setting->editSetting('manline', $current);
				}
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
