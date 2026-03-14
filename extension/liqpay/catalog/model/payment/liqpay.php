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

namespace Opencart\Catalog\Model\Extension\Liqpay\Payment;

class Liqpay extends \Opencart\System\Engine\Model {

    public function getMethods(array $address = []): array {
        $this->load->language('extension/liqpay/payment/liqpay');

        if (!$this->config->get('payment_liqpay_status')) {
            return [];
        }

        if ($this->cart->hasSubscription()) {
            $status = false;
        } else {
            $status = true;
        }

        $method_data = [];

        if ($this->config->get('payment_liqpay_total') > 0 && $this->config->get('payment_liqpay_total') > $this->cart->getTotal()) {
            $status = false;
        }

        if ($this->config->get('payment_liqpay_geo_zone_id')) {
            $this->load->model('localisation/geo_zone');

            $results = $this->model_localisation_geo_zone->getGeoZone(
                (int)$this->config->get('payment_liqpay_geo_zone_id'),
                (int)$address['country_id'],
                (int)$address['zone_id']
            );

            if (!$results) {
                $status = false;
            }
        }

        if ($status) {
            $title_data = $this->config->get('payment_liqpay_title');
            $title = !empty($title_data[$this->config->get('config_language_id')]) ? $title_data[$this->config->get('config_language_id')] : $this->language->get('text_title');

            $option_data['liqpay'] = [
                'code' => 'liqpay.liqpay',
                'name' => $title
            ];

            $method_data = [
                'code'       => 'liqpay',
                'name'       => $title,
                'option'     => $option_data,
                'sort_order' => $this->config->get('payment_liqpay_sort_order')
            ];
        }

        return $method_data;
    }

    public function addTransaction(array $data): int {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "liqpay_transaction` SET 
            `order_id` = '" . (int)$data['order_id'] . "',
            `liqpay_order_id` = '" . $this->db->escape($data['liqpay_order_id']) . "',
            `payment_id` = '" . $this->db->escape($data['payment_id']) . "',
            `amount` = '" . (float)$data['amount'] . "',
            `currency` = '" . $this->db->escape($data['currency']) . "',
            `status` = '" . $this->db->escape($data['status']) . "',
            `action_type` = '" . $this->db->escape($data['action_type']) . "',
            `liqpay_status` = '" . $this->db->escape($data['liqpay_status']) . "',
            `response_data` = '" . $this->db->escape(json_encode($data['response_data'])) . "',
            `date_added` = NOW(),
            `date_modified` = NOW()");

        return $this->db->getLastId();
    }
}