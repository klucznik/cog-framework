<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */

// Columns whose default cannot be written as a constant expression - currently
// the CURRENT_TIMESTAMP family, which has to be evaluated per instance.
$runtimeDefaultColumnArray = [];
foreach ($table->columnArray as $column) {
	if ($column->hasCurrentTimestampDefault()) {
		$runtimeDefaultColumnArray[] = $column;
	}
}

if (!count($runtimeDefaultColumnArray)) {
	return;
}

?>///////////////////////////////
	// CONSTRUCTOR
	///////////////////////////////

	/**
	 * Applies the column defaults that cannot be expressed as a constant
	 * property initializer, so a new <?= $table->className ?> starts out with
	 * the same values the database would have assigned to an inserted row.
	 */
	public function __construct() {
<?php foreach ($runtimeDefaultColumnArray as $column) { ?>
		// <?= $table->name ?>.<?= $column->name ?> defaults to <?= $column->default ?>

		$this-><?= $column->variableName ?> = <?= $column->getDefaultAsString() ?>;
<?php } ?>
	}
