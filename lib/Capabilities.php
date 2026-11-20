<?php

declare(strict_types=1);

namespace OCA\Groceries;

use OCA\Groceries\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\Capabilities\ICapability;

/**
 * Lets other apps (e.g. the Cookbook frontend via @nextcloud/capabilities) detect the integration API.
 */
class Capabilities implements ICapability {
	public function __construct(
		private IAppManager $appManager,
	) {
	}

	/**
	 * @return array{groceries: array{version: string, api: string, features: list<string>, endpoints: array<string,string>}}
	 */
	public function getCapabilities(): array {
		return [
			Application::APP_ID => [
				'version' => $this->appManager->getAppVersion(Application::APP_ID),
				'api' => 'v1',
				'features' => ['lists', 'bulk-add', 'dry-run', 'parse', 'remove-by-source'],
				'endpoints' => [
					'lists' => '/ocs/v2.php/apps/groceries/api/v1/lists',
					'bulk' => '/ocs/v2.php/apps/groceries/api/v1/lists/{listId}/items/bulk',
					'parse' => '/ocs/v2.php/apps/groceries/api/v1/parse',
					'app' => '/apps/groceries/',
				],
			],
		];
	}
}
