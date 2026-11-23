<?php

declare(strict_types=1);

namespace OCA\Groceries\Controller;

use OCA\Groceries\Public\IShoppingListService;
use OCA\Groceries\Service\CatalogService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

class CatalogApiController extends OCSController {
	use ApiHelper;

	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		private CatalogService $catalog,
		private IShoppingListService $shoppingLists,
	) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
	}

	#[NoAdminRequired]
	public function catalog(): DataResponse {
		$response = new DataResponse($this->catalog->getData());
		$response->setETag($this->catalog->getVersion());
		$response->cacheFor(3600);
		return $response;
	}

	/**
	 * Parses ingredient lines ("200 g Mehl") into quantity/name/note + category, without writing anything.
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function parse(array $lines = [], string $lang = 'en'): DataResponse {
		return $this->run(fn () => $this->shoppingLists->parseLines(
			array_map(fn ($l) => is_scalar($l) ? (string)$l : '', array_slice(array_values($lines), 0, \OCA\Groceries\Service\ItemService::MAX_BULK)),
			preg_match('/^[a-z]{2}$/', $lang) === 1 ? $lang : 'en',
		));
	}
}
