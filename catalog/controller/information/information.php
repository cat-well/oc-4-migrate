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

			$data['information_id'] = $information_id;

			$data['heading_title'] = $information_info['title'];

			$data['description'] = html_entity_decode($information_info['description'], ENT_QUOTES, 'UTF-8');

			$data['continue'] = $this->url->link('common/home', 'language=' . $language);

			// Manline: special legacy-like information pages (hardcoded templates in OC2 theme)
			$lang_code = (string)$language;
			$data['is_ua'] = in_array($lang_code, ['uk-ua', 'ua'], true);

			// Track order page (legacy information_id=7): allow GET nomer_zakaza and show tracking status
			$data['track'] = [];
			if ((int)$information_id === 7) {
				$nomer = trim((string)($this->request->get['nomer_zakaza'] ?? ''));
				$data['track']['order_id'] = $nomer;

				if ($nomer !== '' && ctype_digit($nomer)) {
					$oid = (int)$nomer;

					// Basic order status info
					$q = $this->db->query(
						"SELECT o.order_id, o.tracking, o.comment, os.name AS status_name " .
						"FROM `" . DB_PREFIX . "order` o " .
						"LEFT JOIN `" . DB_PREFIX . "order_status` os ON (os.order_status_id = o.order_status_id AND os.language_id = '" . (int)$this->config->get('config_language_id') . "') " .
						"WHERE o.order_id = '" . (int)$oid . "' LIMIT 1"
					);

					if (!empty($q->num_rows)) {
						$data['track']['found'] = true;
						$data['track']['tracking'] = (string)($q->row['tracking'] ?? '');
						$data['track']['comment'] = (string)($q->row['comment'] ?? '');
						$data['track']['status_name'] = (string)($q->row['status_name'] ?? '');

						// Nova Poshta API tracking (if we have API key + tracking number)
						$api_key = trim((string)$this->config->get('shipping_novaposhta_api_key'));
						$doc = trim((string)($q->row['tracking'] ?? ''));

						if ($api_key !== '' && $doc !== '') {
							try {
								$payload = [
									'apiKey' => $api_key,
									'modelName' => 'TrackingDocument',
									'calledMethod' => 'getStatusDocuments',
									'methodProperties' => [
										'Documents' => [
											['DocumentNumber' => $doc]
										]
									]
								];

								$ch = curl_init('https://api.novaposhta.ua/v2.0/json/');
								curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
								curl_setopt($ch, CURLOPT_POST, true);
								curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
								curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
								$resp = curl_exec($ch);
								$err = curl_error($ch);
								curl_close($ch);

								if ($resp && !$err) {
									$j = json_decode($resp, true);
									if (is_array($j) && !empty($j['success']) && !empty($j['data'][0]) && is_array($j['data'][0])) {
										$data['track']['np'] = $j['data'][0];
									}
								}
							} catch (\Throwable $e) {
								// ignore NP errors, still show order status
							}
						}
					} else {
						$data['track']['found'] = false;
					}
				}
			}

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

			// Manline theme: information.twig {% include %}'s a handful of
			// page-specific child templates (o_nas / otsledit /
			// sotrudnichestvo / ishem_modelej) that each use
			// `{{ is_ua ? 'UA' : 'RU' }}` ternaries for inline copy.
			// `is_ua` is not a global in OC4 (per legacy convention it was
			// only set by filterpro_like.php on its own template's data),
			// so without this assignment every UA page silently rendered
			// the Russian branch. Setting it here makes all four pages
			// flip correctly in one go.
			$data['is_ua'] = ($this->config->get('config_language') === 'uk-ua');

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
