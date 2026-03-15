<?php
namespace Opencart\Catalog\Controller\Information;
/**
 * Class Information
 *
 * @package Opencart\Catalog\Controller\Information
 */
class Information extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return ?\Opencart\System\Engine\Action
	 */
	public function index(): ?\Opencart\System\Engine\Action {
		$this->load->language('information/information');

		if (isset($this->request->get['information_id'])) {
			$information_id = (int)$this->request->get['information_id'];
		} else {
			$information_id = 0;
		}

		$this->load->model('catalog/information');

		$information_info = $this->model_catalog_information->getInformation($information_id);

		if ($information_info) {
			$this->document->setTitle($information_info['meta_title']);
			$this->document->setDescription($information_info['meta_description']);
			$this->document->setKeywords($information_info['meta_keyword']);

			$data['breadcrumbs'] = [];

			$language = (string)($this->request->get['language'] ?? ($this->session->data['language'] ?? $this->config->get('config_language')));

			$data['breadcrumbs'][] = [
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home', 'language=' . $language)
			];

			$data['breadcrumbs'][] = [
				'text' => $information_info['title'],
				'href' => $this->url->link('information/information', 'language=' . $language . '&information_id=' . $information_id)
			];

			$data['heading_title'] = $information_info['title'];

			$data['description'] = html_entity_decode($information_info['description'], ENT_QUOTES, 'UTF-8');

			$data['continue'] = $this->url->link('common/home', 'language=' . $language);

			// Manline: help/info menu (left sidebar) - mimic OC2 help navigation
			$data['help_nav'] = [];
			$help_ids = [8, 9, 10, 11, 12, 13, 14, 16];

			foreach ($help_ids as $help_id) {
				$info = $this->model_catalog_information->getInformation((int)$help_id);
				if (!$info) {
					continue;
				}

				$data['help_nav'][] = [
					'information_id' => (int)$help_id,
					'title' => (string)$info['title'],
					'href' => $this->url->link('information/information', 'language=' . $language . '&information_id=' . (int)$help_id),
					'active' => ((int)$help_id === (int)$information_id)
				];
			}

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('information/information', $data));
		} else {
			return new \Opencart\System\Engine\Action('error/not_found');
		}

		return null;
	}

	/**
	 * Info
	 *
	 * @return void
	 */
	public function info(): void {
		if (isset($this->request->get['information_id'])) {
			$information_id = (int)$this->request->get['information_id'];
		} else {
			$information_id = 0;
		}

		$this->load->model('catalog/information');

		$information_info = $this->model_catalog_information->getInformation($information_id);

		if ($information_info) {
			$data['title'] = $information_info['title'];
			$data['description'] = html_entity_decode($information_info['description'], ENT_QUOTES, 'UTF-8');

			$this->response->addHeader('X-Robots-Tag: noindex');
			$this->response->setOutput($this->load->view('information/information_info', $data));
		}
	}
}
