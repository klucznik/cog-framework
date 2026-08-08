<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var \Cog\Codegen\Index $index */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>
<?php $columnArray = $codegen->getColumnArray($table, $index->columnNameArray); ?>
	/**
	 * Load an array of <?= $table->className ?> objects,
	 * by <?= $codegen->implodeObjectArray(', ', '', '', 'propertyName', $codegen->getColumnArray($table, $index->columnNameArray)) ?> Index(es)
<?php foreach ($columnArray as $column) { ?>
	 * @param ?<?= $column->variableTyped ?> $<?= $column->propertyName ?>

<?php } ?>
	 * @param null|QQClause|QQClause[] $optionalClauses additional optional QQClause objects for this query
	 * @return <?= $table->className ?>[]
	 * @throws CogException
	*/
	public static function loadArrayBy<?= $codegen->implodeObjectArray('', '', '', 'propertyNameUppercase', $columnArray) ?>(<?= $codegen->parameterListFromColumnArray($columnArray) ?>, QQClause|array|null $optionalClauses = null): array {
		$optionalClauses = Utils::extendArray(<?= $table->className ?>::getDefaultOptionalClauses(), $optionalClauses);

		// Call <?= $table->className ?>::queryArray to perform the loadArrayBy<?= $codegen->implodeObjectArray('', '', '', 'propertyNameUppercase', $columnArray) ?> query
		try {
			return <?= $table->className ?>::queryArray(
<?php if (count($columnArray) > 1) { ?>
				QQ::andCondition(
<?php } ?>
<?php foreach ($columnArray as $column) { ?>
				QQ::equal((new QQNode<?= $table->className ?>)-><?= $column->propertyName ?>, $<?= $column->propertyName ?>),
<?php } ?><?php \Cog\Codegen\Utils::goBack(2); ?>
<?php if (count($columnArray) > 1) { ?>
				)
<?php } ?>,
				$optionalClauses);
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
	}

	/**
	 * Count <?= $table->classNamePlural ?>

	 * by <?= $codegen->implodeObjectArray(', ', '', '', 'propertyName', $codegen->getColumnArray($table, $index->columnNameArray)) ?> Index(es)
<?php foreach ($columnArray as $column) { ?>
	 * @param ?<?= $column->variableTyped ?> $<?= $column->propertyName ?>

<?php } ?>
	 * @return int
	 * @throws CogException
	*/
	public static function countBy<?= $codegen->implodeObjectArray('', '', '', 'propertyNameUppercase', $columnArray) ?>(<?= $codegen->parameterListFromColumnArray($columnArray) ?>): int {
		// Call <?= $table->className ?>::queryCount to perform the countBy<?= $codegen->implodeObjectArray('', '', '', 'propertyNameUppercase', $columnArray) ?> query
		return <?= $table->className ?>::queryCount(
<?php if (count($columnArray) > 1) { ?>
			QQ::andCondition(
<?php } ?>
<?php foreach ($columnArray as $column) { ?>
			QQ::equal((new QQNode<?= $table->className ?>)-><?= $column->propertyName ?>, $<?= $column->propertyName ?>),
<?php } ?><?php \Cog\Codegen\Utils::goBack(2); ?>
<?php if (count($columnArray) > 1) { ?>
			)
<?php } ?>

		);
	}
