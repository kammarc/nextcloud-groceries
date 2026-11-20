<?php

declare(strict_types=1);

namespace OCA\Groceries\Listener;

use OCA\Groceries\Db\ListMapper;
use OCA\Groceries\Db\Member;
use OCA\Groceries\Db\MemberMapper;
use OCA\Groceries\Service\ListMetaService;
use OCA\Groceries\Service\ListService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;

/**
 * When a user is deleted, their shared lists go to another member with edit rights
 * so the family keeps its list; unshared lists are deleted.
 *
 * @template-implements IEventListener<UserDeletedEvent>
 */
class UserDeletedListener implements IEventListener {
	public function __construct(
		private ListMapper $lists,
		private MemberMapper $members,
		private ListService $listService,
		private ListMetaService $meta,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof UserDeletedEvent) {
			return;
		}
		$uid = $event->getUser()->getUID();
		$this->members->deleteByPrincipal(Member::TYPE_USER, $uid);
		$this->meta->deletePrefsForUser($uid);

		foreach ($this->lists->findByOwner($uid) as $list) {
			$heir = null;
			foreach ($this->members->findByList($list->getId()) as $m) {
				if ($m->getPrincipalType() === Member::TYPE_USER && $m->getPermission() === Member::PERM_EDIT) {
					$heir = $m;
					break;
				}
			}
			if ($heir === null) {
				$this->listService->purgeList($list);
				continue;
			}
			$list->setOwnerUid($heir->getPrincipalId());
			$this->lists->update($list);
			$this->members->delete($heir);
			$this->lists->bumpRevision($list->getId(), true);
		}
	}
}
