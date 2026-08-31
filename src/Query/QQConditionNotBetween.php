<?php

namespace Cog\Query;

use Cog;

class QQConditionNotBetween extends QQConditionBetween {

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$operand = $this->operand;
		$operandTwo = $this->operandTwo;
		if ($operand instanceof QQNamedValue) {
			/** @var QQNamedValue $operand */
			/** @var QQNamedValue $operandTwo */
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' NOT BETWEEN ' . $operand->parameter() . ' AND ' . $operandTwo->parameter());
		} else {
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' NOT BETWEEN ' . $queryBuilder->database->sqlVariable($operand) . ' AND ' . $queryBuilder->database->sqlVariable($operandTwo));
		}
	}
}
