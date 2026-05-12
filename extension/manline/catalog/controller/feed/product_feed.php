<?php
namespace Opencart\Catalog\Controller\Extension\Manline\Feed;

/**
 * Marketplace product feed (YML / Yandex.Market XML).
 *
 * Reads its configuration from the legacy NeoSeo `product_feed` table
 * preserved during the OC2→OC4 migration: feed shortname, currency,
 * selected categories + manufacturers, in-stock filter, image size.
 * One controller drives all configured feeds — Rozetka.com.ua,
 * Hotline.ua, Prom.ua, Google Merchant, Facebook — caller picks via
 * ?shortname=rozetka.
 *
 * URL:    index.php?route=extension/manline/feed/product_feed&shortname=rozetka
 * Output: application/xml UTF-8, YML 1.0 (yml_catalog) format.
 *
 * Rozetka and most other UA marketplaces poll this URL on a schedule
 * (typically every 4–24h) and ingest the catalog. No outbound calls
 * from our side — we just serve the feed.
 *
 * Replaces the ionCube-encoded NeoSeo Product Feed Pro module that
 * stopped being maintainable after the OC4 cutover.
 */
class ProductFeed extends \Opencart\System\Engine\Controller {

	/**
	 * Default to Ukrainian (man_language.language_id = 4). Rozetka and
	 * Hotline customers expect UA descriptions; can be overridden later
	 * per-feed by adding a column to product_feed if a marketplace
	 * needs RU.
	 */
	private const FEED_LANGUAGE_ID = 4;

	public function index(): void {
		$shortname = isset($this->request->get['shortname'])
			? preg_replace('/[^a-z0-9_]/', '', strtolower((string)$this->request->get['shortname']))
			: 'rozetka';

		$config = $this->loadFeedConfig($shortname);

		if (!$config) {
			$this->response->addHeader('HTTP/1.1 404 Not Found');
			$this->response->addHeader('Content-Type: text/plain; charset=utf-8');
			$this->response->setOutput('Feed "' . $shortname . '" not found or disabled.');
			return;
		}

		$category_ids = $this->parseIdList($config['categories']);
		$manufacturer_ids = $this->parseIdList($config['manufacturers']);
		$sql_where = $this->sanitiseProductFilter((string)$config['sql_code']);
		$currency = $config['currency'] !== '' ? (string)$config['currency'] : 'UAH';
		$language_id = self::FEED_LANGUAGE_ID;
		$image_width = (int)($config['image_width'] ?: 600);
		$image_height = (int)($config['image_height'] ?: 600);

		$shop_url = $this->resolveShopUrl();

		$this->load->model('tool/image');

		$categories = $this->loadCategories($category_ids, $language_id);
		$offers = $this->loadOffers(
			$category_ids,
			$manufacturer_ids,
			$sql_where,
			$language_id,
			$shop_url,
			$image_width,
			$image_height
		);

		$xml = $this->renderYml($categories, $offers, $currency, $shop_url);

		$this->response->addHeader('Content-Type: application/xml; charset=utf-8');
		// Aggressive feeds (Rozetka polls multiple times a day) — let CDN
		// cache the response briefly so we don't regenerate on every hit.
		$this->response->addHeader('Cache-Control: public, max-age=900');
		$this->response->setOutput($xml);
	}

	private function loadFeedConfig(string $shortname): ?array {
		$q = $this->db->query(
			"SELECT * FROM `" . DB_PREFIX . "product_feed`
			WHERE feed_shortname = '" . $this->db->escape($shortname) . "'
			  AND status = 1
			LIMIT 1"
		);

		return $q->num_rows ? $q->row : null;
	}

	/**
	 * @return list<int>
	 */
	private function parseIdList(?string $list): array {
		if ($list === null || $list === '') return [];

		return array_values(array_filter(array_map('intval', explode(',', $list))));
	}

