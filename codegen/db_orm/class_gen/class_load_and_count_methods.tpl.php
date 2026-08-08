<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>
///////////////////////////////
	// CLASS-WIDE LOAD AND COUNT METHODS
	///////////////////////////////

	/**
	 * Static method to retrieve the Database object that owns this class.
	 * @return \Cog\Database\Base reference to the Database object that can query this class
	 */
	public static function getDatabase(): \Cog\Database\Base {
		return Database::$databases[<?= $codegen->databaseIndex ?>];
	}

	/**
	 * Load <?= $table->className ?> from PK Info
<?php foreach ($table->columnArray as $column) { ?>
<?php if ($column->primaryKey) { ?>
	 * @param ?<?= $column->variableTyped ?> $<?= $column->propertyName ?><?= "\n"?>
<?php } ?>
<?php } ?>
	 * @param null|QQClause|QQClause[] $optionalClauses additional optional QQClause objects for this query
	 * @return <?= $table->className ?>|null<?= "\n"?>
	 * @throws CogException
	*/
	public static function load(<?= $codegen->parameterListFromColumnArray($table->primaryKeyColumnArray) ?>, QQClause|array|null $optionalClauses = null): ?<?= $table->className ?> {
		$optionalClauses = Utils::extendArray(<?= $table->className ?>::getDefaultOptionalClauses(), $optionalClauses);

		// Use querySingle to Perform the Query
		return <?= $table->className ?>::querySingle(
			QQ::andCondition(
<?php foreach ($table->primaryKeyColumnArray as $column) { ?>
				QQ::equal((new QQNode<?= $table->className ?>)-><?= $column->propertyName ?>, $<?= $column->propertyName ?>),
<?php } ?><?php \Cog\Codegen\Utils::goBack(2); ?>

			),
			$optionalClauses
		);
	}

	/**
	 * Load all <?= $table->classNamePlural ?>

	 * @param null|QQClause|QQClause[] $optionalClauses additional optional QQClause objects for this query
	 * @return <?= $table->className ?>[]<?= "\n"?>
	 * @throws CogException
	 */
	public static function loadAll(QQClause|array|null $optionalClauses = null): array {
		$optionalClauses = Utils::extendArray(<?= $table->className ?>::getDefaultOptionalClauses(), $optionalClauses);

		if (func_num_args() > 1) {
			throw new CogException('loadAll must be called with an array of optional clauses as a single argument');
		}
		// Call <?= $table->className ?>::queryArray to perform the loadAll query
		try {
			return <?= $table->className ?>::queryArray(QQ::all(), $optionalClauses);
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
	}

	/**
	 * Count all <?= $table->classNamePlural ?>

	 * @return int
	 * @throws CogException
	 */
	public static function countAll(): int {
		// Call <?= $table->className ?>::queryCount to perform the countAll query
		return <?= $table->className ?>::queryCount(QQ::all());
	}
