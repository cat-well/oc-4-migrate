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

		// When category page is fetched via AJAX partial endpoint, we re-render only the filter HTML
		// (no inline <script>/<style>) and let the already-loaded JS handle interactions.
		$data['fp_partial'] = !empty($this->request->get['fp_partial']);

		// Current language code (e.g. ru-ru / uk-ua)
		$lang_code = (string)($this->config->get('config_language') ?? '');
		$data['is_ua'] = in_array($lang_code, ['uk-ua', 'ua'], true);

		// Blocks config from admin module (FilterPro settings scaffold)
		$blocks_list = [];
		$blocks_map = [];
		if (isset($setting['blocks']) && is_array($setting['blocks'])) {
			$raw = $setting['blocks'];

			// If stored as associative array (old format), convert to list
			$first_key = array_key_first($raw);
			if ($first_key !== null && !is_int($first_key)) {
				$sort = 10;
				foreach ($raw as $k => $b) {
					if (!is_array($b)) continue;
					$blocks_list[] = [
						'key' => (string)$k,
						'label' => (string)($b['label'] ?? (string)$k),
						'display' => (string)($b['display'] ?? 'checkbox'),
						'expanded' => !empty($b['expanded']) ? 1 : 0,
						'sort_order' => (int)($b['sort_order'] ?? $sort),
						'tooltip' => (is_array($b['tooltip'] ?? null) ? $b['tooltip'] : [])
					];
					$sort += 10;
				}
			} else {
				// New format: list of rows
				foreach ($raw as $row) {
					if (!is_array($row)) continue;
					$k = (string)($row['key'] ?? '');
					$k = trim($k);
					if ($k === '') continue;
					$blocks_list[] = $row;
				}
			}
		}

		// Normalize to map for quick lookups
		foreach ($blocks_list as $row) {
			if (!is_array($row)) continue;
			$k = (string)($row['key'] ?? '');
			$k = trim($k);
			if ($k === '') continue;
			$blocks_map[$k] = $row;
		}

		usort($blocks_list, static function ($a, $b) {
			return (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0);
		});

		// Manline UX rules (customer feedback): ensure the order around key facets
		// Manufacturer → Style → Size (even if admin config is incomplete).
		// We reorder only these two blocks relative to manufacturer; everything else keeps admin order.
		$style_row = null;
		$size_row = null;
		foreach ($blocks_list as $row) {
			$k = is_array($row) ? (string)($row['key'] ?? '') : '';
			if ($k === 'style') $style_row = $row;
			if ($k === 'size') $size_row = $row;
		}

		if ($style_row || $size_row) {
			$rebuilt = [];
			$inserted = false;
			foreach ($blocks_list as $row) {
				$k = is_array($row) ? (string)($row['key'] ?? '') : '';

				// Skip original style/size positions; we'll insert them after manufacturer.
				if ($k === 'style' || $k === 'size') {
					continue;
				}

				$rebuilt[] = $row;

				if ($k === 'manufacturer') {
					if ($style_row) $rebuilt[] = $style_row;
					if ($size_row) $rebuilt[] = $size_row;
					$inserted = true;
				}
			}

			// If manufacturer isn't present, append style/size at the end.
			if (!$inserted) {
				if ($style_row) $rebuilt[] = $style_row;
				if ($size_row) $rebuilt[] = $size_row;
			}

			$blocks_list = $rebuilt;
		}

		$block_on = function (string $key, bool $default = true) use ($blocks_map): bool {
			if (!isset($blocks_map[$key]) || !is_array($blocks_map[$key])) {
				return $default;
			}
			$display = (string)($blocks_map[$key]['display'] ?? '');
			if ($display === 'hide') {
				return false;
			}
			return true;
		};

		// Helper: get per-language tooltip
		$get_tip = function (string $key) use ($blocks_map, $lang_code): string {
			if (!isset($blocks_map[$key]) || !is_array($blocks_map[$key])) {
				return '';
			}
			$tip = $blocks_map[$key]['tooltip'] ?? '';
			if (is_array($tip)) {
				if (isset($tip[$lang_code])) return (string)$tip[$lang_code];
				if (isset($tip['ru-ru'])) return (string)$tip['ru-ru'];
				if (isset($tip['uk-ua'])) return (string)$tip['uk-ua'];
				return '';
			}
			return (string)$tip;
		};

		$get_expanded = function (string $key, int $default = 1) use ($blocks_map): int {
			// Manline UX rules (customer feedback):
			// - "Стиль" and "Размер" are key filters and must always stay expanded.
			// Other filters may be collapsed by default via admin settings.
			if (in_array($key, ['style', 'size'], true)) {
				return 1;
			}

			if (!isset($blocks_map[$key]) || !is_array($blocks_map[$key])) {
				return $default;
			}
			return !empty($blocks_map[$key]['expanded']) ? 1 : 0;
		};

		// Block meta derived from admin config (used later to build $data['blocks'])
		$block_meta = function (string $key, string $default_label) use ($blocks_map, $get_tip, $get_expanded, $lang_code): array {
			$cfg = (isset($blocks_map[$key]) && is_array($blocks_map[$key])) ? $blocks_map[$key] : [];

			$label = $cfg['label'] ?? $default_label;
			if (is_array($label)) {
				if (isset($label[$lang_code]) && (string)$label[$lang_code] !== '') {
					$label = (string)$label[$lang_code];
				} elseif (isset($label['ru-ru']) && (string)$label['ru-ru'] !== '') {
					$label = (string)$label['ru-ru'];
				} elseif (isset($label['uk-ua']) && (string)$label['uk-ua'] !== '') {
					$label = (string)$label['uk-ua'];
				} else {
					$label = (string)($default_label ?: $key);
				}
			}

			return [
				'key' => $key,
				'label' => (string)$label,
				'display' => (string)($cfg['display'] ?? ''),
				'expanded' => $get_expanded($key, 1) ? true : false,
				'tooltip' => $get_tip($key),
			];
		};

		$data['show_price'] = $block_on('price', true);
		$data['show_manufacturer'] = $block_on('manufacturer', true);
		$data['show_color'] = $block_on('color', true);
		$data['show_style'] = $block_on('style', true);
		$data['show_size'] = $block_on('size', false);

		$get_display = function (string $key, string $default = 'checkbox') use ($blocks_map): string {
			if (!isset($blocks_map[$key]) || !is_array($blocks_map[$key])) {
				return $default;
			}
			$display = (string)($blocks_map[$key]['display'] ?? '');
			return $display !== '' ? $display : $default;
		};

		$data['disp_price'] = $get_display('price', 'slider');
		$data['disp_manufacturer'] = $get_display('manufacturer', 'checkbox');
		$data['disp_color'] = $get_display('color', 'image');
		$data['disp_style'] = $get_display('style', 'checkbox');
		$data['disp_size'] = $get_display('size', 'checkbox');

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

		// We'll need catalog/product model for totals (standard toggles etc.)
		$this->load->model('catalog/product');

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

		// Build normalized filter_data for model_catalog_product (used for totals)
		$filter_data_base = [
			'filter_category_id' => $category_id,
			'filter_sub_category' => true,
			'filter_stock' => !empty($this->request->get['stock']) ? 1 : 0,
			'filter_special' => !empty($this->request->get['special']) ? 1 : 0,
			'filter_new' => !empty($this->request->get['new']) ? 1 : 0,
			'filter_manufacturer_ids' => $selected['manufacturer'] ?: [],
			'filter_option_value' => $selected['option_value'] ?: [],
			'filter_attribute_slug' => $selected['attribute_value'] ?: [],
			'filter_min_price' => $selected['min_price'],
			'filter_max_price' => $selected['max_price'],
		];

		// Helper: base category URL (seo rewritten). Use full path chain.
		$base = $this->url->link('product/category', 'language=' . $lang_code . '&path=' . $path_str);
		$data['base_url'] = $base;

		// When language is passed as a query param, we must insert /f/... BEFORE the "?",
		// otherwise it becomes language=uk-ua/f/... which breaks the language controller.
		$base_path = $base;
		$base_query = '';
		$qpos = strpos($base, '?');
		if ($qpos !== false) {
			$base_path = substr($base, 0, $qpos);
			$base_query = substr($base, $qpos + 1);
		}

		// Expose to JS for multi-select apply button
		$data['fp_base_path'] = $base_path;
		$data['fp_base_query'] = $base_query;

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

		// Helper: build /f/ URL from selection slug-map, for arbitrary base URL
		$build_f_url = function (string $base_url, array $sel) use ($base): string {
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

			if (!$parts) {
				return $base_url ?: $base;
			}

			$base_path = $base_url;
			$base_query = '';
			$qpos = strpos($base_url, '?');
			if ($qpos !== false) {
				$base_path = substr($base_url, 0, $qpos);
				$base_query = substr($base_url, $qpos + 1);
			}

			$url = rtrim($base_path, '/') . '/f/' . implode('/', $parts);
			if ($base_query !== '') {
				$url .= '?' . $base_query;
			}
			return $url;
		};

		$build_url = function (array $sel) use ($build_f_url, $base): string {
			return $build_f_url($base, $sel);
		};

		$data['build_url'] = $build_url;
		$data['build_f_url'] = $build_f_url;

		// Helper: apply current selection as SQL conditions (for facet counts)
		// IMPORTANT (Manline requirement): facet totals must be computed only for IN-STOCK products,
		// while the product list still shows out-of-stock items (sorted after in-stock).
		$facet_where = function (string $exclude) use ($selected, $category_id): string {
			$w = [];
			$w[] = "p2c.category_id='" . (int)$category_id . "'";
			$w[] = "p.status='1'";
			$w[] = "p.date_available<=NOW()";
			$w[] = "p.quantity > 0";

			// Standard toggles
			if (!empty($this->request->get['stock']) && $exclude !== 'stock') {
				$w[] = "p.quantity > 0";
			}
			if (!empty($this->request->get['new']) && $exclude !== 'new') {
				$w[] = "p.date_added >= DATE_SUB(NOW(), INTERVAL 60 DAY)";
			}
			// Special toggle (same semantics as model_catalog_product->getTotalProducts)
			if (!empty($this->request->get['special']) && $exclude !== 'special') {
				$w[] = "EXISTS (SELECT 1 FROM `" . DB_PREFIX . "product_discount` ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ps.quantity = '1' AND ps.special = '1' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())))";
			}

			// Manufacturer
			if ($exclude !== 'manufacturer' && !empty($selected['manufacturer'])) {
				$ids = array_map('intval', $selected['manufacturer']);
				$ids = array_values(array_unique(array_filter($ids)));
				if ($ids) $w[] = "p.manufacturer_id IN (" . implode(',', $ids) . ")";
			}

			// Option filters
			if (!empty($selected['option_value']) && is_array($selected['option_value'])) {
				foreach ($selected['option_value'] as $opt_id => $vals) {
					$opt_id = (int)$opt_id;
					if ($opt_id <= 0) continue;
					if ($exclude === 'option:' . $opt_id) continue;
					$ids = array_map('intval', (array)$vals);
					$ids = array_values(array_unique(array_filter($ids)));
					if (!$ids) continue;
					$w[] = "EXISTS (SELECT 1 FROM `" . DB_PREFIX . "product_option_value` povf WHERE povf.product_id=p.product_id AND povf.option_id='" . $opt_id . "' AND povf.option_value_id IN (" . implode(',', $ids) . "))";
				}
			}

			// Attribute filters by slug
			if (!empty($selected['attribute_value']) && is_array($selected['attribute_value'])) {
				foreach ($selected['attribute_value'] as $attr_id => $vals) {
					$attr_id = (int)$attr_id;
					if ($attr_id <= 0) continue;
					if ($exclude === 'attribute:' . $attr_id) continue;
					$in = [];
					foreach ((array)$vals as $v) {
						$v = trim((string)$v);
						if ($v === '') continue;
						$in[] = "'" . $this->db->escape($v) . "'";
					}
					$in = array_values(array_unique($in));
					if (!$in) continue;
					$w[] = "EXISTS (SELECT 1 FROM `" . DB_PREFIX . "product_attribute` paf WHERE paf.product_id=p.product_id AND paf.attribute_id='" . $attr_id . "' AND paf.slug IN (" . implode(',', $in) . "))";
				}
			}

			// Price range (base p.price, consistent with getTotalProducts)
			if ($exclude !== 'price') {
				if ($selected['min_price'] !== null && (float)$selected['min_price'] > 0) {
					$w[] = "p.price >= " . (float)$selected['min_price'];
				}
				if ($selected['max_price'] !== null && (float)$selected['max_price'] > 0) {
					$w[] = "p.price <= " . (float)$selected['max_price'];
				}
			}

			return $w ? (' WHERE ' . implode(' AND ', $w)) : '';
		};

		// Micro-cache for facet queries (session-based, 5s TTL)
		$fp_cache_ttl = 5;
		if (!isset($this->session->data['fp_cache']) || !is_array($this->session->data['fp_cache'])) {
			$this->session->data['fp_cache'] = [];
		}
		$cacheRows = function (string $sql) use ($fp_cache_ttl) {
			$key = 'fpq.' . md5($sql);
			$now = time();
			$store = &$this->session->data['fp_cache'];

			if (isset($store[$key]) && is_array($store[$key]) && isset($store[$key]['t']) && ($now - (int)$store[$key]['t']) <= $fp_cache_ttl) {
				return $store[$key]['rows'] ?? [];
			}

			$q = $this->db->query($sql);
			$rows = $q->rows ?? [];
			$store[$key] = ['t' => $now, 'rows' => $rows];

			// keep cache bounded
			if (is_array($store) && count($store) > 200) {
				// drop oldest ~50
				$keys = array_keys($store);
				usort($keys, function ($a, $b) use ($store) {
					$ta = (int)($store[$a]['t'] ?? 0);
					$tb = (int)($store[$b]['t'] ?? 0);
					return $ta <=> $tb;
				});
				for ($i = 0; $i < 50 && $i < count($keys); $i++) {
					unset($store[$keys[$i]]);
				}
			}

			return $rows;
		};

		// Child categories (Category block)
		$data['child_category_items'] = [];
		try {
			$this->load->model('catalog/category');
			$children = $this->model_catalog_category->getCategories($category_id);
			foreach ($children as $child) {
				$child_id = (int)$child['category_id'];
				$child_path = $path_str ? ($path_str . '_' . $child_id) : (string)$child_id;
				$child_base = $this->url->link('product/category', 'language=' . $lang_code . '&path=' . $child_path);

				// Preserve current /f/ selection when navigating to subcategory
				$child_url = $build_f_url($child_base, $sel_slug);

				// Total products (without applying current filters, for now)
				$filter_data = [
					'filter_category_id' => $child_id,
					'filter_sub_category' => true
				];
				$total = (int)$this->model_catalog_product->getTotalProducts($filter_data);

				$data['child_category_items'][] = [
					'category_id' => $child_id,
					'name' => (string)$child['name'],
					'total' => $total,
					'url' => $child_url,
				];
			}
		} catch (\Throwable $e) {
			// ignore
		}

		// Manufacturers available in category (counts reflect current selection except manufacturer facet)
		$data['manufacturers'] = [];
		$data['manufacturer_items'] = [];
		$m_rows = $cacheRows(
			"SELECT p.manufacturer_id, m.name, COUNT(DISTINCT p.product_id) total " .
			"FROM `" . DB_PREFIX . "product_to_category` p2c " .
			"JOIN `" . DB_PREFIX . "product` p ON p.product_id=p2c.product_id " .
			"JOIN `" . DB_PREFIX . "manufacturer` m ON m.manufacturer_id=p.manufacturer_id " .
			$facet_where('manufacturer') .
			" AND p.manufacturer_id > 0 " .
			" GROUP BY p.manufacturer_id ORDER BY m.name"
		);
		foreach ($m_rows as $row) {
			$id = (int)$row['manufacturer_id'];
			$name = (string)$row['name'];
			$total = (int)$row['total'];

			$cur = $sel_slug;
			$cur_ids = $cur['manufacturer'] ?? [];
			$cur_ids = array_values(array_unique(array_filter(array_map('strval', (array)$cur_ids))));
			$sel = in_array((string)$id, $cur_ids, true);

			// Hide values that have no in-stock products (but keep selected values visible so user can unselect)
			if ($total <= 0 && !$sel) {
				continue;
			}

			$data['manufacturers'][] = ['id' => $id, 'name' => $name, 'total' => $total];

			if ($sel) {
				$cur_ids = array_values(array_filter($cur_ids, static fn($v) => $v !== (string)$id));
			} else {
				$cur_ids[] = (string)$id;
				$cur_ids = array_values(array_unique($cur_ids));
			}

			if ($cur_ids) {
				$cur['manufacturer'] = $cur_ids;
			} else {
				unset($cur['manufacturer']);
			}

			$data['manufacturer_items'][] = [
				'id' => $id,
				'name' => $name,
				'total' => $total,
				'selected' => $sel,
				'url' => $build_url($cur),
			];
		}

		// UX requirement: keep Manufacturer facet visible even when current filter combination yields no products.
		// Fallback: if facet query returned nothing, show manufacturers available in the CATEGORY (in-stock only),
		// ignoring other selected facets. Selected manufacturers remain selectable/unselectable.
		if (empty($data['manufacturer_items'])) {
			$all_rows = $cacheRows(
				"SELECT p.manufacturer_id, m.name, COUNT(DISTINCT p.product_id) total " .
				"FROM `" . DB_PREFIX . "product_to_category` p2c " .
				"JOIN `" . DB_PREFIX . "product` p ON p.product_id=p2c.product_id AND p.status='1' AND p.date_available<=NOW() AND p.quantity > 0 " .
				"JOIN `" . DB_PREFIX . "manufacturer` m ON m.manufacturer_id=p.manufacturer_id " .
				"WHERE p2c.category_id='" . (int)$category_id . "' AND p.manufacturer_id > 0 " .
				"GROUP BY p.manufacturer_id ORDER BY m.name"
			);

			foreach ($all_rows as $row) {
				$id = (int)$row['manufacturer_id'];
				$name = (string)$row['name'];
				$total = (int)$row['total'];

				$cur = $sel_slug;
				$cur_ids = $cur['manufacturer'] ?? [];
				$cur_ids = array_values(array_unique(array_filter(array_map('strval', (array)$cur_ids))));
				$sel = in_array((string)$id, $cur_ids, true);

				if ($total <= 0 && !$sel) {
					continue;
				}

				if ($sel) {
					$cur_ids = array_values(array_filter($cur_ids, static fn($v) => $v !== (string)$id));
				} else {
					$cur_ids[] = (string)$id;
					$cur_ids = array_values(array_unique($cur_ids));
				}

				if ($cur_ids) {
					$cur['manufacturer'] = $cur_ids;
				} else {
					unset($cur['manufacturer']);
				}

				$data['manufacturer_items'][] = [
					'id' => $id,
					'name' => $name,
					'total' => $total,
					'selected' => $sel,
					'url' => $build_url($cur),
				];
			}
		}

		// Price bounds for category (base product price)
		$price_q = $this->db->query(
			"SELECT MIN(p.price) min_price, MAX(p.price) max_price " .
			"FROM `" . DB_PREFIX . "product_to_category` p2c " .
			"JOIN `" . DB_PREFIX . "product` p ON p.product_id=p2c.product_id AND p.status='1' AND p.date_available<=NOW() " .
			"WHERE p2c.category_id='" . (int)$category_id . "'"
		);
		$min_price = isset($price_q->row['min_price']) ? (float)$price_q->row['min_price'] : 0.0;
		$max_price = isset($price_q->row['max_price']) ? (float)$price_q->row['max_price'] : 0.0;

		$data['price_bounds'] = [
			'min' => $min_price,
			'max' => $max_price,
		];

		// Precomputed values to avoid Twig filters incompatibilities
		$data['price_min_floor'] = (int)floor($min_price);
		$data['price_max_ceil'] = (int)ceil($max_price);
		// Default selected values to bounds if not specified (to match OC2 FilterPro UX)
		$data['selected_min_price'] = ($selected['min_price'] !== null) ? (string)$selected['min_price'] : (string)$data['price_min_floor'];
		$data['selected_max_price'] = ($selected['max_price'] !== null) ? (string)$selected['max_price'] : (string)$data['price_max_ceil'];
		$data['price_form_action'] = $build_url($sel_slug);

		// Only show price block when there is a meaningful range (reduce noise)
		$data['has_price_range'] = ($data['price_max_ceil'] > 0 && $data['price_max_ceil'] > $data['price_min_floor']);

		// Color option (option_id=13)
		$color_option_id = 13;
		$color_option_slug = 'tsvet-o';
		$data['colors'] = [];
		$data['color_items'] = [];
		$c_rows = $cacheRows(
			"SELECT pov.option_value_id, ov.image, ovd.name, ovd.slug, COUNT(DISTINCT p.product_id) total " .
			"FROM `" . DB_PREFIX . "product_to_category` p2c " .
			"JOIN `" . DB_PREFIX . "product` p ON p.product_id=p2c.product_id " .
			"JOIN `" . DB_PREFIX . "product_option_value` pov ON pov.product_id=p.product_id AND pov.option_id='" . (int)$color_option_id . "' " .
			"JOIN `" . DB_PREFIX . "option_value` ov ON ov.option_value_id=pov.option_value_id " .
			"JOIN `" . DB_PREFIX . "option_value_description` ovd ON ovd.option_value_id=pov.option_value_id AND ovd.option_id='" . (int)$color_option_id . "' AND ovd.language_id='" . (int)$language_id . "' " .
			$facet_where('option:' . (int)$color_option_id) .
			" AND ovd.slug != '' " .
			" GROUP BY pov.option_value_id ORDER BY ovd.name"
		);
		foreach ($c_rows as $row) {
			$slug = (string)$row['slug'];
			$name = (string)$row['name'];
			$total = (int)$row['total'];
			$image = (string)$row['image'];

			$cur = $sel_slug;
			$key = $color_option_slug;
			$cur_vals = $cur[$key] ?? [];
			$cur_vals = array_values(array_unique(array_filter(array_map('strval', (array)$cur_vals))));
			$sel = in_array($slug, $cur_vals, true);

			// Hide values that have no in-stock products (but keep selected)
			if ($total <= 0 && !$sel) {
				continue;
			}

			$data['colors'][] = [
				'option_id' => $color_option_id,
				'option_slug' => $color_option_slug,
				'id' => (int)$row['option_value_id'],
				'slug' => $slug,
				'name' => $name,
				'image' => $image,
				'total' => $total,
			];

			if ($sel) {
				$cur_vals = array_values(array_filter($cur_vals, static fn($v) => $v !== $slug));
			} else {
				$cur_vals[] = $slug;
				$cur_vals = array_values(array_unique($cur_vals));
			}

			if ($cur_vals) {
				$cur[$key] = $cur_vals;
			} else {
				unset($cur[$key]);
			}

			$data['color_items'][] = [
				'slug' => $slug,
				'name' => $name,
				'total' => $total,
				'image' => $image,
				'selected' => $sel,
				'url' => $build_url($cur),
			];
		}

		// Size option (option_id=14, slug razmer-o)
		$size_option_id = 14;
		$size_option_slug = 'razmer-o';
		$data['size_items'] = [];
		$siz_rows = $cacheRows(
			"SELECT pov.option_value_id, ovd.name, ovd.slug, COUNT(DISTINCT p.product_id) total " .
			"FROM `" . DB_PREFIX . "product_to_category` p2c " .
			"JOIN `" . DB_PREFIX . "product` p ON p.product_id=p2c.product_id " .
			"JOIN `" . DB_PREFIX . "product_option_value` pov ON pov.product_id=p.product_id AND pov.option_id='" . (int)$size_option_id . "' " .
			"JOIN `" . DB_PREFIX . "option_value_description` ovd ON ovd.option_value_id=pov.option_value_id AND ovd.option_id='" . (int)$size_option_id . "' AND ovd.language_id='" . (int)$language_id . "' " .
			$facet_where('option:' . (int)$size_option_id) .
			" AND ovd.slug != '' " .
			" GROUP BY pov.option_value_id ORDER BY ovd.name"
		);
		foreach ($siz_rows as $row) {
			$slug = (string)$row['slug'];
			$name = (string)$row['name'];
			$total = (int)$row['total'];

			$cur = $sel_slug;
			$key = $size_option_slug;
			$cur_vals = $cur[$key] ?? [];
			$cur_vals = array_values(array_unique(array_filter(array_map('strval', (array)$cur_vals))));
			$sel = in_array($slug, $cur_vals, true);

			// Hide values that have no in-stock products (but keep selected)
			if ($total <= 0 && !$sel) {
				continue;
			}

			if ($sel) {
				$cur_vals = array_values(array_filter($cur_vals, static fn($v) => $v !== $slug));
			} else {
				$cur_vals[] = $slug;
				$cur_vals = array_values(array_unique($cur_vals));
			}

			if ($cur_vals) {
				$cur[$key] = $cur_vals;
			} else {
				unset($cur[$key]);
			}

			$data['size_items'][] = [
				'slug' => $slug,
				'name' => $name,
				'total' => $total,
				'selected' => $sel,
				'url' => $build_url($cur),
			];
		}

		// Style attribute (attribute_id=23)
		// IMPORTANT: product_attribute for style may contain colon-separated values (A:B:C).
		// For UX we must split them into separate facet values and count IN-STOCK products.
		$style_attr_id = 23;
		$style_attr_slug = 'stil';
		$data['styles'] = [];
		$data['style_items'] = [];

		// Query one row per product and split in PHP
		$style_rows = $cacheRows(
			"SELECT DISTINCT p.product_id, pa.slug, pa.text " .
			"FROM `" . DB_PREFIX . "product_to_category` p2c " .
			"JOIN `" . DB_PREFIX . "product` p ON p.product_id=p2c.product_id " .
			"JOIN `" . DB_PREFIX . "product_attribute` pa ON pa.product_id=p.product_id AND pa.attribute_id='" . (int)$style_attr_id . "' AND pa.language_id='" . (int)$language_id . "' " .
			$facet_where('attribute:' . (int)$style_attr_id) .
			" AND pa.slug != '' AND pa.text != ''"
		);

		$style_counts = [];
		foreach ($style_rows as $r) {
			$slug_raw = trim((string)($r['slug'] ?? ''));
			$text_raw = trim((string)($r['text'] ?? ''));
			if ($slug_raw === '' || $text_raw === '') continue;

			$slug_parts = array_values(array_filter(array_map('trim', explode(':', $slug_raw)), static fn($v) => $v !== ''));
			$text_parts = array_values(array_filter(array_map('trim', explode(':', $text_raw)), static fn($v) => $v !== ''));

			$max = max(count($slug_parts), count($text_parts));
			for ($i = 0; $i < $max; $i++) {
				$sp = $slug_parts[$i] ?? '';
				$tp = $text_parts[$i] ?? '';
				if ($sp === '' || $tp === '') continue;
				if (!isset($style_counts[$sp])) {
					$style_counts[$sp] = ['slug' => $sp, 'text' => $tp, 'total' => 0];
				}
				$style_counts[$sp]['total'] += 1;
			}
		}

		// Build items sorted by text
		$style_list = array_values($style_counts);
		usort($style_list, static function($a, $b){
			return strcmp((string)($a['text'] ?? ''), (string)($b['text'] ?? ''));
		});

		foreach ($style_list as $row) {
			$slug = (string)($row['slug'] ?? '');
			$text = (string)($row['text'] ?? '');
			$total = (int)($row['total'] ?? 0);

			$cur = $sel_slug;
			$key = $style_attr_slug;
			$cur_vals = $cur[$key] ?? [];
			$cur_vals = array_values(array_unique(array_filter(array_map('strval', (array)$cur_vals))));
			$sel = in_array($slug, $cur_vals, true);

			// Hide values that have no in-stock products (but keep selected)
			if ($total <= 0 && !$sel) {
				continue;
			}

			$data['styles'][] = [
				'attribute_id' => $style_attr_id,
				'attribute_slug' => $style_attr_slug,
				'slug' => $slug,
				'text' => $text,
				'total' => $total,
			];

			if ($sel) {
				$cur_vals = array_values(array_filter($cur_vals, static fn($v) => $v !== $slug));
			} else {
				$cur_vals[] = $slug;
				$cur_vals = array_values(array_unique($cur_vals));
			}

			if ($cur_vals) {
				$cur[$key] = $cur_vals;
			} else {
				unset($cur[$key]);
			}

			$data['style_items'][] = [
				'slug' => $slug,
				'text' => $text,
				'total' => $total,
				'selected' => $sel,
				'url' => $build_url($cur),
			];
		}

		// Helper: load slug for option/attribute
		$get_option_slug = function (int $option_id) use ($language_id): string {
			$q = $this->db->query("SELECT slug FROM `" . DB_PREFIX . "option_description` WHERE language_id='" . (int)$language_id . "' AND option_id='" . (int)$option_id . "' LIMIT 1");
			return $q->num_rows ? (string)$q->row['slug'] : '';
		};

		$get_attribute_slug = function (int $attribute_id) use ($language_id): string {
			$q = $this->db->query("SELECT slug FROM `" . DB_PREFIX . "attribute_description` WHERE language_id='" . (int)$language_id . "' AND attribute_id='" . (int)$attribute_id . "' LIMIT 1");
			return $q->num_rows ? (string)$q->row['slug'] : '';
		};

		// Build ordered blocks list from admin config (avoid hardcoded template sections)
		$data['blocks'] = [];
		foreach ($blocks_list as $row) {
			if (!is_array($row)) continue;
			$key = trim((string)($row['key'] ?? ''));
			if ($key === '') continue;

			$display = (string)($row['display'] ?? '');
			if ($display === 'hide') continue;

			if ($key === 'standard') {
				$meta = $block_meta('standard', ($data['is_ua'] ? 'Стандартні фільтри' : 'Стандартные фильтры'));
				$meta['type'] = 'list';
				$meta['display'] = 'list';

				// Standard toggles map to /f/ segments: stock_1 / special_1 / new_1
				$items = [];
				$std = [
					[
						'key' => 'stock',
						'label' => ($data['is_ua'] ? 'В наявності' : 'В наличии'),
						'selected' => !empty($this->request->get['stock']),
					],
					[
						'key' => 'special',
						'label' => ($data['is_ua'] ? 'Акції' : 'Акция'),
						'selected' => !empty($this->request->get['special']),
					],
					[
						'key' => 'new',
						'label' => ($data['is_ua'] ? 'Новинки' : 'Новинки'),
						'selected' => !empty($this->request->get['new']),
					],
				];

				foreach ($std as $s) {
					$kstd = (string)$s['key'];
					$cur = $sel_slug;

					if (!empty($s['selected'])) {
						unset($cur[$kstd]);
						$sel = true;
					} else {
						$cur[$kstd] = ['1'];
						$sel = false;
					}

					// Compute how many products we'd have if this toggle is ON (with all other filters preserved)
					$fd = $filter_data_base;
					if ($kstd === 'stock') $fd['filter_stock'] = 1;
					if ($kstd === 'special') $fd['filter_special'] = 1;
					if ($kstd === 'new') $fd['filter_new'] = 1;
					$total = (int)$this->model_catalog_product->getTotalProducts($fd);

					$items[] = [
						'label' => (string)$s['label'],
						'total' => $total,
						'selected' => $sel,
						'url' => $build_url($cur),
						'fp_key' => $kstd,
						'fp_val' => '1',
					];
				}

				$meta['items'] = $items;
				$data['blocks'][] = $meta;
				continue;
			}

			if ($key === 'popular_queries') {
				$meta = $block_meta('popular_queries', ($data['is_ua'] ? 'Популярні запити' : 'Популярные запросы'));
				$meta['type'] = 'nav';
				$meta['display'] = 'list';

				$raw = $setting['popular_queries'] ?? [];
				if (!is_array($raw)) $raw = ['' => (string)$raw];

				$txt = '';
				if (isset($raw[$lang_code])) {
					$txt = (string)$raw[$lang_code];
				} elseif (isset($raw['ru-ru'])) {
					$txt = (string)$raw['ru-ru'];
				} elseif (isset($raw['uk-ua'])) {
					$txt = (string)$raw['uk-ua'];
				} elseif (isset($raw[''])) {
					$txt = (string)$raw[''];
				}

				$items = [];
				foreach (preg_split('/\r?\n/', $txt) as $line) {
					$line = trim((string)$line);
					if ($line === '') continue;
					if (strpos($line, '|') === false) continue;
					[$label, $url] = array_map('trim', explode('|', $line, 2));
					if ($label === '' || $url === '') continue;
					$items[] = ['label' => $label, 'url' => $url, 'total' => 0];
				}

				$meta['items'] = $items;
				if ($items) {
					$data['blocks'][] = $meta;
				}
				continue;
			}

			if ($key === 'category' && !empty($data['child_category_items'])) {
				$meta = $block_meta('category', ($data['is_ua'] ? 'Категорії' : 'Категории'));
				$meta['type'] = 'nav';
				$meta['display'] = 'list';
				$meta['items'] = array_map(static function ($c) {
					return [
						'label' => (string)$c['name'],
						'total' => (int)$c['total'],
						'url' => (string)$c['url'],
					];
				}, $data['child_category_items']);
				$data['blocks'][] = $meta;
				continue;
			}

			if ($key === 'price' && $data['show_price'] && !empty($data['has_price_range'])) {
				$meta = $block_meta('price', ($data['is_ua'] ? 'Ціна' : 'Цена'));
				$meta['type'] = 'price';
				$meta['display'] = $data['disp_price'];
				$meta['price'] = [
					'min_floor' => $data['price_min_floor'],
					'max_ceil' => $data['price_max_ceil'],
					'selected_min' => $data['selected_min_price'],
					'selected_max' => $data['selected_max_price'],
					'action' => $data['price_form_action'],
				];
				$data['blocks'][] = $meta;
				continue;
			}

			if ($key === 'manufacturer' && $data['show_manufacturer'] && !empty($data['manufacturer_items'])) {
				$meta = $block_meta('manufacturer', ($data['is_ua'] ? 'Виробники' : 'Производители'));
				$meta['type'] = 'list';
				$meta['display'] = $data['disp_manufacturer'];
				$meta['slug_key'] = 'manufacturer';
				$meta['items'] = array_map(static function ($m) {
					return [
						'label' => (string)$m['name'],
						'total' => (int)$m['total'],
						'selected' => !empty($m['selected']),
						'url' => (string)$m['url'],
						'fp_val' => (string)$m['id'],
					];
				}, $data['manufacturer_items']);
				$data['blocks'][] = $meta;
				continue;
			}

			if ($key === 'color' && $data['show_color'] && !empty($data['color_items'])) {
				$meta = $block_meta('color', ($data['is_ua'] ? 'Колір' : 'Цвет'));
				$meta['type'] = 'list';
				$meta['display'] = $data['disp_color'];
				$meta['slug_key'] = 'tsvet-o';
				$meta['items'] = array_map(static function ($c) {
					return [
						'label' => (string)$c['name'],
						'total' => (int)$c['total'],
						'selected' => !empty($c['selected']),
						'url' => (string)$c['url'],
						'image' => (string)($c['image'] ?? ''),
						'fp_val' => (string)$c['slug'],
					];
				}, $data['color_items']);
				$data['blocks'][] = $meta;
				continue;
			}

			if ($key === 'style' && $data['show_style'] && !empty($data['style_items'])) {
				$meta = $block_meta('style', ($data['is_ua'] ? 'Стиль' : 'Стиль'));
				$meta['type'] = 'list';
				$meta['display'] = $data['disp_style'];
				$meta['slug_key'] = 'stil';
				$meta['items'] = array_map(static function ($s) {
					return [
						'label' => (string)$s['text'],
						'total' => (int)$s['total'],
						'selected' => !empty($s['selected']),
						'url' => (string)$s['url'],
						'fp_val' => (string)$s['slug'],
					];
				}, $data['style_items']);
				$data['blocks'][] = $meta;
				continue;
			}

			if ($key === 'size' && $data['show_size'] && !empty($data['size_items'])) {
				$meta = $block_meta('size', ($data['is_ua'] ? 'Розмір' : 'Размер'));
				$meta['type'] = 'list';
				$meta['display'] = $data['disp_size'];
				$meta['slug_key'] = 'razmer-o';
				$meta['items'] = array_map(static function ($s) {
					return [
						'label' => (string)$s['name'],
						'total' => (int)$s['total'],
						'selected' => !empty($s['selected']),
						'url' => (string)$s['url'],
						'fp_val' => (string)$s['slug'],
					];
				}, $data['size_items']);
				$data['blocks'][] = $meta;
				continue;
			}

			// Dynamic option block: option:<id>
			if (preg_match('/^option:(\d+)$/', $key, $mm)) {
				$option_id = (int)$mm[1];
				$opt_slug = $get_option_slug($option_id);
				if ($opt_slug !== '') {
					$items = [];
					$opt_rows = $cacheRows(
						"SELECT pov.option_value_id, ov.image, ovd.name, ovd.slug, COUNT(DISTINCT pov.product_id) total " .
						"FROM `" . DB_PREFIX . "product_to_category` p2c " .
						"JOIN `" . DB_PREFIX . "product` p ON p.product_id=p2c.product_id AND p.status='1' AND p.date_available<=NOW() AND p.quantity > 0 " .
						"JOIN `" . DB_PREFIX . "product_option_value` pov ON pov.product_id=p.product_id AND pov.option_id='" . (int)$option_id . "' " .
						"JOIN `" . DB_PREFIX . "option_value` ov ON ov.option_value_id=pov.option_value_id " .
						"JOIN `" . DB_PREFIX . "option_value_description` ovd ON ovd.option_value_id=pov.option_value_id AND ovd.option_id='" . (int)$option_id . "' AND ovd.language_id='" . (int)$language_id . "' " .
						"WHERE p2c.category_id='" . (int)$category_id . "' AND ovd.slug != '' " .
						"GROUP BY pov.option_value_id ORDER BY ovd.name"
					);
					foreach ($opt_rows as $r) {
						$slug = (string)$r['slug'];
						$cur = $sel_slug;
						$cur_vals = $cur[$opt_slug] ?? [];
						$cur_vals = array_values(array_unique(array_filter(array_map('strval', (array)$cur_vals))));
						$sel = in_array($slug, $cur_vals, true);

						if ($sel) {
							$cur_vals = array_values(array_filter($cur_vals, static fn($v) => $v !== $slug));
						} else {
							$cur_vals[] = $slug;
							$cur_vals = array_values(array_unique($cur_vals));
						}

						if ($cur_vals) {
							$cur[$opt_slug] = $cur_vals;
						} else {
							unset($cur[$opt_slug]);
						}

						$items[] = [
							'label' => (string)$r['name'],
							'total' => (int)$r['total'],
							'selected' => $sel,
							'url' => $build_url($cur),
							'image' => (string)($r['image'] ?? ''),
							'fp_val' => (string)$slug,
						];
					}

					if ($items) {
						$meta = $block_meta($key, $key);
						$meta['type'] = 'list';
						$meta['display'] = $get_display($key, 'checkbox');
						$meta['slug_key'] = $opt_slug;
						$meta['items'] = $items;
						$data['blocks'][] = $meta;
						continue;
					}
				}
			}

			// Dynamic attribute block: attribute:<id>
			if (preg_match('/^attribute:(\d+)$/', $key, $mm)) {
				$attribute_id = (int)$mm[1];
				$attr_slug = $get_attribute_slug($attribute_id);
				if ($attr_slug !== '') {
					$items = [];
					$attr_rows = $cacheRows(
						"SELECT pa.slug, pa.text, COUNT(DISTINCT pa.product_id) total " .
						"FROM `" . DB_PREFIX . "product_to_category` p2c " .
						"JOIN `" . DB_PREFIX . "product` p ON p.product_id=p2c.product_id AND p.status='1' AND p.date_available<=NOW() AND p.quantity > 0 " .
						"JOIN `" . DB_PREFIX . "product_attribute` pa ON pa.product_id=p.product_id AND pa.attribute_id='" . (int)$attribute_id . "' AND pa.language_id='" . (int)$language_id . "' " .
						"WHERE p2c.category_id='" . (int)$category_id . "' AND pa.slug != '' AND pa.text != '' " .
						"GROUP BY pa.slug, pa.text ORDER BY pa.text"
					);
					foreach ($attr_rows as $r) {
						$slug = (string)$r['slug'];
						$cur = $sel_slug;
						$cur_vals = $cur[$attr_slug] ?? [];
						$cur_vals = array_values(array_unique(array_filter(array_map('strval', (array)$cur_vals))));
						$sel = in_array($slug, $cur_vals, true);

						if ($sel) {
							$cur_vals = array_values(array_filter($cur_vals, static fn($v) => $v !== $slug));
						} else {
							$cur_vals[] = $slug;
							$cur_vals = array_values(array_unique($cur_vals));
						}

						if ($cur_vals) {
							$cur[$attr_slug] = $cur_vals;
						} else {
							unset($cur[$attr_slug]);
						}

						$items[] = [
							'label' => (string)$r['text'],
							'total' => (int)$r['total'],
							'selected' => $sel,
							'url' => $build_url($cur),
							'fp_val' => (string)$slug,
						];
					}

					if ($items) {
						$meta = $block_meta($key, $key);
						$meta['type'] = 'list';
						$meta['display'] = $get_display($key, 'checkbox');
						$meta['slug_key'] = $attr_slug;
						$meta['items'] = $items;
						$data['blocks'][] = $meta;
						continue;
					}
				}
			}
		}

		// Render from active theme template (DIR_TEMPLATE points to our theme folder)
		return $this->load->view('common/filterpro_like', $data);
	}
}
