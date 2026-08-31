<?php

namespace Cog\Query;

class QQConditionNotLike extends QQConditionLike {

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$operand = $this->operand;
		if ($operand instanceof QQNamedValue) {
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' NOT LIKE ' . $operand->parameter());
		} else {
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' NOT LIKE ' . $queryBuilder->database->sqlVariable($operand));
		}
	}
}
