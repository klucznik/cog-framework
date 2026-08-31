<?php

namespace Cog\Query;

use Cog\Exceptions\InvalidCastException;

abstract class QQAggregationClause extends QQClause {

	/** The constructor rejects anything that is not a column-based QQNode. */
	protected QQNode $node;
	protected string $attributeName;
	/** Set by each concrete subclass. */
	protected string $functionName;

	/**
	 * QQAggregationClause constructor.
	 * @param QQBaseNode $node
	 * @param string $attributeName
	 * @throws \Cog\Exceptions\CogException
	 * @throws InvalidCastException
	 */
	public function __construct(QQBaseNode $node, string $attributeName) {
		// Check against root and table QQNodes
		if ($node instanceof QQAssociationNode) {
			throw new \Cog\Exceptions\CogException('Expand clause parameter cannot be the association table\'s QQNode, itself', 2);
		}

		if (!($node instanceof QQNode)) {
			throw new \Cog\Exceptions\CogException('Expand clause parameter must be a QQNode object', 2);
		}

		if (!$node->isColumnBased()) {
			throw new InvalidCastException('Unable to cast "' . $node->getNodeName() . '" table to Column-based QQNode', 3);
		}

		$this->node = $node;
		$this->attributeName = $attributeName;
	}


	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addSelectFunction($this->functionName, $this->node->getColumnAlias($queryBuilder), $this->attributeName);
	}
}
