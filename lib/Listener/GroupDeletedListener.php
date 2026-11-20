<?php

declare(strict_types=1);

namespace OCA\Groceries\Listener;

use OCA\Groceries\Db\Member;
use OCA\Groceries\Db\MemberMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;

/**
 * @template-implements IEventListener<GroupDeletedEvent>
 */
class GroupDeletedListener implements IEventListener {
	public function __construct(
		private MemberMapper $members,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof GroupDeletedEvent) {
			$this->members->deleteByPrincipal(Member::TYPE_GROUP, $event->getGroup()->getGID());
		}
	}
}
