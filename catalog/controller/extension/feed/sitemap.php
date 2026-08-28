<?php
namespace Opencart\Catalog\Controller\Extension\Feed;

/**
 * XML Sitemap generator.
 *
 * Routes:
 * - index.php?route=extension/feed/sitemap
 * - /sitemap.xml (via rewrite to route=extension/feed/sitemap)
 *
 * Query params:
 * - language=<code> (optional) - builds links for that language.
 * - type=index|category|information|manufacturer|product (default: index)
 * - page=<int> (for type=product, 1-indexed)
 */
class Sitemap extends \Opencart\System\Engine\Controller {
	private int $productPageLimit = 40000; // keep < 50k URLs per sitemap

	public function index(): void {
		$type = $this->request->get['type'] ?? 'index';

		switch ($type) {
			case 'category':
				$this->outputUrlset($this->getCategoryUrls());
				return;
			case 'information':
				$this->outputUrlset($this->getInformationUrls());
				return;
			case 'manufacturer':
				$this->outputUrlset($this->getManufacturerUrls());
				return;
			case 'product':
				$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
				if ($page < 1) {
					$page = 1;
				}

				$this->outputUrlset($this->getProductUrls($page));
				return;
			case 'index':
			default:
				$this->outputSitemapIndex($this->getIndexSitemaps());
				return;
		}
	}

	/**
	 * @return array<int, array{loc:string, lastmod?:string, changefreq?:string, priority?:string}>
	 */
	private function getIndexSitemaps(): array {
		$this->load->model('localisation/language');

		$languages = $this->model_localisation_language->getLanguages();
		// If languages table is empty/unexpected, still provide a fallback (no language param)
		if (!$languages) {
			$languages = ['' => ['code' => '']];
		}

		$sitemaps = [];

		foreach ($languages as $code => $language) {
			$langQuery = $code ? 'language=' . $code : '';

			$sitemaps[] = [
				'loc' => $this->url->link('extension/feed/sitemap', $this->joinQuery($langQuery, 'type=category')),
				'changefreq' => 'daily'
			];
			$sitemaps[] = [
				'loc' => $this->url->link('extension/feed/sitemap', $this->joinQuery($langQuery, 'type=information')),
				'changefreq' => 'weekly'
			];
			$sitemaps[] = [
				'loc' => $this->url->link('extension/feed/sitemap', $this->joinQuery($langQuery, 'type=manufacturer')),
				'changefreq' => 'weekly'
			];

			$totalProducts = $this->getTotalProducts();
			$pages = (int)ceil($totalProducts / $this->productPageLimit);
			if ($pages < 1) {
				$pages = 1;
			}

			for ($page = 1; $page <= $pages; $page++) {
				$sitemaps[] = [
					'loc' => $this->url->link('extension/feed/sitemap', $this->joinQuery($langQuery, 'type=product', 'page=' . $page)),
					'changefreq' => 'daily'
				];
			}
		}

		return $sitemaps;
	}

	private function getTotalProducts(): int {
		$query = $this->db->query(
			"SELECT COUNT(DISTINCT p.product_id) AS total\n" .
			"FROM `" . DB_PREFIX . "product_to_store` p2s\n" .
			"JOIN `" . DB_PREFIX . "product` p ON (p.product_id = p2s.product_id)\n" .
			"WHERE p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'\n" .
			"  AND p.status = '1'\n" .
			"  AND p.date_available <= NOW()"
		);

		return (int)($query->row['total'] ?? 0);
	}

