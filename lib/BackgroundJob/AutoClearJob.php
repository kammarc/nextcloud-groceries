<?php

declare(strict_types=1);

namespace OCA\Groceries\BackgroundJob;

use OCA\Groceries\Db\ListMapper;
use OCA\Groceries\Service\ItemService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Removes ticked items some hours after shopping (per-list setting, 0 = never).
 */
class AutoClearJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private ListMapper $lists,
		private ItemService $items,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(15 * 60);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run($argument): void {
		foreach ($this->lists->findWithAutoClear() as $list) {
			try {
				$this->items->autoClear($list);
			} catch (\Throwable $e) {
				$this->logger->warning('Auto-clear failed for list ' . $list->getId(), ['exception' => $e]);
			}
		}
	}
}
