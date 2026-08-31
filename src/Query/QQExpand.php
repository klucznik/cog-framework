<?php

namespace Cog\Query;

use Cog;
use Cog\Exceptions\InvalidCastException;

class QQExpand extends QQClause {

	protected QQNode $node;

	protected ?QQCondition $joinCondition = null;

	protected ?QQSelect $select = null;

	/**
	 * QQExpand constructor.
	 * @param QQNode $node
	 * @param QQCondition|null $joinCondition
	 * @param QQSelect|null $select
	 * @throws \Cog\Exceptions\CogException
	 * @throws InvalidCastException
	 */
	public function __construct($node, ?QQCondition $joinCondition = null, ?QQSelect $select = null) {
		// Check against root and table QQNodes
		if ($node instanceof QQAssociationNode) {
			throw new Cog\Exceptions\CogException('Expand clause parameter cannot be the association table\'s QQNode, itself', 2);
		}

		if (!($node instanceof QQNode)) {
			throw new Cog\Exceptions\CogException('Expand clause parameter must be a QQNode object', 2);
		}

		if (!$node->isColumnBased()) {
			throw new InvalidCastException('Unable to cast "' . $node->getNodeName() . '" table to Column-based QQNode', 3);
		}

		$this->node = $node;
		$this->joinCondition = $joinCondition;
		$this->select = $select;
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$this->node->getColumnAlias($queryBuilder, true, $this->joinCondition, $this->select);
	}

	/** @inheritdoc */
	public function __toString(): string {
		return 'Cog\Query\QQExpand Clause';
	}
}
