<?php
namespace Opencart\Admin\Controller\Extension\Manline\Module;

/**
 * Custom Menu (Manline) — OC4 port with 1:1 admin UX (items + module settings)
 */
class CustomMenu extends \Opencart\System\Engine\Controller {
	private array $error = [];

	public function install(): void {
		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/manline/module/custom_menu');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/manline/module/custom_menu');
	}

	public function uninstall(): void {
		// no-op
	}

	public function index(): void {
		$this->load->language('extension/manline/module/custom_menu');

		$this->document->setTitle($this->language->get('heading_title'));

		// module instance
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
			'href' => $this->url->link('extension/manline/module/custom_menu', 'user_token=' . $this->session->data['user_token'] . ($data['module_id'] ? '&module_id=' . $data['module_id'] : ''))
		];

		// URLs
		$data['save'] = $this->url->link('extension/manline/module/custom_menu.save', 'user_token=' . $this->session->data['user_token'] . ($data['module_id'] ? '&module_id=' . $data['module_id'] : ''));
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

		$data['insert'] = $this->url->link('extension/manline/module/custom_menu.form', 'user_token=' . $this->session->data['user_token'] . '&action=add' . ($data['module_id'] ? '&module_id=' . $data['module_id'] : ''));
		$data['delete'] = $this->url->link('extension/manline/module/custom_menu.delete', 'user_token=' . $this->session->data['user_token'] . ($data['module_id'] ? '&module_id=' . $data['module_id'] : ''));

		// Settings fields
		$data['name'] = $module_info['name'] ?? '';
		$data['status'] = $module_info['status'] ?? 0;
		$data['style'] = $module_info['style'] ?? 0;
		$data['in_module'] = $module_info['in_module'] ?? [];

		// Languages
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		// Heading per language code
		$data['head'] = $module_info['head'] ?? [];

		// Menu items list
		$this->load->model('extension/manline/module/custom_menu');

		$sort = $this->request->get['sort'] ?? 'name';
		$order = $this->request->get['order'] ?? 'ASC';
		$page = (int)($this->request->get['page'] ?? 1);

		$limit = (int)$this->config->get('config_pagination_admin');
		$start = ($page - 1) * $limit;

		$data['custom_menus'] = [];

		$filter_data = [
			'sort'  => $sort,
			'order' => $order,
			'start' => $start,
			'limit' => $limit
		];

		$menu_total = $this->model_extension_manline_module_custom_menu->getTotalCustomMenuItems();
		$results = $this->model_extension_manline_module_custom_menu->getCustomMenuItems($filter_data);

		foreach ($results as $result) {
			$data['custom_menus'][] = [
				'menu_id'    => (int)$result['menu_id'],
				'name'       => $result['name'],
				'link'       => $result['link'],
				'sort_order' => (int)$result['sort_order'],
				'status'     => (int)$result['status'],
				'edit'       => $this->url->link('extension/manline/module/custom_menu.form', 'user_token=' . $this->session->data['user_token'] . '&action=edit&menu_id=' . (int)$result['menu_id'] . ($data['module_id'] ? '&module_id=' . $data['module_id'] : ''))
			];
		}

		// Flat list for in_module selector
		$data['custom_menus_flat'] = $this->model_extension_manline_module_custom_menu->getCustomMenuFlat();

		// Pagination
		$data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $menu_total,
			'page'  => $page,
			'limit' => $limit,
			'url'   => $this->url->link('extension/manline/module/custom_menu', 'user_token=' . $this->session->data['user_token'] . ($data['module_id'] ? '&module_id=' . $data['module_id'] : '') . '&page={page}')
		]);

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/manline/module/custom_menu_list', $data));
	}

	public function save(): void {
		$this->load->language('extension/manline/module/custom_menu');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/manline/module/custom_menu')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$required = [
			'module_id' => 0,
			'name'      => '',
			'status'    => 0,
			'style'     => 0,
			'in_module' => [],
			'head'      => []
		];

		$post_info = $this->request->post + $required;

		if (!oc_validate_length($post_info['name'], 3, 64)) {
			$json['error']['name'] = $this->language->get('error_name');
		}

		if (!$json) {
			$this->load->model('setting/module');

			if (empty($this->request->get['module_id'])) {
				$json['module_id'] = $this->model_setting_module->addModule('manline.custom_menu', $post_info);
			} else {
				$this->model_setting_module->editModule((int)$this->request->get['module_id'], $post_info);
				$json['module_id'] = (int)$this->request->get['module_id'];
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function delete(): void {
		$this->load->language('extension/manline/module/custom_menu');

		if (!$this->user->hasPermission('modify', 'extension/manline/module/custom_menu')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('extension/manline/module/custom_menu', 'user_token=' . $this->session->data['user_token'] . (isset($this->request->get['module_id']) ? '&module_id=' . (int)$this->request->get['module_id'] : '')));
		}

		$selected = $this->request->post['selected'] ?? [];

		$this->load->model('extension/manline/module/custom_menu');

		foreach ($selected as $menu_id) {
			$this->model_extension_manline_module_custom_menu->deleteCustomMenuItem((int)$menu_id);
		}

		$this->session->data['success'] = $this->language->get('text_success');

		$this->response->redirect($this->url->link('extension/manline/module/custom_menu', 'user_token=' . $this->session->data['user_token'] . (isset($this->request->get['module_id']) ? '&module_id=' . (int)$this->request->get['module_id'] : '') . '&tab=items'));
	}

	public function form(): void {
		$this->load->language('extension/manline/module/custom_menu');
		$this->document->setTitle($this->language->get('heading_title'));

		$action = $this->request->get['action'] ?? 'add';
		$menu_id = (int)($this->request->get['menu_id'] ?? 0);
		$module_id = (int)($this->request->get['module_id'] ?? 0);

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
			'href' => $this->url->link('extension/manline/module/custom_menu', 'user_token=' . $this->session->data['user_token'] . ($module_id ? '&module_id=' . $module_id : ''))
		];

		$data['text_form'] = $action === 'edit' ? $this->language->get('text_edit') : $this->language->get('text_add');

		$data['save'] = $this->url->link('extension/manline/module/custom_menu.item_save', 'user_token=' . $this->session->data['user_token'] . '&action=' . $action . ($menu_id ? '&menu_id=' . $menu_id : '') . ($module_id ? '&module_id=' . $module_id : ''));
		$data['back'] = $this->url->link('extension/manline/module/custom_menu', 'user_token=' . $this->session->data['user_token'] . ($module_id ? '&module_id=' . $module_id : '') . '&tab=items');

		$this->load->model('extension/manline/module/custom_menu');
		$this->load->model('localisation/language');
		$this->load->model('tool/image');

		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['custom_menu'] = $this->model_extension_manline_module_custom_menu->getCustomMenuFlat();

		$item = [];
		$descriptions = [];

		if ($action === 'edit' && $menu_id) {
			$item = $this->model_extension_manline_module_custom_menu->getCustomMenuItem($menu_id);
			$descriptions = $this->model_extension_manline_module_custom_menu->getCustomMenuDescriptions($menu_id);
		}

		$data['menu_id'] = $menu_id;
		$data['menu_description'] = $descriptions;

		$data['link'] = $item['link'] ?? '';
		$data['parent_id'] = (int)($item['parent_id'] ?? 0);
		$data['parent_parent_id'] = (int)($item['parent_parent_id'] ?? 0);
		$data['column'] = (int)($item['column'] ?? 1);
		$data['status'] = (int)($item['status'] ?? 1);
		$data['sort_order'] = (int)($item['sort_order'] ?? 0);
		$data['image'] = $item['image'] ?? '';

		if ($data['image'] && is_file(DIR_IMAGE . $data['image'])) {
			$data['thumb'] = $this->model_tool_image->resize($data['image'], 100, 100);
		} else {
			$data['thumb'] = $this->model_tool_image->resize('no_image.png', 100, 100);
		}

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);
		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/manline/module/custom_menu_form', $data));
	}

	public function item_save(): void {
		$this->load->language('extension/manline/module/custom_menu');

		$action = $this->request->get['action'] ?? 'add';
		$menu_id = (int)($this->request->get['menu_id'] ?? 0);
		$module_id = (int)($this->request->get['module_id'] ?? 0);

		if (!$this->user->hasPermission('modify', 'extension/manline/module/custom_menu')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('extension/manline/module/custom_menu', 'user_token=' . $this->session->data['user_token'] . ($module_id ? '&module_id=' . $module_id : '')));
		}

		$post = $this->request->post;

		// Basic validation: name for each language
		if (empty($post['menu_description']) || !is_array($post['menu_description'])) {
			$this->session->data['error_warning'] = $this->language->get('error_title');
			$this->response->redirect($this->url->link('extension/manline/module/custom_menu.form', 'user_token=' . $this->session->data['user_token'] . '&action=' . $action . ($menu_id ? '&menu_id=' . $menu_id : '') . ($module_id ? '&module_id=' . $module_id : '')));
		}

		$this->load->model('extension/manline/module/custom_menu');

		if ($action === 'edit' && $menu_id) {
			$this->model_extension_manline_module_custom_menu->editCustomMenuItem($menu_id, $post);
		} else {
			$this->model_extension_manline_module_custom_menu->addCustomMenuItem($post);
		}

		$this->session->data['success'] = $this->language->get('text_success');
		$this->response->redirect($this->url->link('extension/manline/module/custom_menu', 'user_token=' . $this->session->data['user_token'] . ($module_id ? '&module_id=' . $module_id : '') . '&tab=items'));
	}
}
