<?php

declare(strict_types=1);

namespace OCA\Groceries\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<ShoppingList>
 */
class ListMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'grocery_lists', ShoppingList::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 */
	public function findById(int $id): ShoppingList {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Lists the user owns or is a member of (directly or through one of their groups).
	 *
	 * @param string[] $groupIds
	 * @return ShoppingList[]
	 */
	public function findForUser(string $uid, array $groupIds): array {
		$qb = $this->db->getQueryBuilder();
		$sub = $this->db->getQueryBuilder();
		$sub->select('m.list_id')->from('grocery_members', 'm');
		$or = $sub->expr()->orX(
			$sub->expr()->andX(
				$sub->expr()->eq('m.principal_type', $qb->createNamedParameter(Member::TYPE_USER)),
				$sub->expr()->eq('m.principal_id', $qb->createNamedParameter($uid)),
			),
		);
		if ($groupIds !== []) {
			$or->add($sub->expr()->andX(
				$sub->expr()->eq('m.principal_type', $qb->createNamedParameter(Member::TYPE_GROUP)),
				$sub->expr()->in('m.principal_id', $qb->createNamedParameter($groupIds, IQueryBuilder::PARAM_STR_ARRAY)),
			));
		}
		$sub->where($or);

		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('owner_uid', $qb->createNamedParameter($uid)))
			->orWhere($qb->expr()->in('id', $qb->createFunction($sub->getSQL())))
			->orderBy('created_at', 'ASC');
		return $this->findEntities($qb);
	}

	/** @return ShoppingList[] */
	public function findByOwner(string $uid): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('owner_uid', $qb->createNamedParameter($uid)));
		return $this->findEntities($qb);
	}

	/** @return ShoppingList[] */
	public function findWithAutoClear(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->gt('auto_clear_hours', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	 * Increments the list revision and returns the new value.
	 * Must be called inside a transaction so that the returned value belongs to this change.
	 *
	 * @param bool $meta also mark list metadata (stores, categories, settings, members) as changed
	 */
	public function bumpRevision(int $listId, bool $meta = false): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('revision', $qb->createFunction('revision + 1'))
			->set('updated_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();

		$sel = $this->db->getQueryBuilder();
		$sel->select('revision')->from($this->getTableName())
			->where($sel->expr()->eq('id', $sel->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		$result = $sel->executeQuery();
		$revision = (int)$result->fetchOne();
		$result->closeCursor();

		if ($meta) {
			$upd = $this->db->getQueryBuilder();
			$upd->update($this->getTableName())
				->set('meta_revision', $upd->createNamedParameter($revision, IQueryBuilder::PARAM_INT))
				->where($upd->expr()->eq('id', $upd->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
			$upd->executeStatement();
		}
		return $revision;
	}

	/**
	 * Cheap read of [revision, metaRevision] for polling.
	 *
	 * @return array{0:int,1:int}|null
	 */
	public function getRevisions(int $listId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('revision', 'meta_revision')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		if ($row === false) {
			return null;
		}
		return [(int)$row['revision'], (int)$row['meta_revision']];
	}
}
