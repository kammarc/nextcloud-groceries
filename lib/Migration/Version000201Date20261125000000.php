<?php

declare(strict_types=1);

namespace OCA\Groceries\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * 0.2.1: per-user preference whether shared lists show "Added by …" under items (off by default).
 */
class Version000201Date20261125000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$prefs = $schema->getTable('grocery_prefs');
		if ($prefs->hasColumn('show_added_by')) {
			return null;
		}
		$prefs->addColumn('show_added_by', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
		return $schema;
	}
}
