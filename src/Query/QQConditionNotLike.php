<?php

namespace Cog\Query;

class QQConditionNotLike extends QQConditionLike {

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$mixOperand = $this->mixOperand;
		if ($mixOperand instanceof QQNamedValue) {
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' NOT LIKE ' . $mixOperand->parameter());
		} else {
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' NOT LIKE ' . $queryBuilder->database->sqlVariable($mixOperand));
		}
	}
}
