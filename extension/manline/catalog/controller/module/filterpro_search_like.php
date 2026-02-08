<?php
namespace Opencart\Catalog\Controller\Extension\Manline\Module;

/**
 * FilterPro-like sidebar for PRODUCT SEARCH page.
 *
 * Stage 1: query-param based filtering (no /f/), but keep OC2-like UI.
 *
 * Query params format:
 * - manufacturer=11,13 (or repeated)
 * - f_<slug>=val1,val2  where <slug> matches option_description.slug or attribute_description.slug
 * - min_price/max_price
 * - stock=1 special=1 new=1
 */
class FilterproSearchLike extends \Opencart\System\Engine\Controller {
	/**
	 * @param array<string,mixed> $setting
	 */
	public function index(array $setting = []): string {
		if (isset($setting['status']) && !$setting['status']) {
			return '';
		}

		// only show on product/search
		$route = (string)($this->request->get['route'] ?? '');
		if ($route !== 'product/search') {
			return '';
		}

		$search = (string)($this->request->get['search'] ?? '');
		$tag = (string)($this->request->get['tag'] ?? '');
		if ($search === '' && $tag === '') {
			return '';
		}

		$data = [];
		$data['fp_partial'] = !empty($this->request->get['fp_partial']);
		$data['fp_mode'] = 'search';

		$lang_code = (string)($this->config->get('config_language') ?? '');
		$data['is_ua'] = in_array($lang_code, ['uk-ua', 'ua'], true);

		// Base URL for search (SEO keyword is mapped in seo_url: keyword=search)
		$base = $this->url->link('product/search', 'language=' . $lang_code);
		// We want a stable base path (without query) + stable base query (language + search + tag + category/sub flags)
		$base_path = $base;
		$base_query = '';
		$qpos = strpos($base, '?');
		if ($qpos !== false) {
			$base_path = substr($base, 0, $qpos);
			$base_query = substr($base, $qpos + 1);
		}

		$keep = [];
		foreach (['search', 'tag', 'description', 'category_id', 'sub_category'] as $k) {
			if (isset($this->request->get[$k]) && $this->request->get[$k] !== '') {
				$keep[$k] = $this->request->get[$k];
			}
		}
		// always keep language
		$keep = ['language' => $lang_code] + $keep;
		$data['fp_base_path'] = $base_path;
		$data['fp_base_query'] = http_build_query($keep);
		$data['base_url'] = $base_path . '?' . $data['fp_base_query'];

		$language_id = (int)$this->config->get('config_language_id');

		// Blocks config from admin module (reuse same blocks list)
		$blocks_list = [];
		$blocks_map = [];
		if (isset($setting['blocks']) && is_array($setting['blocks'])) {
			$raw = $setting['blocks'];
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
				foreach ($raw as $row) {
					if (!is_array($row)) continue;
					$k = trim((string)($row['key'] ?? ''));
					if ($k === '') continue;
					$blocks_list[] = $row;
				}
			}
		}

		// On OC2, search doesn't have explicit blocks config; it effectively auto-picks from the result set.
		// Default behavior here: auto-pick unless module explicitly enables using configured blocks.
		$search_use_blocks = !empty($setting['search_use_blocks']);
		if (!$search_use_blocks) {
			$blocks_list = [];
			$blocks_map = [];
		}

