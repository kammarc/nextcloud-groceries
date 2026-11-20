<?php

declare(strict_types=1);

namespace OCA\Groceries\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * 0.1.4: purchase log for statistics ("who added / bought what, when").
 * Seeded from the items that already exist, so statistics are not empty after the update.
 */
class Version000104Date20261124000000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if ($schema->hasTable('grocery_events')) {
			return null;
		}
		$t = $schema->createTable('grocery_events');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
		$t->addColumn('list_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
		$t->addColumn('event_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
		$t->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
		$t->addColumn('type', Types::STRING, ['notnull' => true, 'length' => 16]);
		$t->addColumn('norm_name', Types::STRING, ['notnull' => true, 'length' => 255]);
		$t->addColumn('display_name', Types::STRING, ['notnull' => true, 'length' => 255]);
		$t->addColumn('catalog_key', Types::STRING, ['notnull' => false, 'length' => 64]);
		$t->addColumn('category_key', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => 'other']);
		$t->addColumn('icon', Types::STRING, ['notnull' => false, 'length' => 64]);
		$t->addColumn('quantity', Types::STRING, ['notnull' => false, 'length' => 64]);
		$t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$t->setPrimaryKey(['id']);
		$t->addUniqueIndex(['event_uid'], 'grocery_events_uid');
		$t->addIndex(['list_id', 'created_at'], 'grocery_events_time');
		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from('grocery_items');
		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$this->insert($row, 'add', (string)$row['added_by'], (int)$row['created_at'], 'seed-add-' . $row['id']);
			if ($row['checked_at'] !== null && $row['checked_by'] !== null) {
				$this->insert($row, 'check', (string)$row['checked_by'], (int)$row['checked_at'], 'seed-check-' . $row['id']);
			}
		}
		$result->closeCursor();
	}

	private function insert(array $item, string $type, string $uid, int $at, string $eventUid): void {
		$q = $this->db->getQueryBuilder();
		$q->insert('grocery_events')->values([
			'list_id' => $q->createNamedParameter((int)$item['list_id'], IQueryBuilder::PARAM_INT),
			'event_uid' => $q->createNamedParameter($eventUid),
			'uid' => $q->createNamedParameter($uid),
			'type' => $q->createNamedParameter($type),
			'norm_name' => $q->createNamedParameter((string)$item['norm_name']),
			'display_name' => $q->createNamedParameter((string)$item['name']),
			'catalog_key' => $q->createNamedParameter($item['catalog_key']),
			'category_key' => $q->createNamedParameter((string)$item['category_key']),
			'icon' => $q->createNamedParameter($item['icon']),
			'quantity' => $q->createNamedParameter($item['quantity']),
			'created_at' => $q->createNamedParameter($at, IQueryBuilder::PARAM_INT),
		]);
		try {
			$q->executeStatement();
		} catch (\Throwable) {
			// already seeded
		}
	}
}
