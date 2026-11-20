<?php

declare(strict_types=1);

namespace OCA\Groceries\BackgroundJob;

use OCA\Groceries\Db\ItemMapper;
use OCA\Groceries\Db\ListMapper;
use OCA\Groceries\Service\ItemService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/**
 * Deletes old tombstones (clients that were offline longer get a full resync) and old idempotency keys.
 */
class PurgeJob extends TimedJob {
	public const TOMBSTONE_TTL = 30 * 24 * 3600;
	public const OP_TTL = 7 * 24 * 3600;

	public function __construct(
		ITimeFactory $time,
		private ItemMapper $items,
		private ListMapper $lists,
		private ItemService $itemService,
	) {
		parent::__construct($time);
		$this->setInterval(24 * 3600);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run($argument): void {
		$now = $this->time->getTime();
		foreach ($this->items->purgeTombstones($now - self::TOMBSTONE_TTL) as $listId => $maxRevision) {
			try {
				$list = $this->lists->findById($listId);
			} catch (DoesNotExistException) {
				continue;
			}
			if ($maxRevision > $list->getMinRevision()) {
				$list->setMinRevision($maxRevision);
				$this->lists->update($list);
			}
		}
		$this->itemService->purgeOps($now - self::OP_TTL);
	}
}