	/**
	 * @return array<int, array{loc:string, lastmod?:string, changefreq?:string, priority?:string}>
	 */
	private function getCategoryUrls(): array {
		$this->load->model('catalog/category');

		$urls = [];
		$categories_1 = $this->model_catalog_category->getCategories(0);

		foreach ($categories_1 as $c1) {
			$urls[] = ['loc' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . (int)$c1['category_id']), 'changefreq' => 'weekly', 'priority' => '0.7'];

			$categories_2 = $this->model_catalog_category->getCategories((int)$c1['category_id']);
			foreach ($categories_2 as $c2) {
				$urls[] = ['loc' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . (int)$c1['category_id'] . '_' . (int)$c2['category_id']), 'changefreq' => 'weekly', 'priority' => '0.6'];

				$categories_3 = $this->model_catalog_category->getCategories((int)$c2['category_id']);
				foreach ($categories_3 as $c3) {
					$urls[] = ['loc' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . (int)$c1['category_id'] . '_' . (int)$c2['category_id'] . '_' . (int)$c3['category_id']), 'changefreq' => 'weekly', 'priority' => '0.5'];
				}
			}
		}

		return $urls;
	}

	/**
	 * @return array<int, array{loc:string, lastmod?:string, changefreq?:string, priority?:string}>
	 */
	private function getInformationUrls(): array {
		$this->load->model('catalog/information');

		$urls = [];
		foreach ($this->model_catalog_information->getInformations() as $info) {
			$urls[] = ['loc' => $this->url->link('information/information', 'language=' . $this->config->get('config_language') . '&information_id=' . (int)$info['information_id']), 'changefreq' => 'monthly', 'priority' => '0.3'];
		}

		return $urls;
	}

	/**
	 * @return array<int, array{loc:string, lastmod?:string, changefreq?:string, priority?:string}>
	 */
	private function getManufacturerUrls(): array {
		$this->load->model('catalog/manufacturer');

		$urls = [];
		foreach ($this->model_catalog_manufacturer->getManufacturers() as $m) {
			$urls[] = ['loc' => $this->url->link('product/manufacturer.info', 'language=' . $this->config->get('config_language') . '&manufacturer_id=' . (int)$m['manufacturer_id']), 'changefreq' => 'weekly', 'priority' => '0.4'];
		}

		return $urls;
	}

	/**
	 * @return array<int, array{loc:string, lastmod?:string, changefreq?:string, priority?:string}>
	 */
	private function getProductUrls(int $page): array {
		$start = ($page - 1) * $this->productPageLimit;

		$query = $this->db->query(
			"SELECT DISTINCT p.product_id, p.date_modified\n" .
			"FROM `" . DB_PREFIX . "product_to_store` p2s\n" .
			"JOIN `" . DB_PREFIX . "product` p ON (p.product_id = p2s.product_id)\n" .
			"WHERE p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'\n" .
			"  AND p.status = '1'\n" .
			"  AND p.date_available <= NOW()\n" .
			"ORDER BY p.product_id\n" .
			"LIMIT " . (int)$start . ", " . (int)$this->productPageLimit
		);

		$urls = [];

		foreach ($query->rows as $row) {
			$lastmod = '';
			if (!empty($row['date_modified']) && $row['date_modified'] !== '0000-00-00 00:00:00') {
				// W3C Datetime (date only is acceptable)
				$lastmod = substr($row['date_modified'], 0, 10);
			}

			$entry = [
				'loc' => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . (int)$row['product_id']),
				'changefreq' => 'weekly',
				'priority' => '0.8'
			];

			if ($lastmod) {
				$entry['lastmod'] = $lastmod;
			}

			$urls[] = $entry;
		}

		return $urls;
	}

	/**
	 * @param array<int, array{loc:string, lastmod?:string, changefreq?:string, priority?:string}> $items
	 */
	private function outputUrlset(array $items): void {
		$this->response->addHeader('Content-Type: application/xml; charset=UTF-8');

		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ($items as $item) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . $this->xmlEscape($item['loc']) . "</loc>\n";
			if (!empty($item['lastmod'])) {
				$xml .= "\t\t<lastmod>" . $this->xmlEscape($item['lastmod']) . "</lastmod>\n";
			}
			if (!empty($item['changefreq'])) {
				$xml .= "\t\t<changefreq>" . $this->xmlEscape($item['changefreq']) . "</changefreq>\n";
			}
			if (!empty($item['priority'])) {
				$xml .= "\t\t<priority>" . $this->xmlEscape($item['priority']) . "</priority>\n";
			}
			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>';

		$this->response->setOutput($xml);
	}

	/**
	 * @param array<int, array{loc:string, lastmod?:string, changefreq?:string}> $items
	 */
	private function outputSitemapIndex(array $items): void {
		$this->response->addHeader('Content-Type: application/xml; charset=UTF-8');

		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ($items as $item) {
			$xml .= "\t<sitemap>\n";
			$xml .= "\t\t<loc>" . $this->xmlEscape($item['loc']) . "</loc>\n";
			if (!empty($item['lastmod'])) {
				$xml .= "\t\t<lastmod>" . $this->xmlEscape($item['lastmod']) . "</lastmod>\n";
			}
			$xml .= "\t</sitemap>\n";
		}

		$xml .= '</sitemapindex>';

		$this->response->setOutput($xml);
	}

	private function xmlEscape(string $value): string {
		// url->link() already returns "&amp;"; decode first to avoid double-encoding to "&amp;amp;".
		return htmlspecialchars(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), ENT_XML1 | ENT_COMPAT, 'UTF-8');
	}

	private function joinQuery(string ...$parts): string {
		$parts = array_filter($parts, fn($p) => (string)$p !== '');
		return implode('&', $parts);
	}
}