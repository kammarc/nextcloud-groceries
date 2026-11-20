<?php

declare(strict_types=1);

namespace OCA\Groceries\Controller;

use OCA\Groceries\Service\EventService;
use OCA\Groceries\Service\ItemService;
use OCA\Groceries\Service\ValidationException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

class ItemApiController extends OCSController {
	use ApiHelper;

	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		private ItemService $items,
		private EventService $events,
	) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
	}

	/**
	 * Delta sync: everything that changed after revision $since (0 = full snapshot).
	 * Polled every few seconds by open clients, so it is kept cheap.
	 */
	#[NoAdminRequired]
	public function changes(int $listId, int $since = 0): DataResponse {
		return $this->run(fn () => $this->items->changes($this->uid(), $listId, $since));
	}

	#[NoAdminRequired]
	public function create(int $listId, ?string $opId = null): DataResponse {
		return $this->run(fn () => $this->items->create(
			$this->uid(),
			$listId,
			$this->only($this->request, ['name', 'quantity', 'note', 'category', 'icon', 'source', 'sourceRef', 'sourceLabel']),
			$opId,
		), Http::STATUS_CREATED);
	}

	/**
	 * Integration endpoint (Cookbook etc.). Body:
	 * { items: [{line, name?, quantity?, note?, enabled?, category?}], mode: "merge"|"separate",
	 *   source: "cookbook", sourceRef: "<recipe id>", sourceLabel|recipeName: "Lasagne", dryRun: bool, skipStaples: bool }
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function bulk(int $listId, array $items = [], string $mode = 'merge', ?string $source = null, ?string $sourceRef = null,
		?string $sourceLabel = null, ?string $recipeName = null, bool $dryRun = false, bool $skipStaples = true): DataResponse {
		return $this->run(function () use ($listId, $items, $mode, $source, $sourceRef, $sourceLabel, $recipeName, $dryRun, $skipStaples) {
			foreach ($items as $i) {
				if (!is_array($i)) {
					throw new ValidationException('items must be a list of objects');
				}
			}
			return $this->items->bulk($this->uid(), $listId, array_values($items), [
				'mode' => $mode,
				'source' => $source ?? 'api',
				'sourceRef' => $sourceRef,
				'sourceLabel' => $sourceLabel ?? $recipeName,
				'dryRun' => $dryRun,
				'skipStaples' => $skipStaples,
			]);
		});
	}

	#[NoAdminRequired]
	public function update(int $listId, int $itemId): DataResponse {
		return $this->run(fn () => $this->items->update(
			$this->uid(), $listId, $itemId,
			$this->only($this->request, ['name', 'quantity', 'note', 'category', 'icon', 'checked'])
		));
	}

	#[NoAdminRequired]
	public function destroy(int $listId, int $itemId): DataResponse {
		return $this->run(fn () => ['revision' => $this->items->delete($this->uid(), $listId, $itemId)]);
	}

	#[NoAdminRequired]
	public function checkout(int $listId): DataResponse {
		return $this->run(fn () => $this->items->checkout($this->uid(), $listId));
	}

	#[NoAdminRequired]
	public function removeSource(int $listId, string $source, string $sourceRef): DataResponse {
		return $this->run(fn () => $this->items->removeSource($this->uid(), $listId, $source, $sourceRef));
	}

	/** Purchase log for statistics / export (unix timestamps, [from, to)). */
	#[NoAdminRequired]
	public function events(int $listId, ?int $from = null, ?int $to = null): DataResponse {
		return $this->run(fn () => $this->events->getEvents($this->uid(), $listId, $from, $to));
	}

	/** Merges an exported purchase log into this list (duplicates are skipped). */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 10, period: 3600)]
	public function importEvents(int $listId, array $events = []): DataResponse {
		return $this->run(fn () => $this->events->import($this->uid(), $listId, array_values($events)));
	}

	#[NoAdminRequired]
	public function history(int $listId): DataResponse {
		return $this->run(fn () => $this->items->getHistory($this->uid(), $listId));
	}

	#[NoAdminRequired]
	public function forget(int $listId, int $historyId): DataResponse {
		return $this->run(function () use ($listId, $historyId) {
			$this->items->forget($this->uid(), $listId, $historyId);
			return [];
		});
	}
}