	/**
	 * The product_feed.sql_code column is an admin-editable SQL fragment
	 * that gets inlined into the offers query. To survive the OC2→OC4
	 * migration without rewriting it we keep the same contract, but
	 * reject anything that looks like an injection — only simple
	 * comparison-style fragments are allowed.
	 */
	private function sanitiseProductFilter(string $sql_where): string {
		$sql_where = trim($sql_where);

		if ($sql_where === '') return '';

		// Whitelist: lowercase letters / digits / underscores / dots,
		// comparison operators, parentheses, simple math, quotes, comma,
		// whitespace. Anything else means someone put non-trivial SQL
		// in there — fall back to a known-safe default.
		if (!preg_match('/^[a-z0-9_\.\s<>=!\\-\\+\\(\\)\'\",]+$/i', $sql_where)) {
			return 'p.quantity > 0';
		}

		return $sql_where;
	}

	private function resolveShopUrl(): string {
		$url = (string)$this->config->get('config_url');

		if ($url === '' && defined('HTTPS_CATALOG')) {
			$url = HTTPS_CATALOG;
		}

		if ($url === '' && defined('HTTP_CATALOG')) {
			$url = HTTP_CATALOG;
		}

		return rtrim($url, '/');
	}

	/**
	 * @param list<int> $ids
	 * @return list<array{id:int,parentId:int,name:string}>
	 */
	private function loadCategories(array $ids, int $language_id): array {
		if (!$ids) return [];

		$placeholders = implode(',', $ids);

		$q = $this->db->query(
			"SELECT c.category_id AS id, c.parent_id AS parentId, cd.name
			FROM `" . DB_PREFIX . "category` c
			INNER JOIN `" . DB_PREFIX . "category_description` cd
			  ON cd.category_id = c.category_id AND cd.language_id = '" . (int)$language_id . "'
			WHERE c.category_id IN (" . $placeholders . ")
			  AND c.status = 1
			ORDER BY c.sort_order ASC, cd.name ASC"
		);

		return array_map(static fn (array $r) => [
			'id' => (int)$r['id'],
			'parentId' => (int)$r['parentId'],
			'name' => (string)$r['name'],
		], $q->rows);
	}

	/**
	 * @param list<int> $category_ids
	 * @param list<int> $manufacturer_ids
	 * @return list<array{
	 *   id:int,url:string,price:string,oldprice:?string,categoryId:int,
	 *   name:string,description:string,model:string,vendor:string,vendorCode:string,
	 *   image:list<string>,attributes:list<array{name:string,value:string}>
	 * }>
	 */
	private function loadOffers(
		array $category_ids,
		array $manufacturer_ids,
		string $sql_where,
		int $language_id,
		string $shop_url,
		int $image_width,
		int $image_height
	): array {
		if (!$category_ids) return [];

		$cat_placeholders = implode(',', $category_ids);
		$man_filter = $manufacturer_ids
			? ' AND p.manufacturer_id IN (' . implode(',', $manufacturer_ids) . ')'
			: '';
		$where_extra = $sql_where !== '' ? ' AND (' . $sql_where . ')' : '';

		$rows = $this->db->query(
			"SELECT
				p.product_id, p.model, p.sku, p.price, p.quantity, p.image, p.manufacturer_id,
				pd.name, pd.description,
				m.name AS vendor,
				p2c.category_id
			FROM `" . DB_PREFIX . "product` p
			INNER JOIN `" . DB_PREFIX . "product_description` pd
			  ON pd.product_id = p.product_id AND pd.language_id = '" . (int)$language_id . "'
			INNER JOIN `" . DB_PREFIX . "product_to_category` p2c
			  ON p2c.product_id = p.product_id AND p2c.main_category = 1
			LEFT JOIN `" . DB_PREFIX . "manufacturer` m
			  ON m.manufacturer_id = p.manufacturer_id
			WHERE p.status = 1
			  AND p2c.category_id IN (" . $cat_placeholders . ")
			  " . $man_filter . "
			  " . $where_extra
		)->rows;

		if (!$rows) return [];

		// Bulk-load images, attributes and active specials so we don't
		// hit the DB once per product (≈ 1000 products in the Rozetka
		// feed, that would be 3000 extra queries).
		$product_ids = array_map(static fn ($r) => (int)$r['product_id'], $rows);

		$images_by_pid     = $this->loadImages($product_ids);
		$attributes_by_pid = $this->loadAttributes($product_ids, $language_id);
		$specials_by_pid   = $this->loadActiveSpecials($product_ids);

		$offers = [];

		foreach ($rows as $row) {
			$pid = (int)$row['product_id'];

			$image_urls = [];
			if (!empty($row['image'])) {
				$image_urls[] = $this->model_tool_image->resize((string)$row['image'], $image_width, $image_height);
			}
			foreach ($images_by_pid[$pid] ?? [] as $extra) {
				$image_urls[] = $this->model_tool_image->resize((string)$extra, $image_width, $image_height);
			}
			$image_urls = array_values(array_unique(array_filter($image_urls)));

			$price = (float)$row['price'];
			$special_price = $specials_by_pid[$pid] ?? null;
			$has_special = $special_price !== null && $special_price > 0 && $special_price < $price;

			$offers[] = [
				'id' => $pid,
				'url' => $shop_url . '/index.php?route=product/product&product_id=' . $pid,
				'price' => number_format($has_special ? (float)$special_price : $price, 2, '.', ''),
				'oldprice' => $has_special ? number_format($price, 2, '.', '') : null,
				'categoryId' => (int)$row['category_id'],
				'name' => trim((string)$row['name']),
				'description' => $this->cleanDescription((string)$row['description']),
				'model' => trim((string)$row['model']),
				'vendor' => trim((string)$row['vendor']),
				'vendorCode' => trim((string)$row['sku']),
				'image' => $image_urls,
				'attributes' => $attributes_by_pid[$pid] ?? [],
			];
		}

		return $offers;
	}

