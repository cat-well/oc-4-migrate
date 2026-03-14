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

namespace Opencart\Catalog\Controller\Extension\Liqpay\Payment;

class Liqpay extends \Opencart\System\Engine\Controller {

    public function index(): string {
        $this->load->language('extension/liqpay/payment/liqpay');

        if (isset($this->session->data['order_id'])) {
            $this->load->model('checkout/order');

            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

            if ($order_info) {
                $data['action'] = 'https://www.liqpay.ua/api/3/checkout';
                $data['data'] = $this->generateLiqpayData($order_info);
                $data['signature'] = $this->generateSignature($data['data']);

                $data['language'] = $this->config->get('config_language');

                return $this->load->view('extension/liqpay/payment/liqpay', $data);
            }
        }

        return '';
    }

    public function confirm(): void {
        $this->load->language('extension/liqpay/payment/liqpay');

        $json = [];

        if (!isset($this->session->data['order_id'])) {
            $json['error'] = $this->language->get('error_order');
        }

        if (isset($this->session->data['order_id'])) {
            $this->load->model('checkout/order');

            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

            if (!$order_info) {
                $json['redirect'] = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);

                unset($this->session->data['order_id']);
            }
        }

        if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'liqpay.liqpay') {
            $json['error'] = $this->language->get('error_payment_method');
        }

        if (!$json) {
            $json['redirect'] = $this->url->link('extension/liqpay/payment/liqpay.process', 'language=' . $this->config->get('config_language'), true);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function process(): void {
        if (isset($this->session->data['order_id'])) {
            $this->load->model('checkout/order');

            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

            if ($order_info) {
                $data['action'] = 'https://www.liqpay.ua/api/3/checkout';
                $data['data'] = $this->generateLiqpayData($order_info);
                $data['signature'] = $this->generateSignature($data['data']);

                $this->response->setOutput($this->load->view('extension/liqpay/payment/liqpay_process', $data));
                return;
            }
        }

        $this->response->redirect($this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true));
    }

    public function callback(): void {
        if ($this->request->server['REQUEST_METHOD'] == 'POST' && isset($this->request->post['data']) && isset($this->request->post['signature'])) {
            $data = $this->request->post['data'];
            $signature = $this->request->post['signature'];
            
            $private_key = $this->config->get('payment_liqpay_private_key');
            $generated_signature = base64_encode(sha1($private_key . $data . $private_key, 1));

            if ($signature === $generated_signature) {
                $response_data = json_decode(base64_decode($data), true);

                if ($response_data && isset($response_data['order_id'])) {
                    $order_parts = explode('_', $response_data['order_id']);
                    $order_id = (int)$order_parts[0];

                    $this->load->model('checkout/order');
                    $this->load->model('extension/liqpay/payment/liqpay');

                    $order_info = $this->model_checkout_order->getOrder($order_id);

                    if ($order_info) {
                        $transaction_data = [
                            'order_id' => $order_id,
                            'liqpay_order_id' => $response_data['order_id'],
                            'payment_id' => $response_data['payment_id'] ?? '',
                            'amount' => $response_data['amount'],
                            'currency' => $response_data['currency'],
                            'status' => $this->mapLiqpayStatus($response_data['status']),
                            'action_type' => $response_data['action'] ?? 'pay',
                            'liqpay_status' => $response_data['status'],
                            'response_data' => $response_data
                        ];

                        $this->model_extension_liqpay_payment_liqpay->addTransaction($transaction_data);

                        $order_status_id = $this->getOrderStatusId($response_data['status'], $response_data['action'] ?? 'pay');
                        
                        $this->load->language('extension/liqpay/payment/liqpay');
                        $comment = sprintf($this->language->get('text_transaction'), $response_data['payment_id'] ?? '', $response_data['status']);
                        
                        $this->model_checkout_order->addHistory($order_id, $order_status_id, $comment, false);
                    }
                }
            }
        }

        echo 'OK';
        exit;
    }

    public function success(): void {
        if (isset($this->request->post['data']) && isset($this->request->post['signature'])) {
            $data = $this->request->post['data'];
            $signature = $this->request->post['signature'];
            
            $private_key = $this->config->get('payment_liqpay_private_key');
            $generated_signature = base64_encode(sha1($private_key . $data . $private_key, 1));
            
            if ($signature === $generated_signature) {
                $response_data = json_decode(base64_decode($data), true);
                
                if ($response_data) {
                    if (in_array($response_data['status'], [
                        'success', 'sandbox', 'subscribed', 'hold_wait',
                        'processing', 'wait_secure', 'wait_accept', 'wait_compensation', 'wait_lc',
                        'cash_wait', 'invoice_wait', 'prepared', 'wait_card', 'wait_reserve',
                        '3ds_verify', 'captcha_verify', 'cvv_verify', 'ivr_verify', 'otp_verify',
                        'password_verify', 'phone_verify', 'pin_verify', 'receiver_verify',
                        'sender_verify', 'senderapp_verify', 'wait_qr', 'wait_sender'
                    ])) {
                        $this->response->redirect($this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true));
                        return;
                    }
                    
                    // Статуси reversed, unsubscribed можуть йти на success з відповідним повідомленням
                    if (in_array($response_data['status'], ['reversed', 'unsubscribed'])) {
                        $this->response->redirect($this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true));
                        return;
                    }
                }
            }
        }
        
        // При статусах error/failure
        $this->response->redirect($this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true));
    }

    private function generateLiqpayData(array $order_info): string {
        $public_key = $this->config->get('payment_liqpay_public_key');
        $action_type = $this->config->get('payment_liqpay_action_type') ?: 'pay';

        $description = $this->config->get('payment_liqpay_description_' . $this->config->get('config_language_id'));
        if (!$description) {
            $this->load->language('extension/liqpay/payment/liqpay');
            $description = $this->language->get('text_default_description');
        }
        $description = str_replace('{order_id}', $order_info['order_id'], $description);

        $order_id = $order_info['order_id'] . '_' . time();

        $amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);

        $result_url = $this->url->link('extension/liqpay/payment/liqpay.success', 'language=' . $this->config->get('config_language'), true);
        $server_url = $this->url->link('extension/liqpay/payment/liqpay.callback', 'language=' . $this->config->get('config_language'), true);

        $params = [
            'version' => '3',
            'public_key' => $public_key,
            'action' => $action_type,
            'amount' => $amount,
            'currency' => $order_info['currency_code'],
            'description' => $description,
            'order_id' => $order_id,
            'language' => $this->getLanguageCode(),
            'result_url' => $result_url,
            'server_url' => $server_url
        ];

        return base64_encode(json_encode($params));
    }

    private function generateSignature(string $data): string {
        $private_key = $this->config->get('payment_liqpay_private_key');
        return base64_encode(sha1($private_key . $data . $private_key, 1));
    }

    private function getLanguageCode(): string {
        $language_map = [
            'uk-ua' => 'uk',
            'en-gb' => 'en',
            'ru-ru' => 'ru'
        ];

        $language_code = $this->config->get('config_language');
        
        return $language_map[$language_code] ?? 'uk';
    }

    private function mapLiqpayStatus(string $liqpay_status): string {
        $status_map = [
            // Кінцеві статуси платежу
            'success' => 'success',
            'sandbox' => 'success', 
            'subscribed' => 'success',
            'error' => 'failed',
            'failure' => 'failed',
            'reversed' => 'refunded',
            'unsubscribed' => 'cancelled',
            
            // Hold статус
            'hold_wait' => 'hold',
            
            // Статуси що потребують підтвердження платежу
            '3ds_verify' => 'verification',
            'captcha_verify' => 'verification',
            'cvv_verify' => 'verification', 
            'ivr_verify' => 'verification',
            'otp_verify' => 'verification',
            'password_verify' => 'verification',
            'phone_verify' => 'verification',
            'pin_verify' => 'verification',
            'receiver_verify' => 'verification',
            'sender_verify' => 'verification',
            'senderapp_verify' => 'verification',
            'wait_qr' => 'verification',
            'wait_sender' => 'verification',
            
            // Інші статуси платежу
            'cash_wait' => 'pending',
            'invoice_wait' => 'pending',
            'prepared' => 'pending',
            'processing' => 'pending',
            'wait_accept' => 'pending',
            'wait_card' => 'pending', 
            'wait_compensation' => 'pending',
            'wait_lc' => 'pending',
            'wait_reserve' => 'pending',
            'wait_secure' => 'pending'
        ];

        return $status_map[$liqpay_status] ?? 'pending';
    }

    private function getOrderStatusId(string $liqpay_status, string $action_type): int {
        // Успішні платежі
        if (in_array($liqpay_status, ['success', 'sandbox', 'subscribed'])) {
            return (int)$this->config->get('payment_liqpay_order_status_id');
        }
        
        // Hold статус
        if ($liqpay_status === 'hold_wait') {
            return (int)$this->config->get('payment_liqpay_hold_status_id');
        }
        
        // Помилки
        if (in_array($liqpay_status, ['error', 'failure', 'unsubscribed'])) {
            return (int)$this->config->get('payment_liqpay_failed_status_id');
        }

        // Повернення
        if (in_array($liqpay_status, ['reversed'])) {
            return (int)$this->config->get('payment_liqpay_refund_status_id');
        }
        
        // Статуси що потребують підтвердження
        if (in_array($liqpay_status, [
            '3ds_verify', 'captcha_verify', 'cvv_verify', 'ivr_verify', 'otp_verify',
            'password_verify', 'phone_verify', 'pin_verify', 'receiver_verify', 
            'sender_verify', 'senderapp_verify', 'wait_qr', 'wait_sender'
        ])) {
            return (int)$this->config->get('payment_liqpay_pending_status_id');
        }
        
        // Всі інші статуси очікування
        if (in_array($liqpay_status, [
            'cash_wait', 'invoice_wait', 'prepared', 'processing', 'wait_accept',
            'wait_card', 'wait_compensation', 'wait_lc', 'wait_reserve', 'wait_secure'
        ])) {
            return (int)$this->config->get('payment_liqpay_pending_status_id');
        }
        
        return (int)$this->config->get('config_order_status_id');
    }
}