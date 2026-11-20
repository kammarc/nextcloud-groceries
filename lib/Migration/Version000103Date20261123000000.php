<?php

declare(strict_types=1);

namespace OCA\Groceries\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * 0.1.3: per-user preference whether the add-items screen shows the "frequently bought" section.
 */
class Version000103Date20261123000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$prefs = $schema->getTable('grocery_prefs');
		if ($prefs->hasColumn('show_frequent')) {
			return null;
		}
		$prefs->addColumn('show_frequent', Types::SMALLINT, ['notnull' => true, 'default' => 1]);
		return $schema;
	}
}
