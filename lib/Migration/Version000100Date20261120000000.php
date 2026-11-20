<?php

declare(strict_types=1);

namespace OCA\Groceries\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Initial schema.
 */
class Version000100Date20261120000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('grocery_lists')) {
			$t = $schema->createTable('grocery_lists');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$t->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$t->addColumn('icon', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('color', Types::STRING, ['notnull' => false, 'length' => 16]);
			$t->addColumn('owner_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('template', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'grocery']);
			$t->addColumn('auto_clear_hours', Types::INTEGER, ['notnull' => true, 'default' => 12]);
			$t->addColumn('revision', Types::BIGINT, ['notnull' => true, 'default' => 0, 'unsigned' => true]);
			$t->addColumn('meta_revision', Types::BIGINT, ['notnull' => true, 'default' => 0, 'unsigned' => true]);
			$t->addColumn('min_revision', Types::BIGINT, ['notnull' => true, 'default' => 0, 'unsigned' => true]);
			$t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$t->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$t->setPrimaryKey(['id']);
			$t->addIndex(['owner_uid'], 'grocery_lists_owner');
		}

		if (!$schema->hasTable('grocery_members')) {
			$t = $schema->createTable('grocery_members');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$t->addColumn('list_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$t->addColumn('principal_type', Types::STRING, ['notnull' => true, 'length' => 8]);
			$t->addColumn('principal_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('permission', Types::STRING, ['notnull' => true, 'length' => 8, 'default' => 'edit']);
			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['list_id', 'principal_type', 'principal_id'], 'grocery_members_uniq');
			$t->addIndex(['principal_type', 'principal_id'], 'grocery_members_principal');
		}

		if (!$schema->hasTable('grocery_items')) {
			$t = $schema->createTable('grocery_items');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$t->addColumn('list_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$t->addColumn('catalog_key', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$t->addColumn('norm_name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$t->addColumn('category_key', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => 'other']);
			$t->addColumn('quantity', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('note', Types::STRING, ['notnull' => false, 'length' => 1000]);
			$t->addColumn('icon', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('status', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
			$t->addColumn('added_by', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('checked_by', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('checked_at', Types::BIGINT, ['notnull' => false]);
			$t->addColumn('source', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'manual']);
			$t->addColumn('source_ref', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('source_label', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('revision', Types::BIGINT, ['notnull' => true, 'default' => 0, 'unsigned' => true]);
			$t->addColumn('deleted', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
			$t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$t->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$t->setPrimaryKey(['id']);
			$t->addIndex(['list_id', 'revision'], 'grocery_items_rev');
			$t->addIndex(['list_id', 'norm_name'], 'grocery_items_name');
		}

		if (!$schema->hasTable('grocery_history')) {
			$t = $schema->createTable('grocery_history');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$t->addColumn('list_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$t->addColumn('norm_name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$t->addColumn('display_name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$t->addColumn('catalog_key', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('category_key', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => 'other']);
			$t->addColumn('icon', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('last_quantity', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('use_count', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$t->addColumn('last_used_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['list_id', 'norm_name'], 'grocery_history_uniq');
		}

		if (!$schema->hasTable('grocery_categories')) {
			$t = $schema->createTable('grocery_categories');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$t->addColumn('list_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$t->addColumn('category_key', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$t->addColumn('icon', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['list_id', 'category_key'], 'grocery_categories_uniq');
		}

		if (!$schema->hasTable('grocery_stores')) {
			$t = $schema->createTable('grocery_stores');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$t->addColumn('list_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$t->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$t->addColumn('icon', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('category_order', Types::TEXT, ['notnull' => false]);
			$t->setPrimaryKey(['id']);
			$t->addIndex(['list_id'], 'grocery_stores_list');
		}

		if (!$schema->hasTable('grocery_prefs')) {
			$t = $schema->createTable('grocery_prefs');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$t->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('list_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$t->addColumn('active_store_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
			$t->addColumn('view_mode', Types::STRING, ['notnull' => true, 'length' => 8, 'default' => 'list']);
			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['uid', 'list_id'], 'grocery_prefs_uniq');
		}

		if (!$schema->hasTable('grocery_overrides')) {
			$t = $schema->createTable('grocery_overrides');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$t->addColumn('list_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$t->addColumn('norm_name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$t->addColumn('category_key', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('icon', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['list_id', 'norm_name'], 'grocery_overrides_uniq');
		}

		if (!$schema->hasTable('grocery_ops')) {
			$t = $schema->createTable('grocery_ops');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$t->addColumn('op_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('item_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['op_id'], 'grocery_ops_uniq');
		}

		return $schema;
	}
}
