<?php

namespace Cog\Query;

class QQConditionExists extends QQCondition {

	protected QQSubQueryNode $node;

	public function __construct(QQSubQueryNode $subQueryDefinition) {
		$this->node = $subQueryDefinition;
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addWhereItem('(EXISTS (' . $this->node->getColumnAlias($queryBuilder) . '))');
	}
}
