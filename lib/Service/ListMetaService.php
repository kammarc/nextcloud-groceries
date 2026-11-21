<?php

declare(strict_types=1);

namespace OCA\Groceries\Service;

use OCA\Groceries\Db\Member;
use OCA\Groceries\Db\MemberMapper;
use OCA\Groceries\Db\ShoppingList;
use OCA\Groceries\Db\StoreMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;

/**
 * Per-list metadata that is not worth a full entity: category/icon overrides,
 * custom categories and per-user preferences. Also assembles the "meta" block of sync responses.
 */
class ListMetaService {
	public function __construct(
		private IDBConnection $db,
		private StoreMapper $stores,
		private MemberMapper $members,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
	) {
	}

	/** @return array{category: ?string, icon: ?string}|null */
	public function getOverride(int $listId, string $normName): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('category_key', 'icon')->from('grocery_overrides')
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('norm_name', $qb->createNamedParameter($normName)));
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return $row === false ? null : ['category' => $row['category_key'], 'icon' => $row['icon']];
	}

	public function setOverride(int $listId, string $normName, ?string $category, ?string $icon): void {
		$existing = $this->getOverride($listId, $normName);
		if ($existing === null) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert('grocery_overrides')->values([
				'list_id' => $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT),
				'norm_name' => $qb->createNamedParameter($normName),
				'category_key' => $qb->createNamedParameter($category),
				'icon' => $qb->createNamedParameter($icon),
			]);
			$qb->executeStatement();
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->update('grocery_overrides')
			->set('category_key', $qb->createNamedParameter($category ?? $existing['category']))
			->set('icon', $qb->createNamedParameter($icon ?? $existing['icon']))
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('norm_name', $qb->createNamedParameter($normName)));
		$qb->executeStatement();
	}

	/** @return array<string, array{category: ?string, icon: ?string}> */
	public function getOverrides(int $listId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('norm_name', 'category_key', 'icon')->from('grocery_overrides')
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$out = [];
		while ($row = $result->fetch()) {
			$out[$row['norm_name']] = ['category' => $row['category_key'], 'icon' => $row['icon']];
		}
		$result->closeCursor();
		return $out;
	}

	/** @return list<array{key: string, name: string, icon: ?string}> */
	public function getCustomCategories(int $listId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('category_key', 'name', 'icon')->from('grocery_categories')
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
			->orderBy('id');
		$result = $qb->executeQuery();
		$out = [];
		while ($row = $result->fetch()) {
			$out[] = ['key' => $row['category_key'], 'name' => $row['name'], 'icon' => $row['icon']];
		}
		$result->closeCursor();
		return $out;
	}

	public function customCategoryExists(int $listId, string $key): bool {
		foreach ($this->getCustomCategories($listId) as $c) {
			if ($c['key'] === $key) {
				return true;
			}
		}
		return false;
	}

	public function addCustomCategory(int $listId, string $name, ?string $icon): string {
		$key = 'custom_' . bin2hex(random_bytes(5));
		$qb = $this->db->getQueryBuilder();
		$qb->insert('grocery_categories')->values([
			'list_id' => $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT),
			'category_key' => $qb->createNamedParameter($key),
			'name' => $qb->createNamedParameter($name),
			'icon' => $qb->createNamedParameter($icon),
		]);
		$qb->executeStatement();
		return $key;
	}

	public function deleteCustomCategory(int $listId, string $key): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('grocery_categories')
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('category_key', $qb->createNamedParameter($key)));
		$qb->executeStatement();
	}

	/** @return array{activeStoreId: ?int, viewMode: string, grouped: bool, showFrequent: bool, showAddedBy: bool} */
	public function getPrefs(string $uid, int $listId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('active_store_id', 'view_mode', 'grouped', 'show_frequent', 'show_added_by')->from('grocery_prefs')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		if ($row === false) {
			return ['activeStoreId' => null, 'viewMode' => 'list', 'grouped' => true, 'showFrequent' => true, 'showAddedBy' => false];
		}
		return [
			'activeStoreId' => $row['active_store_id'] === null ? null : (int)$row['active_store_id'],
			'viewMode' => (string)$row['view_mode'],
			'grouped' => (int)$row['grouped'] !== 0,
			'showFrequent' => (int)$row['show_frequent'] !== 0,
			'showAddedBy' => (int)$row['show_added_by'] !== 0,
		];
	}

	/**
	 * @param array{activeStoreId?: ?int, viewMode?: string, grouped?: bool, showFrequent?: bool, showAddedBy?: bool} $prefs
	 */
	public function setPrefs(string $uid, int $listId, array $prefs): array {
		$current = $this->getPrefs($uid, $listId);
		$exists = $this->prefsExist($uid, $listId);
		$merged = array_merge($current, array_intersect_key($prefs, $current));
		if (!in_array($merged['viewMode'], ['list', 'grid'], true)) {
			throw new ValidationException('viewMode must be list or grid');
		}
		$storeType = $merged['activeStoreId'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT;
		$qb = $this->db->getQueryBuilder();
		if ($exists) {
			$qb->update('grocery_prefs')
				->set('active_store_id', $qb->createNamedParameter($merged['activeStoreId'], $storeType))
				->set('view_mode', $qb->createNamedParameter($merged['viewMode']))
				->set('grouped', $qb->createNamedParameter($merged['grouped'] ? 1 : 0, IQueryBuilder::PARAM_INT))
				->set('show_frequent', $qb->createNamedParameter($merged['showFrequent'] ? 1 : 0, IQueryBuilder::PARAM_INT))
				->set('show_added_by', $qb->createNamedParameter($merged['showAddedBy'] ? 1 : 0, IQueryBuilder::PARAM_INT))
				->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
				->andWhere($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		} else {
			$qb->insert('grocery_prefs')->values([
				'uid' => $qb->createNamedParameter($uid),
				'list_id' => $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT),
				'active_store_id' => $qb->createNamedParameter($merged['activeStoreId'], $storeType),
				'view_mode' => $qb->createNamedParameter($merged['viewMode']),
				'grouped' => $qb->createNamedParameter($merged['grouped'] ? 1 : 0, IQueryBuilder::PARAM_INT),
				'show_frequent' => $qb->createNamedParameter($merged['showFrequent'] ? 1 : 0, IQueryBuilder::PARAM_INT),
				'show_added_by' => $qb->createNamedParameter($merged['showAddedBy'] ? 1 : 0, IQueryBuilder::PARAM_INT),
			]);
		}
		$qb->executeStatement();
		return $merged;
	}

	private function prefsExist(string $uid, int $listId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')->from('grocery_prefs')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$exists = $result->fetchOne() !== false;
		$result->closeCursor();
		return $exists;
	}

	/**
	 * Display names of everybody who can see the list (for "added by …").
	 *
	 * @return array<string,string>
	 */
	public function getPeople(ShoppingList $list): array {
		$uids = [$list->getOwnerUid() => true];
		foreach ($this->members->findByList($list->getId()) as $m) {
			if ($m->getPrincipalType() === Member::TYPE_USER) {
				$uids[$m->getPrincipalId()] = true;
			} else {
				$group = $this->groupManager->get($m->getPrincipalId());
				if ($group !== null) {
					foreach ($group->searchUsers('', 200) as $user) {
						$uids[$user->getUID()] = true;
					}
				}
			}
		}
		$out = [];
		foreach (array_keys($uids) as $uid) {
			$out[(string)$uid] = $this->userManager->getDisplayName((string)$uid) ?? (string)$uid;
		}
		return $out;
	}

	public function getMeta(string $uid, ShoppingList $list, string $permission): array {
		return [
			'list' => $list->jsonSerialize() + ['permission' => $permission],
			'stores' => array_map(fn ($s) => $s->jsonSerialize(), $this->stores->findByList($list->getId())),
			'categories' => $this->getCustomCategories($list->getId()),
			'overrides' => (object)$this->getOverrides($list->getId()),
			'prefs' => $this->getPrefs($uid, $list->getId()),
			'people' => (object)$this->getPeople($list),
		];
	}

	public function deleteByList(int $listId): void {
		foreach (['grocery_overrides', 'grocery_categories', 'grocery_prefs'] as $table) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete($table)
				->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
			$qb->executeStatement();
		}
	}

	public function deletePrefsForUser(string $uid): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('grocery_prefs')->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
		$qb->executeStatement();
	}
}
