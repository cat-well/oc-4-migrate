<?php
namespace Opencart\Admin\Controller\Sale;
/**
 * Class Order
 *
 * @package Opencart\Admin\Controller\Sale
 */
class Order extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('sale/order');

		if (isset($this->request->get['filter_order_id'])) {
			$filter_order_id = (int)$this->request->get['filter_order_id'];
		} else {
			$filter_order_id = '';
		}

		if (isset($this->request->get['filter_customer_id'])) {
			$filter_customer_id = $this->request->get['filter_customer_id'];
		} else {
			$filter_customer_id = '';
		}

		if (isset($this->request->get['filter_customer'])) {
			$filter_customer = $this->request->get['filter_customer'];
		} else {
			$filter_customer = '';
		}

		if (isset($this->request->get['filter_store_id'])) {
			$filter_store_id = (int)$this->request->get['filter_store_id'];
		} else {
			$filter_store_id = '';
		}

		if (isset($this->request->get['filter_order_status'])) {
			$filter_order_status = $this->request->get['filter_order_status'];
		} else {
			$filter_order_status = '';
		}

		if (isset($this->request->get['filter_order_status_id'])) {
			$filter_order_status_id = (int)$this->request->get['filter_order_status_id'];
		} else {
			$filter_order_status_id = '';
		}

		if (isset($this->request->get['filter_total'])) {
			$filter_total = $this->request->get['filter_total'];
		} else {
			$filter_total = '';
		}

		if (isset($this->request->get['filter_date_from'])) {
			$filter_date_from = $this->request->get['filter_date_from'];
		} else {
			$filter_date_from = '';
		}

		if (isset($this->request->get['filter_date_to'])) {
			$filter_date_to = $this->request->get['filter_date_to'];
		} else {
			$filter_date_to = '';
		}

		if (isset($this->request->get['filter_date_modified_from'])) {
			$filter_date_modified_from = $this->request->get['filter_date_modified_from'];
		} else {
			$filter_date_modified_from = '';
		}

		if (isset($this->request->get['filter_date_modified_to'])) {
			$filter_date_modified_to = $this->request->get['filter_date_modified_to'];
		} else {
			$filter_date_modified_to = '';
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$url = '';

		if (isset($this->request->get['filter_order_id'])) {
			$url .= '&filter_order_id=' . $this->request->get['filter_order_id'];
		}

		if (isset($this->request->get['filter_customer_id'])) {
			$url .= '&filter_customer_id=' . (int)$this->request->get['filter_customer_id'];
		}

		if (isset($this->request->get['filter_customer'])) {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_store_id'])) {
			$url .= '&filter_store_id=' . (int)$this->request->get['filter_store_id'];
		}

		if (isset($this->request->get['filter_order_status'])) {
			$url .= '&filter_order_status=' . $this->request->get['filter_order_status'];
		}

		if (isset($this->request->get['filter_order_status_id'])) {
			$url .= '&filter_order_status_id=' . (int)$this->request->get['filter_order_status_id'];
		}

		if (isset($this->request->get['filter_total'])) {
			$url .= '&filter_total=' . $this->request->get['filter_total'];
		}

		if (isset($this->request->get['filter_date_from'])) {
			$url .= '&filter_date_from=' . $this->request->get['filter_date_from'];
		}

		if (isset($this->request->get['filter_date_to'])) {
			$url .= '&filter_date_to=' . $this->request->get['filter_date_to'];
		}

		if (isset($this->request->get['filter_date_modified_from'])) {
			$url .= '&filter_date_modified_from=' . $this->request->get['filter_date_modified_from'];
		}

		if (isset($this->request->get['filter_date_modified_to'])) {
			$url .= '&filter_date_modified_to=' . $this->request->get['filter_date_modified_to'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'] . $url)
		];

		$data['add'] = $this->url->link('sale/order.info', 'user_token=' . $this->session->data['user_token'] . $url);
		$data['delete'] = $this->url->link('sale/order.delete', 'user_token=' . $this->session->data['user_token'] . $url);
		$data['invoice'] = $this->url->link('sale/order.invoice', 'user_token=' . $this->session->data['user_token']);
		$data['shipping'] = $this->url->link('sale/order.shipping', 'user_token=' . $this->session->data['user_token']);

		$data['list'] = $this->getList();

		// Store
		$data['stores'] = [];

		$data['stores'][] = [
			'store_id' => 0,
			'name'     => $this->language->get('text_default')
		];

		$this->load->model('setting/store');

		$results = $this->model_setting_store->getStores();

		foreach ($results as $result) {
			$data['stores'][] = $result;
		}

		// Order Status
		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$data['filter_order_id'] = $filter_order_id;
		$data['filter_customer_id'] = $filter_customer_id;
		$data['filter_customer'] = $filter_customer;
		$data['filter_store_id'] = $filter_store_id;
		$data['filter_order_status'] = $filter_order_status;
		$data['filter_order_status_id'] = $filter_order_status_id;
		$data['filter_total'] = $filter_total;
		$data['filter_date_from'] = $filter_date_from;
		$data['filter_date_to'] = $filter_date_to;
		$data['filter_date_modified_from'] = $filter_date_modified_from;
		$data['filter_date_modified_to'] = $filter_date_modified_to;

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('sale/order', $data));
	}

	/**
	 * List
	 *
	 * @return void
	 */
	public function list(): void {
		$this->load->language('sale/order');

		$this->response->setOutput($this->getList());
	}

	/**
	 * Get List
	 *
	 * @return string
	 */
	public function getList(): string {
		if (isset($this->request->get['filter_order_id'])) {
			$filter_order_id = (int)$this->request->get['filter_order_id'];
		} else {
			$filter_order_id = '';
		}

		if (isset($this->request->get['filter_customer_id'])) {
			$filter_customer_id = $this->request->get['filter_customer_id'];
		} else {
			$filter_customer_id = '';
		}

		if (isset($this->request->get['filter_customer'])) {
			$filter_customer = $this->request->get['filter_customer'];
		} else {
			$filter_customer = '';
		}

		if (isset($this->request->get['filter_store_id'])) {
			$filter_store_id = (int)$this->request->get['filter_store_id'];
		} else {
			$filter_store_id = '';
		}

		if (isset($this->request->get['filter_order_status'])) {
			$filter_order_status = $this->request->get['filter_order_status'];
		} else {
			$filter_order_status = '';
		}

		if (isset($this->request->get['filter_order_status_id'])) {
			$filter_order_status_id = (int)$this->request->get['filter_order_status_id'];
		} else {
			$filter_order_status_id = '';
		}

		if (isset($this->request->get['filter_total'])) {
			$filter_total = $this->request->get['filter_total'];
		} else {
			$filter_total = '';
		}

		if (isset($this->request->get['filter_date_from'])) {
			$filter_date_from = $this->request->get['filter_date_from'];
		} else {
			$filter_date_from = '';
		}

		if (isset($this->request->get['filter_date_to'])) {
			$filter_date_to = $this->request->get['filter_date_to'];
		} else {
			$filter_date_to = '';
		}

		if (isset($this->request->get['filter_date_modified_from'])) {
			$filter_date_modified_from = $this->request->get['filter_date_modified_from'];
		} else {
			$filter_date_modified_from = '';
		}

		if (isset($this->request->get['filter_date_modified_to'])) {
			$filter_date_modified_to = $this->request->get['filter_date_modified_to'];
		} else {
			$filter_date_modified_to = '';
		}

		if (isset($this->request->get['sort'])) {
			$sort = (string)$this->request->get['sort'];
		} else {
			$sort = 'o.order_id';
		}

		if (isset($this->request->get['order'])) {
			$order = (string)$this->request->get['order'];
		} else {
			$order = 'DESC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['filter_order_id'])) {
			$url .= '&filter_order_id=' . $this->request->get['filter_order_id'];
		}

		if (isset($this->request->get['filter_customer_id'])) {
			$url .= '&filter_customer_id=' . (int)$this->request->get['filter_customer_id'];
		}

		if (isset($this->request->get['filter_customer'])) {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_store_id'])) {
			$url .= '&filter_store_id=' . (int)$this->request->get['filter_store_id'];
		}

		if (isset($this->request->get['filter_order_status'])) {
			$url .= '&filter_order_status=' . $this->request->get['filter_order_status'];
		}

		if (isset($this->request->get['filter_order_status_id'])) {
			$url .= '&filter_order_status_id=' . (int)$this->request->get['filter_order_status_id'];
		}

		if (isset($this->request->get['filter_total'])) {
			$url .= '&filter_total=' . $this->request->get['filter_total'];
		}

		if (isset($this->request->get['filter_date_from'])) {
			$url .= '&filter_date_from=' . $this->request->get['filter_date_from'];
		}

		if (isset($this->request->get['filter_date_to'])) {
			$url .= '&filter_date_to=' . $this->request->get['filter_date_to'];
		}

		if (isset($this->request->get['filter_date_modified_from'])) {
			$url .= '&filter_date_modified_from=' . $this->request->get['filter_date_modified_from'];
		}

		if (isset($this->request->get['filter_date_modified_to'])) {
			$url .= '&filter_date_modified_to=' . $this->request->get['filter_date_modified_to'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['action'] = $this->url->link('sale/order.list', 'user_token=' . $this->session->data['user_token'] . $url);

		// Order
		$data['orders'] = [];

		$filter_data = [
			'filter_order_id'           => $filter_order_id,
			'filter_customer_id'        => $filter_customer_id,
			'filter_customer'           => $filter_customer,
			'filter_store_id'           => $filter_store_id,
			'filter_order_status'       => $filter_order_status,
			'filter_order_status_id'    => $filter_order_status_id,
			'filter_total'              => $filter_total,
			'filter_date_from'          => $filter_date_from,
			'filter_date_to'            => $filter_date_to,
			'filter_date_modified_from' => $filter_date_modified_from,
			'filter_date_modified_to'   => $filter_date_modified_to,
			'sort'                      => $sort,
			'order'                     => $order,
			'start'                     => ($page - 1) * (int)$this->config->get('config_pagination_admin'),
			'limit'                     => (int)$this->config->get('config_pagination_admin')
		];

		$this->load->model('sale/order');

		$results = $this->model_sale_order->getOrders($filter_data);

		foreach ($results as $result) {
			if (isset($result['shipping_method']['name'])) {
				$shipping_method = $result['shipping_method']['name'];
			} else {
				$shipping_method = '';
			}

			$data['orders'][] = [
				'order_status'    => $result['order_status'] ? $result['order_status'] : $this->language->get('text_missing'),
				'total'           => $this->currency->format($result['total'], $result['currency_code'], $result['currency_value']),
				'date_added'      => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'date_modified'   => date($this->language->get('date_format_short'), strtotime($result['date_modified'])),
				'shipping_method' => $shipping_method,
				'view'            => $this->url->link('sale/order.info', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $result['order_id'] . $url)
			] + $result;
		}

		$url = '';

		if (isset($this->request->get['filter_order_id'])) {
			$url .= '&filter_order_id=' . $this->request->get['filter_order_id'];
		}

		if (isset($this->request->get['filter_customer_id'])) {
			$url .= '&filter_customer_id=' . (int)$this->request->get['filter_customer_id'];
		}

		if (isset($this->request->get['filter_customer'])) {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_store_id'])) {
			$url .= '&filter_store_id=' . (int)$this->request->get['filter_store_id'];
		}

		if (isset($this->request->get['filter_order_status'])) {
			$url .= '&filter_order_status=' . $this->request->get['filter_order_status'];
		}

		if (isset($this->request->get['filter_order_status_id'])) {
			$url .= '&filter_order_status_id=' . (int)$this->request->get['filter_order_status_id'];
		}

		if (isset($this->request->get['filter_total'])) {
			$url .= '&filter_total=' . $this->request->get['filter_total'];
		}

		if (isset($this->request->get['filter_date_from'])) {
			$url .= '&filter_date_from=' . $this->request->get['filter_date_from'];
		}

		if (isset($this->request->get['filter_date_to'])) {
			$url .= '&filter_date_to=' . $this->request->get['filter_date_to'];
		}

		if (isset($this->request->get['filter_date_modified_from'])) {
			$url .= '&filter_date_modified_from=' . $this->request->get['filter_date_modified_from'];
		}

		if (isset($this->request->get['filter_date_modified_to'])) {
			$url .= '&filter_date_modified_to=' . $this->request->get['filter_date_modified_to'];
		}

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		$data['sort_order'] = $this->url->link('sale/order.list', 'user_token=' . $this->session->data['user_token'] . '&sort=o.order_id' . $url);
		$data['sort_store_name'] = $this->url->link('sale/order.list', 'user_token=' . $this->session->data['user_token'] . '&sort=o.store_name' . $url);
		$data['sort_customer'] = $this->url->link('sale/order.list', 'user_token=' . $this->session->data['user_token'] . '&sort=customer' . $url);
		$data['sort_status'] = $this->url->link('sale/order.list', 'user_token=' . $this->session->data['user_token'] . '&sort=order_status' . $url);
		$data['sort_total'] = $this->url->link('sale/order.list', 'user_token=' . $this->session->data['user_token'] . '&sort=o.total' . $url);
		$data['sort_date_added'] = $this->url->link('sale/order.list', 'user_token=' . $this->session->data['user_token'] . '&sort=o.date_added' . $url);
		$data['sort_date_modified'] = $this->url->link('sale/order.list', 'user_token=' . $this->session->data['user_token'] . '&sort=o.date_modified' . $url);

		$url = '';

		if (isset($this->request->get['filter_order_id'])) {
			$url .= '&filter_order_id=' . $this->request->get['filter_order_id'];
		}

		if (isset($this->request->get['filter_customer_id'])) {
			$url .= '&filter_customer_id=' . (int)$this->request->get['filter_customer_id'];
		}

		if (isset($this->request->get['filter_customer'])) {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_store_id'])) {
			$url .= '&filter_store_id=' . (int)$this->request->get['filter_store_id'];
		}

		if (isset($this->request->get['filter_order_status'])) {
			$url .= '&filter_order_status=' . $this->request->get['filter_order_status'];
		}

		if (isset($this->request->get['filter_order_status_id'])) {
			$url .= '&filter_order_status_id=' . (int)$this->request->get['filter_order_status_id'];
		}

		if (isset($this->request->get['filter_total'])) {
			$url .= '&filter_total=' . $this->request->get['filter_total'];
		}

		if (isset($this->request->get['filter_date_from'])) {
			$url .= '&filter_date_from=' . $this->request->get['filter_date_from'];
		}

		if (isset($this->request->get['filter_date_to'])) {
			$url .= '&filter_date_to=' . $this->request->get['filter_date_to'];
		}

		if (isset($this->request->get['filter_date_modified_from'])) {
			$url .= '&filter_date_modified_from=' . $this->request->get['filter_date_modified_from'];
		}

		if (isset($this->request->get['filter_date_modified_to'])) {
			$url .= '&filter_date_modified_to=' . $this->request->get['filter_date_modified_to'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$order_total = $this->model_sale_order->getTotalOrders($filter_data);

		$data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $order_total,
			'page'  => $page,
			'limit' => $this->config->get('config_pagination_admin'),
			'url'   => $this->url->link('sale/order.list', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($order_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($order_total - $this->config->get('config_pagination_admin'))) ? $order_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $order_total, ceil($order_total / $this->config->get('config_pagination_admin')));

		$data['sort'] = $sort;
		$data['order'] = $order;

		return $this->load->view('sale/order_list', $data);
	}

	/**
	 * Info
	 *
	 * @throws \Exception
	 *
	 * @return void
	 */
	public function info(): void {
		$this->load->language('sale/order');

		if (isset($this->request->get['order_id'])) {
			$order_id = (int)$this->request->get['order_id'];
		} else {
			$order_id = 0;
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$data['text_form'] = !$order_id ? $this->language->get('text_add') : sprintf($this->language->get('text_edit'), $order_id);

		$data['error_upload_size'] = sprintf($this->language->get('error_upload_size'), $this->config->get('config_file_max_size'));

		$data['config_file_max_size'] = ((int)$this->config->get('config_file_max_size') * 1024 * 1024);
		$data['config_telephone_required'] = $this->config->get('config_telephone_required');

		$url = '';

		if (isset($this->request->get['filter_order_id'])) {
			$url .= '&filter_order_id=' . $this->request->get['filter_order_id'];
		}

		if (isset($this->request->get['filter_customer_id'])) {
			$url .= '&filter_customer_id=' . (int)$this->request->get['filter_customer_id'];
		}

		if (isset($this->request->get['filter_customer'])) {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_store_id'])) {
			$url .= '&filter_store_id=' . (int)$this->request->get['filter_store_id'];
		}

		if (isset($this->request->get['filter_order_status'])) {
			$url .= '&filter_order_status=' . $this->request->get['filter_order_status'];
		}

		if (isset($this->request->get['filter_order_status_id'])) {
			$url .= '&filter_order_status_id=' . (int)$this->request->get['filter_order_status_id'];
		}

		if (isset($this->request->get['filter_total'])) {
			$url .= '&filter_total=' . $this->request->get['filter_total'];
		}

		if (isset($this->request->get['filter_date_from'])) {
			$url .= '&filter_date_from=' . $this->request->get['filter_date_from'];
		}

		if (isset($this->request->get['filter_date_to'])) {
			$url .= '&filter_date_to=' . $this->request->get['filter_date_to'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'] . $url)
		];

		$data['shipping'] = $this->url->link('sale/order.shipping', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id);
		$data['invoice'] = $this->url->link('sale/order.invoice', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id);
		$data['back'] = $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'] . $url);
		$data['novaposhta_create_ttn'] = $this->url->link('sale/order.createNovaposhtaTtn', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id);
		$data['novaposhta_recreate_ttn'] = $this->url->link('sale/order.recreateNovaposhtaTtn', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id);
		$data['novaposhta_edit_ttn'] = $this->url->link('sale/order.editNovaposhtaTtn', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id);
		$data['novaposhta_delete_ttn'] = $this->url->link('sale/order.deleteNovaposhtaTtn', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id);
		$data['novaposhta_refresh_status'] = $this->url->link('sale/order.refreshNovaposhtaStatus', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id);
		$data['novaposhta_lookup'] = $this->url->link('sale/order.novaposhtaLookup', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id);
		$data['novaposhta_sender_lookup'] = $this->url->link('sale/order.novaposhtaSenderLookup', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id);
		$data['checkbox_create_receipt'] = $this->url->link('sale/order.createCheckboxReceipt', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id);
		$data['checkbox_send_sms'] = $this->url->link('sale/order.sendCheckboxReceiptSms', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id);
		$data['checkbox_create_return_receipt'] = $this->url->link('sale/order.createCheckboxReturnReceipt', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id);
		$data['checkbox_send_return_sms'] = $this->url->link('sale/order.sendCheckboxReturnReceiptSms', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id);
		$data['checkbox_check_auth'] = $this->url->link('sale/order.checkCheckboxAuth', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id);
		$data['upload'] = $this->url->link('tool/upload.upload', 'user_token=' . $this->session->data['user_token']);
		$data['customer_add'] = $this->url->link('customer/customer.form', 'user_token=' . $this->session->data['user_token']);

		$data['novaposhta'] = [
			'is_order' => false,
			'delivery_type' => '',
			'delivery_type_name' => '',
			'shipping_code' => '',
			'city' => '',
			'city_ref' => '',
			'address' => '',
			'address_ref' => '',
			'zone' => '',
			'country' => '',
			'ttn_number' => '',
			'ttn_ref' => '',
			'ttn_error' => '',
			'ttn_date_created' => '',
			'ttn_status_code' => '',
			'ttn_status_text' => '',
			'ttn_status_date' => '',
			'ttn_print_url' => '',
			'can_create_ttn' => false,
			'can_recreate_ttn' => false,
			'can_edit_ttn' => false,
			'can_delete_ttn' => false,
			'can_print_ttn' => false,
			'can_refresh_status' => false,
			'default_sender_city' => '',
			'default_sender_city_ref' => '',
			'default_sender_warehouse' => '',
			'default_sender_warehouse_ref' => '',
			'default_sender_delivery_type' => 'branch',
			'ttn_payload_json' => '{}'
		];

		$data['checkbox'] = [
			'enabled' => false,
			'ready' => false,
			'receipt_id' => '',
			'receipt_pdf_url' => '',
			'sms_phone' => '',
			'sms_sent' => false,
			'error' => ''
		];

		if ($order_id) {
			$this->load->model('sale/order');

			$order_info = $this->model_sale_order->getOrder($order_id);
		}

		if (!empty($order_info)) {
			$data['order_id'] = $order_info['order_id'];
		} else {
			$data['order_id'] = '';
		}

		// Invoice
		if (!empty($order_info)) {
			$data['invoice_no'] = $order_info['invoice_no'];
		} else {
			$data['invoice_no'] = '';
		}

		if (!empty($order_info)) {
			$data['invoice_prefix'] = $order_info['invoice_prefix'];
		} else {
			$data['invoice_prefix'] = '';
		}

		// Customer
		if (!empty($order_info)) {
			$data['customer_id'] = $order_info['customer_id'];
			$data['customer_edit'] = $this->url->link('customer/customer.form', 'user_token=' . $this->session->data['user_token'] . '&customer_id=' . $order_info['customer_id']);
		} else {
			$data['customer_id'] = 0;
			$data['customer_edit'] = '';
		}

		// Customer Group
		$this->load->model('customer/customer_group');

		$data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();

		if (!empty($order_info)) {
			$data['customer_group_id'] = $order_info['customer_group_id'];
		} else {
			$data['customer_group_id'] = (int)$this->config->get('config_customer_group_id');
		}

		if (!empty($order_info)) {
			$data['firstname'] = $order_info['firstname'];
		} else {
			$data['firstname'] = '';
		}

		if (!empty($order_info)) {
			$data['lastname'] = $order_info['lastname'];
		} else {
			$data['lastname'] = '';
		}

		if (!empty($order_info)) {
			$data['email'] = $order_info['email'];
		} else {
			$data['email'] = '';
		}

		if (!empty($order_info)) {
			$data['telephone'] = $order_info['telephone'];
		} else {
			$data['telephone'] = '';
		}

		if (!empty($order_info)) {
			$data['account_custom_field'] = $order_info['custom_field'];
		} else {
			$data['account_custom_field'] = [];
		}

		// Custom Fields
		$data['custom_fields'] = [];

		$filter_data = [
			'filter_status' => 1,
			'sort'          => 'cf.sort_order',
			'order'         => 'ASC'
		];

		$this->load->model('customer/custom_field');

		$custom_fields = $this->model_customer_custom_field->getCustomFields($filter_data);

		foreach ($custom_fields as $custom_field) {
			$data['custom_fields'][] = ['custom_field_value' => $this->model_customer_custom_field->getValues($custom_field['custom_field_id'])] + $custom_field;
		}

		// Store
		$data['stores'] = [];

		$data['stores'][] = [
			'store_id' => 0,
			'name'     => $this->config->get('config_name')
		];

		$this->load->model('setting/store');

		$results = $this->model_setting_store->getStores();

		foreach ($results as $result) {
			$data['stores'][] = $result;
		}

		if (!empty($order_info)) {
			$data['store_id'] = $order_info['store_id'];
		} else {
			$data['store_id'] = (int)$this->config->get('config_store_id');
		}

		// Language
		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (!empty($order_info)) {
			$data['language_code'] = $order_info['language_code'];
		} else {
			$data['language_code'] = $this->config->get('config_language');
		}

		// Currency
		$this->load->model('localisation/currency');

		$data['currencies'] = $this->model_localisation_currency->getCurrencies();

		if (!empty($order_info)) {
			$data['currency_code'] = $order_info['currency_code'];
			$currency_value = $order_info['currency_value'];
		} else {
			$data['currency_code'] = $this->config->get('config_currency');
			$currency_value = 1;
		}

		// Products
		$data['order_products'] = [];

		// Order
		$this->load->model('sale/order');

		// Subscription
		$this->load->model('sale/subscription');

		// Upload
		$this->load->model('tool/upload');

		$products = $this->model_sale_order->getProducts($order_id);

		foreach ($products as $product) {
			$option_data = [];

			$options = $this->model_sale_order->getOptions($order_id, $product['order_product_id']);

			foreach ($options as $option) {
				if ($option['type'] != 'file') {
					$option_data[] = $option;
				} else {
					$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

					if ($upload_info) {
						$option_data[] = [
							'filename' => $upload_info['mask'],
							'href'     => $this->url->link('tool/upload.download', 'user_token=' . $this->session->data['user_token'] . '&code=' . $upload_info['code'])
						] + $option;
					}
				}
			}

			$subscription_plan = '';

			$subscription_info = $this->model_sale_order->getSubscription($order_id, $product['order_product_id']);

			if ($subscription_info) {
				if ($subscription_info['trial_status']) {
					$trial_price = $this->currency->format($subscription_info['trial_price'] + ($this->config->get('config_tax') ? $subscription_info['trial_tax'] : 0), $this->config->get('config_currency'));
					$trial_cycle = $subscription_info['trial_cycle'];
					$trial_frequency = $this->language->get('text_' . $subscription_info['trial_frequency']);
					$trial_duration = $subscription_info['trial_duration'];

					$subscription_plan .= sprintf($this->language->get('text_subscription_trial'), $trial_price, $trial_cycle, $trial_frequency, $trial_duration);
				}

				$price = $this->currency->format($subscription_info['price'] + ($this->config->get('config_tax') ? $subscription_info['tax'] : 0), $this->config->get('config_currency'));
				$cycle = $subscription_info['cycle'];
				$frequency = $this->language->get('text_' . $subscription_info['frequency']);
				$duration = $subscription_info['duration'];

				if ($subscription_info['duration']) {
					$subscription_plan .= sprintf($this->language->get('text_subscription_duration'), $price, $cycle, $frequency, $duration);
				} else {
					$subscription_plan .= sprintf($this->language->get('text_subscription_cancel'), $price, $cycle, $frequency);
				}

				$subscription_plan_id = $subscription_info['subscription_plan_id'];
			} else {
				$subscription_plan_id = 0;
			}

			$subscription_info = $this->model_sale_subscription->getSubscriptionByOrderProductId($order_id, $product['order_product_id']);

			if ($subscription_info) {
				$subscription_edit = $this->url->link('sale/subscription.info', 'user_token=' . $this->session->data['user_token'] . '&subscription_id=' . $subscription_info['subscription_id']);
			} else {
				$subscription_edit = '';
			}

			$data['order_products'][] = [
				'option'               => $option_data,
				'subscription_plan'    => $subscription_plan,
				'subscription_plan_id' => $subscription_plan_id,
				'subscription_edit'    => $subscription_edit,
				'price'                => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $data['currency_code'], $currency_value),
				'total'                => $this->currency->format($product['total'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $data['currency_code'], $currency_value),
				'product_edit'         => $this->url->link('catalog/product.form', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $product['product_id'])
			] + $product;
		}

		// Totals
		$data['order_totals'] = [];

		$totals = $this->model_sale_order->getTotals($order_id);

		foreach ($totals as $total) {
			$data['order_totals'][] = ['text' => $this->currency->format($total['value'], $data['currency_code'], $currency_value)] + $total;
		}

		// Addresses
		if (!empty($order_info)) {
			// Customer
			$this->load->model('customer/customer');

			$data['addresses'] = $this->model_customer_customer->getAddresses($order_info['customer_id']);
		} else {
			$data['addresses'] = [];
		}

		// Payment Address
		if (!empty($order_info)) {
			$data['payment_address_id'] = $order_info['payment_address_id'];
			$data['payment_firstname'] = $order_info['payment_firstname'];
			$data['payment_lastname'] = $order_info['payment_lastname'];
			$data['payment_company'] = $order_info['payment_company'];
			$data['payment_address_1'] = $order_info['payment_address_1'];
			$data['payment_address_2'] = $order_info['payment_address_2'];
			$data['payment_city'] = $order_info['payment_city'];
			$data['payment_postcode'] = $order_info['payment_postcode'];
			$data['payment_country_id'] = $order_info['payment_country_id'];
			$data['payment_country'] = $order_info['payment_country'];
			$data['payment_zone_id'] = $order_info['payment_zone_id'];
			$data['payment_zone'] = $order_info['payment_zone'];
			$data['payment_custom_field'] = $order_info['payment_custom_field'];
		} else {
			$data['payment_address_id'] = 0;
			$data['payment_firstname'] = '';
			$data['payment_lastname'] = '';
			$data['payment_company'] = '';
			$data['payment_address_1'] = '';
			$data['payment_address_2'] = '';
			$data['payment_city'] = '';
			$data['payment_postcode'] = '';
			$data['payment_country_id'] = 0;
			$data['payment_country'] = '';
			$data['payment_zone_id'] = 0;
			$data['payment_zone'] = '';
			$data['payment_custom_field'] = [];
		}

		// Country
		$this->load->model('localisation/country');

		$data['countries'] = $this->model_localisation_country->getCountries();

		// Zone
		$this->load->model('localisation/zone');

		$data['payment_zones'] = $this->model_localisation_zone->getZonesByCountryId($data['payment_country_id']);

		// Payment Method
		if (!empty($order_info['payment_method'])) {
			$data['payment_method_name'] = $order_info['payment_method']['name'];
			$data['payment_method_code'] = $order_info['payment_method']['code'];
		} else {
			$data['payment_method_name'] = '';
			$data['payment_method_code'] = '';
		}

		// Shipping Address
		if (!empty($order_info)) {
			$data['shipping_address_id'] = $order_info['shipping_address_id'];
			$data['shipping_firstname'] = $order_info['shipping_firstname'];
			$data['shipping_lastname'] = $order_info['shipping_lastname'];
			$data['shipping_company'] = $order_info['shipping_company'];
			$data['shipping_address_1'] = $order_info['shipping_address_1'];
			$data['shipping_address_2'] = $order_info['shipping_address_2'];
			$data['shipping_city'] = $order_info['shipping_city'];
			$data['shipping_postcode'] = $order_info['shipping_postcode'];
			$data['shipping_country_id'] = $order_info['shipping_country_id'];
			$data['shipping_country'] = $order_info['shipping_country'];
			$data['shipping_zone_id'] = $order_info['shipping_zone_id'];
			$data['shipping_zone'] = $order_info['shipping_zone'];
			$data['shipping_custom_field'] = $order_info['shipping_custom_field'];
		} else {
			$data['shipping_address_id'] = 0;
			$data['shipping_firstname'] = '';
			$data['shipping_lastname'] = '';
			$data['shipping_company'] = '';
			$data['shipping_address_1'] = '';
			$data['shipping_address_2'] = '';
			$data['shipping_city'] = '';
			$data['shipping_postcode'] = '';
			$data['shipping_country_id'] = 0;
			$data['shipping_country'] = '';
			$data['shipping_zone_id'] = 0;
			$data['shipping_zone'] = '';
			$data['shipping_custom_field'] = [];
		}

		if ($data['payment_country_id'] == $data['shipping_country_id']) {
			$data['shipping_zones'] = $data['payment_zones'];
		} else {
			$data['shipping_zones'] = $this->model_localisation_zone->getZonesByCountryId($data['shipping_country_id']);
		}

		// Shipping method
		if (!empty($order_info['shipping_method'])) {
			$data['shipping_method_name'] = $order_info['shipping_method']['name'];
			$data['shipping_method_code'] = $order_info['shipping_method']['code'];
			$data['shipping_method_cost'] = $order_info['shipping_method']['cost'];
			$data['shipping_method_tax_class_id'] = $order_info['shipping_method']['tax_class_id'];
		} else {
			$data['shipping_method_name'] = '';
			$data['shipping_method_code'] = '';
			$data['shipping_method_cost'] = '';
			$data['shipping_method_tax_class_id'] = 0;
		}

		if (!empty($order_info)) {
			$this->load->model('extension/manline/shipping/novaposhta');

			$novaposhta_meta = $this->model_extension_manline_shipping_novaposhta->getOrderMeta($order_id);
			$shipping_code = $data['shipping_method_code'];

			if (!$shipping_code && !empty($novaposhta_meta['shipping_code'])) {
				$shipping_code = (string)$novaposhta_meta['shipping_code'];
			}

			$is_novaposhta_order = strpos($shipping_code, 'novaposhta.') === 0 || !empty($novaposhta_meta);

			if ($is_novaposhta_order) {
				$delivery_type = (string)($novaposhta_meta['delivery_type'] ?? '');

				if ($delivery_type === '' && $shipping_code) {
					$parts = explode('.', $shipping_code);
					$delivery_type = (string)($parts[1] ?? '');
				}

				$ttn_number = trim((string)($novaposhta_meta['ttn_number'] ?? ''));
				$ttn_print_url = $this->model_extension_manline_shipping_novaposhta->getPrintUrlByOrderId($order_id);
				$can_modify = $this->user->hasPermission('modify', 'sale/order');

				$total_uah = $this->normalizeOrderCurrencyAmount((float)($order_info['total'] ?? 0.0), $order_info);
				$default_sender = $this->model_extension_manline_shipping_novaposhta->getDefaultSenderAddress();

				$data['novaposhta'] = [
					'is_order' => true,
					'delivery_type' => $delivery_type,
					'delivery_type_name' => $this->formatNovaposhtaDeliveryType($delivery_type),
					'shipping_code' => $shipping_code,
					'city' => (string)($novaposhta_meta['city'] ?? $data['shipping_city']),
					'city_ref' => (string)($novaposhta_meta['city_ref'] ?? ''),
					'address' => (string)($novaposhta_meta['address'] ?? $data['shipping_address_1']),
					'address_ref' => (string)($novaposhta_meta['address_ref'] ?? ''),
					'order_total_uah' => $total_uah,
					'zone' => (string)($novaposhta_meta['zone'] ?? $data['shipping_zone']),
					'country' => (string)($novaposhta_meta['country'] ?? $data['shipping_country']),
					'ttn_number' => $ttn_number,
					'ttn_ref' => trim((string)($novaposhta_meta['ttn_ref'] ?? '')),
					'ttn_error' => (function() use (&$novaposhta_meta, $order_id) {
						$err = trim((string)($novaposhta_meta['ttn_error'] ?? ''));
						$ttn_number_local = trim((string)($novaposhta_meta['ttn_number'] ?? ''));
						// Hide stale legacy DateTime errors that were caused by the old
						// timezone/day-rollover behaviour (no TTN created). The new logic
						// always uses tomorrow.
						if ($ttn_number_local === '' && $err === 'DateTime cannot be less then now') {
							$this->model_extension_manline_shipping_novaposhta->clearTtnError((int)$order_id);
							return '';
						}
						return $err;
					})(),
					'ttn_date_created' => (string)($novaposhta_meta['ttn_date_created'] ?? ''),
					'ttn_status_code' => trim((string)($novaposhta_meta['ttn_status_code'] ?? '')),
					'ttn_status_text' => trim((string)($novaposhta_meta['ttn_status_text'] ?? '')),
					'ttn_status_date' => trim((string)($novaposhta_meta['ttn_status_date'] ?? '')),
					'ttn_print_url' => $ttn_print_url,
					'can_create_ttn' => $can_modify && $ttn_number === '',
					'can_recreate_ttn' => $can_modify && $ttn_number !== '',
					'can_edit_ttn' => $can_modify && $ttn_number !== '' && !empty($novaposhta_meta['ttn_payload']),
					'can_delete_ttn' => $can_modify && $ttn_number !== '',
					'can_print_ttn' => $ttn_number !== '' && $ttn_print_url !== '',
					'can_refresh_status' => $can_modify && $ttn_number !== '',
					'default_sender_city' => (string)($default_sender['city'] ?? ''),
					'default_sender_city_ref' => (string)($default_sender['city_ref'] ?? ''),
					'default_sender_warehouse' => (string)($default_sender['warehouse'] ?? ''),
					'default_sender_warehouse_ref' => (string)($default_sender['warehouse_ref'] ?? ''),
					'default_sender_delivery_type' => (string)($default_sender['delivery_type'] ?? 'branch'),
					'ttn_payload_json' => json_encode(
						is_array($novaposhta_meta['ttn_payload'] ?? null) ? $novaposhta_meta['ttn_payload'] : [],
						JSON_UNESCAPED_UNICODE
					)
				];
			}
		}

		// Checkbox receipts
		if (!empty($order_info)) {
			$this->load->model('setting/module');
			$modules = $this->model_setting_module->getModulesByCode('manline.checkbox');

			$enabled_modules = [];
			foreach ($modules as $m) {
				$settings = json_decode($m['setting'] ?? '', true);
				if (!is_array($settings)) {
					$settings = [];
				}
				if (!empty($settings['status'])) {
					$enabled_modules[] = [
						'module_id' => (int)($m['module_id'] ?? 0),
						'name' => (string)($m['name'] ?? 'Checkbox')
					];
				}
			}

			$checkbox_module = [];
			$selected_module_id = 0;

			$this->load->model('extension/manline/integration/checkbox');
			$meta = $this->model_extension_manline_integration_checkbox->getOrderMeta($order_id);
			$selected_module_id = (int)($meta['module_id'] ?? 0);
			if ($selected_module_id <= 0 && $enabled_modules) {
				$selected_module_id = (int)($enabled_modules[0]['module_id'] ?? 0);
			}

			if ($selected_module_id > 0) {
				$checkbox_module = $this->getCheckboxConfig($selected_module_id);
			}

			if (!empty($checkbox_module['enabled'])) {
				$receipt_id = trim((string)($meta['receipt_id'] ?? ''));
				$receipt_pdf_url = '';
				if ($receipt_id !== '') {
					$api = rtrim((string)($checkbox_module['api_url'] ?? 'https://api.checkbox.in.ua'), '/');
					$receipt_pdf_url = $api . '/api/v1/receipts/' . $receipt_id . '/pdf';
				}

				$return_receipt_id = trim((string)($meta['return_receipt_id'] ?? ''));
				$return_receipt_pdf_url = '';
				if ($return_receipt_id !== '') {
					$api = rtrim((string)($checkbox_module['api_url'] ?? 'https://api.checkbox.in.ua'), '/');
					$return_receipt_pdf_url = $api . '/api/v1/receipts/' . $return_receipt_id . '/pdf';
				}

				$data['checkbox'] = [
					'enabled' => true,
					'modules' => $enabled_modules,
					'selected_module_id' => $selected_module_id,
					'receipt_id' => $receipt_id,
					'receipt_pdf_url' => $receipt_pdf_url,
					'return_receipt_id' => $return_receipt_id,
					'return_receipt_pdf_url' => $return_receipt_pdf_url,
					'order_phone' => (string)($order_info['telephone'] ?? ''),
					'sms_phone' => (string)($meta['sms_phone'] ?? ''),
					'sms_sent' => !empty($meta['sms_sent']),
					'return_sms_sent' => !empty($meta['return_sms_sent']),
					'error' => trim((string)($meta['error'] ?? ''))
				];
			}
		}

		// Reward Points
		if (!empty($order_info)) {
			$data['points'] = $this->model_sale_order->getRewardTotal($order_id);
		} else {
			$data['points'] = 0;
		}

		// Reward Points
		if (!empty($order_info)) {
			$data['reward_total'] = $this->model_customer_customer->getTotalRewardsByOrderId($order_id);
		} else {
			$data['reward_total'] = 0;
		}

		// Affiliate
		if (!empty($order_info)) {
			$data['affiliate_id'] = $order_info['affiliate_id'];
			$data['affiliate_edit'] = $this->url->link('marketing/affiliate.form', 'user_token=' . $this->session->data['user_token'] . '&customer_id=' . $order_info['customer_id']);
		} else {
			$data['affiliate_id'] = 0;
			$data['affiliate_edit'] = '';
		}

		if (!empty($order_info)) {
			$data['affiliate'] = $order_info['affiliate'];
		} else {
			$data['affiliate'] = '';
		}

		// Commission
		if (!empty($order_info) && (float)$order_info['commission']) {
			$data['commission'] = $this->currency->format($order_info['commission'], $this->config->get('config_currency'));
		} else {
			$data['commission'] = '';
		}

		if (!empty($order_info)) {
			$data['commission_total'] = $this->model_customer_customer->getTotalTransactionsByOrderId($order_id);
		} else {
			$data['commission_total'] = '';
		}

		// Extension Order Tabs can be called here.
		$data['extensions'] = [];

		$this->load->model('setting/extension');

		$extensions = $this->model_setting_extension->getExtensionsByType('total');

		foreach ($extensions as $extension) {
			if ($this->config->get('total_' . $extension['code'] . '_status')) {
				$output = $this->load->controller('extension/' . $extension['extension'] . '/api/' . $extension['code']);

				if (!$output instanceof \Exception) {
					$data['extensions'][] = $output;
				}
			}
		}

		// Comment
		if (!empty($order_info)) {
			$data['comment'] = nl2br($order_info['comment']);
		} else {
			$data['comment'] = '';
		}

		// Totals
		$data['order_totals'] = [];

		if (!empty($order_info)) {
			$totals = $this->model_sale_order->getTotals($order_id);

			foreach ($totals as $total) {
				$data['order_totals'][] = ['text' => $this->currency->format($total['value'], $order_info['currency_code'], $order_info['currency_value'])] + $total;
			}
		}

		// Order Status
		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (!empty($order_info)) {
			$data['order_status_id'] = $order_info['order_status_id'];
		} else {
			$data['order_status_id'] = (int)$this->config->get('config_order_status_id');
		}

		$data['complete_status'] = in_array($data['order_status_id'], (array)$this->config->get('config_complete_status'));

		// Additional tabs that are payment gateway specific
		$data['tabs'] = [];

		// Extension Order Tabs can be called here.
		$this->load->model('setting/extension');

		if (!empty($order_info['payment_method']['code'])) {
			if (isset($order_info['payment_method']['code'])) {
				$code = oc_substr($order_info['payment_method']['code'], 0, strpos($order_info['payment_method']['code'], '.'));
			} else {
				$code = '';
			}

			$extension_info = $this->model_setting_extension->getExtensionByCode('payment', $code);

			if ($extension_info && $this->user->hasPermission('access', 'extension/' . $extension_info['extension'] . '/payment/' . $extension_info['code'])) {
				$output = $this->load->controller('extension/' . $extension_info['extension'] . '/payment/' . $extension_info['code'] . '.order');

				if (!$output instanceof \Exception) {
					$this->load->language('extension/' . $extension_info['extension'] . '/payment/' . $extension_info['code'], 'extension');

					$data['tabs'][] = [
						'code'    => $extension_info['code'],
						'title'   => $this->language->get('extension_heading_title'),
						'content' => $output
					];
				}
			}
		}

		// Extension Order Tabs can be called here.
		$this->load->model('setting/extension');

		$extensions = $this->model_setting_extension->getExtensionsByType('fraud');

		foreach ($extensions as $extension) {
			if ($this->config->get('fraud_' . $extension['code'] . '_status')) {
				$this->load->language('extension/' . $extension['extension'] . '/fraud/' . $extension['code'], 'extension');

				$output = $this->load->controller('extension/' . $extension['extension'] . '/fraud/' . $extension['code'] . '.order');

				if (!$output instanceof \Exception) {
					$data['tabs'][] = [
						'code'    => $extension['extension'],
						'title'   => $this->language->get('extension_heading_title'),
						'content' => $output
					];
				}
			}
		}

		// Additional information
		if (!empty($order_info)) {
			$data['ip'] = $order_info['ip'];
			$data['forwarded_ip'] = $order_info['forwarded_ip'];
			$data['user_agent'] = $order_info['user_agent'];
			$data['accept_language'] = $order_info['accept_language'];
			$data['date_added'] = date($this->language->get('date_format_short'), strtotime($order_info['date_added']));
			$data['date_modified'] = date($this->language->get('date_format_short'), strtotime($order_info['date_modified']));
		} else {
			$data['ip'] = '';
			$data['forwarded_ip'] = '';
			$data['user_agent'] = '';
			$data['accept_language'] = '';
			$data['date_added'] = date($this->language->get('date_format_short'), time());
			$data['date_modified'] = date($this->language->get('date_format_short'), time());
		}

		$data['user_token'] = $this->session->data['user_token'];

		// Histories
		$data['history'] = $this->getHistory();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('sale/order_info', $data));
	}

	/**
	 * Call
	 *
	 * Method to call the storefront API and return a response.
	 *
	 * @Example
	 *
	 * We create a hash from the data in a similar method to how amazon does things.
	 *
	 * $call     = 'order';
	 * $username = 'API username';
	 * $key      = 'API Key';
	 * $domain   = 'www.yourdomain.com';
	 * $path     = '/';
	 * $store_id = 0;
	 * $language = 'en-gb';
	 * $time     = time();
	 *
	 * // Build hash string
	 * $string  = $call . "\n";
	 * $string .= $username . "\n";
	 * $string .= $domain . "\n";
	 * $string .= $path . "\n";
	 * $string .= $store_id . "\n";
	 * $string .= $language . "\n";
	 * $string .= $currency . "\n";
	 * $string .= json_encode($_POST) . "\n";
	 * $string .= $time . "\n";
	 *
	 * $signature = base64_encode(hash_hmac('sha1', $string, $key, true));
	 *
	 * // Make remote call
	 * $url  = '&call=' . $call;
	 * $url  = '&username=' . urlencode($username);
	 * $url .= '&store_id=' . $store_id;
	 * $url .= '&language=' . $language;
	 * $url .= '&currency=' . $currency;
	 * $url .= '&time=' . $time;
	 * $url .= '&signature=' . rawurlencode($signature);
	 *
	 * $curl = curl_init();
	 *
	 * curl_setopt($curl, CURLOPT_URL, 'https://' . $domain . $path . 'index.php?route=api/api' . $url);
	 * curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	 * curl_setopt($curl, CURLOPT_HEADER, false);
	 * curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
	 * curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
	 * curl_setopt($curl, CURLOPT_TIMEOUT, 30);
	 * curl_setopt($curl, CURLOPT_POST, 1);
	 * curl_setopt($curl, CURLOPT_POSTFIELDS, $_POST);
	 *
	 * $response = curl_exec($curl);
	 *
	 * $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	 *
	 * curl_close($curl);
	 *
	 * if ($status == 200) {
	 *      $response_info = json_decode($response, true);
	 * } else {
	 *      $response_info = [];
	 * }
	 *
	 * @return void
	 */
	public function call(): void {
		$this->load->language('sale/order');

		$json = [];

		if (isset($this->request->get['call'])) {
			$call = (string)$this->request->get['call'];
		} else {
			$call = '';
		}

		if (isset($this->request->get['store_id'])) {
			$store_id = (int)$this->request->get['store_id'];
		} else {
			$store_id = 0;
		}

		if (isset($this->request->get['language'])) {
			$language = (string)$this->request->get['language'];
		} else {
			$language = (string)$this->config->get('config_language');
		}

		if (isset($this->request->get['currency'])) {
			$currency = (string)$this->request->get['currency'];
		} else {
			$currency = (string)$this->config->get('config_currency');
		}

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		}

		// API
		$this->load->model('user/api');

		$api_info = $this->model_user_api->getApi((int)$this->config->get('config_api_id'));

		if (!$api_info) {
			$json['error'] = $this->language->get('error_api');
		}

		if (!$json) {
			// 1. Create a store instance using loader class to call controllers, models, views, libraries.
			$this->load->model('setting/store');

			$store = $this->model_setting_store->createStoreInstance($store_id, $language, $currency);

			// Set the store ID.
			$store->config->set('config_store_id', $store_id);

			$store->session->data['currency'] = $currency;

			// 2. Remove the unneeded keys.
			$request_data = $this->request->get;

			unset($request_data['user_token']);

			// 3. Add the request GET vars.
			$store->request->get = $request_data;

			$store->request->get['route'] = 'api/order';

			// 4. Add the request POST var
			$store->request->post = $this->request->post;

			// 5. Call the required API controller.
			$store->load->controller($store->request->get['route']);

			// 6. Call the required API controller and get the output.
			$output = $store->response->getOutput();

			// 7. Clean up data by clearing cart.
			$store->cart->clear();

			// 8. Deleting the current session so we are not creating infinite sessions.
			$store->session->destroy();
		} else {
			$output = json_encode($json);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput($output);
	}

	/**
	 * Delete
	 *
	 * @return void
	 */
	public function delete(): void {
		$this->load->language('sale/order');

		$json = [];

		if (isset($this->request->post['selected'])) {
			$selected = (array)$this->request->post['selected'];
		} else {
			$selected = [];
		}

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			// Order
			$this->load->model('sale/order');

			foreach ($selected as $order_id) {
				$this->model_sale_order->deleteOrder($order_id);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Invoice
	 *
	 * @return void
	 */
	public function invoice(): void {
		$this->load->language('sale/order');

		$data['title'] = $this->language->get('text_invoice');

		$data['base'] = HTTP_SERVER;
		$data['direction'] = $this->language->get('direction');
		$data['lang'] = $this->language->get('code');

		// Hard coding css paths so that they can be replaced via the event's system.
		$data['bootstrap_css'] = 'view/stylesheet/bootstrap.css';
		$data['icons'] = 'view/stylesheet/fonts/fontawesome/css/all.min.css';
		$data['stylesheet'] = 'view/stylesheet/stylesheet.css';

		// Hard coding scripts so they can be replaced via the events system.
		$data['jquery'] = 'view/javascript/jquery/jquery-3.7.1.min.js';
		$data['bootstrap_js'] = 'view/javascript/bootstrap/js/bootstrap.bundle.min.js';

		// Order
		$this->load->model('sale/order');

		// Subscription
		$this->load->model('sale/subscription');

		// Setting
		$this->load->model('setting/setting');

		// Upload
		$this->load->model('tool/upload');

		$data['orders'] = [];

		$orders = [];

		if (isset($this->request->post['selected'])) {
			$orders = (array)$this->request->post['selected'];
		}

		if (isset($this->request->get['order_id'])) {
			$orders[] = (int)$this->request->get['order_id'];
		}

		foreach ($orders as $order_id) {
			$order_info = $this->model_sale_order->getOrder($order_id);

			if ($order_info) {
				$store_info = $this->model_setting_setting->getSetting('config', $order_info['store_id']);

				if ($store_info) {
					$store_address = $store_info['config_address'];
					$store_email = $store_info['config_email'];
					$store_telephone = $store_info['config_telephone'];
				} else {
					$store_address = $this->config->get('config_address');
					$store_email = $this->config->get('config_email');
					$store_telephone = $this->config->get('config_telephone');
				}

				if ($order_info['invoice_no']) {
					$invoice_no = $order_info['invoice_prefix'] . $order_info['invoice_no'];
				} else {
					$invoice_no = '';
				}

				// Payment Address
				if ($order_info['payment_address_format']) {
					$format = $order_info['payment_address_format'];
				} else {
					$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
				}

				$find = [
					'{firstname}',
					'{lastname}',
					'{company}',
					'{address_1}',
					'{address_2}',
					'{city}',
					'{postcode}',
					'{zone}',
					'{zone_code}',
					'{country}'
				];

				$replace = [
					'firstname' => $order_info['payment_firstname'],
					'lastname'  => $order_info['payment_lastname'],
					'company'   => $order_info['payment_company'],
					'address_1' => $order_info['payment_address_1'],
					'address_2' => $order_info['payment_address_2'],
					'city'      => $order_info['payment_city'],
					'postcode'  => $order_info['payment_postcode'],
					'zone'      => $order_info['payment_zone'],
					'zone_code' => $order_info['payment_zone_code'],
					'country'   => $order_info['payment_country']
				];

				$pattern_1 = [
					"\r\n",
					"\r",
					"\n"
				];

				$pattern_2 = [
					"/\\s\\s+/",
					"/\r\r+/",
					"/\n\n+/"
				];

				$payment_address = str_replace($pattern_1, '<br/>', preg_replace($pattern_2, '<br/>', trim(str_replace($find, $replace, $format))));

				// Shipping Address
				if ($order_info['shipping_address_format']) {
					$format = $order_info['shipping_address_format'];
				} else {
					$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
				}

				$find = [
					'{firstname}',
					'{lastname}',
					'{company}',
					'{address_1}',
					'{address_2}',
					'{city}',
					'{postcode}',
					'{zone}',
					'{zone_code}',
					'{country}'
				];

				$replace = [
					'firstname' => $order_info['shipping_firstname'],
					'lastname'  => $order_info['shipping_lastname'],
					'company'   => $order_info['shipping_company'],
					'address_1' => $order_info['shipping_address_1'],
					'address_2' => $order_info['shipping_address_2'],
					'city'      => $order_info['shipping_city'],
					'postcode'  => $order_info['shipping_postcode'],
					'zone'      => $order_info['shipping_zone'],
					'zone_code' => $order_info['shipping_zone_code'],
					'country'   => $order_info['shipping_country']
				];

				$shipping_address = str_replace($pattern_1, '<br/>', preg_replace($pattern_2, '<br/>', trim(str_replace($find, $replace, $format))));

				$product_data = [];

				$products = $this->model_sale_order->getProducts($order_id);

				foreach ($products as $product) {
					$option_data = [];

					$options = $this->model_sale_order->getOptions($order_id, $product['order_product_id']);

					foreach ($options as $option) {
						if ($option['type'] != 'file') {
							$value = $option['value'];
						} else {
							$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

							if ($upload_info) {
								$value = $upload_info['name'];
							} else {
								$value = '';
							}
						}

						$option_data[] = ['value' => $value] + $option;
					}

					// Subscription
					$description = '';

					$subscription_info = $this->model_sale_order->getSubscription($order_id, $product['order_product_id']);

					if ($subscription_info) {
						if ($subscription_info['trial_status']) {
							$trial_price = $this->currency->format($subscription_info['trial_price'], $this->config->get('config_currency'));
							$trial_cycle = $subscription_info['trial_cycle'];
							$trial_frequency = $this->language->get('text_' . $subscription_info['trial_frequency']);
							$trial_duration = $subscription_info['trial_duration'];

							$description .= sprintf($this->language->get('text_subscription_trial'), $trial_price, $trial_cycle, $trial_frequency, $trial_duration);
						}
						$price = $this->currency->format($subscription_info['price'], $this->config->get('config_currency'));
						$cycle = $subscription_info['cycle'];
						$frequency = $this->language->get('text_' . $subscription_info['frequency']);
						$duration = $subscription_info['duration'];

						if ($subscription_info['duration']) {
							$description .= sprintf($this->language->get('text_subscription_duration'), $price, $cycle, $frequency, $duration);
						} else {
							$description .= sprintf($this->language->get('text_subscription_cancel'), $price, $cycle, $frequency);
						}
					}

					$product_data[] = [
						'name'         => $product['name'],
						'model'        => $product['model'],
						'option'       => $option_data,
						'subscription' => $description,
						'quantity'     => $product['quantity'],
						'price'        => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
						'total'        => $this->currency->format($product['total'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $order_info['currency_code'], $order_info['currency_value'])
					];
				}

				$total_data = [];

				$totals = $this->model_sale_order->getTotals($order_id);

				foreach ($totals as $total) {
					$total_data[] = ['text' => $this->currency->format($total['value'], $order_info['currency_code'], $order_info['currency_value'])] + $total;
				}

				$data['orders'][] = [
					'order_id'         => $order_id,
					'invoice_no'       => $invoice_no,
					'date_added'       => date($this->language->get('date_format_short'), strtotime($order_info['date_added'])),
					'store_name'       => $order_info['store_name'],
					'store_url'        => rtrim($order_info['store_url'], '/'),
					'store_address'    => nl2br($store_address),
					'store_email'      => $store_email,
					'store_telephone'  => $store_telephone,
					'email'            => $order_info['email'],
					'telephone'        => $order_info['telephone'],
					'shipping_address' => $shipping_address,
					'shipping_method'  => ($order_info['shipping_method'] ? $order_info['shipping_method']['name'] : ''),
					'payment_address'  => $payment_address,
					'payment_method'   => $order_info['payment_method']['name'],
					'product'          => $product_data,
					'total'            => $total_data,
					'comment'          => nl2br($order_info['comment'])
				];
			}
		}

		$this->response->setOutput($this->load->view('sale/order_invoice', $data));
	}

	/**
	 * Shipping
	 *
	 * @return void
	 */
	public function shipping(): void {
		$this->load->language('sale/order');

		$data['title'] = $this->language->get('text_shipping');

		$data['base'] = HTTP_SERVER;
		$data['direction'] = $this->language->get('direction');
		$data['lang'] = $this->language->get('code');

		// Hard coding CSS so they can be replaced via the event's system.
		$data['bootstrap_css'] = 'view/stylesheet/bootstrap.css';
		$data['icons'] = 'view/stylesheet/fonts/fontawesome/css/all.min.css';
		$data['stylesheet'] = 'view/stylesheet/stylesheet.css';

		// Hard coding scripts so they can be replaced via the event's system.
		$data['jquery'] = 'view/javascript/jquery/jquery-3.7.1.min.js';
		$data['bootstrap_js'] = 'view/javascript/bootstrap/js/bootstrap.bundle.min.js';

		// Order
		$this->load->model('sale/order');

		// Product
		$this->load->model('catalog/product');

		// Setting
		$this->load->model('setting/setting');

		// Upload
		$this->load->model('tool/upload');

		// Subscription
		$this->load->model('sale/subscription');

		$data['orders'] = [];

		$orders = [];

		if (isset($this->request->post['selected'])) {
			$orders = (array)$this->request->post['selected'];
		}

		if (isset($this->request->get['order_id'])) {
			$orders[] = (int)$this->request->get['order_id'];
		}

		foreach ($orders as $order_id) {
			$order_info = $this->model_sale_order->getOrder($order_id);

			// Make sure there is a shipping method
			if ($order_info && $order_info['shipping_method']) {
				$store_info = $this->model_setting_setting->getSetting('config', $order_info['store_id']);

				if ($store_info) {
					$store_address = $store_info['config_address'];
					$store_email = $store_info['config_email'];
					$store_telephone = $store_info['config_telephone'];
				} else {
					$store_address = $this->config->get('config_address');
					$store_email = $this->config->get('config_email');
					$store_telephone = $this->config->get('config_telephone');
				}

				if ($order_info['invoice_no']) {
					$invoice_no = $order_info['invoice_prefix'] . $order_info['invoice_no'];
				} else {
					$invoice_no = '';
				}

				// Shipping Address
				if ($order_info['shipping_address_format']) {
					$format = $order_info['shipping_address_format'];
				} else {
					$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
				}

				$find = [
					'{firstname}',
					'{lastname}',
					'{company}',
					'{address_1}',
					'{address_2}',
					'{city}',
					'{postcode}',
					'{zone}',
					'{zone_code}',
					'{country}'
				];

				$replace = [
					'firstname' => $order_info['shipping_firstname'],
					'lastname'  => $order_info['shipping_lastname'],
					'company'   => $order_info['shipping_company'],
					'address_1' => $order_info['shipping_address_1'],
					'address_2' => $order_info['shipping_address_2'],
					'city'      => $order_info['shipping_city'],
					'postcode'  => $order_info['shipping_postcode'],
					'zone'      => $order_info['shipping_zone'],
					'zone_code' => $order_info['shipping_zone_code'],
					'country'   => $order_info['shipping_country']
				];

				$pattern_1 = [
					"\r\n",
					"\r",
					"\n"
				];

				$pattern_2 = [
					"/\\s\\s+/",
					"/\r\r+/",
					"/\n\n+/"
				];

				$shipping_address = str_replace($pattern_1, '<br/>', preg_replace($pattern_2, '<br/>', trim(str_replace($find, $replace, $format))));

				$product_data = [];

				$products = $this->model_sale_order->getProducts($order_id);

				foreach ($products as $product) {
					$option_weight = 0;

					$product_info = $this->model_catalog_product->getProduct($product['product_id']);

					if ($product_info) {
						$option_data = [];

						$options = $this->model_sale_order->getOptions($order_id, $product['order_product_id']);

						foreach ($options as $option) {
							if ($option['type'] != 'file') {
								$value = $option['value'];
							} else {
								$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

								if ($upload_info) {
									$value = $upload_info['name'];
								} else {
									$value = '';
								}
							}

							$option_data[] = ['value' => $value] + $option;

							$product_option_value_info = $this->model_catalog_product->getOptionValue($product['product_id'], $option['product_option_value_id']);

							if (!empty($product_option_value_info['weight'])) {
								if ($product_option_value_info['weight_prefix'] == '+') {
									$option_weight += $product_option_value_info['weight'];
								} elseif ($product_option_value_info['weight_prefix'] == '-') {
									$option_weight -= $product_option_value_info['weight'];
								}
							}
						}

						$product_data[] = [
							'option'   => $option_data,
							'quantity' => $product['quantity'],
							'weight'   => $this->weight->format(($product_info['weight'] + (float)$option_weight) * $product['quantity'], $product_info['weight_class_id'], $this->language->get('decimal_point'), $this->language->get('thousand_point'))
						] + $product_info;
					}
				}

				$data['orders'][] = [
					'order_id'         => $order_id,
					'invoice_no'       => $invoice_no,
					'date_added'       => date($this->language->get('date_format_short'), strtotime($order_info['date_added'])),
					'store_name'       => $order_info['store_name'],
					'store_url'        => rtrim($order_info['store_url'], '/'),
					'store_address'    => nl2br($store_address),
					'store_email'      => $store_email,
					'store_telephone'  => $store_telephone,
					'email'            => $order_info['email'],
					'telephone'        => $order_info['telephone'],
					'shipping_address' => $shipping_address,
					'shipping_method'  => $order_info['shipping_method']['name'],
					'product'          => $product_data,
					'comment'          => nl2br($order_info['comment'])
				];
			}
		}

		$this->response->setOutput($this->load->view('sale/order_shipping', $data));
	}

	/**
	 * History
	 *
	 * @return void
	 */
	public function history(): void {
		$this->load->language('sale/order');

		$this->response->setOutput($this->getHistory());
	}

	/**
	 * Get History
	 *
	 * @return string
	 */
	public function getHistory(): string {
		if (isset($this->request->get['order_id'])) {
			$order_id = (int)$this->request->get['order_id'];
		} else {
			$order_id = 0;
		}

		if (isset($this->request->get['page']) && $this->request->get['route'] == 'sale/order.history') {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$limit = 10;

		// Histories
		$data['histories'] = [];

		$this->load->model('sale/order');

		$results = $this->model_sale_order->getHistories($order_id, ($page - 1) * $limit, $limit);

		foreach ($results as $result) {
			$data['histories'][] = [
				'comment'    => nl2br($result['comment']),
				'notify'     => $result['notify'] ? $this->language->get('text_yes') : $this->language->get('text_no'),
				'date_added' => date($this->language->get('date_format_short'), strtotime($result['date_added']))
			] + $result;
		}

		$history_total = $this->model_sale_order->getTotalHistories($order_id);

		$data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $history_total,
			'page'  => $page,
			'limit' => $limit,
			'url'   => $this->url->link('sale/order.history', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($history_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($history_total - $limit)) ? $history_total : ((($page - 1) * $limit) + $limit), $history_total, ceil($history_total / $limit));

		return $this->load->view('sale/order_history', $data);
	}

	/**
	 * Create Invoice No
	 *
	 * @return void
	 */
	public function createInvoiceNo(): void {
		$this->load->language('sale/order');

		$json = [];

		if (isset($this->request->get['order_id'])) {
			$order_id = (int)$this->request->get['order_id'];
		} else {
			$order_id = 0;
		}

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		}

		// Order
		$this->load->model('sale/order');

		$order_info = $this->model_sale_order->getOrder($order_id);

		if ($order_info) {
			if ($order_info['invoice_no']) {
				$json['error'] = $this->language->get('error_invoice_no');
			}
		} else {
			$json['error'] = $this->language->get('error_order');
		}

		if (!$json) {
			$json['success'] = $this->language->get('text_success');

			// Order
			$this->load->model('sale/order');

			$json['invoice_no'] = $this->model_sale_order->createInvoiceNo($order_id);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Create Nova Poshta TTN
	 *
	 * @return void
	 */
	public function createNovaposhtaTtn(): void {
		$this->load->language('sale/order');

		$json = [];
		$order_id = $this->getOrderIdFromRequest();
		$order_info = [];

		if ($this->prepareNovaposhtaAction($order_id, $order_info, $json)) {
			// Optional operator-chosen sender warehouse (NP Ref). Empty
			// string keeps the legacy "first Warehouse, else first"
			// auto-pick inside fetchSenderData.
			$sender_address_ref = trim((string)($this->request->post['sender_address_ref'] ?? ''));

			$changes = [
				'delivery_type' => (string)($this->request->post['delivery_type'] ?? ''),
				'recipient_city_ref' => (string)($this->request->post['recipient_city_ref'] ?? ''),
				'recipient_address_ref' => (string)($this->request->post['recipient_address_ref'] ?? ''),
				'recipient_phone' => (string)($this->request->post['recipient_phone'] ?? ''),
				'Weight' => (string)($this->request->post['Weight'] ?? ''),
				'SeatsAmount' => (string)($this->request->post['SeatsAmount'] ?? ''),
				'Cost' => (string)($this->request->post['Cost'] ?? ''),
				'Description' => (string)($this->request->post['Description'] ?? ''),
				'PayerType' => (string)($this->request->post['PayerType'] ?? ''),
				'additional_service' => (string)($this->request->post['additional_service'] ?? ''),
				'cod_total' => (string)($this->request->post['cod_total'] ?? ''),
				'cod_payer' => (string)($this->request->post['cod_payer'] ?? ''),
			];
			$result = $this->model_extension_manline_shipping_novaposhta->createTtnForOrder($order_id, false, $sender_address_ref, $changes);

			if (empty($result['success'])) {
				$json['error'] = (string)($result['error'] ?? $this->language->get('error_np_ttn_failed'));
			} else {
				$json['success'] = $this->language->get('text_np_ttn_success');
				$json['ttn_number'] = (string)($result['ttn_number'] ?? '');
				$json['ttn_ref'] = (string)($result['ttn_ref'] ?? '');
				$json['ttn_date_created'] = (string)($result['ttn_date_created'] ?? '');
				$json['print_url'] = (string)($result['print_url'] ?? $this->model_extension_manline_shipping_novaposhta->getPrintUrlByOrderId($order_id));

				$this->addOrderHistoryLog($order_id, sprintf($this->language->get('text_np_history_created'), $json['ttn_number']));
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Recreate Nova Poshta TTN
	 *
	 * @return void
	 */
	public function recreateNovaposhtaTtn(): void {
		$this->load->language('sale/order');

		$json = [];
		$order_id = $this->getOrderIdFromRequest();
		$order_info = [];

		if ($this->prepareNovaposhtaAction($order_id, $order_info, $json)) {
			$meta_before = $this->model_extension_manline_shipping_novaposhta->getOrderMeta($order_id);
			$old_ttn = trim((string)($meta_before['ttn_number'] ?? ''));

			$sender_address_ref = trim((string)($this->request->post['sender_address_ref'] ?? ''));

			$result = $this->model_extension_manline_shipping_novaposhta->createTtnForOrder($order_id, true, $sender_address_ref);

			if (empty($result['success'])) {
				$json['error'] = (string)($result['error'] ?? $this->language->get('error_np_ttn_failed'));
			} else {
				$json['success'] = $this->language->get('text_np_ttn_recreate_success');
				$json['ttn_number'] = (string)($result['ttn_number'] ?? '');
				$json['ttn_ref'] = (string)($result['ttn_ref'] ?? '');
				$json['ttn_date_created'] = (string)($result['ttn_date_created'] ?? '');
				$json['print_url'] = (string)($result['print_url'] ?? $this->model_extension_manline_shipping_novaposhta->getPrintUrlByOrderId($order_id));

				$history_ttn = $json['ttn_number'];

				if ($old_ttn !== '') {
					$history_ttn .= ' (old: ' . $old_ttn . ')';
				}

				$this->addOrderHistoryLog($order_id, sprintf($this->language->get('text_np_history_recreated'), $history_ttn));
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Edit Nova Poshta TTN in-place via NP `InternetDocument.update`.
	 *
	 * The TTN number / Ref stays the same — only the payload (weight,
	 * seats, cost, cargo type, description, payer, payment method) is
	 * updated server-side at NP. Use this when the old TTN number has
	 * already been printed or given to the customer.
	 *
	 * If NP rejects the update (typically because the document is no
	 * longer in a pre-pickup status and is therefore immutable), the
	 * verbatim NP error is returned in `error` so the UI can offer
	 * Recreate as a fallback.
	 *
	 * @return void
	 */
	public function editNovaposhtaTtn(): void {
		$this->load->language('sale/order');

		$json = [];
		$order_id = $this->getOrderIdFromRequest();
		$order_info = [];

		if ($this->prepareNovaposhtaAction($order_id, $order_info, $json)) {
			// NP-shape scalar fields — forwarded straight through.
			$changes = [];

			$scalar_fields = [
				'Weight', 'SeatsAmount', 'Cost', 'CargoType', 'Description',
				'PayerType', 'PaymentMethod', 'VolumeGeneral', 'RecipientsPhone',
			];

			foreach ($scalar_fields as $field) {
				if (isset($this->request->post[$field]) && $this->request->post[$field] !== '') {
					$changes[$field] = $this->request->post[$field];
				}
			}

			// High-level form fields — translated to NP-shape inside the model.
			//   additional/cod_* → BackwardDeliveryData or AfterpaymentOnGoodsCost
			//   volume_*         → OptionsSeat[].volumetric*
			//   internal_number  → InfoRegClientBarcodes
			//   recipient_*      → CityRecipient / RecipientAddress / ServiceType
			$high_level = [
				'additional_service', 'cod_enabled', 'cod_total', 'cod_payer',
				'volume_width', 'volume_length', 'volume_height',
				'internal_number',
				'recipient_city_name', 'recipient_address_name', 'delivery_type',
				'recipient_city_ref', 'recipient_address_ref',
				'sender_address_ref',
			];

			foreach ($high_level as $field) {
				if (array_key_exists($field, $this->request->post)) {
					$changes[$field] = $this->request->post[$field];
				}
			}

			$result = $this->model_extension_manline_shipping_novaposhta->updateTtnForOrder($order_id, $changes);

			if (empty($result['success'])) {
				$json['error'] = (string)($result['error'] ?? $this->language->get('error_np_ttn_failed'));
			} else {
				$json['success'] = $this->language->get('text_np_ttn_edit_success');
				$json['ttn_number'] = (string)($result['ttn_number'] ?? '');
				$json['ttn_ref'] = (string)($result['ttn_ref'] ?? '');
				$json['changed_fields'] = (array)($result['changed_fields'] ?? []);
				$json['old_values'] = (array)($result['old_values'] ?? []);
				$json['new_values'] = (array)($result['new_values'] ?? []);

				// Order-history breadcrumb: "TTN N edited: Weight 0.5 → 1.0, Cost 350 → 400".
				// Array-valued fields (BackwardDeliveryData, OptionsSeat) can't be
				// cast to string directly — PHP emits a Warning that bleeds into
				// the JSON response and breaks parsing on the client. Compress
				// arrays to a marker so the history entry stays clean.
				$diff_parts = [];

				foreach ($json['new_values'] as $field => $new_value) {
					$old_value = $json['old_values'][$field] ?? '';

					if (is_array($old_value) || is_array($new_value)) {
						$diff_parts[] = $field . ' (updated)';
					} else {
						$diff_parts[] = $field . ' ' . (string)$old_value . ' → ' . (string)$new_value;
					}
				}

				$history_text = sprintf(
					$this->language->get('text_np_history_edited'),
					$json['ttn_number'],
					$diff_parts ? implode(', ', $diff_parts) : '(no changes)'
				);

				$this->addOrderHistoryLog($order_id, $history_text);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Refresh Nova Poshta TTN status from API
	 *
	 * @return void
	 */
	public function refreshNovaposhtaStatus(): void {
		$this->load->language('sale/order');

		$json = [];
		$order_id = $this->getOrderIdFromRequest();
		$order_info = [];

		if ($this->prepareNovaposhtaAction($order_id, $order_info, $json)) {
			$result = $this->model_extension_manline_shipping_novaposhta->refreshTtnStatusForOrder($order_id);

			if (empty($result['success'])) {
				$json['error'] = (string)($result['error'] ?? $this->language->get('error_np_ttn_status_failed'));
			} else {
				$json['success'] = $this->language->get('text_np_status_refresh_success');
				$json['ttn_number'] = (string)($result['ttn_number'] ?? '');
				$json['ttn_status_code'] = (string)($result['ttn_status_code'] ?? '');
				$json['ttn_status_text'] = (string)($result['ttn_status_text'] ?? '');
				$json['ttn_status_date'] = (string)($result['ttn_status_date'] ?? '');
				$json['changed'] = !empty($result['changed']);

				if (!$json['changed']) {
					$json['warning'] = $this->language->get('text_np_status_refresh_unchanged');
				}

				$history_comment = sprintf(
					$this->language->get('text_np_history_status_refreshed'),
					$json['ttn_number'],
					$json['ttn_status_code'],
					$json['ttn_status_text']
				);

				if ($json['ttn_status_date'] !== '') {
					$history_comment .= ' (' . $json['ttn_status_date'] . ')';
				}

				$this->addOrderHistoryLog($order_id, $history_comment);

				$sync_result = $this->syncOrderStatusFromNovaposhta(
					$order_id,
					$json['ttn_status_code'],
					$json['ttn_status_text'],
					$json['ttn_status_date']
				);

				if (!empty($sync_result['applied'])) {
					$json['order_status_id'] = (int)$sync_result['order_status_id'];
					$json['order_status_name'] = (string)$sync_result['order_status_name'];
					$this->appendJsonMessage($json, 'success', sprintf($this->language->get('text_np_status_order_sync_success'), $json['order_status_name']));
				}

				if (!empty($sync_result['warning'])) {
					$this->appendJsonMessage($json, 'warning', (string)$sync_result['warning']);
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Cancel Nova Poshta TTN in API and remove from order meta
	 *
	 * @return void
	 */
	public function deleteNovaposhtaTtn(): void {
		$this->load->language('sale/order');

		$json = [];
		$order_id = $this->getOrderIdFromRequest();
		$order_info = [];

		if ($this->prepareNovaposhtaAction($order_id, $order_info, $json)) {
			$result = $this->model_extension_manline_shipping_novaposhta->deleteTtnForOrder($order_id);

			if (empty($result['success'])) {
				$json['error'] = (string)($result['error'] ?? $this->language->get('error_np_ttn_not_found'));
			} else {
				$deleted_ttn = (string)($result['deleted_ttn_number'] ?? '');
				$json['success'] = $this->language->get('text_np_ttn_delete_success');
				$json['deleted_ttn_number'] = $deleted_ttn;
				$json['remote_deleted'] = !empty($result['remote_deleted']);
				$json['remote_already_missing'] = !empty($result['remote_already_missing']);

				if ($json['remote_already_missing']) {
					$json['warning'] = $this->language->get('text_np_ttn_delete_missing_remote');
				}

				$this->addOrderHistoryLog($order_id, sprintf($this->language->get('text_np_history_deleted'), $deleted_ttn));
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Live lookup endpoint for the admin TTN edit modal address pickers —
	 * mirrors the checkout-side `simplecheckout.novaposhta` response shape so
	 * the same dropdown widget renders results identically on both sides.
	 *
	 * POST params:
	 *   action        — 'getCities' | 'getWarehouses' | 'getWarehouseByRef'
	 *   search        — free-text query (optional; empty returns top-N list)
	 *   city_ref      — required for getWarehouses
	 *   delivery_type — 'branch' | 'locker' | 'courier' (filters warehouse list)
	 */
	public function novaposhtaLookup(): void {
		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		$this->load->model('extension/manline/shipping/novaposhta');

		$action = trim((string)($this->request->post['action'] ?? ''));
		$search = trim((string)($this->request->post['search'] ?? ''));
		$city_ref = trim((string)($this->request->post['city_ref'] ?? ''));
		$delivery_type = trim((string)($this->request->post['delivery_type'] ?? ''));

		if ($action === 'getCities') {
			$json = $this->model_extension_manline_shipping_novaposhta->lookupCities($search);
		} elseif ($action === 'getWarehouses') {
			$json = $this->model_extension_manline_shipping_novaposhta->lookupWarehouses($city_ref, $search, $delivery_type);
		} elseif ($action === 'getWarehouseByRef') {
			$json = $this->model_extension_manline_shipping_novaposhta->lookupWarehouseByRef(trim((string)($this->request->post['ref'] ?? '')));
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Lookup endpoint for the sender warehouse picker in the TTN edit /
	 * create modal. Returns the full list of addresses configured under
	 * the (single) sender counterparty in the connected NP account, so
	 * the operator can pick "shipping from" instead of relying on
	 * fetchSenderData()'s implicit "first Warehouse, else first" default.
	 *
	 * Idempotent GET-style POST (POST so the same permission gate as the
	 * other Novaposhta lookup endpoints applies and we can extend later
	 * with filter params without breaking the URL contract).
	 */
	public function novaposhtaSenderLookup(): void {
		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		$this->load->model('extension/manline/shipping/novaposhta');

		$json = $this->model_extension_manline_shipping_novaposhta->lookupSenderAddresses();

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Create Checkbox receipt (fiscalization)
	 */
	public function createCheckboxReceipt(): void {
		$this->load->language('sale/order');

		$json = [];
		$order_id = $this->getOrderIdFromRequest();
		$order_info = [];

		if ($this->prepareCheckboxAction($order_id, $order_info, $json)) {
			$this->load->model('extension/manline/integration/checkbox');
			$config = $this->getCheckboxConfig();

			if (empty($config['enabled'])) {
				$json['error'] = $this->language->get('error_checkbox_disabled');
			} else {
				$payload = $this->buildCheckboxSellPayload($order_info);

				$result = $this->model_extension_manline_integration_checkbox->createSellReceipt($config, $payload);

				if (empty($result['success'])) {
					$json['error'] = (string)($result['error'] ?? $this->language->get('error_checkbox_failed'));

					$this->model_extension_manline_integration_checkbox->saveOrderMeta($order_id, [
						'error' => $json['error'],
						'payload' => $payload,
						'response' => (array)($result['response'] ?? [])
					]);
				} else {
					$receipt_id = (string)($result['receipt_id'] ?? '');

					$json['success'] = $this->language->get('text_checkbox_receipt_created');
					$json['receipt_id'] = $receipt_id;
					$json['pdf_url'] = rtrim((string)$config['api_url'], '/') . '/api/v1/receipts/' . $receipt_id . '/pdf';

					$this->model_extension_manline_integration_checkbox->saveOrderMeta($order_id, [
						'receipt_id' => $receipt_id,
						'receipt_status' => 'CREATED',
						'error' => '',
						'payload' => $payload,
						'response' => (array)($result['response'] ?? [])
					]);

					$this->addOrderHistoryLog($order_id, sprintf($this->language->get('text_checkbox_history_created'), $receipt_id));
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Send Checkbox receipt via SMS
	 */
	public function sendCheckboxReceiptSms(): void {
		$this->load->language('sale/order');

		$json = [];
		$order_id = $this->getOrderIdFromRequest();
		$order_info = [];

		if ($this->prepareCheckboxAction($order_id, $order_info, $json)) {
			$this->load->model('extension/manline/integration/checkbox');
			$config = $this->getCheckboxConfig();

			if (empty($config['enabled'])) {
				$json['error'] = $this->language->get('error_checkbox_disabled');
			} else {
				$meta = $this->model_extension_manline_integration_checkbox->getOrderMeta($order_id);
				$receipt_id = trim((string)($meta['receipt_id'] ?? ''));

				if ($receipt_id === '') {
					$json['error'] = $this->language->get('error_checkbox_no_receipt');
				} else {
					$requested_phone = '';
					if (!empty($this->request->post['phone'])) {
						$requested_phone = (string)$this->request->post['phone'];
					}

					$phone380 = $this->model_extension_manline_integration_checkbox->normalizePhoneTo380($requested_phone !== '' ? $requested_phone : (string)($order_info['telephone'] ?? ''));

					if ($phone380 === '') {
						$json['error'] = $this->language->get('error_checkbox_phone');
					} else {
						$result = $this->model_extension_manline_integration_checkbox->sendReceiptSms($config, $receipt_id, $phone380);

						if (empty($result['success'])) {
							$json['error'] = (string)($result['error'] ?? $this->language->get('error_checkbox_failed'));
							$this->model_extension_manline_integration_checkbox->saveOrderMeta($order_id, [
								'receipt_id' => $receipt_id,
								'sms_phone' => $phone380,
								'sms_sent' => 0,
								'error' => $json['error'],
								'response' => (array)($result['response'] ?? [])
							]);
						} else {
							$json['success'] = $this->language->get('text_checkbox_sms_sent');
							$this->model_extension_manline_integration_checkbox->saveOrderMeta($order_id, [
								'receipt_id' => $receipt_id,
								'sms_phone' => $phone380,
								'sms_sent' => 1,
								'error' => ''
							]);

							$this->addOrderHistoryLog($order_id, sprintf($this->language->get('text_checkbox_history_sms'), $receipt_id, $phone380));
						}
					}
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	
	/**
	 * Create Checkbox return receipt (manual refund/storno)
	 */
	public function createCheckboxReturnReceipt(): void {
		$this->load->language('sale/order');

		$json = [];
		$order_id = $this->getOrderIdFromRequest();
		$order_info = [];

		if ($this->prepareCheckboxAction($order_id, $order_info, $json)) {
			$this->load->model('extension/manline/integration/checkbox');
			$module_id = 0;
			if (isset($this->request->post['module_id'])) {
				$module_id = (int)$this->request->post['module_id'];
			}
			$config = $this->getCheckboxConfig($module_id);

			if (empty($config['enabled'])) {
				$json['error'] = $this->language->get('error_checkbox_disabled');
			} else {
				$meta = $this->model_extension_manline_integration_checkbox->getOrderMeta($order_id);
				$sell_receipt_id = trim((string)($meta['receipt_id'] ?? ''));
				if ($sell_receipt_id === '') {
					$json['error'] = $this->language->get('error_checkbox_no_receipt');
				} else {
					$result = $this->model_extension_manline_integration_checkbox->createReturnReceipt($config, $sell_receipt_id);
					if (empty($result['success'])) {
						$json['error'] = (string)($result['error'] ?? $this->language->get('error_checkbox_failed'));
						$this->model_extension_manline_integration_checkbox->saveOrderMeta($order_id, [
							'module_id' => $module_id,
							'receipt_id' => $sell_receipt_id,
							'error' => $json['error'],
							'response' => (array)($result['response'] ?? [])
						]);
					} else {
						$return_id = (string)($result['receipt_id'] ?? '');
						$json['success'] = $this->language->get('text_checkbox_return_created');
						$json['return_receipt_id'] = $return_id;
						$json['pdf_url'] = rtrim((string)$config['api_url'], '/') . '/api/v1/receipts/' . $return_id . '/pdf';

						$this->model_extension_manline_integration_checkbox->saveOrderMeta($order_id, [
							'module_id' => $module_id,
							'receipt_id' => $sell_receipt_id,
							'return_receipt_id' => $return_id,
							'return_receipt_status' => 'CREATED',
							'error' => '',
							'response' => (array)($result['response'] ?? [])
						]);

						$this->addOrderHistoryLog($order_id, sprintf($this->language->get('text_checkbox_history_return_created'), $return_id, $sell_receipt_id));
					}
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Send Checkbox return receipt via SMS
	 */
	public function sendCheckboxReturnReceiptSms(): void {
		$this->load->language('sale/order');

		$json = [];
		$order_id = $this->getOrderIdFromRequest();
		$order_info = [];

		if ($this->prepareCheckboxAction($order_id, $order_info, $json)) {
			$this->load->model('extension/manline/integration/checkbox');
			$module_id = 0;
			if (isset($this->request->post['module_id'])) {
				$module_id = (int)$this->request->post['module_id'];
			}
			$config = $this->getCheckboxConfig($module_id);

			if (empty($config['enabled'])) {
				$json['error'] = $this->language->get('error_checkbox_disabled');
			} else {
				$meta = $this->model_extension_manline_integration_checkbox->getOrderMeta($order_id);
				$return_id = trim((string)($meta['return_receipt_id'] ?? ''));
				if ($return_id === '') {
					$json['error'] = $this->language->get('error_checkbox_no_return');
				} else {
					$requested_phone = '';
					if (!empty($this->request->post['phone'])) {
						$requested_phone = (string)$this->request->post['phone'];
					}
					$phone380 = $this->model_extension_manline_integration_checkbox->normalizePhoneTo380($requested_phone !== '' ? $requested_phone : (string)($order_info['telephone'] ?? ''));
					if ($phone380 === '') {
						$json['error'] = $this->language->get('error_checkbox_phone');
					} else {
						$result = $this->model_extension_manline_integration_checkbox->sendReceiptSms($config, $return_id, $phone380);
						if (empty($result['success'])) {
							$json['error'] = (string)($result['error'] ?? $this->language->get('error_checkbox_failed'));
							$this->model_extension_manline_integration_checkbox->saveOrderMeta($order_id, [
								'module_id' => $module_id,
								'return_receipt_id' => $return_id,
								'sms_phone' => $phone380,
								'return_sms_sent' => 0,
								'error' => $json['error'],
								'response' => (array)($result['response'] ?? [])
							]);
						} else {
							$json['success'] = $this->language->get('text_checkbox_return_sms_sent');
							$this->model_extension_manline_integration_checkbox->saveOrderMeta($order_id, [
								'module_id' => $module_id,
								'return_receipt_id' => $return_id,
								'sms_phone' => $phone380,
								'return_sms_sent' => 1,
								'error' => ''
							]);
							$this->addOrderHistoryLog($order_id, sprintf($this->language->get('text_checkbox_history_return_sms'), $return_id, $phone380));
						}
					}
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
/**
	 * Add Reward
	 *
	 * @return void
	 */
	public function addReward(): void {
		$this->load->language('sale/order');

		$json = [];

		if (isset($this->request->get['order_id'])) {
			$order_id = (int)$this->request->get['order_id'];
		} else {
			$order_id = 0;
		}

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		}

		// Order
		$this->load->model('sale/order');

		$order_info = $this->model_sale_order->getOrder($order_id);

		if ($order_info) {
			if (!$order_info['customer_id']) {
				$json['error'] = $this->language->get('error_reward_guest');
			}
		} else {
			$json['error'] = $this->language->get('error_order');
		}

		// Customer
		$this->load->model('customer/customer');

		$reward_total = $this->model_customer_customer->getTotalRewardsByOrderId($order_id);

		if ($reward_total) {
			$json['error'] = $this->language->get('error_reward_add');
		}

		if (!$json) {
			$this->model_customer_customer->addReward($order_info['customer_id'], $this->language->get('text_order_id') . ' #' . $order_id, $order_info['reward'], $order_id);

			$json['success'] = $this->language->get('text_reward_add');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Check Checkbox authentication (show cashier profile)
	 */
	public function checkCheckboxAuth(): void {
		$this->load->language('sale/order');

		$json = [];
		$order_id = $this->getOrderIdFromRequest();
		$order_info = [];

		if ($this->prepareCheckboxAction($order_id, $order_info, $json)) {
			$this->load->model('extension/manline/integration/checkbox');
			$module_id = 0;
			if (isset($this->request->post['module_id'])) {
				$module_id = (int)$this->request->post['module_id'];
			}
			$config = $this->getCheckboxConfig($module_id);

			if (empty($config['enabled'])) {
				$json['error'] = $this->language->get('error_checkbox_disabled');
			} else {
				$result = $this->model_extension_manline_integration_checkbox->cashierMe($config);
				if (empty($result['success'])) {
					$json['error'] = (string)($result['error'] ?? $this->language->get('error_checkbox_failed'));
					$json['details'] = (array)($result['response'] ?? []);
				} else {
					$cashier = (array)($result['response'] ?? []);
					$name = (string)($cashier['full_name'] ?? $cashier['email'] ?? '');
					$id = (string)($cashier['id'] ?? '');
					$json['success'] = sprintf($this->language->get('text_checkbox_auth_ok'), $name !== '' ? $name : $id);
					$json['cashier'] = $cashier;
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Remove Reward
	 *
	 * @return void
	 */
	public function removeReward(): void {
		$this->load->language('sale/order');

		$json = [];

		if (isset($this->request->get['order_id'])) {
			$order_id = (int)$this->request->get['order_id'];
		} else {
			$order_id = 0;
		}

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		}

		// Order
		$this->load->model('sale/order');

		$order_info = $this->model_sale_order->getOrder($order_id);

		if (!$order_info) {
			$json['error'] = $this->language->get('error_order');
		}

		if (!$json) {
			// Customer
			$this->load->model('customer/customer');

			$this->model_customer_customer->deleteRewardsByOrderId($order_id);

			$json['success'] = $this->language->get('text_reward_remove');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Add Commission
	 *
	 * @return void
	 */
	public function addCommission(): void {
		$this->load->language('sale/order');

		$json = [];

		if (isset($this->request->get['order_id'])) {
			$order_id = (int)$this->request->get['order_id'];
		} else {
			$order_id = 0;
		}

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		}

		// Order
		$this->load->model('sale/order');

		$order_info = $this->model_sale_order->getOrder($order_id);

		if ($order_info) {
			// Customer
			$this->load->model('customer/customer');

			$customer_info = $this->model_customer_customer->getCustomer($order_info['affiliate_id']);

			if (!$customer_info) {
				$json['error'] = $this->language->get('error_affiliate');
			}

			$affiliate_total = $this->model_customer_customer->getTotalTransactionsByOrderId($order_id);

			if ($affiliate_total) {
				$json['error'] = $this->language->get('error_commission_add');
			}
		} else {
			$json['error'] = $this->language->get('error_order');
		}

		if (!$json) {
			$this->model_customer_customer->addTransaction($order_info['affiliate_id'], $this->language->get('text_order_id') . ' #' . $order_id, $order_info['commission'], $order_id);

			$json['success'] = $this->language->get('text_commission_add');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Remove Commission
	 *
	 * @return void
	 */
	public function removeCommission(): void {
		$this->load->language('sale/order');

		$json = [];

		if (isset($this->request->get['order_id'])) {
			$order_id = (int)$this->request->get['order_id'];
		} else {
			$order_id = 0;
		}

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		}

		// Order
		$this->load->model('sale/order');

		$order_info = $this->model_sale_order->getOrder($order_id);

		if (!$order_info) {
			$json['error'] = $this->language->get('error_order');
		}

		if (!$json) {
			// Customer
			$this->load->model('customer/customer');

			$this->model_customer_customer->deleteTransactionsByOrderId($order_id);

			$json['success'] = $this->language->get('text_commission_remove');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Autocomplete
	 *
	 * @return void
	 */
	public function autocomplete(): void {
		$this->load->language('sale/order');

		$json = [];

		// Order
		if (isset($this->request->get['order_id'])) {
			$order_id = (int)$this->request->get['order_id'];
		} else {
			$order_id = 0;
		}

		$this->load->model('sale/order');

		$order_info = $this->model_sale_order->getOrder($order_id);

		if (!$order_info) {
			$json['error'] = $this->language->get('error_order');
		}

		if (!$json) {
			$json = $order_info;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function getOrderIdFromRequest(): int {
		if (isset($this->request->get['order_id'])) {
			return (int)$this->request->get['order_id'];
		}

		if (isset($this->request->post['order_id'])) {
			return (int)$this->request->post['order_id'];
		}

		return 0;
	}

	private function prepareNovaposhtaAction(int $order_id, array &$order_info, array &$json): bool {
		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');

			return false;
		}

		$this->load->model('sale/order');

		$order_info = $this->model_sale_order->getOrder($order_id);

		if (!$order_info) {
			$json['error'] = $this->language->get('error_order');

			return false;
		}

		$this->load->model('extension/manline/shipping/novaposhta');

		$shipping_code = (string)($order_info['shipping_method']['code'] ?? '');

		if (strpos($shipping_code, 'novaposhta.') !== 0) {
			$novaposhta_meta = $this->model_extension_manline_shipping_novaposhta->getOrderMeta($order_id);
			$shipping_code = (string)($novaposhta_meta['shipping_code'] ?? '');
		}

		if (strpos($shipping_code, 'novaposhta.') !== 0) {
			$json['error'] = $this->language->get('error_np_not_order');

			return false;
		}

		return true;
	}

	private function prepareCheckboxAction(int $order_id, array &$order_info, array &$json): bool {
		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');

			return false;
		}

		$this->load->model('sale/order');
		$order_info = $this->model_sale_order->getOrder($order_id);

		if (!$order_info) {
			$json['error'] = $this->language->get('error_order');

			return false;
		}

		return true;
	}

	private function getCheckboxConfig(int $module_id = 0): array {
		$config = ['enabled' => false];

		$this->load->model('setting/module');

		if ($module_id > 0) {
			$module = $this->model_setting_module->getModule($module_id);
			if (is_array($module) && !empty($module['status'])) {
				$module['enabled'] = true;
				return $module;
			}
			return $config;
		}

		$modules = $this->model_setting_module->getModulesByCode('manline.checkbox');
		foreach ($modules as $m) {
			$settings = json_decode($m['setting'] ?? '', true);
			if (!is_array($settings)) {
				$settings = [];
			}
			if (!empty($settings['status'])) {
				$settings['enabled'] = true;
				$settings['module_id'] = (int)($m['module_id'] ?? 0);
				$settings['name'] = (string)($m['name'] ?? 'Checkbox');
				return $settings;
			}
		}

		return $config;
	}

	
	private function buildCheckboxReturnPayload(array $order_info, string $sell_receipt_id): array {
		$payload = $this->buildCheckboxSellPayload($order_info);

		// Mark as RETURN by linking to original receipt
		$payload['id'] = $this->uuidv4();
		$payload['related_receipt_id'] = $sell_receipt_id;

		// For return receipts, payments should be negative values (money outflow).
		if (!empty($payload['payments']) && is_array($payload['payments'])) {
			foreach ($payload['payments'] as &$p) {
				if (is_array($p) && isset($p['value'])) {
					$p['value'] = 0 - (int)$p['value'];
				}
			}
			unset($p);
		}

		return $payload;
	}

private function buildCheckboxSellPayload(array $order_info): array {
		$this->load->model('sale/order');
		$products = $this->model_sale_order->getProducts((int)$order_info['order_id']);

		$goods = [];
		foreach ($products as $p) {
			$name = (string)($p['name'] ?? '');
			$model = (string)($p['model'] ?? '');
			$qty = (int)($p['quantity'] ?? 0);
			$price = (float)($p['price'] ?? 0.0);

			if ($qty <= 0) {
				continue;
			}

			// Checkbox expects amount in kopiykas and quantity in milli-units (1 = 1000)
			$price_uah = $this->normalizeOrderCurrencyAmount($price, $order_info);
			$price_kop = (int)round($price_uah * 100);

			$goods[] = [
				'good' => [
					'code' => $model !== '' ? $model : ('order-' . (int)$order_info['order_id']),
					'name' => $name,
					'price' => $price_kop
				],
				'quantity' => $qty * 1000
			];
		}

		$total_uah = $this->normalizeOrderCurrencyAmount((float)($order_info['total'] ?? 0.0), $order_info);
		$total_kop = (int)round($total_uah * 100);

		$payments = [];
		if ($this->isCodPayment($order_info)) {
			$payments[] = ['type' => 'CASH', 'value' => $total_kop, 'label' => 'COD'];
		} else {
			$payments[] = ['type' => 'CASHLESS', 'value' => $total_kop, 'label' => 'Online'];
		}

		return [
			'id' => $this->uuidv4(),
			'goods' => $goods,
			'payments' => $payments
		];
	}

	private function normalizeOrderCurrencyAmount(float $amount, array $order_info): float {
		// OC stores order values in order currency. If order currency is not UAH, try to convert to base using currency_value.
		$currency_code = (string)($order_info['currency_code'] ?? '');
		$currency_value = (float)($order_info['currency_value'] ?? 1.0);

		if ($currency_code === 'UAH' || $currency_code === 'грн' || $currency_value <= 0) {
			return $amount;
		}

		// In OC: total in order currency, currency_value is rate to default. For safety we just divide.
		return $amount / $currency_value;
	}

	private function isCodPayment(array $order_info): bool {
		$code = '';
		if (!empty($order_info['payment_method']) && is_array($order_info['payment_method'])) {
			$code = (string)($order_info['payment_method']['code'] ?? '');
		}
		$code = strtolower($code);
		if ($code === '') {
			$code = strtolower((string)($order_info['payment_code'] ?? ''));
		}

		return $code !== '' && (
			strpos($code, 'cod') !== false ||
			strpos($code, 'cash') !== false ||
			strpos($code, 'upon') !== false
		);
	}

	private function uuidv4(): string {
		$data = random_bytes(16);
		$data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
		$data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}

	private function addOrderHistoryLog(int $order_id, string $comment): void {
		$comment = trim($comment);

		if ($order_id <= 0 || $comment === '') {
			return;
		}

		$query = $this->db->query("SELECT order_status_id FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "' LIMIT 1");

		if (!$query->num_rows) {
			return;
		}

		$order_status_id = (int)$query->row['order_status_id'];

		$this->db->query(
			"INSERT INTO `" . DB_PREFIX . "order_history`
			SET order_id = '" . (int)$order_id . "',
				order_status_id = '" . (int)$order_status_id . "',
				notify = '0',
				comment = '" . $this->db->escape($comment) . "',
				date_added = NOW()"
		);
	}

	private function syncOrderStatusFromNovaposhta(int $order_id, string $np_status_code, string $np_status_text, string $np_status_date): array {
		$target_order_status_id = $this->mapNovaposhtaStatusCodeToOrderStatusId($np_status_code);

		if ($target_order_status_id <= 0) {
			return ['applied' => false];
		}

		$query = $this->db->query("SELECT order_status_id FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "' LIMIT 1");

		if (!$query->num_rows) {
			return ['applied' => false, 'warning' => $this->language->get('error_order')];
		}

		$current_order_status_id = (int)$query->row['order_status_id'];

		if ($current_order_status_id === $target_order_status_id) {
			return ['applied' => false];
		}

		$current_rank = $this->getOrderStatusProgressRank($current_order_status_id);
		$target_rank = $this->getOrderStatusProgressRank($target_order_status_id);

		if ($current_rank > 0 && $target_rank > 0 && $target_rank <= $current_rank) {
			$current_name = $this->getOrderStatusNameById($current_order_status_id);
			$target_name = $this->getOrderStatusNameById($target_order_status_id);

			return [
				'applied' => false,
				'warning' => sprintf($this->language->get('text_np_status_order_sync_skipped'), $current_name, $target_name)
			];
		}

		$current_name = $this->getOrderStatusNameById($current_order_status_id);
		$target_name = $this->getOrderStatusNameById($target_order_status_id);

		$status_label = '[' . trim($np_status_code) . '] ' . trim($np_status_text);

		if (trim($np_status_date) !== '') {
			$status_label .= ' (' . trim($np_status_date) . ')';
		}

		$comment = sprintf(
			$this->language->get('text_np_history_order_status_sync'),
			$status_label,
			$current_name,
			$target_name
		);

		if (!$this->setOrderStatusWithHistory($order_id, $target_order_status_id, $comment)) {
			return ['applied' => false];
		}

		return [
			'applied' => true,
			'order_status_id' => $target_order_status_id,
			'order_status_name' => $target_name
		];
	}

	private function mapNovaposhtaStatusCodeToOrderStatusId(string $np_status_code): int {
		$np_status_code = trim($np_status_code);

		if ($np_status_code === '') {
			return 0;
		}

		$processing_codes = [
			'1', '4', '5', '6', '7', '8', '11', '12', '13', '14',
			'101', '102', '103', '104', '105', '106', '107', '108'
		];
		$delivered_codes = ['9'];

		$processing_order_status_id = (int)$this->config->get('shipping_novaposhta_order_status_processing_id');
		$delivered_order_status_id = (int)$this->config->get('shipping_novaposhta_order_status_delivered_id');

		if ($processing_order_status_id <= 0) {
			$processing_order_status_id = 2;
		}

		if ($delivered_order_status_id <= 0) {
			$delivered_order_status_id = 3;
		}

		if (in_array($np_status_code, $delivered_codes, true)) {
			return $delivered_order_status_id;
		}

		if (in_array($np_status_code, $processing_codes, true)) {
			return $processing_order_status_id;
		}

		return 0;
	}

	private function getOrderStatusProgressRank(int $order_status_id): int {
		if ($order_status_id <= 0) {
			return 0;
		}

		$default_order_status_id = (int)$this->config->get('config_order_status_id');
		$processing_order_status_id = (int)$this->config->get('shipping_novaposhta_order_status_processing_id');
		$delivered_order_status_id = (int)$this->config->get('shipping_novaposhta_order_status_delivered_id');
		$processing_statuses = array_map('intval', (array)$this->config->get('config_processing_status'));
		$complete_statuses = array_map('intval', (array)$this->config->get('config_complete_status'));

		if ($processing_order_status_id <= 0) {
			$processing_order_status_id = 2;
		}

		if ($delivered_order_status_id <= 0) {
			$delivered_order_status_id = 3;
		}

		if ($order_status_id === $default_order_status_id) {
			return 10;
		}

		if ($order_status_id === $delivered_order_status_id) {
			return 40;
		}

		if (in_array($order_status_id, $complete_statuses, true)) {
			return 50;
		}

		if ($order_status_id === $processing_order_status_id || in_array($order_status_id, $processing_statuses, true)) {
			return 20;
		}

		// Unknown/manual statuses are treated as advanced to avoid unsafe automatic overrides.
		return 35;
	}

	private function getOrderStatusNameById(int $order_status_id): string {
		if ($order_status_id <= 0) {
			return (string)$order_status_id;
		}

		$query = $this->db->query("SELECT name FROM `" . DB_PREFIX . "order_status` WHERE order_status_id = '" . (int)$order_status_id . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "' LIMIT 1");

		if ($query->num_rows) {
			return (string)$query->row['name'];
		}

		return (string)$order_status_id;
	}

	private function setOrderStatusWithHistory(int $order_id, int $order_status_id, string $comment): bool {
		if ($order_id <= 0 || $order_status_id <= 0) {
			return false;
		}

		$this->db->query(
			"UPDATE `" . DB_PREFIX . "order`
			SET order_status_id = '" . (int)$order_status_id . "',
				date_modified = NOW()
			WHERE order_id = '" . (int)$order_id . "'"
		);

		$this->db->query(
			"INSERT INTO `" . DB_PREFIX . "order_history`
			SET order_id = '" . (int)$order_id . "',
				order_status_id = '" . (int)$order_status_id . "',
				notify = '0',
				comment = '" . $this->db->escape(trim($comment)) . "',
				date_added = NOW()"
		);

		return true;
	}

	private function appendJsonMessage(array &$json, string $field, string $message): void {
		$message = trim($message);

		if ($message === '') {
			return;
		}

		if (empty($json[$field])) {
			$json[$field] = $message;
		} else {
			$json[$field] .= ' ' . $message;
		}
	}

	private function formatNovaposhtaDeliveryType(string $delivery_type): string {
		switch ($delivery_type) {
			case 'courier':
				return $this->language->get('text_np_delivery_courier');
			case 'locker':
				return $this->language->get('text_np_delivery_locker');
			case 'branch':
				return $this->language->get('text_np_delivery_branch');
			default:
				return $delivery_type ? $delivery_type : $this->language->get('text_none');
		}
	}
}
