<?php
/**
 * Liqpay for OpenCart 4
 * 
 * @package     OpenCartBot
 * @author      OpenCartBot
 * @copyright   (c) OpenCartBot
 * @license     Commercial License - This software is not open-source and cannot be redistributed, modified, or used without a valid license from the author.
 * @link        https://opencartbot.com
 * @support     support@opencartbot.com
 */

namespace Opencart\Admin\Controller\Extension\Liqpay\Payment;

class Liqpay extends \Opencart\System\Engine\Controller {
    private $confirm;

    public function index(): void {
        $this->load->language('extension/liqpay/payment/liqpay');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/liqpay/payment/liqpay', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['save'] = $this->url->link('extension/liqpay/payment/liqpay.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');
        $data['payments'] = $this->url->link('extension/liqpay/payment/liqpay.payments', 'user_token=' . $this->session->data['user_token']);
        $data['user_token'] = $this->session->data['user_token'];

        $data['payment_liqpay_title'] = $this->config->get('payment_liqpay_title') ?: [];
        $data['payment_liqpay_description'] = $this->config->get('payment_liqpay_description') ?: [];
        $data['payment_liqpay_public_key'] = $this->config->get('payment_liqpay_public_key');
        $data['payment_liqpay_private_key'] = $this->config->get('payment_liqpay_private_key');
        $data['payment_liqpay_action_type'] = $this->config->get('payment_liqpay_action_type') ?: 'pay';
        $data['payment_liqpay_total'] = $this->config->get('payment_liqpay_total');
        $data['payment_liqpay_order_status_id'] = (int)$this->config->get('payment_liqpay_order_status_id');
        $data['payment_liqpay_pending_status_id'] = (int)$this->config->get('payment_liqpay_pending_status_id');
        $data['payment_liqpay_hold_status_id'] = (int)$this->config->get('payment_liqpay_hold_status_id');
        $data['payment_liqpay_refund_status_id'] = (int)$this->config->get('payment_liqpay_refund_status_id');
        $data['payment_liqpay_failed_status_id'] = (int)$this->config->get('payment_liqpay_failed_status_id');
        $data['payment_liqpay_license'] = $this->config->get('payment_liqpay_license');

        $data['config_session_samesite'] = $this->config->get('config_session_samesite');

        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages();

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $data['payment_liqpay_geo_zone_id'] = $this->config->get('payment_liqpay_geo_zone_id');
        $data['payment_liqpay_status'] = $this->config->get('payment_liqpay_status');
        $data['payment_liqpay_sort_order'] = $this->config->get('payment_liqpay_sort_order');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/liqpay/payment/liqpay', $data));
    }

    public function save(): void {
        $this->load->language('extension/liqpay/payment/liqpay');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/liqpay/payment/liqpay')) {
            $json['error']['warning'] = $this->language->get('error_permission');
        }

        if (!empty($this->request->post['payment_liqpay_license']) && !empty($_SERVER['SERVER_NAME'])) {
            $domain = preg_replace('/^www\./', '', $_SERVER['SERVER_NAME']);
            $server = HTTP_SERVER;
            $parse_domain = parse_url($server);
            $config_domain = preg_replace('/^www\./', '', $parse_domain['host']);
            if ($domain == $config_domain) {
                if (filter_input(INPUT_POST, 'payment_liqpay_license', FILTER_SANITIZE_SPECIAL_CHARS)!=hash('sha256', 'liqpay' . $domain . base64_decode('RGFSeU5hMw=='))) {
                    $json['error']['warning'] = $this->language->get('error_license3');
                } else {
                    $this->confirm = true;
                }
            } else {
                $json['error']['warning'] = $this->language->get('error_license2') . ' '.$domain.' ('.$config_domain.')';
            }
        } else {
            $json['error']['warning'] = $this->language->get('error_license1');
        }

        if (!$this->request->post['payment_liqpay_public_key']) {
            $json['error']['public_key'] = $this->language->get('error_public_key');
        }

        if (!$this->request->post['payment_liqpay_private_key']) {
            $json['error']['private_key'] = $this->language->get('error_private_key');
        }

