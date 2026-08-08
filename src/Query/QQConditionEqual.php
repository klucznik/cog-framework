<?php

namespace Cog\Query;

class QQConditionEqual extends QQConditionComparison {

	protected $operator = ' = ';

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' ' . $this->queryNode->getValue($this->mixOperand, $queryBuilder, true));
	}
}
