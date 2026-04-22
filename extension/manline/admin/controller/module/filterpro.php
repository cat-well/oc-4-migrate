<?php
namespace Opencart\Admin\Controller\Extension\Manline\Module;

/**
 * FilterPro (Manline) — OC4 custom filter module (admin settings scaffold)
 *
 * Stage 1: admin UI to configure blocks (type/expanded/tooltip) + bind one module instance
 *          as the global category sidebar filter (like OC2 FilterPro behavior).
 */
class FilterPro extends \Opencart\System\Engine\Controller {
	private array $error = [];

	public function install(): void {
		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/manline/module/filterpro');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/manline/module/filterpro');
	}

	public function uninstall(): void {
		// no-op
	}

	public function index(): void {
		$this->load->language('extension/manline/module/filterpro');
		$this->document->setTitle($this->language->get('heading_title'));

		$module_info = [];
		if (isset($this->request->get['module_id'])) {
			$this->load->model('setting/module');
			$module_info = $this->model_setting_module->getModule((int)$this->request->get['module_id']);
		}

		$data['module_id'] = (int)($this->request->get['module_id'] ?? 0);

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/manline/module/filterpro', 'user_token=' . $this->session->data['user_token'] . ($data['module_id'] ? '&module_id=' . $data['module_id'] : ''))
		];

		$data['save'] = $this->url->link('extension/manline/module/filterpro.save', 'user_token=' . $this->session->data['user_token'] . ($data['module_id'] ? '&module_id=' . $data['module_id'] : ''));
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

		$data['name'] = $module_info['name'] ?? '';
		$data['status'] = $module_info['status'] ?? 0;

		// Default blocks (based on OC2 FilterPro UI)
		$default_blocks = [
			[
				'key' => 'price',
				'label' => ['' => $this->language->get('text_block_price')],
				'display' => 'slider',
				'expanded' => 1,
				'sort_order' => 10,
				'tooltip' => []
			],
			[
				'key' => 'manufacturer',
				'label' => ['' => $this->language->get('text_block_manufacturer')],
				'display' => 'checkbox',
				'expanded' => 1,
				'sort_order' => 20,
				'tooltip' => []
			],
			[
				'key' => 'size',
				'label' => ['' => $this->language->get('text_block_size')],
				'display' => 'checkbox',
				'expanded' => 1,
				'sort_order' => 30,
				'tooltip' => []
			],
			[
				'key' => 'color',
				'label' => ['' => $this->language->get('text_block_color')],
				'display' => 'image',
				'expanded' => 1,
				'sort_order' => 40,
				'tooltip' => []
			],
			[
				'key' => 'style',
				'label' => ['' => $this->language->get('text_block_style')],
				'display' => 'checkbox',
				'expanded' => 0,
				'sort_order' => 50,
				'tooltip' => []
			],
		];

		$data['display_options'] = [
			['value' => 'hide', 'text' => $this->language->get('text_display_hide')],
			['value' => 'checkbox', 'text' => $this->language->get('text_display_checkbox')],
			['value' => 'list', 'text' => $this->language->get('text_display_list')],
			['value' => 'image', 'text' => $this->language->get('text_display_image')],
			['value' => 'tiles', 'text' => $this->language->get('text_display_tiles') ?? 'Tiles'],
			['value' => 'slider', 'text' => $this->language->get('text_display_slider')],
		];

		$blocks = $module_info['blocks'] ?? $default_blocks;

		// Backward-compat: if blocks are stored as associative array, convert to list
		if (is_array($blocks) && $blocks) {
			$first_key = array_key_first($blocks);
			if ($first_key !== null && !is_int($first_key)) {
				$list = [];
				$sort = 10;
				foreach ($blocks as $k => $b) {
					if (!is_array($b)) continue;
					$list[] = [
						'key' => (string)$k,
						'label' => (is_array($b['label'] ?? null) ? $b['label'] : ['' => (string)($b['label'] ?? (string)$k)]),
						'display' => (string)($b['display'] ?? 'checkbox'),
						'expanded' => !empty($b['expanded']) ? 1 : 0,
						'sort_order' => (int)($b['sort_order'] ?? $sort),
						'tooltip' => (is_array($b['tooltip'] ?? null) ? $b['tooltip'] : [])
					];
					$sort += 10;
				}
				$blocks = $list;
			}
		}

		// Ensure stable ordering
		usort($blocks, static function ($a, $b) {
			$sa = (int)($a['sort_order'] ?? 0);
			$sb = (int)($b['sort_order'] ?? 0);
			return $sa <=> $sb;
		});

		$data['blocks'] = $blocks;

		// Popular queries (optional): per-language raw text (one per line: label|url)
		$data['popular_queries'] = $module_info['popular_queries'] ?? [];

		// SEO landings (legacy FilterPro): stored in DB_PREFIX . filterpro_seo + seo_url route=filter_id
		$data['seo_landings'] = $this->loadSeoLandings();

		// Languages
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		// Global binding (use one module instance on category pages)
		$this->load->model('setting/setting');
		$current_id = (int)$this->model_setting_setting->getValue('manline_filterpro_module_id');
		$data['use_globally'] = $data['module_id'] && $current_id === $data['module_id'];

		$data['user_token'] = $this->session->data['user_token'];
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		// Use dedicated admin template path
		$this->response->setOutput($this->load->view('extension/manline/module/filterpro', $data));
	}

	/**
	 * Load legacy FilterPro SEO landings from DB.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function loadSeoLandings(): array {
		$items = [];

		// Landings are stored in `filterpro_seo` with url='filter_id=N' and serialized `data`.
		$q = $this->db->query("SELECT `url`, `data` FROM `" . DB_PREFIX . "filterpro_seo` ORDER BY `url`");
		foreach (($q->rows ?? []) as $row) {
			$url = (string)($row['url'] ?? '');
			$data_raw = (string)($row['data'] ?? '');
			$filter_id = 0;
			if (preg_match('/^filter_id=(\\d+)$/', $url, $m)) {
				$filter_id = (int)$m[1];
			}

			$landing = @unserialize($data_raw);
			if (!is_array($landing)) {
				$landing = [];
			}

			// Find current keyword from seo_url table (OC4): key='route', value='filter_id=N'.
			$keyword = '';
			if ($filter_id > 0) {
				$kq = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "seo_url` WHERE store_id='0' AND `key`='route' AND `value`='filter_id=" . (int)$filter_id . "' ORDER BY sort_order DESC, seo_url_id ASC LIMIT 1");
				if ($kq->num_rows) {
					$keyword = (string)$kq->row['keyword'];
				}
			}

			$items[] = [
				'filter_id' => $filter_id,
				'keyword' => $keyword,
				'url' => (string)($landing['url'] ?? ''),
				'lang' => (is_array($landing['lang'] ?? null) ? $landing['lang'] : []),
			];
		}

		return $items;
	}

	/**
	 * Persist legacy FilterPro SEO landings into DB.
	 *
	 * Writes:
	 * - DB_PREFIX.filterpro_seo (url='filter_id=N', data=serialize(...))
	 * - DB_PREFIX.seo_url (key='route', value='filter_id=N', keyword='<slug>')
	 */
	private function saveSeoLandings(array $landings): void {
		// Safer sync (vs legacy full DELETE):
		// - Upsert provided landings
		// - Delete landings removed from the form
		// - Keep all writes in a transaction

		// Normalize input ids
		$incoming_ids = [];
		foreach ($landings as $row) {
			if (!is_array($row)) continue;
			$id = (int)($row['filter_id'] ?? 0);
			if ($id > 0) $incoming_ids[] = $id;
		}
		$incoming_ids = array_values(array_unique($incoming_ids));

		// Load existing ids
		$existing_ids = [];
		$q = $this->db->query("SELECT `url` FROM `" . DB_PREFIX . "filterpro_seo` WHERE `url` LIKE 'filter_id=%'");
		foreach (($q->rows ?? []) as $r) {
			$u = (string)($r['url'] ?? '');
			if (preg_match('/^filter_id=(\\d+)$/', $u, $m)) {
				$existing_ids[] = (int)$m[1];
			}
		}
		$existing_ids = array_values(array_unique($existing_ids));

		$to_delete = array_values(array_diff($existing_ids, $incoming_ids));

		$this->db->query('START TRANSACTION');
		try {
			// Remove deleted landings
			foreach ($to_delete as $filter_id) {
				$filter_id = (int)$filter_id;
				if ($filter_id <= 0) continue;
				$this->db->query("DELETE FROM `" . DB_PREFIX . "filterpro_seo` WHERE `url`='filter_id=" . (int)$filter_id . "'");
				$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE store_id='0' AND `key`='route' AND `value`='filter_id=" . (int)$filter_id . "'");
			}

			// Upsert provided landings
			foreach ($landings as $row) {
				if (!is_array($row)) continue;
				$filter_id = (int)($row['filter_id'] ?? 0);
				if ($filter_id <= 0) continue;

				$keyword = trim((string)($row['keyword'] ?? ''));
				$keyword = trim($keyword, " /\t\r\n");

				$payload = [
					'url' => (string)($row['url'] ?? ''),
					'seo' => $keyword,
					'lang' => (is_array($row['lang'] ?? null) ? $row['lang'] : []),
				];

				$this->db->query(
					"INSERT INTO `" . DB_PREFIX . "filterpro_seo` (`url`, `data`) VALUES ('filter_id=" . (int)$filter_id . "', '" . $this->db->escape(serialize($payload)) . "') " .
					"ON DUPLICATE KEY UPDATE `data`=VALUES(`data`)"
				);

				// Update seo_url mapping for the landing keyword
				$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE store_id='0' AND `key`='route' AND `value`='filter_id=" . (int)$filter_id . "'");

				if ($keyword !== '') {
					$this->db->query(
						"INSERT INTO `" . DB_PREFIX . "seo_url` (store_id, language_id, `key`, `value`, keyword, sort_order) VALUES (0, NULL, 'route', 'filter_id=" . (int)$filter_id . "', '" . $this->db->escape($keyword) . "', 0)"
					);
				}
			}

			$this->db->query('COMMIT');
		} catch (\Throwable $e) {
			$this->db->query('ROLLBACK');
			throw $e;
		}
	}

	public function save(): void {
		$this->load->language('extension/manline/module/filterpro');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/manline/module/filterpro')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$required = [
			'name' => '',
			'status' => 0,
			'blocks' => [],
			'popular_queries' => [],
			'use_globally' => 0,
			'seo_landings' => []
		];

		$post_info = $this->request->post + $required;

		if (!oc_validate_length($post_info['name'], 3, 64)) {
			$json['error']['name'] = $this->language->get('error_name');
		}

		// Normalize blocks (store as ordered list)
		$norm = [];
		if (is_array($post_info['blocks'])) {
			foreach ($post_info['blocks'] as $i => $b) {
				if (!is_array($b)) continue;
				$key = (string)($b['key'] ?? $i);
				$key = trim($key);
				// allow keys like option:14 / attribute:23 / manufacturer / price etc.
				$key = preg_replace('/[^a-z0-9_\-:]/i', '', $key);
				if ($key === '') continue;

				$tooltip = $b['tooltip'] ?? [];
				if (!is_array($tooltip)) {
					$tooltip = ['' => (string)$tooltip];
				}

				$label = $b['label'] ?? [];
				if (!is_array($label)) {
					$label = ['' => (string)$label];
				}

				$norm[] = [
					'key' => $key,
					'label' => $label,
					'display' => (string)($b['display'] ?? 'checkbox'),
					'expanded' => !empty($b['expanded']) ? 1 : 0,
					'sort_order' => (int)($b['sort_order'] ?? ($i * 10)),
					'tooltip' => $tooltip
				];
			}
		}

		usort($norm, static function ($a, $b) {
			return (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0);
		});

		// Auto-fill label per language for option:<id> / attribute:<id> when missing
		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();

		foreach ($norm as &$row) {
			$key = (string)($row['key'] ?? '');
			if (!isset($row['label']) || !is_array($row['label'])) {
				$row['label'] = ['' => (string)$key];
			}

			$needs = true;
			foreach ($languages as $lang) {
				$code = (string)$lang['code'];
				if (!empty($row['label'][$code])) {
					$needs = false;
					break;
				}
			}

			if (!$needs) {
				continue;
			}

			if (preg_match('/^option:(\d+)$/', $key, $m)) {
				$option_id = (int)$m[1];
				foreach ($languages as $lang) {
					$code = (string)$lang['code'];
					$lang_id = (int)$lang['language_id'];
					$q = $this->db->query("SELECT name FROM `" . DB_PREFIX . "option_description` WHERE option_id='" . (int)$option_id . "' AND language_id='" . (int)$lang_id . "' LIMIT 1");
					if ($q->num_rows) {
						$row['label'][$code] = (string)$q->row['name'];
					}
				}
			} elseif (preg_match('/^attribute:(\d+)$/', $key, $m)) {
				$attribute_id = (int)$m[1];
				foreach ($languages as $lang) {
					$code = (string)$lang['code'];
					$lang_id = (int)$lang['language_id'];
					$q = $this->db->query("SELECT name FROM `" . DB_PREFIX . "attribute_description` WHERE attribute_id='" . (int)$attribute_id . "' AND language_id='" . (int)$lang_id . "' LIMIT 1");
					if ($q->num_rows) {
						$row['label'][$code] = (string)$q->row['name'];
					}
				}
			}
		}
		unset($row);

		$post_info['blocks'] = $norm;

		// Normalize popular queries (keep as per-language raw text)
		if (!is_array($post_info['popular_queries'] ?? null)) {
			$post_info['popular_queries'] = ['' => (string)($post_info['popular_queries'] ?? '')];
		}
		foreach ($post_info['popular_queries'] as $code => $txt) {
			$post_info['popular_queries'][(string)$code] = (string)$txt;
		}

		// Normalize SEO landings from POST (legacy ids: ru=1, ua=4)
		$seo_landings = [];
		if (is_array($post_info['seo_landings'] ?? null)) {
			foreach ($post_info['seo_landings'] as $row) {
				if (!is_array($row)) continue;
				$filter_id = (int)($row['filter_id'] ?? 0);
				if ($filter_id <= 0) continue;

				$keyword = trim((string)($row['keyword'] ?? ''));
				$keyword = trim($keyword, " /\t\r\n");
				$url = trim((string)($row['url'] ?? ''));

				$lang = [];
				if (is_array($row['lang'] ?? null)) {
					foreach ($row['lang'] as $lid => $ldata) {
						$lid = (int)$lid;
						if (!in_array($lid, [1, 4], true)) continue;
						if (!is_array($ldata)) $ldata = [];
						$lang[$lid] = [
							'h1' => (string)($ldata['h1'] ?? ''),
							'title' => (string)($ldata['title'] ?? ''),
							'meta_description' => (string)($ldata['meta_description'] ?? ''),
							'meta_keywords' => (string)($ldata['meta_keywords'] ?? ''),
							'description' => (string)($ldata['description'] ?? ''),
						];
					}
				}

				$seo_landings[] = [
					'filter_id' => $filter_id,
					'keyword' => $keyword,
					'url' => $url,
					'lang' => $lang,
				];
			}
		}

		// Validate: duplicate keywords inside the form
		$kw_map = [];
		$dup_kw = [];
		foreach ($seo_landings as $l) {
			$kw = (string)($l['keyword'] ?? '');
			$fid = (int)($l['filter_id'] ?? 0);
			if ($kw === '') continue;
			if (isset($kw_map[$kw]) && (int)$kw_map[$kw] !== $fid) {
				$dup_kw[] = $kw;
			} else {
				$kw_map[$kw] = $fid;
			}
		}
		$dup_kw = array_values(array_unique($dup_kw));
		if ($dup_kw) {
			$json['error']['warning'] = 'Дубли keyword в SEO-лендингах: ' . implode(', ', $dup_kw);
		}

		// Validate: keyword conflicts with existing seo_url entries
		if (!$json) {
			foreach ($seo_landings as $l) {
				$kw = (string)($l['keyword'] ?? '');
				$fid = (int)($l['filter_id'] ?? 0);
				if ($kw === '' || $fid <= 0) continue;

				$conf = $this->db->query("SELECT `key`,`value` FROM `" . DB_PREFIX . "seo_url` WHERE store_id='0' AND keyword='" . $this->db->escape($kw) . "' LIMIT 10");
				foreach (($conf->rows ?? []) as $r) {
					$k = (string)($r['key'] ?? '');
					$v = (string)($r['value'] ?? '');
					if ($k === 'route' && $v === 'filter_id=' . (int)$fid) {
						continue;
					}
					$json['error']['warning'] = 'Keyword конфликтует с существующим SEO URL: ' . $kw;
					break 2;
				}
			}
		}

		if (!$json) {
			// Persist SEO landings into dedicated tables (outside oc_module)
			$this->saveSeoLandings($seo_landings);

			$this->load->model('setting/module');

			if (empty($this->request->get['module_id'])) {
				$json['module_id'] = $this->model_setting_module->addModule('manline.filterpro', $post_info);
			} else {
				$this->model_setting_module->editModule((int)$this->request->get['module_id'], $post_info);
				$json['module_id'] = (int)$this->request->get['module_id'];
			}

			// Persist global binding
			$this->load->model('setting/setting');
			$current = $this->model_setting_setting->getSetting('manline');
			$selected_id = (int)$json['module_id'];
			$current_id = (int)$this->model_setting_setting->getValue('manline_filterpro_module_id');

			if (!empty($post_info['use_globally'])) {
				$current['manline_filterpro_module_id'] = $selected_id;
				$this->model_setting_setting->editSetting('manline', $current);
			} else {
				if ($current_id === $selected_id) {
					$current['manline_filterpro_module_id'] = 0;
					$this->model_setting_setting->editSetting('manline', $current);
				}
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
