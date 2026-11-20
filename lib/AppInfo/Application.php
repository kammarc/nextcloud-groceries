<?php

declare(strict_types=1);

namespace OCA\Groceries\AppInfo;

use OCA\Groceries\Capabilities;
use OCA\Groceries\Listener\GroupDeletedListener;
use OCA\Groceries\Listener\UserDeletedListener;
use OCA\Groceries\Public\IShoppingListService;
use OCA\Groceries\Service\ShoppingListService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'groceries';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerCapability(Capabilities::class);
		$context->registerServiceAlias(IShoppingListService::class, ShoppingListService::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
		$context->registerEventListener(GroupDeletedEvent::class, GroupDeletedListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
