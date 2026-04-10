<?php
namespace Opencart\Catalog\Controller\Startup;
/**
 * Class Customer
 *
 * @package Opencart\Catalog\Controller\Startup
 */
class Customer extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->registry->set('customer', new \Opencart\System\Library\Cart\Customer($this->registry));

		// Customer Group
		if (isset($this->session->data['customer'])) {
			$this->config->set('config_customer_group_id', $this->session->data['customer']['customer_group_id']);
		} elseif ($this->customer->isLogged()) {
			// Logged in customers
			$this->config->set('config_customer_group_id', $this->customer->getGroupId());
		} else {
			// Guest visitors must still have a customer group for pricing rules (discount/special/tax).
			// Some legacy Manline DB dumps may not include config_customer_group_id in settings.
			$cg = (int)$this->config->get('config_customer_group_id');
			if ($cg <= 0) {
				$this->config->set('config_customer_group_id', 1); // Default
			}
		}
	}
}
