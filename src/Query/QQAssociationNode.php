<?php

namespace Cog\Query;

use Cog;

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

	public function getColumnAlias(QueryBuilder $queryBuilder, bool $expandSelection = false, ?QQCondition $joinCondition = null, ?QQSelect $select = null) {
		// Make sure our Root Tables Match
		if ($this->rootTableName !== $queryBuilder->rootTableName) {
			throw new Cog\Exceptions\CogException('Cannot use QQNode for "' . $this->rootTableName . '" when querying against the "' . $queryBuilder->rootTableName . '" table', 3);
		}

		// Pull the Begin and End Escape Identifiers from the Database Adapter
		$begin = $queryBuilder->database->escapeIdentifierBegin;
		$end = $queryBuilder->database->escapeIdentifierEnd;

		// If we are a standard QQNode at the top level column, simply return the column name
		if (get_class($this) === 'Cog\Query\QQNode' &&  $this->parentNode->type === null) {
			return sprintf('%s%s%s.%s%s%s', $begin, $this->parentNode->name, $end, $begin, $this->name, $end);
		}

		// Use the Helper to Iterate Through the Parent Chain and get the Parent Alias
		$strParentAlias = $this->parentNode->getColumnAliasHelper($queryBuilder, $expandSelection, $select ? QQ::select() : null);

		if ($this->tableName) {
			// Next, Join the Appropriate Table
			$queryBuilder->addJoinItem($this->tableName, $strParentAlias . '__' . $this->name,
				$strParentAlias, $this->name, $this->primaryKey);

			if ($expandSelection) {
				call_user_func([$this->classNameQualified, 'getSelectFields'], $queryBuilder, $strParentAlias . '__' . $this->name, $select);
			}
		}

		// Finally, return the final column alias name (Parent Prefix with Current Node Name)
		return sprintf(
			'%s%s%s.%s%s%s',
			$begin,
			$strParentAlias,
			$end,
			$begin,
			$this->name,
			$end
		);
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
//					call_user_func([$this->strClassName, 'GetSelectFields'], $objBuilder, $parentAlias . '__' . $this->strName);
//				}

		// Return the Parent Alias
		return $parentAlias . '__' . $this->alias;
	}

	public function getExpandArrayAlias() {
		$node = $this;
		$childTableNode = $this->_childTableNode;
		$toReturn = $childTableNode->name . '__' . $childTableNode->primaryKey;

		while ($node) {
			$toReturn = $node->name . '__' . $toReturn;
			$node = $node->parentNode;
		}

		return $toReturn;
	}
}
