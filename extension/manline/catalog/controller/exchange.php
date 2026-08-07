<?php
namespace Opencart\Catalog\Controller\Extension\Manline;

/**
 * 1C exchange endpoint (Manline) — CommerceML 2 protocol.
 *
 * Skeleton stage: authentication (checkauth) + session init are fully working;
 * file chunks are captured to storage; import/order modes are logged and
 * acknowledged but not processed yet. Reached via the compat shim
 * export/neoseo_exchange1c.php (kept only so 1C's configured URL still resolves).
 */
class Exchange extends \Opencart\System\Engine\Controller {
	private const COOKIE = 'MANLINE1C';
	private const FILE_LIMIT = 52428800; // 50 MB per chunk

	public function index(): void {
		$log = new \Opencart\System\Library\Log('manline_1c.log');

		$type = (string)($this->request->get['type'] ?? '');
		$mode = (string)($this->request->get['mode'] ?? '');
		$filename = isset($this->request->get['filename']) ? basename((string)$this->request->get['filename']) : '';

		$log->write('type=' . $type . '&mode=' . $mode . ($filename ? '&filename=' . $filename : '') . ' from ' . ($this->request->server['REMOTE_ADDR'] ?? '?'));

		$this->response->addHeader('Content-Type: text/plain; charset=utf-8');
		// Set-Cookie makes the host's Cloudflare treat this endpoint as dynamic and skip caching (a cached checkauth broke 1C auth)
		$this->response->addHeader('Set-Cookie: mln1c=1; Path=/; HttpOnly; SameSite=Lax');

		if ($this->setting('module_manline_exchange1c_status') !== '1') {
			// Still record the knock (with auth verdict) so you can confirm the
			// real 1C is reaching us — and that its credentials match — without
			// turning the exchange on.
			$log->write('exchange DISABLED — ignored ' . $type . '/' . $mode . ' [' . $this->authNote() . ']');
			$this->response->setOutput("failure\nОбмен отключён в админке магазина");
			return;
		}

		if ($mode === 'checkauth') {
			$this->modeCheckauth($log);
			return;
		}

		if (!$this->authed()) {
			$log->write('rejected: auth failed on mode=' . $mode);
			$this->response->setOutput("failure\nАвторизация не пройдена");
			return;
		}

		switch ($type . '/' . $mode) {
			case 'catalog/init':
			case 'sale/init':
				$this->modeInit();
				break;

			case 'catalog/file':
			case 'sale/file':
				$this->modeFile($filename, $log);
				break;

			case 'catalog/import':
			case 'sale/import':
				// Skeleton: capture only, real processing lands in a later phase.
				$log->write('import received (' . $filename . ') — not processed yet (skeleton)');
				$this->response->setOutput('success');
				break;

			case 'sale/query':
				// Skeleton: no orders exported yet — return an empty CommerceML doc.
				$log->write('query — empty response (skeleton)');
				$this->response->addHeader('Content-Type: application/xml; charset=utf-8');
				$this->response->setOutput('<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<КоммерческаяИнформация ВерсияСхемы="2.07" xmlns="urn:1C.ru:commerceml_2"/>');
				break;

			case 'sale/success':
			case 'catalog/export':
			case 'sale/status':
				$log->write($mode . ' — acknowledged (skeleton)');
				$this->response->setOutput('success');
				break;

			default:
				$log->write('unknown mode: ' . $type . '/' . $mode);
				$this->response->setOutput("failure\nНеизвестный режим");
		}
	}

	private function modeCheckauth(\Opencart\System\Library\Log $log): void {
		[$user, $pass] = $this->basicAuth();

		if ($user !== '' && $user === $this->setting('module_manline_exchange1c_username') && $pass === $this->setting('module_manline_exchange1c_password')) {
			$log->write('checkauth OK for user ' . $user);
			$this->response->setOutput("success\n" . self::COOKIE . "\n" . $this->token());
		} else {
			$log->write('checkauth FAILED for user ' . $user);
			$this->response->setOutput("failure\nНеверный логин или пароль");
		}
	}

	private function modeInit(): void {
		$this->response->setOutput("zip=no\nfile_limit=" . self::FILE_LIMIT);
	}

	private function modeFile(string $filename, \Opencart\System\Library\Log $log): void {
		if ($filename === '') {
			$this->response->setOutput("failure\nНе указано имя файла");
			return;
		}

		$dir = DIR_STORAGE . 'manline1c/';

		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		// 1C sends chunks; append so multi-part files reassemble correctly.
		$body = file_get_contents('php://input');
		file_put_contents($dir . $filename, $body, FILE_APPEND);

		$log->write('file saved: ' . $filename . ' (+' . strlen((string)$body) . ' bytes)');
		$this->response->setOutput('success');
	}

	/**
	 * Human-readable auth verdict for the log — lets you tell the real 1C
	 * (correct credentials) apart from random bots hitting the URL.
	 */
	private function authNote(): string {
		[$user, $pass] = $this->basicAuth();

		if ($user === '') {
			return 'no credentials';
		}

		if ($user === $this->setting('module_manline_exchange1c_username') && $pass === $this->setting('module_manline_exchange1c_password')) {
			return 'credentials OK — this is the real 1C';
		}

		return 'WRONG credentials (user=' . $user . ')';
	}

	private function authed(): bool {
		[$user, $pass] = $this->basicAuth();

		if ($user !== '' && $user === $this->setting('module_manline_exchange1c_username') && $pass === $this->setting('module_manline_exchange1c_password')) {
			return true;
		}

		$cookie = (string)($this->request->cookie[self::COOKIE] ?? ($_COOKIE[self::COOKIE] ?? ''));

		return $cookie !== '' && hash_equals($this->token(), $cookie);
	}

	/**
	 * Stateless session token derived from the credentials — no session store
	 * needed (catalog runs with session_autostart=false).
	 */
	private function token(): string {
		return substr(hash('sha256', $this->setting('module_manline_exchange1c_username') . ':' . $this->setting('module_manline_exchange1c_password') . ':manline1c'), 0, 32);
	}

	/**
	 * @return array{0:string,1:string} [user, pass]
	 */
	private function basicAuth(): array {
		$user = (string)($this->request->server['PHP_AUTH_USER'] ?? '');
		$pass = (string)($this->request->server['PHP_AUTH_PW'] ?? '');

		if ($user === '') {
			$header = (string)($this->request->server['HTTP_AUTHORIZATION'] ?? $this->request->server['REDIRECT_HTTP_AUTHORIZATION'] ?? '');

			if (stripos($header, 'Basic ') === 0) {
				$decoded = base64_decode(substr($header, 6));
				if ($decoded !== false && str_contains($decoded, ':')) {
					[$user, $pass] = explode(':', $decoded, 2);
				}
			}
		}

		return [$user, $pass];
	}

	private function setting(string $key): string {
		$query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `key` = '" . $this->db->escape($key) . "' LIMIT 1");

		return $query->num_rows ? (string)$query->row['value'] : '';
	}
}