		// If no blocks configured (or auto mode), auto-pick a sane subset based on common Manline facets.
		// Goal: behave like "analyze results and show appropriate blocks".
		if (!$blocks_list) {
			$findOptionId = function (array $slugs, array $names) use ($language_id) : int {
				$w = [];
				foreach ($slugs as $s) {
					$s = trim((string)$s);
					if ($s !== '') $w[] = "slug='" . $this->db->escape($s) . "'";
				}
				foreach ($names as $n) {
					$n = trim((string)$n);
					if ($n !== '') $w[] = "name='" . $this->db->escape($n) . "'";
				}
				if (!$w) return 0;
				$q = $this->db->query("SELECT option_id FROM `" . DB_PREFIX . "option_description` WHERE language_id='" . (int)$language_id . "' AND (" . implode(' OR ', $w) . ") LIMIT 1");
				return $q->num_rows ? (int)$q->row['option_id'] : 0;
			};

			$findAttributeId = function (array $slugs, array $names) use ($language_id) : int {
				$w = [];
				foreach ($slugs as $s) {
					$s = trim((string)$s);
					if ($s !== '') $w[] = "slug='" . $this->db->escape($s) . "'";
				}
				foreach ($names as $n) {
					$n = trim((string)$n);
					if ($n !== '') $w[] = "name='" . $this->db->escape($n) . "'";
				}
				if (!$w) return 0;
				$q = $this->db->query("SELECT attribute_id FROM `" . DB_PREFIX . "attribute_description` WHERE language_id='" . (int)$language_id . "' AND (" . implode(' OR ', $w) . ") LIMIT 1");
				return $q->num_rows ? (int)$q->row['attribute_id'] : 0;
			};

			// Common facets (RU/UA) — on Manline these are ATTRIBUTES (not options)
			$size_attr_id = $findAttributeId(['size','rozmir','razmer'], ['Размер', 'Розмір']);
			$color_attr_id = $findAttributeId(['color','kolir','cvet'], ['Цвет', 'Колір']);
			$style_attr_id = $findAttributeId(['stil','style'], ['Стиль']);
			$delivery_attr_id = $findAttributeId(['srok-dostavki','delivery'], ['Срок доставки', 'Термін доставки']);

			$blocks_list[] = ['key' => 'manufacturer', 'sort_order' => 10, 'display' => 'checkbox', 'expanded' => 1];
			if ($style_attr_id) $blocks_list[] = ['key' => 'attribute:' . $style_attr_id, 'sort_order' => 20, 'display' => 'checkbox', 'expanded' => 1];
			if ($delivery_attr_id) $blocks_list[] = ['key' => 'attribute:' . $delivery_attr_id, 'sort_order' => 30, 'display' => 'checkbox', 'expanded' => 1];
			if ($size_attr_id) $blocks_list[] = ['key' => 'attribute:' . $size_attr_id, 'sort_order' => 40, 'display' => 'checkbox', 'expanded' => 1];
			if ($color_attr_id) $blocks_list[] = ['key' => 'attribute:' . $color_attr_id, 'sort_order' => 50, 'display' => 'checkbox', 'expanded' => 1];
			$blocks_list[] = ['key' => 'price', 'sort_order' => 60, 'display' => 'slider', 'expanded' => 1];
		}

		foreach ($blocks_list as $row) {
			if (!is_array($row)) continue;
			$k = trim((string)($row['key'] ?? ''));
			if ($k === '') continue;
			$blocks_map[$k] = $row;
		}

		usort($blocks_list, static function ($a, $b) {
			return (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0);
		});

		$get_tip = function (string $key) use ($blocks_map, $lang_code): string {
			if (!isset($blocks_map[$key]) || !is_array($blocks_map[$key])) return '';
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
			if (!isset($blocks_map[$key]) || !is_array($blocks_map[$key])) return $default;
			return !empty($blocks_map[$key]['expanded']) ? 1 : 0;
		};

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

		$get_display = function (string $key, string $default = 'checkbox') use ($blocks_map): string {
			if (!isset($blocks_map[$key]) || !is_array($blocks_map[$key])) return $default;
			$display = (string)($blocks_map[$key]['display'] ?? '');
			return $display !== '' ? $display : $default;
		};

		$getAttributeName = function (int $attribute_id) use ($language_id): string {
			$q = $this->db->query("SELECT name FROM `" . DB_PREFIX . "attribute_description` WHERE attribute_id='" . (int)$attribute_id . "' AND language_id='" . (int)$language_id . "' LIMIT 1");
			return $q->num_rows ? (string)$q->row['name'] : '';
		};

