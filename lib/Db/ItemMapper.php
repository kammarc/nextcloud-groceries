<?php

declare(strict_types=1);

namespace OCA\Groceries\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Item>
 */
class ItemMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'grocery_items', Item::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 */
	public function findInList(int $listId, int $itemId): Item {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * All live items (since = 0) or every item changed after $since, including tombstones.
	 *
	 * @return Item[]
	 */
	public function findChanges(int $listId, int $since): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		if ($since > 0) {
			$qb->andWhere($qb->expr()->gt('revision', $qb->createNamedParameter($since, IQueryBuilder::PARAM_INT)));
		} else {
			$qb->andWhere($qb->expr()->eq('deleted', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		}
		$qb->orderBy('revision', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Live items (open or checked) with a given normalized name, open ones first.
	 *
	 * @return Item[]
	 */
	public function findLiveByNormName(int $listId, string $normName): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('norm_name', $qb->createNamedParameter($normName)))
			->andWhere($qb->expr()->eq('deleted', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->orderBy('status', 'ASC')->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/** @return Item[] */
	public function findChecked(int $listId, ?int $checkedBefore = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(Item::STATUS_CHECKED, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('deleted', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		if ($checkedBefore !== null) {
			$qb->andWhere($qb->expr()->lt('checked_at', $qb->createNamedParameter($checkedBefore, IQueryBuilder::PARAM_INT)));
		}
		return $this->findEntities($qb);
	}

	/** @return Item[] */
	public function findLiveBySource(int $listId, string $source, string $sourceRef): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('source', $qb->createNamedParameter($source)))
			->andWhere($qb->expr()->eq('source_ref', $qb->createNamedParameter($sourceRef)))
			->andWhere($qb->expr()->eq('deleted', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	 * @param int[] $listIds
	 * @return array<int, int> listId => number of open items
	 */
	public function countOpen(array $listIds): array {
		if ($listIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('list_id')
			->selectAlias($qb->func()->count('id'), 'cnt')
			->from($this->getTableName())
			->where($qb->expr()->in('list_id', $qb->createNamedParameter($listIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(Item::STATUS_OPEN, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('deleted', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->groupBy('list_id');
		$result = $qb->executeQuery();
		$counts = [];
		while ($row = $result->fetch()) {
			$counts[(int)$row['list_id']] = (int)$row['cnt'];
		}
		$result->closeCursor();
		return $counts;
	}

	/**
	 * Hard-deletes tombstones older than $before and returns the highest purged revision per list.
	 *
	 * @return array<int,int>
	 */
	public function purgeTombstones(int $before): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('list_id')
			->selectAlias($qb->func()->max('revision'), 'maxrev')
			->from($this->getTableName())
			->where($qb->expr()->eq('deleted', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lt('updated_at', $qb->createNamedParameter($before, IQueryBuilder::PARAM_INT)))
			->groupBy('list_id');
		$result = $qb->executeQuery();
		$max = [];
		while ($row = $result->fetch()) {
			$max[(int)$row['list_id']] = (int)$row['maxrev'];
		}
		$result->closeCursor();

		$del = $this->db->getQueryBuilder();
		$del->delete($this->getTableName())
			->where($del->expr()->eq('deleted', $del->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->andWhere($del->expr()->lt('updated_at', $del->createNamedParameter($before, IQueryBuilder::PARAM_INT)));
		$del->executeStatement();
		return $max;
	}

	public function deleteByList(int $listId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
