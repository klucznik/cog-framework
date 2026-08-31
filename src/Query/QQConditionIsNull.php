<?php

namespace Cog\Query;

use Cog\Exceptions\InvalidCastException;

class QQConditionIsNull extends QQConditionComparison {

	public function __construct(QQNode $queryNode) {
		$this->queryNode = $queryNode;
		if (!$queryNode->isColumnBased()) {
			throw new InvalidCastException('Unable to cast "' . $queryNode->getNodeName() . '" table to Column-based QQNode', 3);
		}
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' IS NULL');
	}
}
