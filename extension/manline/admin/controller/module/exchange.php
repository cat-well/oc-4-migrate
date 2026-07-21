<?php
namespace Opencart\Admin\Controller\Extension\Manline\Module;

/**
 * 1C exchange (Manline) — admin control panel: on/off, credentials, endpoint URL
 * and a live tail of the exchange log.
 */
class Exchange extends \Opencart\System\Engine\Controller {
	private const ROUTE = 'extension/manline/module/exchange';
	private const GROUP = 'module_manline_exchange1c';

	public function install(): void {
		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', self::ROUTE);
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', self::ROUTE);
	}

	public function uninstall(): void {
		// no-op; settings are left in place
	}

	public function index(): void {
		$this->load->language(self::ROUTE);

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$setting = $this->model_setting_setting->getSetting(self::GROUP);

		$data['breadcrumbs'] = [
			['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])],
			['text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')],
			['text' => $this->language->get('heading_title'), 'href' => $this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token'])],
		];

		$data['save'] = $this->url->link(self::ROUTE . '.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

		$data['status'] = (int)($setting[self::GROUP . '_status'] ?? 0);
		$data['username'] = (string)($setting[self::GROUP . '_username'] ?? '');
		$data['password'] = (string)($setting[self::GROUP . '_password'] ?? '');

		$store_url = rtrim((string)$this->config->get('config_url'), '/');
		$data['endpoint'] = $store_url . '/export/neoseo_exchange1c.php';

		$data['log'] = $this->tailLog(40);
		$data['captured'] = $this->capturedFiles();

		$data['success'] = $this->flash('success');
		$data['error_warning'] = $this->flash('error_warning');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/manline/module/exchange', $data));
	}

	public function save(): void {
		$this->load->language(self::ROUTE);

		if (!$this->user->hasPermission('modify', self::ROUTE)) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token']));
		}

		$post = $this->request->post;

		$this->load->model('setting/setting');

		// Keep the stored password if the field was left blank.
		$password = trim((string)($post['password'] ?? ''));
		if ($password === '') {
			$existing = $this->model_setting_setting->getSetting(self::GROUP);
			$password = (string)($existing[self::GROUP . '_password'] ?? '');
		}

		$this->model_setting_setting->editSetting(self::GROUP, [
			self::GROUP . '_status'   => !empty($post['status']) ? 1 : 0,
			self::GROUP . '_username' => trim((string)($post['username'] ?? '')),
			self::GROUP . '_password' => $password,
		]);

		$this->session->data['success'] = $this->language->get('text_success');

		$this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token']));
	}

	private function tailLog(int $lines): string {
		$file = DIR_STORAGE . 'logs/manline_1c.log';

		if (!is_file($file)) {
			return '';
		}

		$data = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		if ($data === false) {
			return '';
		}

		return implode("\n", array_slice($data, -$lines));
	}

	/**
	 * @return list<array{name:string,size:int,date:string}>
	 */
	private function capturedFiles(): array {
		$dir = DIR_STORAGE . 'manline1c/';

		if (!is_dir($dir)) {
			return [];
		}

		$out = [];
		foreach (glob($dir . '*') ?: [] as $path) {
			$out[] = [
				'name' => basename($path),
				'size' => (int)filesize($path),
				'date' => date('Y-m-d H:i', (int)filemtime($path)),
			];
		}

		return $out;
	}

	private function flash(string $key): string {
		$value = (string)($this->session->data[$key] ?? '');

		unset($this->session->data[$key]);

		return $value;
	}
}
