<?php

declare(strict_types=1);

namespace OCA\Groceries\Migration;

use Closure;
use OCA\Groceries\Service\CatalogService;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * 0.1.1: store images, and the switch from 48 fine-grained categories to 20 store departments.
 * Existing items/history/overrides/store orders are converted to the new category keys.
 */
class Version000101Date20261121000000 extends SimpleMigrationStep {
	/** old category key => new category key */
	public const CATEGORY_MAP = [
		'produce_fruit' => 'produce', 'produce_veg' => 'produce', 'herbs' => 'produce',
		'dairy' => 'dairy', 'eggs' => 'dairy', 'cheese' => 'dairy',
		'meat' => 'meat_fish', 'deli' => 'meat_fish', 'fish' => 'meat_fish',
		'plant_protein' => 'vegan', 'ready_meals' => 'frozen', 'frozen' => 'frozen',
		'sauces' => 'ingredients', 'spices' => 'ingredients', 'baking' => 'ingredients',
		'breakfast' => 'cereals', 'coffee_tea' => 'drinks', 'alcohol' => 'drinks', 'household' => 'paper',
		'personal_care' => 'care', 'baby' => 'care', 'pharmacy_pain' => 'care', 'pharmacy_cold' => 'care',
		'pharmacy_digest' => 'care', 'first_aid' => 'care', 'vitamins' => 'care', 'pharmacy_skin' => 'care',
		'dental' => 'care', 'hygiene' => 'care', 'medical_devices' => 'care', 'prescriptions' => 'care',
		'tools' => 'hardware', 'fasteners' => 'hardware', 'paint' => 'hardware', 'electrical' => 'hardware',
		'plumbing' => 'hardware', 'lumber' => 'hardware', 'garden' => 'hardware', 'adhesives' => 'hardware',
		'storage' => 'hardware', 'safety' => 'hardware',
	];

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('grocery_stores');
		if (!$table->hasColumn('image')) {
			// small data: URL (the client scales pictures down to 160 px before upload)
			$table->addColumn('image', Types::TEXT, ['notnull' => false]);
			return $schema;
		}
		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$catalog = new CatalogService();

		foreach (['grocery_items', 'grocery_history'] as $table) {
			// catalog items get the category the catalog now assigns to them …
			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'catalog_key', 'category_key')->from($table);
			$result = $qb->executeQuery();
			while ($row = $result->fetch()) {
				$item = $row['catalog_key'] !== null ? $catalog->getItem((string)$row['catalog_key']) : null;
				$old = (string)$row['category_key'];
				$new = $item !== null && isset(self::CATEGORY_MAP[$old]) ? $item['category'] : $this->map($old);
				if ($new !== $old) {
					$upd = $this->db->getQueryBuilder();
					$upd->update($table)
						->set('category_key', $upd->createNamedParameter($new))
						->where($upd->expr()->eq('id', $upd->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)));
					$upd->executeStatement();
				}
			}
			$result->closeCursor();
		}

		// … user overrides keep their meaning through the plain mapping
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'category_key')->from('grocery_overrides')->where($qb->expr()->isNotNull('category_key'));
		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$new = $this->map((string)$row['category_key']);
			if ($new !== $row['category_key']) {
				$upd = $this->db->getQueryBuilder();
				$upd->update('grocery_overrides')
					->set('category_key', $upd->createNamedParameter($new))
					->where($upd->expr()->eq('id', $upd->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)));
				$upd->executeStatement();
			}
		}
		$result->closeCursor();

		// store aisle orders: map and de-duplicate, keeping the first position
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'category_order')->from('grocery_stores');
		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$order = json_decode((string)($row['category_order'] ?? '[]'), true);
			if (!is_array($order)) {
				continue;
			}
			$mapped = array_values(array_unique(array_map(fn ($k) => $this->map((string)$k), $order)));
			$upd = $this->db->getQueryBuilder();
			$upd->update('grocery_stores')
				->set('category_order', $upd->createNamedParameter(json_encode($mapped)))
				->where($upd->expr()->eq('id', $upd->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)));
			$upd->executeStatement();
		}
		$result->closeCursor();

		// lists changed underneath the clients: force a full resync
		// (per row in PHP: MySQL evaluates multi-column SET left to right, other databases don't)
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'revision')->from('grocery_lists');
		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$rev = (int)$row['revision'] + 1;
			$upd = $this->db->getQueryBuilder();
			$upd->update('grocery_lists')
				->set('revision', $upd->createNamedParameter($rev, IQueryBuilder::PARAM_INT))
				->set('meta_revision', $upd->createNamedParameter($rev, IQueryBuilder::PARAM_INT))
				->set('min_revision', $upd->createNamedParameter($rev, IQueryBuilder::PARAM_INT))
				->where($upd->expr()->eq('id', $upd->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)));
			$upd->executeStatement();
		}
		$result->closeCursor();
	}

	private function map(string $key): string {
		return self::CATEGORY_MAP[$key] ?? $key;
	}
}
