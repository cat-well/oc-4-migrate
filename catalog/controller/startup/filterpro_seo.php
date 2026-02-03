<?php
namespace Opencart\Catalog\Controller\Startup;

/**
 * FilterPro SEO landing support (legacy OC2 -> OC4)
 *
 * In OC2 FilterPro stored SEO landings in `man_filterpro_seo` and mapped slugs via seo_url:
 *   key='route', value='filter_id=NNN', keyword='<slug>'
 *
 * This startup controller translates `route=filter_id=NNN` into a normal category route
 * (`product/category`) while injecting filter params + meta overrides.
 */
class FilterproSeo extends \Opencart\System\Engine\Controller {
	public function index() {
		$route = $this->request->get['route'] ?? '';

		// Match route=filter_id=101 (exactly as stored in man_seo_url.value)
		if (!is_string($route) || !preg_match('/^filter_id=(\d+)$/', $route, $m)) {
			return null;
		}

		$filter_id = (int)$m[1];
		$key = 'filter_id=' . $filter_id;

		// Fetch serialized config from DB
		$query = $this->db->query("SELECT `data` FROM `" . DB_PREFIX . "filterpro_seo` WHERE `url` = '" . $this->db->escape($key) . "' LIMIT 1");
		if (!$query->num_rows) {
			return null;
		}

		$raw = (string)$query->row['data'];
		$data = @unserialize($raw);
		if (!is_array($data)) {
			return null;
		}

		// Stored URL params (HTML escaped &amp;)
		$params_raw = html_entity_decode((string)($data['url'] ?? ''), ENT_QUOTES, 'UTF-8');
		$params = [];
		if ($params_raw) {
			parse_str(str_replace('&amp;', '&', $params_raw), $params);
		}

		// Inject params into request
		if (is_array($params)) {
			foreach ($params as $k => $v) {
				// Don't overwrite route; we translate to product/category
				if ($k === 'route') {
					continue;
				}
				$this->request->get[$k] = $v;
			}
		}

		// Ensure category route
		$this->request->get['route'] = 'product/category';

		// Meta overrides per language.
		// OC2 language mapping (seen in legacy table): ru=1, ua=4.
		$oc4_lang_id = (int)$this->config->get('config_language_id');
		$legacy_lang_map = [
			1 => 2, // ru
			4 => 3  // ua
		];
		$legacy_lang_id = array_search($oc4_lang_id, $legacy_lang_map, true);

		$meta = [];
		if (isset($data['lang']) && is_array($data['lang'])) {
			if ($legacy_lang_id && isset($data['lang'][$legacy_lang_id]) && is_array($data['lang'][$legacy_lang_id])) {
				$meta = $data['lang'][$legacy_lang_id];
			} elseif (isset($data['lang'][$oc4_lang_id]) && is_array($data['lang'][$oc4_lang_id])) {
				// Fallback if IDs already match
				$meta = $data['lang'][$oc4_lang_id];
			} else {
				// Last resort: first language entry
				$first = reset($data['lang']);
				if (is_array($first)) $meta = $first;
			}
		}

		// Normalize meta fields (decode HTML where appropriate)
		$meta_out = [
			'filter_id'         => $filter_id,
			'slug'              => (string)($data['seo'] ?? ''),
			'h1'                => (string)($meta['h1'] ?? ''),
			'title'             => (string)($meta['title'] ?? ''),
			'meta_description'  => (string)($meta['meta_description'] ?? ''),
			'meta_keywords'     => (string)($meta['meta_keywords'] ?? ''),
			'description_html'  => html_entity_decode((string)($meta['description'] ?? ''), ENT_QUOTES, 'UTF-8'),
		];

		$this->registry->set('filterpro_seo', $meta_out);

		return null;
	}
}
