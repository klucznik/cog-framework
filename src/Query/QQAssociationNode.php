<?php

namespace Cog\Query;

use Cog\Exceptions\InvalidCastException;

/**
 * @property-read QQNode $_childTableNode
 */
class QQAssociationNode extends QQBaseNode {

	public function __construct(QQBaseNode $parentNode) {
		$this->parentNode = $parentNode;
		if ($parentNode) {
			$this->rootTableName = $parentNode->rootTableName;
			$parentNode->childNodeArray[$this->name] = $this;
		} else {
			$this->rootTableName = $this->name;
		}
		$this->alias = $this->name;
	}

	/**
	 * An association node is a hop, never a column: it names the join table, which
	 * has nothing to select. Every clause that takes a column rejects one before it
	 * gets here - QQConditionComparison, QQGroupBy, QQAggregationClause and QQExpand
	 * all check - and QQExpandAsArray deliberately asks the child table node
	 * instead. The abstract on QQBaseNode still has to be satisfied, so this states
	 * the rule rather than carrying an unreachable copy of QQNode's implementation.
	 */
	public function getColumnAlias(QueryBuilder $queryBuilder, bool $expandSelection = false, ?QQCondition $joinCondition = null, ?QQSelect $select = null) {
		throw new InvalidCastException('Unable to cast "' . $this->name . '" association to a Column-based QQNode', 3);
	}

	public function getColumnAliasHelper(QueryBuilder $queryBuilder, bool $expandSelection, ?QQSelect $select = null) {
		// Are we at the Parent Node?
		if ($this->parentNode === null) {
			// Yep -- Simply return the Parent Node Name
			return $this->name;
		}

		// No -- First get the Parent Alias
		$parentAlias = $this->parentNode->getColumnAliasHelper($queryBuilder, $expandSelection, $select ? QQ::select() : null);

		// Next, Join the Appropriate Table
		$queryBuilder->addJoinItem($this->tableName, $parentAlias . '__' . $this->alias,
			$parentAlias, $this->parentNode->primaryKey, $this->primaryKey);

		// Next, Expand the Selection Fields for this Table (if applicable)
		// TODO: If/when we add assn-based attributes, possibly add selectionfields addition here?
//				if ($blnExpandSelection) {
//					call_user_func([$this->strClassName, 'GetSelectFields'], $builder, $parentAlias . '__' . $this->strName);
//				}

		// Return the Parent Alias
		return $parentAlias . '__' . $this->alias;
	}
}
