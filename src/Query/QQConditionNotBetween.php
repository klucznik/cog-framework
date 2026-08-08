<?php

namespace Cog\Query;

use Cog;

class QQConditionNotBetween extends QQConditionBetween {

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$mixOperand = $this->mixOperand;
		$mixOperandTwo = $this->mixOperandTwo;
		if ($mixOperand instanceof QQNamedValue) {
			/** @var QQNamedValue $mixOperand */
			/** @var QQNamedValue $mixOperandTwo */
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' NOT BETWEEN ' . $mixOperand->parameter() . ' AND ' . $mixOperandTwo->parameter());
		} else {
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' NOT BETWEEN ' . $queryBuilder->database->sqlVariable($mixOperand) . ' AND ' . $queryBuilder->database->sqlVariable($mixOperandTwo));
		}
	}
}
