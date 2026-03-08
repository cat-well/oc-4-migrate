<?php
namespace Opencart\Catalog\Model\Extension\Manline\Shipping;

class Novaposhta extends \Opencart\System\Engine\Model {
	/**
	 * @param array<string, mixed> $address
	 *
	 * @return array<string, mixed>
	 */
	public function getQuote(array $address): array {
		$this->load->language('extension/manline/shipping/novaposhta');

		// Geo Zone
		$this->load->model('localisation/geo_zone');

		$results = $this->model_localisation_geo_zone->getGeoZone(
			(int)$this->config->get('shipping_novaposhta_geo_zone_id'),
			(int)($address['country_id'] ?? 0),
			(int)($address['zone_id'] ?? 0)
		);

		if (!$this->config->get('shipping_novaposhta_geo_zone_id')) {
			$status = true;
		} elseif ($results) {
			$status = true;
		} else {
			$status = false;
		}

		$method_data = [];

		if (!$status) {
			return $method_data;
		}

		$sub_total = (float)$this->cart->getSubTotal();
		$free_total = (float)$this->config->get('shipping_novaposhta_free_total');
		$is_free = $free_total > 0 && $sub_total >= $free_total;
		$tax_class_id = (int)$this->config->get('shipping_novaposhta_tax_class_id');

		$quote_data = [];

		if ($this->config->get('shipping_novaposhta_branch_status')) {
			$branch_cost = $is_free ? 0.0 : (float)$this->config->get('shipping_novaposhta_branch_cost');

			$quote_data['branch'] = [
				'code'         => 'novaposhta.branch',
				'name'         => $this->language->get('text_branch'),
				'description'  => $this->language->get('text_branch_desc'),
				'cost'         => $branch_cost,
				'tax_class_id' => $tax_class_id,
				'text'         => $this->formatCost($branch_cost, $tax_class_id)
			];
		}

		if ($this->config->get('shipping_novaposhta_courier_status')) {
			$courier_cost = $is_free ? 0.0 : (float)$this->config->get('shipping_novaposhta_courier_cost');

			$quote_data['courier'] = [
				'code'         => 'novaposhta.courier',
				'name'         => $this->language->get('text_courier'),
				'description'  => $this->language->get('text_courier_desc'),
				'cost'         => $courier_cost,
				'tax_class_id' => $tax_class_id,
				'text'         => $this->formatCost($courier_cost, $tax_class_id)
			];
		}

		if ($this->config->get('shipping_novaposhta_locker_status')) {
			$locker_cost = $is_free ? 0.0 : (float)$this->config->get('shipping_novaposhta_locker_cost');

			$quote_data['locker'] = [
				'code'         => 'novaposhta.locker',
				'name'         => $this->language->get('text_locker'),
				'description'  => $this->language->get('text_locker_desc'),
				'cost'         => $locker_cost,
				'tax_class_id' => $tax_class_id,
				'text'         => $this->formatCost($locker_cost, $tax_class_id)
			];
		}

		if (!$quote_data) {
			return $method_data;
		}

		$method_data = [
			'code'       => 'novaposhta',
			'name'       => $this->language->get('heading_title'),
			'quote'      => $quote_data,
			'sort_order' => (int)$this->config->get('shipping_novaposhta_sort_order'),
			'error'      => false
		];

		return $method_data;
	}

	private function formatCost(float $cost, int $tax_class_id): string {
		if ($cost <= 0) {
			return (string)$this->language->get('text_free');
		}

		return $this->currency->format(
			$this->tax->calculate($cost, $tax_class_id, (bool)$this->config->get('config_tax')),
			$this->session->data['currency']
		);
	}
}
