<?php
namespace Opencart\Catalog\Model\Extension\Manline\Module;

/**
 * CustomMenu (Manline) — OC4 port
 *
 * Data source: legacy tables imported from old store:
 * - {DB_PREFIX}custom_menu
 * - {DB_PREFIX}custom_menu_description
 *
 * Structure:
 * - parent_id = 0 → top level
 * - parent_id = <menu_id> → second level
 * - parent_id = <menu_id> (of second level) → third level
 */
class Custommenu extends \Opencart\System\Engine\Model {
	public function getCustomMenu(): array {
		$this->load->model('tool/image');

		$data = [];

		$language_id = (int)$this->config->get('config_language_id');

		$level1 = $this->db->query(
			"SELECT * FROM " . DB_PREFIX . "custom_menu h " .
			"LEFT JOIN " . DB_PREFIX . "custom_menu_description hd ON (h.menu_id = hd.menu_id) " .
			"WHERE hd.language_id = '" . $language_id . "' AND h.parent_id = 0 AND h.status = 1 " .
			"ORDER BY h.sort_order"
		);

		foreach ($level1->rows as $row) {
			$level2 = $this->db->query(
				"SELECT * FROM " . DB_PREFIX . "custom_menu h " .
				"LEFT JOIN " . DB_PREFIX . "custom_menu_description hd ON (h.menu_id = hd.menu_id) " .
				"WHERE hd.language_id = '" . $language_id . "' AND h.parent_id = '" . (int)$row['menu_id'] . "' AND h.status = 1 " .
				"ORDER BY h.sort_order"
			);

			$subtitle = [];

			foreach ($level2->rows as $row1) {
				$subtitlenew = [];

				$level3 = $this->db->query(
					"SELECT * FROM " . DB_PREFIX . "custom_menu h " .
					"LEFT JOIN " . DB_PREFIX . "custom_menu_description hd ON (h.menu_id = hd.menu_id) " .
					"WHERE hd.language_id = '" . $language_id . "' AND h.status = 1 AND h.parent_id = '" . (int)$row1['menu_id'] . "' " .
					"ORDER BY h.sort_order"
				);

				foreach ($level3->rows as $row2) {
					$subtitlenew[] = [
						'name' => $row2['name'],
						'id' => (int)$row2['menu_id'],
						'column' => (int)$row2['column'],
						'link' => $row2['link'],
						'sub_menu' => []
					];
				}

				$subtitle[] = [
					'name' => $row1['name'],
					'id' => (int)$row1['menu_id'],
					'image' => $row1['image'] ? $this->model_tool_image->resize($row1['image'], 20, 20) : '',
					'link' => $row1['link'],
					'column' => (int)$row1['column'],
					'sub_menu' => $subtitlenew
				];
			}

			$data[] = [
				'id' => (int)$row['menu_id'],
				'name' => $row['name'],
				'image' => $row['image'] ? $this->model_tool_image->resize($row['image'], 20, 20) : '',
				'link' => $row['link'],
				'column' => (int)$row['column'],
				'sub_menu' => $subtitle
			];
		}

		return $data;
	}
}
