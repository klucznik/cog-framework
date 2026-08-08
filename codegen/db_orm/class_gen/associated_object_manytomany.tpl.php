<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen  */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
/** @var \Cog\Codegen\ManyToManyReference $manyToManyReference  */
?>
<?php $manyToManyReferenceTable = $codegen->getTable(strtolower($manyToManyReference->associatedTable)); ?>


	// Related Many-to-Many Objects' Methods for <?= $manyToManyReference->objectDescription ?>

	//-------------------------------------------------------------------

	/**
	 * Gets all many-to-many associated <?= $manyToManyReference->objectDescriptionPlural ?> as an array of <?= $manyToManyReference->variableType ?> objects
	 * @param null|QQClause|QQClause[] $optionalClauses additional optional QQClause objects for this query
	 * @param array|null $parameterArray
	 * @return <?= $manyToManyReference->variableType ?>[]
	 * @throws CogException
	*/
	public function get<?= $manyToManyReference->objectDescriptionUppercase ?>Array(QQClause|array|null $optionalClauses = null, ?array $parameterArray = null): array {
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($this->', ')', 'variableName', $table->primaryKeyColumnArray) ?>) {
			return [];
		}

		try {
			return <?= $manyToManyReference->variableType ?>::loadArrayBy<?= $manyToManyReference->oppositeObjectDescription ?>($this-><?= $table->primaryKeyColumnArray[0]->variableName ?>, $optionalClauses, $parameterArray);
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
	}

	/**
	 * Counts all many-to-many associated <?= $manyToManyReference->objectDescriptionPluralUppercase ?>

	 * @return int
	 * @throws CogException
	*/
	public function count<?= $manyToManyReference->objectDescriptionPluralUppercase ?>(): int {
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($this->', ')', 'variableName', $table->primaryKeyColumnArray) ?>) {
			return 0;
		}

		return <?= $manyToManyReference->variableType ?>::countBy<?= $manyToManyReference->oppositeObjectDescription ?>($this-><?= $table->primaryKeyColumnArray[0]->variableName ?>);
	}

	/**
	 * Checks to see if an association exists with a specific <?= $manyToManyReference->objectDescriptionUppercase ?>

	 * @param <?= $manyToManyReference->variableType ?> $<?= $manyToManyReference->variableName ?>

	 * @return bool
	 * @throws UndefinedPrimaryKeyException
	 * @throws CogException
	*/
	public function is<?= $manyToManyReference->objectDescriptionUppercase ?>Associated(<?= $manyToManyReference->variableType ?> $<?= $manyToManyReference->variableName ?>): bool {
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($this->', ')', 'variableName', $table->primaryKeyColumnArray) ?>) {
			throw new UndefinedPrimaryKeyException('Unable to call is<?= $manyToManyReference->objectDescription ?>Associated on this unsaved <?= $table->className ?>.');
		}
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($' . $manyToManyReference->variableName . '->', ')', 'propertyName', $manyToManyReferenceTable->primaryKeyColumnArray) ?>) {
			throw new UndefinedPrimaryKeyException('Unable to call is<?= $manyToManyReference->objectDescription ?>Associated on this <?= $table->className ?> with an unsaved <?= $manyToManyReferenceTable->className ?>.');
		}

		$intRowCount = <?= $table->className ?>::queryCount(
			QQ::andCondition(
				QQ::equal((new QQNode<?= $table->className ?>)-><?= $table->primaryKeyColumnArray[0]->propertyName ?>, $this-><?= $table->primaryKeyColumnArray[0]->variableName ?>),
				QQ::equal((new QQNode<?= $table->className ?>)-><?= $manyToManyReference->objectDescription ?>-><?= $manyToManyReference->oppositePropertyName ?>, $<?= $manyToManyReference->variableName ?>-><?= $manyToManyReferenceTable->primaryKeyColumnArray[0]->propertyName ?>)
			)
		);

		return ($intRowCount > 0);
	}

	/**
	 * Associates a <?= $manyToManyReference->objectDescription ?>

	 * @param <?= $manyToManyReference->variableType ?> $<?= $manyToManyReference->variableName ?>

	 * @return void
	 * @throws UndefinedPrimaryKeyException
	 * @throws CogException
	*/
	public function associate<?= $manyToManyReference->objectDescriptionUppercase ?>(<?= $manyToManyReference->variableType ?> $<?= $manyToManyReference->variableName ?>): void {
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($this->', ')', 'variableName', $table->primaryKeyColumnArray) ?>) {
			throw new UndefinedPrimaryKeyException('Unable to call associate<?= $manyToManyReference->objectDescription ?> on this unsaved <?= $table->className ?>.');
		}
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($' . $manyToManyReference->variableName . '->', ')', 'propertyName', $manyToManyReferenceTable->primaryKeyColumnArray) ?>) {
			throw new UndefinedPrimaryKeyException('Unable to call associate<?= $manyToManyReference->objectDescription ?> on this <?= $table->className ?> with an unsaved <?= $manyToManyReferenceTable->className ?>.');
		}

		// Get the database object for this class
		$database = <?= $table->className ?>::getDatabase();

		// Perform the SQL Query
		$database->nonQuery('
			INSERT INTO <?= $escapeIdentifierBegin ?><?= $manyToManyReference->table ?><?= $escapeIdentifierEnd ?> (
				<?= $escapeIdentifierBegin ?><?= $manyToManyReference->column ?><?= $escapeIdentifierEnd ?>,
				<?= $escapeIdentifierBegin ?><?= $manyToManyReference->oppositeColumn ?><?= $escapeIdentifierEnd ?>

			) VALUES (
				' . $database->sqlVariable($this-><?= $table->primaryKeyColumnArray[0]->variableName ?>) . ',
				' . $database->sqlVariable($<?= $manyToManyReference->variableName ?>-><?= $manyToManyReferenceTable->primaryKeyColumnArray[0]->propertyName ?>) . '
			)
		');
	}

	/**
	 * Unassociates a <?= $manyToManyReference->objectDescription ?>

	 * @param <?= $manyToManyReference->variableType ?> $<?= $manyToManyReference->variableName ?>

	 * @return void
	 * @throws UndefinedPrimaryKeyException
	 * @throws CogException
	*/
	public function unassociate<?= $manyToManyReference->objectDescriptionUppercase ?>(<?= $manyToManyReference->variableType ?> $<?= $manyToManyReference->variableName ?>): void {
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($this->', ')', 'variableName', $table->primaryKeyColumnArray) ?>) {
			throw new UndefinedPrimaryKeyException('Unable to call unassociate<?= $manyToManyReference->objectDescription ?> on this unsaved <?= $table->className ?>.');
		}
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($' . $manyToManyReference->variableName . '->', ')', 'propertyName', $manyToManyReferenceTable->primaryKeyColumnArray) ?>) {
			throw new UndefinedPrimaryKeyException('Unable to call unassociate<?= $manyToManyReference->objectDescription ?> on this <?= $table->className ?> with an unsaved <?= $manyToManyReferenceTable->className ?>.');
		}

		// Get the database object for this class
		$database = <?= $table->className ?>::getDatabase();

		// Perform the SQL Query
		$database->nonQuery('
			DELETE FROM
				<?= $escapeIdentifierBegin ?><?= $manyToManyReference->table ?><?= $escapeIdentifierEnd ?>

			WHERE
				<?= $escapeIdentifierBegin ?><?= $manyToManyReference->column ?><?= $escapeIdentifierEnd ?> = ' . $database->sqlVariable($this-><?= $table->primaryKeyColumnArray[0]->variableName ?>) . ' AND
				<?= $escapeIdentifierBegin ?><?= $manyToManyReference->oppositeColumn ?><?= $escapeIdentifierEnd ?> = ' . $database->sqlVariable($<?= $manyToManyReference->variableName ?>-><?= $manyToManyReferenceTable->primaryKeyColumnArray[0]->propertyName ?>) . '
		');
	}

	/**
	 * Unassociates all <?= $manyToManyReference->objectDescriptionPlural ?>

	 * @return void
	 * @throws UndefinedPrimaryKeyException
	 * @throws CogException
	*/
	public function unassociateAll<?= $manyToManyReference->objectDescriptionPluralUppercase ?>(): void {
		if (<?= $codegen->implodeObjectArray(' || ', 'is_null($this->', ')', 'variableName', $table->primaryKeyColumnArray) ?>) {
			throw new UndefinedPrimaryKeyException('Unable to call unassociateAll<?= $manyToManyReference->objectDescriptionUppercase ?>Array on this unsaved <?= $table->className ?>.');
		}

		// Get the database object for this class
		$database = <?= $table->className ?>::getDatabase();

		// Perform the SQL Query
		$database->nonQuery('
			DELETE FROM
				<?= $escapeIdentifierBegin ?><?= $manyToManyReference->table ?><?= $escapeIdentifierEnd ?>

			WHERE
				<?= $escapeIdentifierBegin ?><?= $manyToManyReference->column ?><?= $escapeIdentifierEnd ?> = ' . $database->sqlVariable($this-><?= $table->primaryKeyColumnArray[0]->variableName ?>) . '
		');
	}
