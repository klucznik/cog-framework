<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen  */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
/** @var \Cog\Codegen\ReverseReference $reverseReference  */
?>
<?php $reverseReferenceTable = $codegen->getTable(strtolower($reverseReference->table)); ?>
<?php $reverseReferenceColumn = $reverseReferenceTable->getColumnByName(strtolower($reverseReference->column)); ?>


	// Related Objects' Methods for <?= $reverseReference->objectDescription ?>

	//-------------------------------------------------------------------

	/**
	 * Gets all associated <?= $reverseReference->objectDescriptionPlural ?> as an array of <?= $reverseReference->variableType ?> objects
	 * @param null|QQClause|QQClause[] $optionalClauses additional optional QQClause objects for this query
	 * @return <?= $reverseReference->variableType ?>[]
	 * @throws CogException
	*/
	public function get<?= $reverseReference->objectDescriptionUppercase ?>Array(QQClause|array|null $optionalClauses = null): array {
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($this->', ')', 'variableName', $table->primaryKeyColumnArray) ?>) {
			return [];
		}

		try {
			return <?= $reverseReference->variableType ?>::loadArrayBy<?= $reverseReferenceColumn->propertyNameUppercase ?>(<?= $codegen->implodeObjectArray(', ', '$this->', '', 'variableName', $table->primaryKeyColumnArray) ?>, $optionalClauses);
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
	}

	/**
	 * Counts all associated <?= $reverseReference->objectDescriptionPlural ?>

	 * @return int
	 * @throws CogException
	*/
	public function count<?= $reverseReference->objectDescriptionPluralUppercase ?>(): int {
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($this->', ')', 'variableName', $table->primaryKeyColumnArray) ?>) {
			return 0;
		}

		return <?= $reverseReference->variableType ?>::countBy<?= $reverseReferenceColumn->propertyNameUppercase ?>(<?= $codegen->implodeObjectArray(', ', '$this->', '', 'variableName', $table->primaryKeyColumnArray) ?>);
	}

	/**
	 * Associates a <?= $reverseReference->objectDescription ?>

	 * @param <?= $reverseReference->variableType ?> $<?= $reverseReference->variableName ?>

	 * @throws UndefinedPrimaryKeyException
	 * @throws CogException
	*/
	public function associate<?= $reverseReference->objectDescriptionUppercase ?>(<?= $reverseReference->variableType ?> $<?= $reverseReference->variableName ?>): void {
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($this->', ')', 'variableName', $table->primaryKeyColumnArray) ?>) {
			throw new UndefinedPrimaryKeyException('Unable to call associate<?= $reverseReference->objectDescriptionUppercase ?> on this unsaved <?= $table->className ?>.');
		}
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($' . $reverseReference->variableName . '->', ')', 'propertyName', $reverseReferenceTable->primaryKeyColumnArray) ?>) {
			throw new UndefinedPrimaryKeyException('Unable to call associate<?= $reverseReference->objectDescriptionUppercase ?> on this <?= $table->className ?> with an unsaved <?= $reverseReferenceTable->className ?>.');
		}

		// Get the database object for this class
		$database = <?= $table->className ?>::getDatabase();

		// Perform the SQL Query
		$database->nonQuery('
			UPDATE
				<?= $escapeIdentifierBegin ?><?= $reverseReference->table ?><?= $escapeIdentifierEnd ?>

			SET
				<?= $escapeIdentifierBegin ?><?= $reverseReference->column ?><?= $escapeIdentifierEnd ?> = ' . $database->sqlVariable($this-><?= $table->primaryKeyColumnArray[0]->variableName ?>) . '
			WHERE
<?php foreach ($reverseReferenceTable->columnArray as $column) { ?>
<?php if ($column->primaryKey) { ?>
				<?= $escapeIdentifierBegin ?><?= $column->name ?><?= $escapeIdentifierEnd ?> = ' . $database->sqlVariable($<?= $reverseReference->variableName ?>-><?= $column->propertyName ?>) . ' AND
<?php } ?><?php } ?><?php \Cog\Codegen\Utils::goBack(5); ?>

		');
	}

	/**
	 * Unassociates a <?= $reverseReference->objectDescription ?>

	 * @param <?= $reverseReference->variableType ?> $<?= $reverseReference->variableName ?>

	 * @throws UndefinedPrimaryKeyException
	 * @throws CogException
	*/
	public function unassociate<?= $reverseReference->objectDescriptionUppercase ?>(<?= $reverseReference->variableType ?> $<?= $reverseReference->variableName ?>): void {
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($this->', ')', 'variableName', $table->primaryKeyColumnArray) ?>) {
			throw new UndefinedPrimaryKeyException('Unable to call unassociate<?= $reverseReference->objectDescriptionUppercase ?> on this unsaved <?= $table->className ?>.');
		}
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($' . $reverseReference->variableName . '->', ')', 'propertyName', $reverseReferenceTable->primaryKeyColumnArray) ?>) {
			throw new UndefinedPrimaryKeyException('Unable to call unassociate<?= $reverseReference->objectDescriptionUppercase ?> on this <?= $table->className ?> with an unsaved <?= $reverseReferenceTable->className ?>.');
		}

		// Get the database object for this class
		$database = <?= $table->className ?>::getDatabase();

		// Perform the SQL Query
		$database->nonQuery('
			UPDATE
				<?= $escapeIdentifierBegin ?><?= $reverseReference->table ?><?= $escapeIdentifierEnd ?>

			SET
				<?= $escapeIdentifierBegin ?><?= $reverseReference->column ?><?= $escapeIdentifierEnd ?> = null
			WHERE
<?php foreach ($reverseReferenceTable->columnArray as $column) { ?>
<?php if ($column->primaryKey) { ?>
				<?= $escapeIdentifierBegin ?><?= $column->name ?><?= $escapeIdentifierEnd ?> = ' . $database->sqlVariable($<?= $reverseReference->variableName ?>-><?= $column->propertyName ?>) . ' AND
<?php } ?><?php } ?><?php \Cog\Codegen\Utils::goBack(1); ?>

				<?= $escapeIdentifierBegin ?><?= $reverseReference->column ?><?= $escapeIdentifierEnd ?> = ' . $database->sqlVariable($this-><?= $table->primaryKeyColumnArray[0]->variableName ?>) . '
		');
	}

	/**
	 * Unassociates all <?= $reverseReference->objectDescriptionPlural ?>

	 * @return void
	 * @throws UndefinedPrimaryKeyException
	 * @throws CogException
	*/
	public function unassociateAll<?= $reverseReference->objectDescriptionPluralUppercase ?>(): void {
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($this->', ')', 'variableName', $table->primaryKeyColumnArray) ?>) {
			throw new UndefinedPrimaryKeyException('Unable to call unassociateAll<?= $reverseReference->objectDescriptionUppercase ?> on this unsaved <?= $table->className ?>.');
		}

		// Get the database object for this class
		$database = <?= $table->className ?>::getDatabase();

		// Perform the SQL Query
		$database->nonQuery('
			UPDATE
				<?= $escapeIdentifierBegin ?><?= $reverseReference->table ?><?= $escapeIdentifierEnd ?>

			SET
				<?= $escapeIdentifierBegin ?><?= $reverseReference->column ?><?= $escapeIdentifierEnd ?> = null
			WHERE
				<?= $escapeIdentifierBegin ?><?= $reverseReference->column ?><?= $escapeIdentifierEnd ?> = ' . $database->sqlVariable($this-><?= $table->primaryKeyColumnArray[0]->variableName ?>) . '
		');
	}

	/**
	 * Deletes an associated <?= $reverseReference->objectDescription ?>

	 * @param <?= $reverseReference->variableType ?> $<?= $reverseReference->variableName ?>

	 * @return void
	 * @throws UndefinedPrimaryKeyException
	 * @throws CogException
	*/
	public function deleteAssociated<?= $reverseReference->objectDescriptionUppercase ?>(<?= $reverseReference->variableType ?> $<?= $reverseReference->variableName ?>): void {
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($this->', ')', 'variableName', $table->primaryKeyColumnArray) ?>) {
			throw new UndefinedPrimaryKeyException('Unable to call deleteAssociated<?= $reverseReference->objectDescriptionUppercase ?> on this unsaved <?= $table->className ?>.');
		}
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($' . $reverseReference->variableName . '->', ')', 'propertyName', $reverseReferenceTable->primaryKeyColumnArray) ?>) {
			throw new UndefinedPrimaryKeyException('Unable to call deleteAssociated<?= $reverseReference->objectDescriptionUppercase ?> on this <?= $table->className ?> with an unsaved <?= $reverseReferenceTable->className ?>.');
		}

		$<?= $reverseReference->variableName ?>->delete();
	}

	/**
	 * Deletes all associated <?= $reverseReference->objectDescriptionPlural ?>

	 * @return void
	 * @throws UndefinedPrimaryKeyException
	 * @throws CogException
	*/
	public function deleteAll<?= $reverseReference->objectDescriptionPluralUppercase ?>(): void {
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($this->', ')', 'variableName', $table->primaryKeyColumnArray) ?>) {
			throw new UndefinedPrimaryKeyException('Unable to call deleteAll<?= $reverseReference->objectDescriptionUppercase ?> on this unsaved <?= $table->className ?>.');
		}

		foreach ($this->get<?= $reverseReference->objectDescriptionUppercase ?>Array() as $obj) {
			$obj->delete();
		}
	}
