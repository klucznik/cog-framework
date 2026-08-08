<?php

namespace Cog\Query;

use Cog;

class QQExpandAsArray extends QQClause {

	/** @var QQAssociationNode|QQReverseReferenceNode */
	protected $node;

	/** @var QQSelect|null */
	protected $select;

	public function __construct($node, ?QQSelect $select = null) {
		// Ensure that this is an Cog\Query\QQAssociationNode
		if (!$node instanceof QQAssociationNode && !$node instanceof QQReverseReferenceNode) {
			throw new Cog\Exceptions\CogException('ExpandAsArray clause parameter must be an Association Table-based QQNode', 2);
		}

		$this->node = $node;
		$this->select = $select;
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		if ($this->node instanceof QQAssociationNode) {
			$this->node->_childTableNode->getColumnAlias($queryBuilder, true, null, $this->select);
		} else {
			$this->node->getColumnAlias($queryBuilder, true, null, $this->select);
		}
		$queryBuilder->addExpandAsArrayNode($this->node);
	}

	/** @inheritdoc */
	public function __toString(): string {
		return 'Cog\Query\QQExpandAsArray Clause';
	}
}