		$getOptionName = function (int $option_id) use ($language_id): string {
			$q = $this->db->query("SELECT name FROM `" . DB_PREFIX . "option_description` WHERE option_id='" . (int)$option_id . "' AND language_id='" . (int)$language_id . "' LIMIT 1");
			return $q->num_rows ? (string)$q->row['name'] : '';
		};

		$limitItems = function (array $items, int $limit = 20): array {
			// Remove empties + sort by total desc, then label asc
			$items = array_values(array_filter($items, static fn($it) => is_array($it) && !empty($it['label'])));
			usort($items, static function ($a, $b) {
				$ta = (int)($a['total'] ?? 0);
				$tb = (int)($b['total'] ?? 0);
				if ($ta !== $tb) return $tb <=> $ta;
				return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
			});
			if ($limit > 0 && count($items) > $limit) {
				$items = array_slice($items, 0, $limit);
			}
			return $items;
		};

		// Selection parsing (query params)
		$sel = [];
		// manufacturer
		$man = $this->request->get['manufacturer'] ?? null;
		$mans = [];
		if (is_array($man)) {
			foreach ($man as $v) foreach (explode(',', (string)$v) as $vv) $mans[] = (int)$vv;
		} elseif ($man !== null && $man !== '') {
			foreach (explode(',', (string)$man) as $vv) $mans[] = (int)$vv;
		}
		$mans = array_values(array_unique(array_filter($mans)));
		if ($mans) $sel['manufacturer'] = array_map('strval', $mans);

		// standard toggles
		if (!empty($this->request->get['stock'])) $sel['stock'] = ['1'];
		if (!empty($this->request->get['special'])) $sel['special'] = ['1'];
		if (!empty($this->request->get['new'])) $sel['new'] = ['1'];

		// dynamic slug filters: f_<slug>=a,b
		foreach ($this->request->get as $k => $v) {
			$k = (string)$k;
			if (!str_starts_with($k, 'f_')) continue;
			$slug_key = substr($k, 2);
			$vals = [];
			if (is_array($v)) {
				foreach ($v as $vv) $vals = array_merge($vals, explode(',', (string)$vv));
			} else {
				$vals = explode(',', (string)$v);
			}
			$vals = array_values(array_unique(array_filter(array_map('trim', $vals))));
			if ($slug_key !== '' && $vals) {
				$sel[$slug_key] = $vals;
			}
		}

		$data['selected_slug'] = $sel;

		$min_price = isset($this->request->get['min_price']) ? (float)$this->request->get['min_price'] : null;
		$max_price = isset($this->request->get['max_price']) ? (float)$this->request->get['max_price'] : null;
		$data['selected_min_price'] = ($min_price !== null) ? (string)$min_price : '';
		$data['selected_max_price'] = ($max_price !== null) ? (string)$max_price : '';

		// Micro-cache function shared with category filter
		$fp_cache_ttl = 5;
		if (!isset($this->session->data['fp_cache']) || !is_array($this->session->data['fp_cache'])) {
			$this->session->data['fp_cache'] = [];
		}
		$cacheRows = function (string $sql) use ($fp_cache_ttl) {
			$key = 'fps.' . md5($sql);
			$now = time();
			$store = &$this->session->data['fp_cache'];
			if (isset($store[$key]) && is_array($store[$key]) && isset($store[$key]['t']) && ($now - (int)$store[$key]['t']) <= $fp_cache_ttl) {
				return $store[$key]['rows'] ?? [];
			}
			$q = $this->db->query($sql);
			$rows = $q->rows ?? [];
			$store[$key] = ['t' => $now, 'rows' => $rows];
			return $rows;
		};