	/**
	 * @param list<int> $product_ids
	 * @return array<int, list<string>>
	 */
	private function loadImages(array $product_ids): array {
		if (!$product_ids) return [];

		$placeholders = implode(',', $product_ids);

		$rows = $this->db->query(
			"SELECT product_id, image FROM `" . DB_PREFIX . "product_image`
			WHERE product_id IN (" . $placeholders . ")
			ORDER BY sort_order ASC, product_image_id ASC"
		)->rows;

		$out = [];
		foreach ($rows as $r) {
			$out[(int)$r['product_id']][] = (string)$r['image'];
		}

		return $out;
	}

	/**
	 * @param list<int> $product_ids
	 * @return array<int, list<array{name:string,value:string}>>
	 */
	private function loadAttributes(array $product_ids, int $language_id): array {
		if (!$product_ids) return [];

		$placeholders = implode(',', $product_ids);

		$rows = $this->db->query(
			"SELECT pa.product_id, ad.name, pa.text
			FROM `" . DB_PREFIX . "product_attribute` pa
			INNER JOIN `" . DB_PREFIX . "attribute_description` ad
			  ON ad.attribute_id = pa.attribute_id AND ad.language_id = '" . (int)$language_id . "'
			WHERE pa.product_id IN (" . $placeholders . ")
			  AND pa.language_id = '" . (int)$language_id . "'
			  AND pa.text != ''"
		)->rows;

		$out = [];
		foreach ($rows as $r) {
			$out[(int)$r['product_id']][] = [
				'name' => trim((string)$r['name']),
				'value' => trim((string)$r['text']),
			];
		}

		return $out;
	}

	/**
	 * @param list<int> $product_ids
	 * @return array<int, float>
	 */
	private function loadActiveSpecials(array $product_ids): array {
		if (!$product_ids) return [];

		$placeholders = implode(',', $product_ids);
		$now = date('Y-m-d');

		$rows = $this->db->query(
			"SELECT product_id, MIN(price) AS price
			FROM `" . DB_PREFIX . "product_special`
			WHERE product_id IN (" . $placeholders . ")
			  AND (date_start = '0000-00-00' OR date_start IS NULL OR date_start <= '" . $now . "')
			  AND (date_end   = '0000-00-00' OR date_end   IS NULL OR date_end   >= '" . $now . "')
			GROUP BY product_id"
		)->rows;

		$out = [];
		foreach ($rows as $r) {
			$out[(int)$r['product_id']] = (float)$r['price'];
		}

		return $out;
	}