        if (!$json && $this->confirm) {
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('payment_liqpay', $this->request->post);
            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function payments(): void {
        $this->load->language('extension/liqpay/payment/liqpay');
        $this->load->model('extension/liqpay/payment/liqpay');

        $this->document->setTitle($this->language->get('heading_title_payments'));

        $page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
        $limit = $this->config->get('config_pagination_admin');

        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/liqpay/payment/liqpay', 'user_token=' . $this->session->data['user_token'], true)
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_payments'),
            'href' => $this->url->link('extension/liqpay/payment/liqpay.payments', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['capture'] = $this->url->link('extension/liqpay/payment/liqpay.capture', 'user_token=' . $this->session->data['user_token']);
        $data['refund'] = $this->url->link('extension/liqpay/payment/liqpay.refund', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('extension/liqpay/payment/liqpay', 'user_token=' . $this->session->data['user_token'], true);
        $data['user_token'] = $this->session->data['user_token'];

        $filter_data = [
            'start' => ($page - 1) * $limit,
            'limit' => $limit
        ];

        $transactions = $this->model_extension_liqpay_payment_liqpay->getTransactions($filter_data);
        $total_transactions = $this->model_extension_liqpay_payment_liqpay->getTotalTransactions();

        $data['transactions'] = [];
        foreach ($transactions as $transaction) {
            $data['transactions'][] = [
                'transaction_id' => $transaction['transaction_id'],
                'order_id' => $transaction['order_id'],
                'liqpay_order_id' => $transaction['liqpay_order_id'],
                'amount' => number_format($transaction['amount'], 2, '.', ''),
                'currency' => $transaction['currency'],
                'status' => $transaction['status'],
                'action_type' => $transaction['action_type'],
                'date_added' => date($this->language->get('datetime_format'), strtotime($transaction['date_added'])),
                'view_order' => $this->url->link('sale/order.info', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $transaction['order_id'], true)
            ];
        }

        $data['pagination'] = $this->load->controller('common/pagination', [
            'total' => $total_transactions,
            'page'  => $page,
            'limit' => $limit,
            'url'   => $this->url->link('extension/liqpay/payment/liqpay.payments', 'user_token=' . $this->session->data['user_token'] . '&page={page}')
        ]);

        $data['results'] = sprintf($this->language->get('text_pagination'), ($total_transactions) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($total_transactions - $limit)) ? $total_transactions : ((($page - 1) * $limit) + $limit), $total_transactions, ceil($total_transactions / $limit));

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/liqpay/payment/liqpay_payments', $data));
    }

    public function capture(): void {
        $this->load->language('extension/liqpay/payment/liqpay');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/liqpay/payment/liqpay')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!isset($this->request->post['transaction_id'])) {
            $json['error'] = $this->language->get('error_transaction');
        }

        if (!$json) {
            $this->load->model('extension/liqpay/payment/liqpay');

            $result = $this->model_extension_liqpay_payment_liqpay->capturePayment($this->request->post['transaction_id']);

            if ($result['success']) {
                if (!$this->addOrderHistory($this->request->post['order_id'], $this->config->get('payment_liqpay_order_status_id'), $this->language->get($result['message']))) {
                    $json['error'] = $this->language->get('error_capture_status_update');
                } else {
                    $json['success'] = $this->language->get($result['message']);
                }
            } else {
                $json['error'] = $this->language->get($result['message']);
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function refund(): void {
        $this->load->language('extension/liqpay/payment/liqpay');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/liqpay/payment/liqpay')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!isset($this->request->post['transaction_id']) || !isset($this->request->post['amount'])) {
            $json['error'] = $this->language->get('error_transaction');
        }

        if (!$json) {
            $this->load->model('extension/liqpay/payment/liqpay');

            $result = $this->model_extension_liqpay_payment_liqpay->refundPayment($this->request->post['transaction_id'], $this->request->post['amount']);

            if ($result['success']) {
                if (!$this->addOrderHistory($this->request->post['order_id'], $this->config->get('payment_liqpay_refund_status_id'), $this->language->get($result['message']))) {
                    $json['error'] = $this->language->get('error_refund_status_update');
                } else {
                    $json['success'] = $this->language->get($result['message']);
                }
            } else {
                $json['error'] = $this->language->get($result['message']);
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function addOrderHistory(int $orderId, int $orderStatusId, string $comment): bool {
        $originalPost = $this->request->post;
        $originalGet = $this->request->get;
        
        try {
            $this->request->post = [
                'order_status_id' => $orderStatusId,
                'override' => 0,
                'notify' => 0,
                'comment' => $comment,
                'order_id' => $orderId
            ];
            
            $this->request->get['call'] = 'history_add';
            $this->request->get['order_id'] = $orderId;
            $this->request->get['store_id'] = $this->config->get('config_store_id') ?? 0;
            $this->request->get['language'] = $this->config->get('config_language');
            $this->request->get['currency'] = $this->config->get('config_currency');
            $this->request->get['user_token'] = $this->session->data['user_token'];
            
            $this->response->setOutput('');
            $this->load->controller('sale/order.call');
            $output = $this->response->getOutput();
            
            $this->request->post = $originalPost;
            $this->request->get = $originalGet;
            
            $data = json_decode($output, true);
            
            if ($data && isset($data['error'])) {
                return false;
            }
            
            return $data && isset($data['success']);
            
        } catch (Exception $e) {
            $this->request->post = $originalPost;
            $this->request->get = $originalGet;
            return false;
        }
    }
    
    public function setSessionSameSite(): void {
        $this->load->language('extension/liqpay/payment/liqpay');
        
        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/liqpay/payment/liqpay')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $value = (string)$this->request->post['value'] ?? 'Lax';
            
            $this->load->model('setting/setting');
            $this->model_setting_setting->editValue('config', 'config_session_samesite', $value);

            $json['success'] = $this->language->get('text_session_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function install(): void {
        $this->load->model('extension/liqpay/payment/liqpay');
        $this->model_extension_liqpay_payment_liqpay->install();
    }

    public function uninstall(): void {
        $this->load->model('extension/liqpay/payment/liqpay');
        $this->model_extension_liqpay_payment_liqpay->uninstall();
    }
}