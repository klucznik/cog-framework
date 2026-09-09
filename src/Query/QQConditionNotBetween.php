<?php

namespace Cog\Query;

class QQConditionNotBetween extends QQConditionBetween {

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' NOT BETWEEN ' . $this->boundSql($this->operand, $queryBuilder) . ' AND ' . $this->boundSql($this->operandTwo, $queryBuilder));
	}
}
