<?php
namespace Opencart\Admin\Model\Extension\Manline\Module;

class Custommenu extends \Opencart\System\Engine\Model {
	public function getCustomMenuItems(array $data = []): array {
		$language_id = (int)$this->config->get('config_language_id');

		$sql = "SELECT i.menu_id as menu_id, CONCAT_WS(' &gt; ', id3.name, id2.name, id.name) as name, i.link as link, i.parent_id as parent_id, i.parent_parent_id as parent_parent_id, i.image as image, i.`column` as `column`, i.sort_order as sort_order, i.status as status " .
			"FROM `" . DB_PREFIX . "custom_menu` i " .
			"LEFT JOIN `" . DB_PREFIX . "custom_menu_description` id ON (i.menu_id = id.menu_id AND id.language_id = " . $language_id . ") " .
			"LEFT JOIN `" . DB_PREFIX . "custom_menu_description` id2 ON (i.parent_id = id2.menu_id AND id2.language_id = " . $language_id . ") " .
			"LEFT JOIN `" . DB_PREFIX . "custom_menu_description` id3 ON (i.parent_parent_id = id3.menu_id AND id3.language_id = " . $language_id . ") " .
			"WHERE id.language_id = " . $language_id;

		$sort = $data['sort'] ?? 'name';
		$order = $data['order'] ?? 'ASC';

		$sort_data = ['name', 'link', 'sort_order', 'status'];
		if (!in_array($sort, $sort_data, true)) {
			$sort = 'name';
		}

		$sql .= " ORDER BY " . $sort . " " . ($order === 'DESC' ? 'DESC' : 'ASC') . ", sort_order ASC";

		if (isset($data['start']) || isset($data['limit'])) {
			$start = (int)($data['start'] ?? 0);
			$limit = (int)($data['limit'] ?? 20);
			if ($start < 0) $start = 0;
			if ($limit < 1) $limit = 20;
			$sql .= " LIMIT " . $start . "," . $limit;
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalCustomMenuItems(): int {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "custom_menu`");
		return (int)$query->row['total'];
	}

	public function getCustomMenuFlat(): array {
		// Used for parent selector.
		$language_id = (int)$this->config->get('config_language_id');
		$sql = "SELECT i.menu_id, CONCAT_WS(' &gt; ', id3.name, id2.name, id.name) as name, i.parent_id " .
			"FROM `" . DB_PREFIX . "custom_menu` i " .
			"LEFT JOIN `" . DB_PREFIX . "custom_menu_description` id ON (i.menu_id = id.menu_id AND id.language_id = " . $language_id . ") " .
			"LEFT JOIN `" . DB_PREFIX . "custom_menu_description` id2 ON (i.parent_id = id2.menu_id AND id2.language_id = " . $language_id . ") " .
			"LEFT JOIN `" . DB_PREFIX . "custom_menu_description` id3 ON (i.parent_parent_id = id3.menu_id AND id3.language_id = " . $language_id . ") " .
			"WHERE id.language_id = " . $language_id . " ORDER BY name ASC, i.sort_order ASC";
		return $this->db->query($sql)->rows;
	}

	public function getCustomMenuItem(int $menu_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "custom_menu` WHERE menu_id = '" . (int)$menu_id . "'");
		return $query->row;
	}

	public function getCustomMenuDescriptions(int $menu_id): array {
		$custom_menu_description_data = [];
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "custom_menu_description` WHERE menu_id = '" . (int)$menu_id . "'");

		foreach ($query->rows as $result) {
			$custom_menu_description_data[$result['language_id']] = ['name' => $result['name']];
		}

		return $custom_menu_description_data;
	}

	public function addCustomMenuItem(array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "custom_menu` SET link = '" . $this->db->escape($data['link'] ?? '') . "', image = '" . $this->db->escape($data['image'] ?? '') . "', parent_id = '" . (int)($data['parent_id'] ?? 0) . "', parent_parent_id = '" . (int)($data['parent_parent_id'] ?? 0) . "', `column` = '" . (int)($data['column'] ?? 1) . "', status = '" . (int)($data['status'] ?? 0) . "', sort_order = '" . (int)($data['sort_order'] ?? 0) . "'");

		$menu_id = (int)$this->db->getLastId();

		if (!empty($data['menu_description']) && is_array($data['menu_description'])) {
			foreach ($data['menu_description'] as $language_id => $value) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "custom_menu_description` SET menu_id = '" . $menu_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name'] ?? '') . "'");
			}
		}

		return $menu_id;
	}

	public function editCustomMenuItem(int $menu_id, array $data): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "custom_menu` SET link = '" . $this->db->escape($data['link'] ?? '') . "', image = '" . $this->db->escape($data['image'] ?? '') . "', parent_id = '" . (int)($data['parent_id'] ?? 0) . "', parent_parent_id = '" . (int)($data['parent_parent_id'] ?? 0) . "', `column` = '" . (int)($data['column'] ?? 1) . "', status = '" . (int)($data['status'] ?? 0) . "', sort_order = '" . (int)($data['sort_order'] ?? 0) . "' WHERE menu_id = '" . (int)$menu_id . "'");

		$this->db->query("DELETE FROM `" . DB_PREFIX . "custom_menu_description` WHERE menu_id = '" . (int)$menu_id . "'");

		if (!empty($data['menu_description']) && is_array($data['menu_description'])) {
			foreach ($data['menu_description'] as $language_id => $value) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "custom_menu_description` SET menu_id = '" . (int)$menu_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name'] ?? '') . "'");
			}
		}
	}

	public function deleteCustomMenuItem(int $menu_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "custom_menu` WHERE menu_id = '" . (int)$menu_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "custom_menu_description` WHERE menu_id = '" . (int)$menu_id . "'");
	}
}
