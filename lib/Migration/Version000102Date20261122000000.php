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
 * 0.1.2: list pictures, the per-user "group by category" preference, and own category sets for
 * pharmacy and hardware lists (their items move from the grocery departments into those sets).
 */
class Version000102Date20261122000000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;
		$lists = $schema->getTable('grocery_lists');
		if (!$lists->hasColumn('image')) {
			$lists->addColumn('image', Types::TEXT, ['notnull' => false]);
			$changed = true;
		}
		$prefs = $schema->getTable('grocery_prefs');
		if (!$prefs->hasColumn('grouped')) {
			$prefs->addColumn('grouped', Types::SMALLINT, ['notnull' => true, 'default' => 1]);
			$changed = true;
		}
		return $changed ? $schema : null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$catalog = new CatalogService();
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'template', 'revision')->from('grocery_lists')
			->where($qb->expr()->in('template', $qb->createNamedParameter(['pharmacy', 'hardware'], IQueryBuilder::PARAM_STR_ARRAY)));
		$lists = $qb->executeQuery()->fetchAll();

		foreach ($lists as $list) {
			$listId = (int)$list['id'];
			$template = (string)$list['template'];
			$revision = (int)$list['revision'] + 1;
			foreach (['grocery_items', 'grocery_history'] as $table) {
				$sel = $this->db->getQueryBuilder();
				$sel->select('id', 'catalog_key', 'category_key')->from($table)
					->where($sel->expr()->eq('list_id', $sel->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
				foreach ($sel->executeQuery()->fetchAll() as $row) {
					if ($catalog->isCategoryFor((string)$row['category_key'], $template) || str_starts_with((string)$row['category_key'], 'custom_')) {
						continue;
					}
					$item = $row['catalog_key'] !== null ? $catalog->getItem((string)$row['catalog_key']) : null;
					$upd = $this->db->getQueryBuilder();
					$upd->update($table)
						->set('category_key', $upd->createNamedParameter($item !== null ? $catalog->itemCategory($item, $template) : 'other'))
						->where($upd->expr()->eq('id', $upd->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)));
					if ($table === 'grocery_items') {
						$upd->set('revision', $upd->createNamedParameter($revision, IQueryBuilder::PARAM_INT));
					}
					$upd->executeStatement();
				}
			}
			// store orders of such lists: keep only categories of the list type
			$sel = $this->db->getQueryBuilder();
			$sel->select('id', 'category_order')->from('grocery_stores')
				->where($sel->expr()->eq('list_id', $sel->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
			foreach ($sel->executeQuery()->fetchAll() as $row) {
				$order = json_decode((string)($row['category_order'] ?? '[]'), true);
				$order = is_array($order) ? array_values(array_filter($order, fn ($k) => is_string($k) && ($catalog->isCategoryFor($k, $template) || str_starts_with($k, 'custom_')))) : [];
				$upd = $this->db->getQueryBuilder();
				$upd->update('grocery_stores')
					->set('category_order', $upd->createNamedParameter(json_encode($order)))
					->where($upd->expr()->eq('id', $upd->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)));
				$upd->executeStatement();
			}
			$upd = $this->db->getQueryBuilder();
			$upd->update('grocery_lists')
				->set('revision', $upd->createNamedParameter($revision, IQueryBuilder::PARAM_INT))
				->set('meta_revision', $upd->createNamedParameter($revision, IQueryBuilder::PARAM_INT))
				->where($upd->expr()->eq('id', $upd->createNamedParameter($listId, IQueryBuilder::PARAM_INT)));
			$upd->executeStatement();
		}
	}
}
