<?php
namespace Opencart\Admin\Controller\Extension\Manline\Feed;

/**
 * Marketplace feeds (Manline) — admin UI for the Rozetka / Prom.ua feeds that
 * were previously only configurable by hand-editing the product_feed table.
 */
class ProductFeed extends \Opencart\System\Engine\Controller {
	private const ROUTE = 'extension/manline/feed/product_feed';

	public function install(): void {
		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', self::ROUTE);
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', self::ROUTE);

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('feed_product_feed', ['feed_product_feed_status' => 1]);
	}

	public function uninstall(): void {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('feed_product_feed');
	}

	public function index(): void {
		$this->load->language(self::ROUTE);

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = $this->breadcrumbs();

		$this->load->model('extension/manline/feed/product_feed');

		$data['feeds'] = [];

		foreach ($this->model_extension_manline_feed_product_feed->getFeeds() as $result) {
			$data['feeds'][] = [
				'feed_name'     => $result['feed_name'],
				'shortname'     => $result['feed_shortname'],
				'format'        => $result['format'],
				'status'        => (int)$result['status'],
				'categories'    => count($this->parseIdList($result['categories'])),
				'manufacturers' => count($this->parseIdList($result['manufacturers'])),
				'sizes'         => (int)$result['size_option_id'] > 0,
				'url'           => $this->feedUrl((string)$result['feed_shortname']),
				'edit'          => $this->url->link(self::ROUTE . '.form', 'user_token=' . $this->session->data['user_token'] . '&product_feed_id=' . (int)$result['product_feed_id'])
			];
		}

		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed');

		$data['success'] = $this->flash('success');
		$data['error_warning'] = $this->flash('error_warning');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/manline/feed/product_feed_list', $data));
	}

	public function form(): void {
		$this->load->language(self::ROUTE);

		$this->document->setTitle($this->language->get('heading_title'));

		$product_feed_id = (int)($this->request->get['product_feed_id'] ?? 0);

		$this->load->model('extension/manline/feed/product_feed');

		$feed = $this->model_extension_manline_feed_product_feed->getFeed($product_feed_id);

		if (!$feed) {
			$this->session->data['error_warning'] = $this->language->get('error_feed');
			$this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token']));
		}

		$data['breadcrumbs'] = $this->breadcrumbs();

		$data['save'] = $this->url->link(self::ROUTE . '.save', 'user_token=' . $this->session->data['user_token'] . '&product_feed_id=' . $product_feed_id);
		$data['back'] = $this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token']);

		$data['product_feed_id'] = $product_feed_id;
		$data['feed_name'] = $feed['feed_name'];
		$data['shortname'] = $feed['feed_shortname'];
		$data['format'] = $feed['format'];
		$data['status'] = (int)$feed['status'];
		$data['language_id'] = (int)$feed['language_id'];
		$data['currency'] = $feed['currency'];
		$data['size_option_id'] = (int)$feed['size_option_id'];
		$data['image_width'] = (int)$feed['image_width'];
		$data['image_height'] = (int)$feed['image_height'];
		$data['in_stock_only'] = trim((string)$feed['sql_code']) !== '';
		$data['url'] = $this->feedUrl((string)$feed['feed_shortname']);

		$data['feed_category'] = $this->parseIdList($feed['categories']);
		$data['feed_manufacturer'] = $this->parseIdList($feed['manufacturers']);

		$this->load->model('localisation/language');
		$this->load->model('localisation/currency');
		$this->load->model('catalog/category');
		$this->load->model('catalog/manufacturer');
		$this->load->model('catalog/option');

		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['currencies'] = $this->model_localisation_currency->getCurrencies();
		$data['categories'] = $this->model_catalog_category->getCategories(['start' => 0, 'limit' => 1000]);
		$data['manufacturers'] = $this->model_catalog_manufacturer->getManufacturers(['start' => 0, 'limit' => 1000]);
		$data['options'] = $this->model_catalog_option->getOptions(['start' => 0, 'limit' => 1000]);

		$data['error_warning'] = $this->flash('error_warning');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/manline/feed/product_feed_form', $data));
	}

	public function save(): void {
		$this->load->language(self::ROUTE);

		$product_feed_id = (int)($this->request->get['product_feed_id'] ?? 0);

		if (!$this->user->hasPermission('modify', self::ROUTE)) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token']));
		}

		$post = $this->request->post;

		$categories = array_map('intval', (array)($post['feed_category'] ?? []));

		// An empty category list would silently publish an empty feed.
		if (!$categories) {
			$this->session->data['error_warning'] = $this->language->get('error_category');
			$this->response->redirect($this->url->link(self::ROUTE . '.form', 'user_token=' . $this->session->data['user_token'] . '&product_feed_id=' . $product_feed_id));
		}

		$this->load->model('extension/manline/feed/product_feed');

		$this->model_extension_manline_feed_product_feed->editFeed($product_feed_id, [
			'feed_name'      => trim((string)($post['feed_name'] ?? '')),
			'format'         => ($post['format'] ?? 'yml') === 'prom' ? 'prom' : 'yml',
			'status'         => !empty($post['status']) ? 1 : 0,
			'language_id'    => (int)($post['language_id'] ?? 0),
			'currency'       => strtoupper(substr((string)($post['currency'] ?? 'UAH'), 0, 3)),
			'categories'     => implode(',', $categories),
			'manufacturers'  => implode(',', array_map('intval', (array)($post['feed_manufacturer'] ?? []))),
			'sql_code'       => !empty($post['in_stock_only']) ? 'p.quantity > 0' : '',
			'size_option_id' => (int)($post['size_option_id'] ?? 0),
			'image_width'    => max(1, (int)($post['image_width'] ?? 600)),
			'image_height'   => max(1, (int)($post['image_height'] ?? 600))
		]);

		$this->session->data['success'] = $this->language->get('text_success');

		$this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token']));
	}

	private function breadcrumbs(): array {
		return [
			[
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
			],
			[
				'text' => $this->language->get('text_extension'),
				'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed')
			],
			[
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token'])
			]
		];
	}

	private function feedUrl(string $shortname): string {
		$store_url = defined('HTTP_CATALOG') ? HTTP_CATALOG : (string)$this->config->get('config_url');

		return rtrim($store_url, '/') . '/index.php?route=extension/manline/feed/product_feed&shortname=' . $shortname;
	}

	/**
	 * @return list<int>
	 */
	private function parseIdList(?string $list): array {
		if ($list === null || $list === '') return [];

		return array_values(array_filter(array_map('intval', explode(',', $list))));
	}

	private function flash(string $key): string {
		$value = (string)($this->session->data[$key] ?? '');

		unset($this->session->data[$key]);

		return $value;
	}
}
