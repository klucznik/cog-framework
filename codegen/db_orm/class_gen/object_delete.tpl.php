<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>
/**
	 * Delete this <?= $table->className ?>

	 * @throws UndefinedPrimaryKeyException
	 * @throws CogException
	 * @return void
	 */
	public function delete(): void {
		if (<?= $codegen->implodeObjectArray(' || ', 'null === $this->', '', 'variableName', $table->primaryKeyColumnArray) ?>) {
			throw new UndefinedPrimaryKeyException('Cannot delete this <?= $table->className ?> with an unset primary key.');
		}

		$this->unassociateEverything();

		// Get the Database Object for this Class
		$database = <?= $table->className ?>::getDatabase();

<?php foreach ($table->reverseReferenceArray as $reverseReference) { ?>
<?php if ($reverseReference->unique) { ?>
<?php if (!$reverseReference->notNull) { ?>
<?php $reverseReferenceTable = $codegen->tableArray[strtolower($reverseReference->table)]; ?>
<?php $reverseReferenceColumn = $reverseReferenceTable->columnArray[strtolower($reverseReference->column)]; ?>


		// Update the adjoined <?= $reverseReference->objectDescription ?> object (if applicable) and perform the unassociation

		// Optional -- if you **KNOW** that you do not want to EVER run any level of business logic on the disassocation,
		// you *could* override delete() so that this step can be a single hard coded query to optimize performance.
		if ($associated = <?= $reverseReference->variableType ?>::loadBy<?= $reverseReferenceColumn->propertyName ?>(<?= $codegen->implodeObjectArray(', ', '$this->', '', 'variableName', $table->primaryKeyColumnArray) ?>)) {
			$associated-><?= $reverseReferenceColumn->propertyName ?> = null;
			$associated->save();
		}
<?php } ?><?php if ($reverseReference->notNull) { ?>
<?php $reverseReferenceTable = $codegen->tableArray[strtolower($reverseReference->table)]; ?>
<?php $reverseReferenceColumn = $reverseReferenceTable->columnArray[strtolower($reverseReference->column)]; ?>


		// Update the adjoined <?= $reverseReference->objectDescription ?> object (if applicable) and perform a delete

		// Optional -- if you **KNOW** that you do not want to EVER run any level of business logic on the disassocation,
		// you *could* override delete() so that this step can be a single hard coded query to optimize performance.
		if ($associated = <?= $reverseReference->variableType ?>::loadBy<?= $reverseReferenceColumn->propertyName ?>(<?= $codegen->implodeObjectArray(', ', '$this->', '', 'variableName', $table->primaryKeyColumnArray) ?>)) {
			$associated->delete();
		}
<?php } ?>
<?php } ?>
<?php } ?>

		// Perform the SQL Query
		$database->nonQuery('
			DELETE FROM
				<?= $escapeIdentifierBegin ?><?= $table->name ?><?= $escapeIdentifierEnd ?>

			WHERE
<?php foreach ($table->primaryKeyColumnArray as $column) { ?>
				<?= $escapeIdentifierBegin ?><?= $column->name ?><?= $escapeIdentifierEnd ?> = ' . $database->sqlVariable($this-><?= $column->variableName ?>) . ' AND
<?php } ?><?php \Cog\Codegen\Utils::goBack(8); ?>);
	}

	/**
	 * Truncate <?= $table->name ?> table
	 * @return void
     * @throws CogException
	 */
	public static function truncate(): void {
		// Get the Database Object for this Class
		$database = <?= $table->className ?>::getDatabase();

		// Perform the Query
		$database->nonQuery('TRUNCATE <?= $escapeIdentifierBegin ?><?= $table->name ?><?= $escapeIdentifierEnd ?>');
	}