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

namespace Opencart\Admin\Model\Extension\Liqpay\Payment;

class Liqpay extends \Opencart\System\Engine\Model {
    private $api_url = 'https://www.liqpay.ua/api/request';
    
    public function install(): void {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "liqpay_transaction` (
            `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
            `order_id` int(11) NOT NULL,
            `liqpay_order_id` varchar(255) NOT NULL,
            `payment_id` varchar(255) NOT NULL,
            `amount` decimal(15,4) NOT NULL,
            `currency` varchar(3) NOT NULL,
            `status` varchar(20) NOT NULL,
            `action_type` varchar(20) NOT NULL,
            `captured_amount` decimal(15,4) DEFAULT 0.0000,
            `refunded_amount` decimal(15,4) DEFAULT 0.0000,
            `liqpay_status` varchar(50) NOT NULL,
            `response_data` text,
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`transaction_id`),
            INDEX (`order_id`),
            INDEX (`liqpay_order_id`),
            INDEX (`payment_id`),
            INDEX (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function uninstall(): void {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "liqpay_transaction`");
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

    public function updateTransaction(int $transaction_id, array $data): void {
        $sql = "UPDATE `" . DB_PREFIX . "liqpay_transaction` SET ";
        $updates = [];

        if (isset($data['status'])) {
            $updates[] = "`status` = '" . $this->db->escape($data['status']) . "'";
        }

        if (isset($data['liqpay_status'])) {
            $updates[] = "`liqpay_status` = '" . $this->db->escape($data['liqpay_status']) . "'";
        }

        if (isset($data['captured_amount'])) {
            $updates[] = "`captured_amount` = '" . (float)$data['captured_amount'] . "'";
        }

        if (isset($data['refunded_amount'])) {
            $updates[] = "`refunded_amount` = '" . (float)$data['refunded_amount'] . "'";
        }

        if (isset($data['response_data'])) {
            $updates[] = "`response_data` = '" . $this->db->escape(json_encode($data['response_data'])) . "'";
        }

        $updates[] = "`date_modified` = NOW()";

        $sql .= implode(', ', $updates);
        $sql .= " WHERE `transaction_id` = '" . (int)$transaction_id . "'";

        $this->db->query($sql);
    }

    public function getTransaction(int $transaction_id): array {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "liqpay_transaction` WHERE `transaction_id` = '" . (int)$transaction_id . "'");

        if ($query->num_rows) {
            $transaction = $query->row;
            $transaction['response_data'] = json_decode($transaction['response_data'], true);
            return $transaction;
        }

        return [];
    }

    public function getTransactionByOrderId(int $order_id): array {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "liqpay_transaction` WHERE `order_id` = '" . (int)$order_id . "' ORDER BY `date_added` DESC LIMIT 1");

        if ($query->num_rows) {
            $transaction = $query->row;
            $transaction['response_data'] = json_decode($transaction['response_data'], true);
            return $transaction;
        }

        return [];
    }

    public function getTransactionByLiqpayOrderId(string $liqpay_order_id): array {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "liqpay_transaction` WHERE `liqpay_order_id` = '" . $this->db->escape($liqpay_order_id) . "'");

        if ($query->num_rows) {
            $transaction = $query->row;
            $transaction['response_data'] = json_decode($transaction['response_data'], true);
            return $transaction;
        }

        return [];
    }

    public function getTransactions(array $data = []): array {
        $sql = "SELECT * FROM `" . DB_PREFIX . "liqpay_transaction`";

        $sql .= " ORDER BY `date_added` DESC";

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        $transactions = [];
        foreach ($query->rows as $row) {
            $row['response_data'] = json_decode($row['response_data'], true);
            $transactions[] = $row;
        }

        return $transactions;
    }

    public function getTotalTransactions(): int {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "liqpay_transaction`");

        return (int)$query->row['total'];
    }

    public function capturePayment(int $transaction_id): array {
        $transaction = $this->getTransaction($transaction_id);

        if (!$transaction) {
            return ['success' => false, 'message' => 'error_transaction_not_found'];
        }

        if ($transaction['action_type'] !== 'hold') {
            return ['success' => false, 'message' => 'error_not_hold_payment'];
        }

        if ($transaction['status'] !== 'hold') {
            return ['success' => false, 'message' => 'error_cannot_capture'];
        }

        $public_key = $this->config->get('payment_liqpay_public_key');
        $private_key = $this->config->get('payment_liqpay_private_key');

        $params = [
            'version' => '3',
            'public_key' => $public_key,
            'action' => 'hold_completion',
            'order_id' => $transaction['liqpay_order_id'],
            'amount' => $transaction['amount']
        ];

        $data = base64_encode(json_encode($params));
        $signature = base64_encode(sha1($private_key . $data . $private_key, 1));

        $postfields = 'data=' . $data . '&signature=' . $signature;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->$api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postfields,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($http_code === 200 && $response) {
            $result = json_decode($response, true);

            if ($result && isset($result['status'])) {
                $this->updateTransaction($transaction_id, [
                    'status' => 'captured',
                    'liqpay_status' => $result['status'],
                    'captured_amount' => $transaction['amount'],
                    'response_data' => $result
                ]);

                return ['success' => true, 'message' => 'success_captured'];
            }
        }

        return ['success' => false, 'message' => 'error_capture_failed'];
    }

    public function refundPayment(int $transaction_id, float $amount): array {
        $transaction = $this->getTransaction($transaction_id);

        if (!$transaction) {
            return ['success' => false, 'message' => 'error_transaction_not_found'];
        }

        if (!in_array($transaction['status'], ['success', 'captured'])) {
            return ['success' => false, 'message' => 'error_cannot_refund'];
        }

        $available_amount = $transaction['amount'] - $transaction['refunded_amount'];
        if ($amount > $available_amount) {
            return ['success' => false, 'message' => 'error_refund_amount_exceeds'];
        }

        $public_key = $this->config->get('payment_liqpay_public_key');
        $private_key = $this->config->get('payment_liqpay_private_key');

        $params = [
            'version' => '3',
            'public_key' => $public_key,
            'action' => 'refund',
            'order_id' => $transaction['liqpay_order_id'],
            'amount' => $amount
        ];

        $data = base64_encode(json_encode($params));
        $signature = base64_encode(sha1($private_key . $data . $private_key, 1));

        $postfields = 'data=' . $data . '&signature=' . $signature;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->$api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postfields,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($http_code === 200 && $response) {
            $result = json_decode($response, true);

            if ($result && isset($result['status'])) {
                $new_refunded_amount = $transaction['refunded_amount'] + $amount;
                $new_status = $new_refunded_amount >= $transaction['amount'] ? 'refunded' : $transaction['status'];

                $this->updateTransaction($transaction_id, [
                    'status' => $new_status,
                    'liqpay_status' => $result['status'],
                    'refunded_amount' => $new_refunded_amount,
                    'response_data' => $result
                ]);

                return ['success' => true, 'message' => 'success_refunded'];
            }
        }

        return ['success' => false, 'message' => 'error_refund_failed'];
    }
}