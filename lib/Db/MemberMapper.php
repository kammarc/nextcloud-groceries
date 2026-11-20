<?php

declare(strict_types=1);

namespace OCA\Groceries\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Member>
 */
class MemberMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'grocery_members', Member::class);
	}

	/** @return Member[] */
	public function findByList(int $listId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
			->orderBy('principal_type')->addOrderBy('principal_id');
		return $this->findEntities($qb);
	}

	public function deleteByList(int $listId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function deleteByPrincipal(string $type, string $id): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('principal_type', $qb->createNamedParameter($type)))
			->andWhere($qb->expr()->eq('principal_id', $qb->createNamedParameter($id)));
		$qb->executeStatement();
	}
}
