<?php
namespace Opencart\Admin\Controller\Extension\Manline\Module;

class Recommended extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->load->language('extension/manline/module/recommended');

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
				'href' => $this->url->link('extension/manline/module/recommended', 'user_token=' . $this->session->data['user_token'])
			];
			$data['save'] = $this->url->link('extension/manline/module/recommended.save', 'user_token=' . $this->session->data['user_token']);
		} else {
			$data['breadcrumbs'][] = [
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/manline/module/recommended', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . (int)$this->request->get['module_id'])
			];
			$data['save'] = $this->url->link('extension/manline/module/recommended.save', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . (int)$this->request->get['module_id']);
		}

		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');
		$data['user_token'] = $this->session->data['user_token'];

		$module_info = [];
		$module_setting = [];
		if (isset($this->request->get['module_id'])) {
			$this->load->model('setting/module');
			$module_info = $this->model_setting_module->getModule((int)$this->request->get['module_id']);

			if (!empty($module_info['setting'])) {
				$decoded = json_decode($module_info['setting'], true);
				if (is_array($decoded)) {
					$module_setting = $decoded;
				}
			}
		}

		$data['module_id'] = isset($this->request->get['module_id']) ? (int)$this->request->get['module_id'] : 0;
		$data['name'] = $module_info['name'] ?? $this->language->get('heading_title');
		$data['status'] = $module_setting['status'] ?? 0;
		$data['blocks'] = $module_setting['blocks'] ?? [];

		if (!is_array($data['blocks'])) {
			$data['blocks'] = [];
		}

		// Normalize blocks
		foreach ($data['blocks'] as $i => $b) {
			if (!is_array($b)) {
				unset($data['blocks'][$i]);
				continue;
			}
			$data['blocks'][$i] = $b + [
				'title_ru' => '',
				'title_ua' => '',
				'product_ids' => '',
				'image_width' => 250,
				'image_height' => 375,
				'status' => 1,
				'sort_order' => 0
			];
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/manline/module/recommended', $data));
	}

	public function save(): void {
		$this->load->language('extension/manline/module/recommended');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/manline/module/recommended')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$required = [
			'module_id' => 0,
			'name' => '',
			'status' => 0,
			'blocks' => []
		];

		$post = $this->request->post + $required;

		if (!oc_validate_length($post['name'], 3, 64)) {
			$json['error']['name'] = $this->language->get('error_name');
		}

		if (!is_array($post['blocks'])) {
			$post['blocks'] = [];
		}

		// Clean blocks
		$clean = [];
		foreach ($post['blocks'] as $b) {
			if (!is_array($b)) {
				continue;
			}

			$title_ru = trim((string)($b['title_ru'] ?? ''));
			$title_ua = trim((string)($b['title_ua'] ?? ''));
			$product_ids = trim((string)($b['product_ids'] ?? ''));
			$w = (int)($b['image_width'] ?? 250);
			$h = (int)($b['image_height'] ?? 375);
			$status = !empty($b['status']) ? 1 : 0;
			$sort_order = (int)($b['sort_order'] ?? 0);

			if ($product_ids === '') {
				continue;
			}

			if ($w <= 0) $w = 250;
			if ($h <= 0) $h = 375;

			$clean[] = [
				'title_ru' => $title_ru,
				'title_ua' => $title_ua,
				'product_ids' => $product_ids,
				'image_width' => $w,
				'image_height' => $h,
				'status' => $status,
				'sort_order' => $sort_order
			];
		}

		$post['blocks'] = $clean;

		if (!$json) {
			$this->load->model('setting/module');

			if (!(int)$post['module_id']) {
				$json['module_id'] = $this->model_setting_module->addModule('manline.recommended', $post);
			} else {
				$this->model_setting_module->editModule((int)$post['module_id'], $post);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
