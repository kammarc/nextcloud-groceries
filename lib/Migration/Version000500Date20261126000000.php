<?php

declare(strict_types=1);

namespace OCA\Groceries\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * 0.5.0: per-user preference to draw item icons as one-colour pen sketches (off by default).
 */
class Version000500Date20261126000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$prefs = $schema->getTable('grocery_prefs');
		if ($prefs->hasColumn('sketch_icons')) {
			return null;
		}
		$prefs->addColumn('sketch_icons', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
		return $schema;
	}
}
