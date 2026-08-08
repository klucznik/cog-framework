<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var \Cog\Codegen\Index $index */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */

$columnArray = $codegen->getColumnArray($table, $index->columnNameArray);
?>
	/**
	 * Load a single <?= $table->className ?> object,
	 * by <?= $codegen->implodeObjectArray(', ', '', '', 'propertyName', $codegen->getColumnArray($table, $index->columnNameArray)) ?> Index(es)
<?php foreach ($columnArray as $column) { ?>
	 * @param ?<?= $column->variableTyped ?> $<?= $column->propertyName ?>

<?php } ?>
	 * @param null|QQClause|QQClause[] $optionalClauses additional optional QQClause objects for this query
	 * @throws CogException
	 * @return ?<?= $table->className ?><?= "\n"?>
	*/
	public static function loadBy<?= $codegen->implodeObjectArray('', '', '', 'propertyNameUppercase', $columnArray) ?>(<?= $codegen->parameterListFromColumnArray($columnArray) ?>, QQClause|array|null $optionalClauses = null): ?<?= $table->className ?> {
		$optionalClauses = Utils::extendArray(<?= $table->className ?>::getDefaultOptionalClauses(), $optionalClauses);

		return <?= $table->className ?>::querySingle(
			QQ::andCondition(
<?php foreach ($columnArray as $column) { ?>
				QQ::equal((new QQNode<?= $table->className ?>)-><?= $column->propertyName ?>, $<?= $column->propertyName ?>),
<?php } ?><?php \Cog\Codegen\Utils::goBack(2); ?>

			),
			$optionalClauses
		);
	}
