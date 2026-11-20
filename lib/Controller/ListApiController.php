<?php

declare(strict_types=1);

namespace OCA\Groceries\Controller;

use OCA\Groceries\Service\ListAccessService;
use OCA\Groceries\Service\ListMetaService;
use OCA\Groceries\Service\ListService;
use OCA\Groceries\Service\ValidationException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

class ListApiController extends OCSController {
	use ApiHelper;

	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		private ListService $lists,
		private ListMetaService $meta,
		private ListAccessService $access,
	) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		return $this->run(function () {
			$user = $this->userSession->getUser();
			return [
				'user' => ['id' => $this->uid(), 'displayName' => $user?->getDisplayName() ?? $this->uid()],
				'lists' => $this->lists->getLists($this->uid()),
			];
		});
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 30, period: 3600)]
	public function create(string $name = '', string $template = 'grocery', ?string $icon = null, ?string $color = null, ?string $image = null): DataResponse {
		return $this->run(function () use ($name, $template, $icon, $color, $image) {
			$list = $this->lists->create($this->uid(), $name, $template, $icon, $color, $image);
			return $list->jsonSerialize() + ['permission' => ListAccessService::OWNER, 'openCount' => 0, 'shared' => false];
		}, Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 300, period: 3600)]
	public function update(int $listId): DataResponse {
		return $this->run(fn () => $this->lists->update(
			$this->uid(), $listId, $this->only($this->request, ['name', 'icon', 'image', 'color', 'template', 'autoClearHours'])
		)->jsonSerialize());
	}

	#[NoAdminRequired]
	public function destroy(int $listId): DataResponse {
		return $this->run(function () use ($listId) {
			$this->lists->delete($this->uid(), $listId);
			return [];
		});
	}

	#[NoAdminRequired]
	public function members(int $listId): DataResponse {
		return $this->run(fn () => $this->lists->getMembers($this->uid(), $listId));
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 3600)]
	public function setMembers(int $listId, array $members = []): DataResponse {
		return $this->run(fn () => $this->lists->setMembers($this->uid(), $listId, $members));
	}

	#[NoAdminRequired]
	public function leave(int $listId): DataResponse {
		return $this->run(function () use ($listId) {
			$this->lists->leave($this->uid(), $listId);
			return [];
		});
	}

	#[NoAdminRequired]
	public function setPrefs(int $listId): DataResponse {
		return $this->run(function () use ($listId) {
			$this->access->require($this->uid(), $listId);
			$prefs = $this->only($this->request, ['activeStoreId', 'viewMode', 'grouped', 'showFrequent']);
			foreach (['grouped', 'showFrequent'] as $flag) {
				if (array_key_exists($flag, $prefs)) {
					$prefs[$flag] = filter_var($prefs[$flag], FILTER_VALIDATE_BOOLEAN);
				}
			}
			if (array_key_exists('activeStoreId', $prefs)) {
				$prefs['activeStoreId'] = $prefs['activeStoreId'] === null || $prefs['activeStoreId'] === '' ? null : (int)$prefs['activeStoreId'];
			}
			if (array_key_exists('viewMode', $prefs) && !is_string($prefs['viewMode'])) {
				throw new ValidationException('viewMode must be a string');
			}
			return $this->meta->setPrefs($this->uid(), $listId, $prefs);
		});
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 300, period: 3600)]
	public function setOrder(array $ids = []): DataResponse {
		return $this->run(fn () => ['ids' => $this->lists->setListOrder($this->uid(), $ids)]);
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function searchPrincipals(string $search = ''): DataResponse {
		return $this->run(fn () => $this->lists->searchPrincipals($this->uid(), trim($search)));
	}
}
