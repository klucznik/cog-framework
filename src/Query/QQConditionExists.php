<?php

namespace Cog\Query;

class QQConditionExists extends QQCondition {

	protected $objNode;

	public function __construct(QQSubQueryNode $objSubQueryDefinition) {
		$this->objNode = $objSubQueryDefinition;
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addWhereItem('(EXISTS (' . $this->objNode->getColumnAlias($queryBuilder) . '))');
	}
}
