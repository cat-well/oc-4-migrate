<?php
namespace Opencart\Catalog\Controller\Extension\Manline\Module;

/**
 * Minimal FilterPro-like sidebar (legacy-compatible URLs)
 *
 * Generates /f/ URLs understood by startup/seo_url.php
 *
 * Base version:
 * - Manufacturers
 * - Price (min/max)
 * - Color (option_id=13 / slug tsvet-o)
 * - Style (attribute_id=23 / slug stil)
 */
class FilterproLike extends \Opencart\System\Engine\Controller {
	/**
	 * @param array<string,mixed> $setting
	 */
	public function index(array $setting = []): string {
		if (isset($setting['status']) && !$setting['status']) {
			return '';
		}

		$data = [];

		// Determine category_id from path + keep full path string for SEO URLs
		$category_id = 0;
		$path_str = '';
		if (isset($this->request->get['path'])) {
			$path_str = (string)$this->request->get['path'];
			$parts = explode('_', $path_str);
			$category_id = (int)array_pop($parts);
		}
		$data['category_id'] = $category_id;
		$data['path'] = $path_str;

		if (!$category_id) {
			return '';
		}

		$language_id = (int)$this->config->get('config_language_id');
		$lang_code = (string)($this->config->get('config_language') ?? '');
		$data['is_ua'] = in_array($lang_code, ['uk-ua', 'ua'], true);

		// Selected filters (from request, already parsed for /f/ by startup/seo_url)
		$selected = [
			'manufacturer'   => [],
			'option_value'   => [],
			'attribute_value'=> [],
			'min_price'      => isset($this->request->get['min_price']) ? (float)$this->request->get['min_price'] : null,
			'max_price'      => isset($this->request->get['max_price']) ? (float)$this->request->get['max_price'] : null,
		];

		if (isset($this->request->get['manufacturer'])) {
			$m = $this->request->get['manufacturer'];
			if (is_array($m)) {
				foreach ($m as $id) $selected['manufacturer'][] = (int)$id;
			} else {
				$selected['manufacturer'][] = (int)$m;
			}
		}
		$selected['manufacturer'] = array_values(array_unique(array_filter($selected['manufacturer'])));

		if (isset($this->request->get['option_value']) && is_array($this->request->get['option_value'])) {
			foreach ($this->request->get['option_value'] as $opt_id => $vals) {
				$opt_id = (int)$opt_id;
				if ($opt_id <= 0) continue;
				if (!is_array($vals)) $vals = [$vals];
				foreach ($vals as $v) {
					$v = (int)$v;
					if ($v > 0) $selected['option_value'][$opt_id][] = $v;
				}
				if (isset($selected['option_value'][$opt_id])) {
					$selected['option_value'][$opt_id] = array_values(array_unique($selected['option_value'][$opt_id]));
				}
			}
		}

		if (isset($this->request->get['attribute_value']) && is_array($this->request->get['attribute_value'])) {
			foreach ($this->request->get['attribute_value'] as $attr_id => $vals) {
				$attr_id = (int)$attr_id;
				if ($attr_id <= 0) continue;
				if (!is_array($vals)) $vals = [$vals];
				foreach ($vals as $v) {
					$v = trim((string)$v);
					if ($v !== '') $selected['attribute_value'][$attr_id][] = $v;
				}
				if (isset($selected['attribute_value'][$attr_id])) {
					$selected['attribute_value'][$attr_id] = array_values(array_unique($selected['attribute_value'][$attr_id]));
				}
			}
		}

		$data['selected'] = $selected;

		// Helper: base category URL (seo rewritten). Use full path chain.
		$base = $this->url->link('product/category', 'language=' . $lang_code . '&path=' . $path_str);
		$data['base_url'] = $base;

		// Build current selection as slug-map for URL generation
		$sel_slug = [];

		// Manufacturer (numeric)
		if ($selected['manufacturer']) {
			$sel_slug['manufacturer'] = array_map('strval', $selected['manufacturer']);
		}

		// Option selection slugs
		if ($selected['option_value']) {
			foreach ($selected['option_value'] as $opt_id => $ids) {
				$opt_slug_q = $this->db->query("SELECT slug FROM `" . DB_PREFIX . "option_description` WHERE language_id='" . (int)$language_id . "' AND option_id='" . (int)$opt_id . "' LIMIT 1");
				if (!$opt_slug_q->num_rows) continue;
				$opt_slug = (string)$opt_slug_q->row['slug'];
				if ($opt_slug === '') continue;

				$vals = [];
				foreach ($ids as $ov_id) {
					$ov_q = $this->db->query("SELECT slug FROM `" . DB_PREFIX . "option_value_description` WHERE language_id='" . (int)$language_id . "' AND option_id='" . (int)$opt_id . "' AND option_value_id='" . (int)$ov_id . "' LIMIT 1");
					if ($ov_q->num_rows && $ov_q->row['slug'] !== '') {
						$vals[] = (string)$ov_q->row['slug'];
					}
				}
				$vals = array_values(array_unique(array_filter($vals)));
				if ($vals) {
					$sel_slug[$opt_slug] = $vals;
				}
			}
		}

		// Attribute selection slugs (map text -> slug via product_attribute)
		if ($selected['attribute_value']) {
			foreach ($selected['attribute_value'] as $attr_id => $texts) {
				$attr_slug_q = $this->db->query("SELECT slug FROM `" . DB_PREFIX . "attribute_description` WHERE language_id='" . (int)$language_id . "' AND attribute_id='" . (int)$attr_id . "' LIMIT 1");
				if (!$attr_slug_q->num_rows) continue;
				$attr_slug = (string)$attr_slug_q->row['slug'];
				if ($attr_slug === '') continue;

				$vals = [];
				foreach ($texts as $t) {
					$t = trim((string)$t);
					if ($t === '') continue;
					$pa_q = $this->db->query("SELECT slug FROM `" . DB_PREFIX . "product_attribute` WHERE language_id='" . (int)$language_id . "' AND attribute_id='" . (int)$attr_id . "' AND text='" . $this->db->escape($t) . "' AND slug != '' LIMIT 1");
					if ($pa_q->num_rows) {
						$vals[] = (string)$pa_q->row['slug'];
					}
				}
				$vals = array_values(array_unique(array_filter($vals)));
				if ($vals) {
					$sel_slug[$attr_slug] = $vals;
				}
			}
		}

		$data['selected_slug'] = $sel_slug;

		// Helper: build /f/ URL from selection slug-map
		$data['build_url'] = function (array $sel) use ($base) {
			ksort($sel);
			$parts = [];
			foreach ($sel as $k => $vals) {
				$vals = array_values(array_unique(array_filter(array_map('strval', (array)$vals), static fn($v) => $v !== '')));
				if (!$vals) {
					continue;
				}
				sort($vals);
				$parts[] = $k . '_' . implode(',', $vals);
			}
			if ($parts) {
				return rtrim($base, '/') . '/f/' . implode('/', $parts);
			}
			return $base;
		};

		// Manufacturers available in category
		$data['manufacturers'] = [];
		$m_q = $this->db->query(
			"SELECT p.manufacturer_id, m.name, COUNT(DISTINCT p.product_id) total " .
			"FROM `" . DB_PREFIX . "product_to_category` p2c " .
			"JOIN `" . DB_PREFIX . "product` p ON p.product_id=p2c.product_id AND p.status='1' AND p.date_available<=NOW() " .
			"JOIN `" . DB_PREFIX . "manufacturer` m ON m.manufacturer_id=p.manufacturer_id " .
			"WHERE p2c.category_id='" . (int)$category_id . "' AND p.manufacturer_id > 0 " .
			"GROUP BY p.manufacturer_id ORDER BY m.name"
		);
		foreach ($m_q->rows as $row) {
			$data['manufacturers'][] = [
				'id' => (int)$row['manufacturer_id'],
				'name' => (string)$row['name'],
				'total' => (int)$row['total'],
			];
		}

		// Price bounds for category (base product price)
		$price_q = $this->db->query(
			"SELECT MIN(p.price) min_price, MAX(p.price) max_price " .
			"FROM `" . DB_PREFIX . "product_to_category` p2c " .
			"JOIN `" . DB_PREFIX . "product` p ON p.product_id=p2c.product_id AND p.status='1' AND p.date_available<=NOW() " .
			"WHERE p2c.category_id='" . (int)$category_id . "'"
		);
		$data['price_bounds'] = [
			'min' => isset($price_q->row['min_price']) ? (float)$price_q->row['min_price'] : 0,
			'max' => isset($price_q->row['max_price']) ? (float)$price_q->row['max_price'] : 0,
		];

		// Color option (option_id=13)
		$color_option_id = 13;
		$color_option_slug = 'tsvet-o';
		$data['colors'] = [];
		$c_q = $this->db->query(
			"SELECT pov.option_value_id, ov.image, ovd.name, ovd.slug, COUNT(DISTINCT pov.product_id) total " .
			"FROM `" . DB_PREFIX . "product_to_category` p2c " .
			"JOIN `" . DB_PREFIX . "product` p ON p.product_id=p2c.product_id AND p.status='1' AND p.date_available<=NOW() " .
			"JOIN `" . DB_PREFIX . "product_option_value` pov ON pov.product_id=p.product_id AND pov.option_id='" . (int)$color_option_id . "' " .
			"JOIN `" . DB_PREFIX . "option_value` ov ON ov.option_value_id=pov.option_value_id " .
			"JOIN `" . DB_PREFIX . "option_value_description` ovd ON ovd.option_value_id=pov.option_value_id AND ovd.option_id='" . (int)$color_option_id . "' AND ovd.language_id='" . (int)$language_id . "' " .
			"WHERE p2c.category_id='" . (int)$category_id . "' " .
			"GROUP BY pov.option_value_id ORDER BY ovd.name"
		);
		foreach ($c_q->rows as $row) {
			$data['colors'][] = [
				'option_id' => $color_option_id,
				'option_slug' => $color_option_slug,
				'id' => (int)$row['option_value_id'],
				'slug' => (string)$row['slug'],
				'name' => (string)$row['name'],
				'image' => (string)$row['image'],
				'total' => (int)$row['total'],
			];
		}

		// Style attribute (attribute_id=23)
		$style_attr_id = 23;
		$style_attr_slug = 'stil';
		$data['styles'] = [];
		$s_q = $this->db->query(
			"SELECT pa.slug, pa.text, COUNT(DISTINCT pa.product_id) total " .
			"FROM `" . DB_PREFIX . "product_to_category` p2c " .
			"JOIN `" . DB_PREFIX . "product` p ON p.product_id=p2c.product_id AND p.status='1' AND p.date_available<=NOW() " .
			"JOIN `" . DB_PREFIX . "product_attribute` pa ON pa.product_id=p.product_id AND pa.attribute_id='" . (int)$style_attr_id . "' AND pa.language_id='" . (int)$language_id . "' " .
			"WHERE p2c.category_id='" . (int)$category_id . "' AND pa.slug != '' AND pa.text != '' " .
			"GROUP BY pa.slug, pa.text ORDER BY pa.text"
		);
		foreach ($s_q->rows as $row) {
			$data['styles'][] = [
				'attribute_id' => $style_attr_id,
				'attribute_slug' => $style_attr_slug,
				'slug' => (string)$row['slug'],
				'text' => (string)$row['text'],
				'total' => (int)$row['total'],
			];
		}

		// Render using active theme templates (our theme does not auto-register extension template namespaces)
		return $this->load->view('common/filterpro_like', $data);
	}
}