	private function cleanDescription(string $html): string {
		$text = strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		// Collapse whitespace runs (legacy OC2 descriptions had a lot of
		// breaks + nbsp + multiline cruft).
		$text = preg_replace('/\s+/u', ' ', $text);

		return trim((string)$text);
	}

	/**
	 * @param list<array{id:int,parentId:int,name:string}> $categories
	 * @param list<array<string,mixed>>                    $offers
	 */
	private function renderYml(array $categories, array $offers, string $currency, string $shop_url): string {
		$esc = static fn (string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<yml_catalog date="' . date('Y-m-d H:i') . '">' . "\n";
		$xml .= '  <shop>' . "\n";
		$xml .= '    <name>Manline</name>' . "\n";
		$xml .= '    <company>Manline</company>' . "\n";
		$xml .= '    <url>' . $esc($shop_url) . '</url>' . "\n";
		$xml .= '    <currencies>' . "\n";
		$xml .= '      <currency id="' . $esc($currency) . '" rate="1"/>' . "\n";
		$xml .= '    </currencies>' . "\n";

		$xml .= '    <categories>' . "\n";
		$selected_ids = array_flip(array_map(static fn ($c) => (int)$c['id'], $categories));
		foreach ($categories as $cat) {
			// Only include parentId attribute when the parent is itself
			// in the feed — otherwise Rozetka warns about dangling refs.
			$parent_attr = '';
			if ($cat['parentId'] > 0 && isset($selected_ids[$cat['parentId']])) {
				$parent_attr = ' parentId="' . $cat['parentId'] . '"';
			}
			$xml .= '      <category id="' . $cat['id'] . '"' . $parent_attr . '>' . $esc($cat['name']) . '</category>' . "\n";
		}
		$xml .= '    </categories>' . "\n";

		$xml .= '    <offers>' . "\n";
		foreach ($offers as $o) {
			$xml .= '      <offer available="true" id="' . (int)$o['id'] . '">' . "\n";
			$xml .= '        <url>' . $esc((string)$o['url']) . '</url>' . "\n";
			$xml .= '        <price>' . (string)$o['price'] . '</price>' . "\n";
			if (!empty($o['oldprice'])) {
				$xml .= '        <oldprice>' . (string)$o['oldprice'] . '</oldprice>' . "\n";
			}
			$xml .= '        <currencyId>' . $esc($currency) . '</currencyId>' . "\n";
			$xml .= '        <categoryId>' . (int)$o['categoryId'] . '</categoryId>' . "\n";
			$xml .= '        <name>' . $esc((string)$o['name']) . '</name>' . "\n";
			$xml .= '        <description>' . $esc((string)$o['description']) . '</description>' . "\n";
			$xml .= '        <model>' . $esc((string)$o['model']) . '</model>' . "\n";
			$xml .= '        <vendor>' . $esc((string)$o['vendor']) . '</vendor>' . "\n";
			$xml .= '        <vendorCode>' . $esc((string)$o['vendorCode']) . '</vendorCode>' . "\n";
			$xml .= '        <pickup>false</pickup>' . "\n";
			$xml .= '        <delivery>false</delivery>' . "\n";
			$xml .= '        <store>false</store>' . "\n";
			foreach ($o['image'] as $image_url) {
				$xml .= '        <picture>' . $esc((string)$image_url) . '</picture>' . "\n";
			}
			foreach ($o['attributes'] as $attr) {
				$xml .= '        <param name="' . $esc((string)$attr['name']) . '">' . $esc((string)$attr['value']) . '</param>' . "\n";
			}
			$xml .= '      </offer>' . "\n";
		}
		$xml .= '    </offers>' . "\n";

		$xml .= '  </shop>' . "\n";
		$xml .= '</yml_catalog>' . "\n";

		return $xml;
	}
}
