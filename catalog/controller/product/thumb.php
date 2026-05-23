<?php
namespace Opencart\Catalog\Controller\Product;
/**
 * Class Thumb
 *
 * Can be loaded using $this->load->controller('product/thumb', $product_data);
 *
 * @example
 *
 * $product_data = [
 *     'description' => '',
 *     'thumb'       => '',
 *     'price'       => 1.00,
 *     'special'     => 0.00,
 *     'tax'         => 0.00,
 *     'minimum'     => 1,
 *     'href'        => ''
 * ];
 *
 * @package Opencart\Catalog\Controller\Product
 */
class Thumb extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @param array<string, mixed> $data array of data
	 *
	 * @return string
	 */
	public function index(array $data): string {
		$this->load->language('product/thumb');

		$data['cart'] = $this->url->link('common/cart.info', 'language=' . $this->config->get('config_language'));

		$data['cart_add'] = $this->url->link('checkout/cart.add', 'language=' . $this->config->get('config_language'));
		$data['wishlist_add'] = $this->url->link('account/wishlist.add', 'language=' . $this->config->get('config_language'));
		$data['compare_add'] = $this->url->link('product/compare.add', 'language=' . $this->config->get('config_language'));

		$data['review_status'] = (int)$this->config->get('config_review_status');

		// Manline thumb.twig uses `{{ is_ua ? 'UA' : 'RU' }}` ternaries
		// for the "Купити", "Є в наявності", "Код товару", "Ціна",
		// "Розміри в наявності" labels. thumb is rendered as an
		// independently-loaded sub-controller from category / search /
		// special / manufacturer / home / related — none of those
		// pass through their own language flag.
		//
		// CAVEAT: thumb.twig line 2 RE-COMPUTES is_ua from a `lang_code`
		// variable: `{% set is_ua = (lang_code is defined) and (lang_code
		// in ['uk-ua','ua']) %}`. That line OVERWRITES whatever is_ua we
		// might pass, so setting is_ua directly silently has no effect.
		// We have to pass `lang_code` (the full config_language value)
		// for the template's own set-statement to flip is_ua correctly.
		$data['lang_code'] = (string)$this->config->get('config_language');

		return $this->load->view('product/thumb', $data);
	}
}
