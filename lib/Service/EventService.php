<?php

declare(strict_types=1);

namespace OCA\Groceries\Service;

use OCA\Groceries\Db\Item;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Purchase log for statistics: "add" when someone puts an item on a list, "check" when someone
 * ticks it off while shopping. Statistics are computed in the app from these events, and the
 * same events are what export/import moves around.
 */
class EventService {
	public const TYPES = ['add', 'check'];
	public const MAX_IMPORT = 50000;

	public function __construct(
		private IDBConnection $db,
		private ListAccessService $access,
		private ListMetaService $meta,
	) {
	}

	public function record(Item $item, string $uid, string $type): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('grocery_events')->values([
			'list_id' => $qb->createNamedParameter($item->getListId(), IQueryBuilder::PARAM_INT),
			'event_uid' => $qb->createNamedParameter(bin2hex(random_bytes(16))),
			'uid' => $qb->createNamedParameter($uid),
			'type' => $qb->createNamedParameter($type),
			'norm_name' => $qb->createNamedParameter($item->getNormName()),
			'display_name' => $qb->createNamedParameter($item->getName()),
			'catalog_key' => $qb->createNamedParameter($item->getCatalogKey()),
			'category_key' => $qb->createNamedParameter($item->getCategoryKey()),
			'icon' => $qb->createNamedParameter($item->getIcon()),
			'quantity' => $qb->createNamedParameter($item->getQuantity()),
			'created_at' => $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT),
		]);
		$qb->executeStatement();
	}

	/** Unticking an item takes back its most recent "check" (a mis-tap is not a purchase). */
	public function undoLastCheck(Item $item): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')->from('grocery_events')
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($item->getListId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('norm_name', $qb->createNamedParameter($item->getNormName())))
			->andWhere($qb->expr()->eq('type', $qb->createNamedParameter('check')))
			->orderBy('created_at', 'DESC')->addOrderBy('id', 'DESC')
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$id = $result->fetchOne();
		$result->closeCursor();
		if ($id === false) {
			return;
		}
		$del = $this->db->getQueryBuilder();
		$del->delete('grocery_events')->where($del->expr()->eq('id', $del->createNamedParameter((int)$id, IQueryBuilder::PARAM_INT)));
		$del->executeStatement();
	}

	/** @return list<array> events of a list, oldest first (optionally within [from, to)) */
	public function getEvents(string $uid, int $listId, ?int $from = null, ?int $to = null): array {
		$this->access->require($uid, $listId);
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from('grocery_events')
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		if ($from !== null) {
			$qb->andWhere($qb->expr()->gte('created_at', $qb->createNamedParameter($from, IQueryBuilder::PARAM_INT)));
		}
		if ($to !== null) {
			$qb->andWhere($qb->expr()->lt('created_at', $qb->createNamedParameter($to, IQueryBuilder::PARAM_INT)));
		}
		$qb->orderBy('created_at', 'ASC')->addOrderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$out = [];
		while ($row = $result->fetch()) {
			$out[] = [
				'id' => (string)$row['event_uid'],
				'user' => (string)$row['uid'],
				'type' => (string)$row['type'],
				'normName' => (string)$row['norm_name'],
				'name' => (string)$row['display_name'],
				'catalogKey' => $row['catalog_key'],
				'category' => (string)$row['category_key'],
				'icon' => $row['icon'],
				'quantity' => $row['quantity'],
				'at' => (int)$row['created_at'],
			];
		}
		$result->closeCursor();
		return $out;
	}

	/**
	 * Merges exported events into a list; events that already exist (same id) are skipped.
	 *
	 * @return array{imported: int, skipped: int}
	 */
	public function import(string $uid, int $listId, array $events): array {
		[$list] = $this->access->require($uid, $listId, ListAccessService::EDIT);
		if (count($events) > self::MAX_IMPORT) {
			throw new ValidationException('At most ' . self::MAX_IMPORT . ' events per import');
		}
		// events may only name people who can see the list or already appear in its log; anything
		// else is credited to the importing user (no faking purchases in someone else's name)
		$known = $this->meta->getPeople($list) + array_flip($this->loggedUsers($listId));
		$imported = 0;
		$skipped = 0;
		$this->db->beginTransaction();
		try {
			foreach ($events as $e) {
				if (!is_array($e) || !in_array($e['type'] ?? null, self::TYPES, true)
					|| !is_string($e['id'] ?? null) || $e['id'] === '' || strlen($e['id']) > 64
					|| !is_string($e['normName'] ?? null) || !is_string($e['name'] ?? null)
					|| !is_numeric($e['at'] ?? null) || !is_string($e['user'] ?? null)) {
					$skipped++;
					continue;
				}
				if ($this->exists($e['id'])) {
					$skipped++;
					continue;
				}
				$qb = $this->db->getQueryBuilder();
				$qb->insert('grocery_events')->values([
					'list_id' => $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT),
					'event_uid' => $qb->createNamedParameter($e['id']),
					'uid' => $qb->createNamedParameter(isset($known[$e['user']]) ? $e['user'] : $uid),
					'type' => $qb->createNamedParameter($e['type']),
					'norm_name' => $qb->createNamedParameter(mb_substr($e['normName'], 0, 255)),
					'display_name' => $qb->createNamedParameter(mb_substr($e['name'], 0, 255)),
					'catalog_key' => $qb->createNamedParameter(isset($e['catalogKey']) && is_string($e['catalogKey']) ? mb_substr($e['catalogKey'], 0, 64) : null),
					'category_key' => $qb->createNamedParameter(is_string($e['category'] ?? null) ? mb_substr($e['category'], 0, 64) : 'other'),
					'icon' => $qb->createNamedParameter(is_string($e['icon'] ?? null) ? mb_substr($e['icon'], 0, 64) : null),
					'quantity' => $qb->createNamedParameter(is_string($e['quantity'] ?? null) ? mb_substr($e['quantity'], 0, 64) : null),
					'created_at' => $qb->createNamedParameter((int)$e['at'], IQueryBuilder::PARAM_INT),
				]);
				$qb->executeStatement();
				$imported++;
			}
			$this->db->commit();
		} catch (\Throwable $ex) {
			$this->db->rollBack();
			throw $ex;
		}
		return ['imported' => $imported, 'skipped' => $skipped];
	}

	/** @return list<string> users that appear in the list's purchase log */
	private function loggedUsers(int $listId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('uid')->from('grocery_events')
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$uids = [];
		while (($uid = $result->fetchOne()) !== false) {
			$uids[] = (string)$uid;
		}
		$result->closeCursor();
		return $uids;
	}

	public function deleteByList(int $listId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('grocery_events')->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	private function exists(string $eventUid): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')->from('grocery_events')->where($qb->expr()->eq('event_uid', $qb->createNamedParameter($eventUid)));
		$result = $qb->executeQuery();
		$found = $result->fetchOne() !== false;
		$result->closeCursor();
		return $found;
	}
}
