<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>
/**
	 * Reload this <?= $table->className ?> from the database.
	 * @return void
	 * @throws CogException
	 */
	public function reload(): void {
		// Make sure we are actually Restored from the database
		if (!$this->__blnRestored) {
			throw new CogException('Cannot call reload() on a new, unsaved <?= $table->className ?> object.');
		}

		// Reload the object
		$reloaded = <?= $table->className ?>::load(<?php foreach ($table->primaryKeyColumnArray as $column) { ?>$this-><?= $column->variableName ?>, <?php } ?><?php \Cog\Codegen\Utils::goBack(2); ?>);

		// Update local variables to match
<?php foreach ($table->columnArray as $column) { ?>
<?php if (!$column->identity) { ?>
<?php if ($column->reference) { ?>
		$this-><?= $column->propertyName ?> = $reloaded-><?= $column->propertyName ?>;
<?php } ?><?php if (!$column->reference) { ?>
		$this-><?= $column->variableName ?> = $reloaded-><?= $column->variableName ?>;
<?php } ?><?php if ($column->primaryKey) { ?>
		$this->__<?= $column->variableName ?> = $this-><?= $column->variableName ?>;
<?php } ?><?php } ?><?php } ?>
	}