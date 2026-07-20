<?php
namespace Opencart\Admin\Model\Extension\Manline\Feed;

/**
 * Marketplace feed config (Manline) — CRUD over the trimmed product_feed table
 * that drives the Rozetka / Prom.ua feeds.
 */
class ProductFeed extends \Opencart\System\Engine\Model {
	public function getFeeds(): array {
		return $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_feed` ORDER BY `feed_name` ASC")->rows;
	}

	public function getFeed(int $product_feed_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_feed` WHERE `product_feed_id` = '" . (int)$product_feed_id . "'");

		return $query->row ?: [];
	}

	public function editFeed(int $product_feed_id, array $data): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "product_feed` SET
			`feed_name` = '" . $this->db->escape((string)$data['feed_name']) . "',
			`format` = '" . $this->db->escape((string)$data['format']) . "',
			`status` = '" . (int)$data['status'] . "',
			`language_id` = '" . (int)$data['language_id'] . "',
			`currency` = '" . $this->db->escape((string)$data['currency']) . "',
			`categories` = '" . $this->db->escape((string)$data['categories']) . "',
			`manufacturers` = '" . $this->db->escape((string)$data['manufacturers']) . "',
			`sql_code` = '" . $this->db->escape((string)$data['sql_code']) . "',
			`size_option_id` = '" . (int)$data['size_option_id'] . "',
			`image_width` = '" . (int)$data['image_width'] . "',
			`image_height` = '" . (int)$data['image_height'] . "'
			WHERE `product_feed_id` = '" . (int)$product_feed_id . "'");
	}
}
