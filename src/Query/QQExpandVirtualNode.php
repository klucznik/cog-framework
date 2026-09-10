<?php

namespace Cog\Query;

use Cog;

class QQExpandVirtualNode extends QQClause {

	protected QQVirtualNode $node;

	public function __construct(QQVirtualNode $node) {
		$this->node = $node;
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addSelectFunction(null, $this->node->getColumnAlias($queryBuilder), $this->node->getAttributeName());
	}

	/** @inheritdoc */
	public function __toString(): string {
		return 'Cog\Query\QQExpandVirtualNode Clause';
	}
}