		// Build base search SQL WHERE used by facet queries
		$search_where = function () use ($search, $tag, $language_id): string {
			$term = trim($search !== '' ? $search : $tag);
			if ($term === '') return '';
			$words = preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY);
			$conds = [];
			foreach ($words as $w) {
				$w = $this->db->escape((string)$w);
				$conds[] = "`pd`.`name` LIKE '%" . $w . "%'";
				$conds[] = "`pd`.`tag` LIKE '%" . $w . "%'";
			}
			if (!$conds) return '';
			return " AND `pd`.`language_id`='" . (int)$language_id . "' AND (" . implode(' OR ', $conds) . ")";
		};

		$facet_where = function (string $exclude) use ($sel, $min_price, $max_price): string {
			$w = [];
			$w[] = "p.status='1'";
			$w[] = "p.date_available<=NOW()";

			if ($exclude !== 'manufacturer' && !empty($sel['manufacturer'])) {
				$ids = array_map('intval', (array)$sel['manufacturer']);
				$ids = array_values(array_unique(array_filter($ids)));
				if ($ids) $w[] = "p.manufacturer_id IN (" . implode(',', $ids) . ")";
			}

			foreach ($sel as $k => $vals) {
				if (!is_array($vals) || !$vals) continue;
				if (in_array($k, ['manufacturer','stock','special','new'], true)) continue;
				if ($exclude === 'slug:' . $k) continue;

				// OPTION by slug
				$opt_q = $this->db->query("SELECT option_id FROM `" . DB_PREFIX . "option_description` WHERE slug='" . $this->db->escape($k) . "' ORDER BY language_id='" . (int)$this->config->get('config_language_id') . "' DESC LIMIT 1");
				if ($opt_q->num_rows) {
					$option_id = (int)$opt_q->row['option_id'];
					$in = [];
					foreach ($vals as $vslug) {
						$ov_q = $this->db->query("SELECT option_value_id FROM `" . DB_PREFIX . "option_value_description` WHERE option_id='" . $option_id . "' AND slug='" . $this->db->escape((string)$vslug) . "' ORDER BY language_id='" . (int)$this->config->get('config_language_id') . "' DESC LIMIT 1");
						if ($ov_q->num_rows) $in[] = (int)$ov_q->row['option_value_id'];
					}
					$in = array_values(array_unique(array_filter($in)));
					if ($in) {
						$w[] = "EXISTS (SELECT 1 FROM `" . DB_PREFIX . "product_option_value` povf WHERE povf.product_id=p.product_id AND povf.option_id='" . $option_id . "' AND povf.option_value_id IN (" . implode(',', $in) . "))";
						continue;
					}
				}

				// ATTRIBUTE by slug
				$attr_q = $this->db->query("SELECT attribute_id FROM `" . DB_PREFIX . "attribute_description` WHERE slug='" . $this->db->escape($k) . "' ORDER BY language_id='" . (int)$this->config->get('config_language_id') . "' DESC LIMIT 1");
				if ($attr_q->num_rows) {
					$attribute_id = (int)$attr_q->row['attribute_id'];
					$in = [];
					foreach ($vals as $vslug) {
						$vslug = trim((string)$vslug);
						if ($vslug !== '') $in[] = "'" . $this->db->escape($vslug) . "'";
					}
					$in = array_values(array_unique($in));
					if ($in) {
						$w[] = "EXISTS (SELECT 1 FROM `" . DB_PREFIX . "product_attribute` paf WHERE paf.product_id=p.product_id AND paf.attribute_id='" . $attribute_id . "' AND paf.slug IN (" . implode(',', $in) . "))";
						continue;
				}
				}
			}

			// standard toggles
			if (!empty($sel['stock']) && $exclude !== 'stock') $w[] = "p.quantity > 0";
			if (!empty($sel['new']) && $exclude !== 'new') $w[] = "p.date_added >= DATE_SUB(NOW(), INTERVAL 60 DAY)";
			if (!empty($sel['special']) && $exclude !== 'special') {
				$w[] = "EXISTS (SELECT 1 FROM `" . DB_PREFIX . "product_discount` ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ps.quantity = '1' AND ps.special = '1' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())))";
			}

			// price on base p.price (good enough for stage 1)
			if ($exclude !== 'price') {
				if ($min_price !== null && (float)$min_price > 0) $w[] = "p.price >= " . (float)$min_price;
				if ($max_price !== null && (float)$max_price > 0) $w[] = "p.price <= " . (float)$max_price;
			}

			return $w ? (' WHERE ' . implode(' AND ', $w)) : '';
		};

		// Price bounds across search result set (base price)
		$price_rows = $cacheRows(
			"SELECT MIN(p.price) min_price, MAX(p.price) max_price " .
			"FROM `" . DB_PREFIX . "product` p " .
			"JOIN `" . DB_PREFIX . "product_description` pd ON pd.product_id=p.product_id " .
			$facet_where('price') .
			$search_where()
		);
		$min_floor = isset($price_rows[0]['min_price']) ? (int)floor((float)$price_rows[0]['min_price']) : 0;
		$max_ceil = isset($price_rows[0]['max_price']) ? (int)ceil((float)$price_rows[0]['max_price']) : 0;
		$data['price_min_floor'] = $min_floor;
		$data['price_max_ceil'] = $max_ceil;
		if ($data['selected_min_price'] === '') $data['selected_min_price'] = (string)$min_floor;
		if ($data['selected_max_price'] === '') $data['selected_max_price'] = (string)$max_ceil;
		$data['price_form_action'] = $data['base_url'];

		// Only show price block when there is a meaningful range (avoid noise when no results)
		$data['has_price_range'] = ($max_ceil > 0 && $max_ceil >= $min_floor);

		// Build blocks
		$data['blocks'] = [];
		foreach ($blocks_list as $row) {
			if (!is_array($row)) continue;
			$key = trim((string)($row['key'] ?? ''));
			if ($key === '') continue;
			$display = (string)($row['display'] ?? '');
			if ($display === 'hide') continue;

			if ($key === 'price') {
				if (empty($data['has_price_range'])) {
					continue;
				}
				$meta = $block_meta('price', ($data['is_ua'] ? 'Ціна' : 'Цена'));
				$meta['type'] = 'price';
				$meta['display'] = $get_display('price', 'slider');
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

			if ($key === 'manufacturer') {
				$meta = $block_meta('manufacturer', ($data['is_ua'] ? 'Виробники' : 'Производители'));
				$meta['type'] = 'list';
				$meta['display'] = $get_display('manufacturer', 'checkbox');
				$meta['slug_key'] = 'manufacturer';

				$m_rows = $cacheRows(
					"SELECT p.manufacturer_id, m.name, COUNT(DISTINCT p.product_id) total " .
					"FROM `" . DB_PREFIX . "product` p " .
					"JOIN `" . DB_PREFIX . "product_description` pd ON pd.product_id=p.product_id " .
					"JOIN `" . DB_PREFIX . "manufacturer` m ON m.manufacturer_id=p.manufacturer_id " .
					$facet_where('manufacturer') .
					$search_where() .
					" AND p.manufacturer_id > 0 GROUP BY p.manufacturer_id ORDER BY m.name"
				);

				$items = [];
				foreach ($m_rows as $r) {
					$id = (int)($r['manufacturer_id'] ?? 0);
					if ($id <= 0) continue;
					$name = (string)($r['name'] ?? '');
					$total = (int)($r['total'] ?? 0);
					$cur = $sel;
					$cur_ids = $cur['manufacturer'] ?? [];
					$cur_ids = array_values(array_unique(array_filter(array_map('strval', (array)$cur_ids))));
					$selected = in_array((string)$id, $cur_ids, true);
					if ($selected) {
						$cur_ids = array_values(array_filter($cur_ids, static fn($v) => $v !== (string)$id));
					} else {
						$cur_ids[] = (string)$id;
						$cur_ids = array_values(array_unique($cur_ids));
					}
					if ($cur_ids) $cur['manufacturer'] = $cur_ids; else unset($cur['manufacturer']);

					// build url query
					$q = $keep;
					if (!empty($cur['manufacturer'])) $q['manufacturer'] = implode(',', $cur['manufacturer']);
					foreach ($cur as $k2 => $vals2) {
						if (in_array($k2, ['manufacturer'], true)) continue;
						if (!is_array($vals2) || !$vals2) continue;
						$q['f_' . $k2] = implode(',', $vals2);
					}
					if ($min_price !== null) $q['min_price'] = (float)$min_price;
					if ($max_price !== null) $q['max_price'] = (float)$max_price;
					$url = $base_path . '?' . http_build_query($q);

					$items[] = [
						'label' => $name,
						'total' => $total,
						'selected' => $selected,
						'url' => $url,
						'fp_key' => 'manufacturer',
						'fp_val' => (string)$id,
					];
				}

				$meta['items'] = $limitItems($items, 20);
				if ($meta['items']) $data['blocks'][] = $meta;
				continue;
			}

			// For search we support dynamic option/attribute blocks by key.
			if (preg_match('/^option:(\d+)$/', $key, $mm)) {
				$option_id = (int)$mm[1];
				$opt_q = $this->db->query("SELECT slug FROM `" . DB_PREFIX . "option_description` WHERE language_id='" . (int)$language_id . "' AND option_id='" . (int)$option_id . "' LIMIT 1");
				$opt_slug = $opt_q->num_rows ? (string)$opt_q->row['slug'] : '';
				if ($opt_slug === '') continue;

				$rows = $cacheRows(
					"SELECT pov.option_value_id, ov.image, ovd.name, ovd.slug, COUNT(DISTINCT p.product_id) total " .
					"FROM `" . DB_PREFIX . "product` p " .
					"JOIN `" . DB_PREFIX . "product_description` pd ON pd.product_id=p.product_id " .
					"JOIN `" . DB_PREFIX . "product_option_value` pov ON pov.product_id=p.product_id AND pov.option_id='" . (int)$option_id . "' " .
					"JOIN `" . DB_PREFIX . "option_value` ov ON ov.option_value_id=pov.option_value_id " .
					"JOIN `" . DB_PREFIX . "option_value_description` ovd ON ovd.option_value_id=pov.option_value_id AND ovd.option_id='" . (int)$option_id . "' AND ovd.language_id='" . (int)$language_id . "' " .
					$facet_where('slug:' . $opt_slug) .
					$search_where() .
					" AND ovd.slug != '' GROUP BY pov.option_value_id ORDER BY ovd.name"
				);

				$items = [];
				foreach ($rows as $r) {
					$vslug = (string)($r['slug'] ?? '');
					if ($vslug === '') continue;
					$cur = $sel;
					$cur_vals = $cur[$opt_slug] ?? [];
					$cur_vals = array_values(array_unique(array_filter(array_map('strval', (array)$cur_vals))));
					$selected = in_array($vslug, $cur_vals, true);
					if ($selected) {
						$cur_vals = array_values(array_filter($cur_vals, static fn($v) => $v !== $vslug));
					} else {
						$cur_vals[] = $vslug;
						$cur_vals = array_values(array_unique($cur_vals));
					}
					if ($cur_vals) $cur[$opt_slug] = $cur_vals; else unset($cur[$opt_slug]);

					$q = $keep;
					if (!empty($sel['manufacturer'])) $q['manufacturer'] = implode(',', $sel['manufacturer']);
					foreach ($cur as $k2 => $vals2) {
						if ($k2 === 'manufacturer') continue;
						if (!is_array($vals2) || !$vals2) continue;
						$q['f_' . $k2] = implode(',', $vals2);
					}
					if ($min_price !== null) $q['min_price'] = (float)$min_price;
					if ($max_price !== null) $q['max_price'] = (float)$max_price;
					$url = $base_path . '?' . http_build_query($q);

					$items[] = [
						'label' => (string)($r['name'] ?? ''),
						'total' => (int)($r['total'] ?? 0),
						'selected' => $selected,
						'url' => $url,
						'image' => (string)($r['image'] ?? ''),
						'fp_key' => 'f_' . $opt_slug,
						'fp_val' => $vslug,
					];
				}

				if ($items) {
					$opt_name = $getOptionName($option_id);
					$meta = $block_meta($key, $opt_name !== '' ? $opt_name : $key);
					$meta['type'] = 'list';
					$meta['display'] = $get_display($key, 'checkbox');
					$meta['slug_key'] = 'f_' . $opt_slug;
					$meta['items'] = $limitItems($items, 20);
					if ($meta['items']) {
						$data['blocks'][] = $meta;
					}
				}
				continue;
			}

			if (preg_match('/^attribute:(\d+)$/', $key, $mm)) {
				$attribute_id = (int)$mm[1];
				$attr_q = $this->db->query("SELECT slug FROM `" . DB_PREFIX . "attribute_description` WHERE language_id='" . (int)$language_id . "' AND attribute_id='" . (int)$attribute_id . "' LIMIT 1");
				$attr_slug = $attr_q->num_rows ? (string)$attr_q->row['slug'] : '';
				if ($attr_slug === '') continue;

				$rows = $cacheRows(
					"SELECT pa.slug, pa.text, COUNT(DISTINCT p.product_id) total " .
					"FROM `" . DB_PREFIX . "product` p " .
					"JOIN `" . DB_PREFIX . "product_description` pd ON pd.product_id=p.product_id " .
					"JOIN `" . DB_PREFIX . "product_attribute` pa ON pa.product_id=p.product_id AND pa.attribute_id='" . (int)$attribute_id . "' AND pa.language_id='" . (int)$language_id . "' " .
					$facet_where('slug:' . $attr_slug) .
					$search_where() .
					" AND pa.slug != '' AND pa.text != '' GROUP BY pa.slug, pa.text ORDER BY total DESC, pa.text"
				);

				$items = [];
				foreach ($rows as $r) {
					$vslug = (string)($r['slug'] ?? '');
					if ($vslug === '') continue;
					$cur = $sel;
					$cur_vals = $cur[$attr_slug] ?? [];
					$cur_vals = array_values(array_unique(array_filter(array_map('strval', (array)$cur_vals))));
					$selected = in_array($vslug, $cur_vals, true);
					if ($selected) {
						$cur_vals = array_values(array_filter($cur_vals, static fn($v) => $v !== $vslug));
					} else {
						$cur_vals[] = $vslug;
						$cur_vals = array_values(array_unique($cur_vals));
					}
					if ($cur_vals) $cur[$attr_slug] = $cur_vals; else unset($cur[$attr_slug]);

					$q = $keep;
					if (!empty($sel['manufacturer'])) $q['manufacturer'] = implode(',', $sel['manufacturer']);
					foreach ($cur as $k2 => $vals2) {
						if ($k2 === 'manufacturer') continue;
						if (!is_array($vals2) || !$vals2) continue;
						$q['f_' . $k2] = implode(',', $vals2);
					}
					if ($min_price !== null) $q['min_price'] = (float)$min_price;
					if ($max_price !== null) $q['max_price'] = (float)$max_price;
					$url = $base_path . '?' . http_build_query($q);

					$items[] = [
						'label' => (string)($r['text'] ?? ''),
						'total' => (int)($r['total'] ?? 0),
						'selected' => $selected,
						'url' => $url,
						'fp_key' => 'f_' . $attr_slug,
						'fp_val' => $vslug,
					];
				}

				if ($items) {
					$attr_name = $getAttributeName($attribute_id);
					$meta = $block_meta($key, $attr_name !== '' ? $attr_name : $key);
					$meta['type'] = 'list';
					$meta['display'] = $get_display($key, 'checkbox');
					$meta['slug_key'] = 'f_' . $attr_slug;
					$meta['items'] = $limitItems($items, 20);
					if ($meta['items']) {
						$data['blocks'][] = $meta;
					}
				}
				continue;
			}
		}

		return $this->load->view('common/filterpro_like', $data);
	}
}
