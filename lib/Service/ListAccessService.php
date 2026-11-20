<?php

declare(strict_types=1);

namespace OCA\Groceries\Service;

use OCA\Groceries\Db\ListMapper;
use OCA\Groceries\Db\Member;
use OCA\Groceries\Db\MemberMapper;
use OCA\Groceries\Db\ShoppingList;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use OCP\IUserManager;

/**
 * Single place where list permissions are decided.
 */
class ListAccessService {
	public const OWNER = 'owner';
	public const EDIT = 'edit';
	public const VIEW = 'view';

	private const RANK = [self::VIEW => 1, self::EDIT => 2, self::OWNER => 3];

	/** @var array<string, string[]> */
	private array $groupCache = [];

	public function __construct(
		private ListMapper $lists,
		private MemberMapper $members,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
	) {
	}

	/** @return string[] */
	public function getGroupIds(string $uid): array {
		if (!isset($this->groupCache[$uid])) {
			$user = $this->userManager->get($uid);
			$this->groupCache[$uid] = $user === null ? [] : $this->groupManager->getUserGroupIds($user);
		}
		return $this->groupCache[$uid];
	}

	public function getPermission(string $uid, ShoppingList $list): ?string {
		if ($list->getOwnerUid() === $uid) {
			return self::OWNER;
		}
		$groups = $this->getGroupIds($uid);
		$best = null;
		foreach ($this->members->findByList($list->getId()) as $m) {
			$applies = ($m->getPrincipalType() === Member::TYPE_USER && $m->getPrincipalId() === $uid)
				|| ($m->getPrincipalType() === Member::TYPE_GROUP && in_array($m->getPrincipalId(), $groups, true));
			if (!$applies) {
				continue;
			}
			$perm = $m->getPermission() === Member::PERM_EDIT ? self::EDIT : self::VIEW;
			if ($best === null || self::RANK[$perm] > self::RANK[$best]) {
				$best = $perm;
			}
		}
		return $best;
	}

	/**
	 * @return array{0: ShoppingList, 1: string} list and effective permission
	 * @throws NotFoundException when the list does not exist or is not visible to the user
	 * @throws ForbiddenException when the permission is insufficient
	 */
	public function require(string $uid, int $listId, string $needed = self::VIEW): array {
		try {
			$list = $this->lists->findById($listId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('List not found');
		}
		$perm = $this->getPermission($uid, $list);
		if ($perm === null) {
			throw new NotFoundException('List not found');
		}
		if (self::RANK[$perm] < self::RANK[$needed]) {
			throw new ForbiddenException('Insufficient permissions for this list');
		}
		return [$list, $perm];
	}
}
