<?php

namespace Cog\Query;

class QQConditionNotExists extends QQCondition {

	protected QQSubQueryNode $node;

	public function __construct(QQSubQueryNode $subQueryDefinition) {
		$this->node = $subQueryDefinition;
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addWhereItem('(NOT EXISTS (' . $this->node->getColumnAlias($queryBuilder) . '))');
	}
}
