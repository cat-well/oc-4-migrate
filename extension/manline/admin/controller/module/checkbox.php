<?php
namespace Opencart\Admin\Controller\Extension\Manline\Module;

class Checkbox extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->load->language('extension/manline/module/checkbox');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')
		];

		if (!isset($this->request->get['module_id'])) {
			$data['breadcrumbs'][] = [
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/manline/module/checkbox', 'user_token=' . $this->session->data['user_token'])
			];
			$data['save'] = $this->url->link('extension/manline/module/checkbox.save', 'user_token=' . $this->session->data['user_token']);
		} else {
			$data['breadcrumbs'][] = [
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/manline/module/checkbox', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . (int)$this->request->get['module_id'])
			];
			$data['save'] = $this->url->link('extension/manline/module/checkbox.save', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . (int)$this->request->get['module_id']);
		}

		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

		$module_info = [];
		if (isset($this->request->get['module_id'])) {
			$this->load->model('setting/module');
			$module_info = $this->model_setting_module->getModule((int)$this->request->get['module_id']);
		}

		$data['name'] = $module_info['name'] ?? 'Checkbox';
		$data['status'] = $module_info['status'] ?? 0;
		$data['api_url'] = $module_info['api_url'] ?? 'https://api.checkbox.ua';
		$data['auth_method'] = $module_info['auth_method'] ?? 'pin';
		$data['license_key'] = $module_info['license_key'] ?? '';
		$data['cashier_pin'] = $module_info['cashier_pin'] ?? '';
		$data['cashier_login'] = $module_info['cashier_login'] ?? '';
		$data['cashier_password'] = $module_info['cashier_password'] ?? '';
		$data['client_name'] = $module_info['client_name'] ?? 'Manline OpenCart';
		$data['client_version'] = $module_info['client_version'] ?? 'oc4';

		$data['module_id'] = isset($this->request->get['module_id']) ? (int)$this->request->get['module_id'] : 0;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/manline/module/checkbox', $data));
	}

	public function save(): void {
		$this->load->language('extension/manline/module/checkbox');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/manline/module/checkbox')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$required = [
			'module_id' => 0,
			'name' => '',
			'status' => 0,
			'api_url' => '',
			'auth_method' => 'pin',
			'license_key' => '',
			'cashier_pin' => '',
			'cashier_login' => '',
			'cashier_password' => '',
			'client_name' => '',
			'client_version' => ''
		];

		$post_info = $this->request->post + $required;

		if (!oc_validate_length($post_info['name'], 3, 64)) {
			$json['error']['name'] = $this->language->get('error_name');
		}

		if (!filter_var($post_info['api_url'], FILTER_VALIDATE_URL)) {
			$json['error']['api_url'] = $this->language->get('error_api_url');
		}

		if (!in_array((string)$post_info['auth_method'], ['pin', 'password'], true)) {
			$post_info['auth_method'] = 'pin';
		}

		if (!$json) {
			$this->load->model('setting/module');

			if (!(int)$post_info['module_id']) {
				$json['module_id'] = $this->model_setting_module->addModule('manline.checkbox', $post_info);
			} else {
				$this->model_setting_module->editModule((int)$post_info['module_id'], $post_info);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
