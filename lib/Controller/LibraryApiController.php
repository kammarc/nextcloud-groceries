<?php

declare(strict_types=1);

namespace OCA\Groceries\Controller;

use OCA\Groceries\Service\LibraryService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Backup and restore of the whole library (all lists with items, history, statistics and preferences).
 */
class LibraryApiController extends OCSController {
	use ApiHelper;

	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		private LibraryService $library,
	) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 30, period: 3600)]
	public function export(): DataResponse {
		return $this->run(fn () => $this->library->export($this->uid()));
	}

	/** Body: { library: <export>, mode: "add"|"replace" } */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 10, period: 3600)]
	public function import(array $library = [], string $mode = LibraryService::MODE_ADD): DataResponse {
		return $this->run(fn () => $this->library->import($this->uid(), $library, $mode));
	}
}
