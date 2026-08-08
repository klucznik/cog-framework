<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
/** @var \Cog\Codegen\ManyToManyReference $manyToManyReference */
?>
/**
	 * Load an array of <?= $manyToManyReference->variableType ?> objects for a given <?= $manyToManyReference->objectDescription ?>

	 * via the <?= $manyToManyReference->table ?> table
	 * @param <?= $manyToManyReference->oppositeVariableTyped ?> $<?= $manyToManyReference->oppositeVariableName ?>

	 * @param null|QQClause|QQClause[] $optionalClauses additional optional QQClause objects for this query
	 * @param ?array $parameterArray
	 * @return <?= $table->className ?>[]
	 * @throws CogException
	*/
	public static function loadArrayBy<?= $manyToManyReference->objectDescriptionUppercase ?>(<?= $manyToManyReference->oppositeVariableTyped ?> $<?= $manyToManyReference->oppositeVariableName ?>, QQClause|array|null $optionalClauses = null, ?array $parameterArray = null): array {
		$optionalClauses = Utils::extendArray(<?= $table->className ?>::getDefaultOptionalClauses(), $optionalClauses);

		// Call <?= $table->className ?>::queryArray to perform the loadArrayBy<?= $manyToManyReference->objectDescriptionUppercase ?> query
		try {
			return <?= $table->className ?>::queryArray(
				QQ::equal((new QQNode<?= $table->className ?>)-><?= $manyToManyReference->objectDescription ?>-><?= $manyToManyReference->oppositePropertyName ?>, $<?= $manyToManyReference->oppositeVariableName ?>),
				$optionalClauses,
				$parameterArray
			);
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
	}

	/**
	 * Count <?= $table->classNamePlural ?> for a given <?= $manyToManyReference->objectDescription ?>

	 * via the <?= $manyToManyReference->table ?> table
	 * @param <?= $manyToManyReference->oppositeVariableTyped ?> $<?= $manyToManyReference->oppositeVariableName ?>

	 * @return int
	 * @throws CogException
	*/
	public static function countBy<?= $manyToManyReference->objectDescriptionUppercase ?>(<?= $manyToManyReference->oppositeVariableTyped ?> $<?= $manyToManyReference->oppositeVariableName ?>): int {
		return <?= $table->className ?>::queryCount(
			QQ::equal((new QQNode<?= $table->className ?>)-><?= $manyToManyReference->objectDescription ?>-><?= $manyToManyReference->oppositePropertyName ?>, $<?= $manyToManyReference->oppositeVariableName ?>)
		);
	}
