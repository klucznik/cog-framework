<?php

namespace Cog\Query;

use Cog;

class QQExpandVirtualNode extends QQClause {

	/** @var QQVirtualNode */
	protected $node;

	public function __construct(QQVirtualNode $node) {
		$this->node = $node;
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		try {
			$queryBuilder->addSelectFunction(null, $this->node->getColumnAlias($queryBuilder), $this->node->getAttributeName());
		} catch (Cog\Exceptions\CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
	}

	/** @inheritdoc */
	public function __toString(): string {
		return 'Cog\Query\QQExpandVirtualNode Clause';
	}
}
