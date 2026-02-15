<?php
namespace Opencart\Admin\Controller\Extension\Manline\Module;

class Slider extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->load->language('extension/manline/module/slider');

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
				'href' => $this->url->link('extension/manline/module/slider', 'user_token=' . $this->session->data['user_token'])
			];
			$data['save'] = $this->url->link('extension/manline/module/slider.save', 'user_token=' . $this->session->data['user_token']);
		} else {
			$data['breadcrumbs'][] = [
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/manline/module/slider', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . (int)$this->request->get['module_id'])
			];
			$data['save'] = $this->url->link('extension/manline/module/slider.save', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . (int)$this->request->get['module_id']);
		}

		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

		// Module
		$module_info = [];
		if (isset($this->request->get['module_id'])) {
			$this->load->model('setting/module');
			$module_info = $this->model_setting_module->getModule((int)$this->request->get['module_id']);
		}

		$data['name'] = $module_info['name'] ?? '';
		$data['banner_id'] = $module_info['banner_id'] ?? '';
		$data['width'] = $module_info['width'] ?? 1170;
		$data['height'] = $module_info['height'] ?? 380;
		$data['dots'] = $module_info['dots'] ?? 1;
		$data['autoplay'] = $module_info['autoplay'] ?? 1;
		$data['autoplay_speed'] = $module_info['autoplay_speed'] ?? 4000;
		$data['status'] = $module_info['status'] ?? 0;

		$this->load->model('design/banner');
		$data['banners'] = $this->model_design_banner->getBanners();

		$data['module_id'] = isset($this->request->get['module_id']) ? (int)$this->request->get['module_id'] : 0;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/manline/module/slider', $data));
	}

	public function save(): void {
		$this->load->language('extension/manline/module/slider');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/manline/module/slider')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$required = [
			'module_id' => 0,
			'name' => '',
			'banner_id' => 0,
			'width' => 0,
			'height' => 0,
			'dots' => 0,
			'autoplay' => 0,
			'autoplay_speed' => 0,
			'status' => 0
		];

		$post_info = $this->request->post + $required;

		if (!oc_validate_length($post_info['name'], 3, 64)) {
			$json['error']['name'] = $this->language->get('error_name');
		}

		if (!(int)$post_info['banner_id']) {
			$json['error']['banner_id'] = $this->language->get('error_banner');
		}

		if (!(int)$post_info['width']) {
			$json['error']['width'] = $this->language->get('error_width');
		}

		if (!(int)$post_info['height']) {
			$json['error']['height'] = $this->language->get('error_height');
		}

		if (!(int)$post_info['autoplay_speed']) {
			$json['error']['autoplay_speed'] = $this->language->get('error_autoplay_speed');
		}

		if (!$json) {
			$this->load->model('setting/module');

			if (!(int)$post_info['module_id']) {
				$json['module_id'] = $this->model_setting_module->addModule('manline.slider', $post_info);
			} else {
				$this->model_setting_module->editModule((int)$post_info['module_id'], $post_info);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
