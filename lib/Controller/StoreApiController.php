<?php

declare(strict_types=1);

namespace OCA\Groceries\Controller;

use OCA\Groceries\Service\ListService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

class StoreApiController extends OCSController {
	use ApiHelper;

	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		private ListService $lists,
	) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 3600)]
	public function create(int $listId, string $name = '', ?string $icon = null, ?array $order = null, ?string $image = null): DataResponse {
		return $this->run(fn () => $this->lists->createStore($this->uid(), $listId, $name, $icon, $order, $image)->jsonSerialize(), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 300, period: 3600)]
	public function update(int $listId, int $storeId): DataResponse {
		return $this->run(fn () => $this->lists->updateStore(
			$this->uid(), $listId, $storeId, $this->only($this->request, ['name', 'icon', 'order', 'image'])
		)->jsonSerialize());
	}

	#[NoAdminRequired]
	public function destroy(int $listId, int $storeId): DataResponse {
		return $this->run(function () use ($listId, $storeId) {
			$this->lists->deleteStore($this->uid(), $listId, $storeId);
			return [];
		});
	}

	#[NoAdminRequired]
	public function setOrder(int $listId, int $storeId, array $order = []): DataResponse {
		return $this->run(fn () => $this->lists->updateStore($this->uid(), $listId, $storeId, ['order' => $order])->jsonSerialize());
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 3600)]
	public function createCategory(int $listId, string $name = '', ?string $icon = null): DataResponse {
		return $this->run(fn () => $this->lists->createCategory($this->uid(), $listId, $name, $icon), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	public function deleteCategory(int $listId, string $categoryKey): DataResponse {
		return $this->run(function () use ($listId, $categoryKey) {
			$this->lists->deleteCategory($this->uid(), $listId, $categoryKey);
			return [];
		});
	}
}
