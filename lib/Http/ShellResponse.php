<?php

declare(strict_types=1);

namespace OCA\Groceries\Http;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;

/**
 * A complete HTML document rendered without the Nextcloud page chrome (header, core CSS),
 * so the mobile app can use its own native-looking UI.
 *
 * @template-extends Response<Http::STATUS_OK, array<string, mixed>>
 */
class ShellResponse extends Response {
	public function __construct(
		private string $html,
	) {
		parent::__construct();
		$this->addHeader('Content-Type', 'text/html; charset=utf-8');
		$this->addHeader('Cache-Control', 'no-cache');
	}

	public function render(): string {
		return $this->html;
	}
}
