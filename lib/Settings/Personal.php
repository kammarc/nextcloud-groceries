<?php

declare(strict_types=1);

namespace OCA\Groceries\Settings;

use OCA\Groceries\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;

/**
 * Explains how to install the app on a phone (link + steps for iOS and Android).
 */
class Personal implements ISettings {
	public function __construct(
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getForm(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'personal', [
			'appUrl' => $this->urlGenerator->linkToRouteAbsolute('groceries.page.index'),
		], '');
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 50;
	}
}
