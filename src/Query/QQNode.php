<?php

namespace Cog\Query;

use Cog\Database\FieldType;
use Cog\Exceptions\CogException;
use Exception;

class QQNode extends QQBaseNode {

	/**
	 * QQNode constructor.
	 * @param string $name
	 * @param string $propertyName
	 * @param string $type
	 * @param QQBaseNode|null $parentNode
	 */
	public function __construct($name, $propertyName, $type, ?QQBaseNode $parentNode = null) {
		$this->parentNode = $parentNode;
		$this->name = $name;

		if ($parentNode) {
			$parentNode->childNodeArray[$name] = $this;
		}

		$this->alias = $name;
		$this->propertyName = $propertyName;
		$this->type = $type;

		$this->rootTableName = $name;
		if ($parentNode) {
			$this->rootTableName = $parentNode->rootTableName;
		}
	}

	/**
	 * @param mixed $value
	 * @param QueryBuilder $queryBuilder
	 * @param bool|null $equalityType can be null (for no equality), true (to add a standard "equal to") or false (to add a standard "not equal to")
	 * @return string|null
	 * @throws CogException
	 */
	public function getValue($value, QueryBuilder $queryBuilder, ?bool $equalityType = null): ?string {
		if ($value instanceof QQNamedValue) {
			return $value->parameter($equalityType);
		}

		if ($value instanceof self) {
			/** @var QQNode $value */
			if ($equalityType === null) {
				$toReturn = '';
			} elseif ($equalityType) {
				$toReturn = '= ';
			} else {
				$toReturn = '!= ';
			}

			try {
				return $toReturn . $value->getColumnAlias($queryBuilder);
			} catch (CogException $exception) {
				$exception->incrementOffset();
				throw $exception;
			}
		} else {
			if ($equalityType === null) {
				$includeEquality = false;
				$reverseEquality = false;
			} else {
				$includeEquality = true;
				$reverseEquality = true;
				if ($equalityType) {
					$reverseEquality = false;
				}
			}

//			try {
//				return $queryBuilder->database->sqlVariable(Type::cast($value, $this->type), $includeEquality, $reverseEquality);
//			} catch (\Cog\CogException\CogException $exception) {
//		        $exception->incrementOffset();
//			   	$exception->incrementOffset();
//				throw $exception;
//			}

			return $queryBuilder->database->sqlVariable($value, $includeEquality, $reverseEquality);
		}
	}

	public function getAsManualSqlColumn() {
		if ($this->tableName) {
			return $this->tableName . '.' . $this->name;
		}

		if ($this->parentNode && $this->parentNode->tableName) {
			return $this->parentNode->tableName . '.' . $this->name;
		}

		return $this->name;
	}

	public function isTopLevelLeafNode() {
		return (\get_class($this) === 'Cog\Query\QQNode' && null === $this->parentNode->type);
	}

	public function getTable(QueryBuilder $queryBuilder, bool $blnExpandSelection = false, ?QQCondition $objJoinCondition = null, ?QQSelect $objSelect = null) {
		// Make sure our Root Tables Match
		if ($this->rootTableName !== $queryBuilder->rootTableName) {
			throw new CogException(
				'Cannot use Cog\Query\QQNode for "' . $this->rootTableName . '" when querying against the "' . $queryBuilder->rootTableName . '" table', 3
			);
		}

		// If we are a standard Cog\Query\QQNode at the top level column, simply return the column name
		if ($this->isTopLevelLeafNode()) {
			return $this->parentNode->name;
		}

		// Use the Helper to Iterate Through the Parent Chain and get the Parent Alias
		try {
			$strParentAlias = $this->parentNode->getColumnAliasHelper($queryBuilder, $blnExpandSelection, $objSelect ? QQ::select() : null);

			if ($this->tableName) {
				$strJoinTableAlias = $strParentAlias . '__' . ($this->alias ?: $this->name);
				// Next, Join the Appropriate Table
				$this->addJoinTable($queryBuilder, $strJoinTableAlias, $strParentAlias, $objJoinCondition);

				if ($blnExpandSelection) {
					call_user_func([$this->classNameQualified, 'getSelectFields'], $queryBuilder, $strJoinTableAlias, $objSelect);
				}
			}
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}

		return $strParentAlias;
	}

	public function getTableAlias(QueryBuilder $queryBuilder, bool $blnExpandSelection = false, ?QQCondition $objJoinCondition = null, ?QQSelect $objSelect = null) {
		$strTable = $this->getTable($queryBuilder, $blnExpandSelection, $objJoinCondition, $objSelect);
		return $queryBuilder->getTableAlias($strTable);
	}

	/**
	 * @param QueryBuilder $queryBuilder
	 * @param string $tableAlias
	 * @return string
	 */
	public function makeColumnAlias(QueryBuilder $queryBuilder, $tableAlias) {
		$strBegin = $queryBuilder->database->escapeIdentifierBegin;
		$strEnd = $queryBuilder->database->escapeIdentifierEnd;

		return sprintf('%s%s%s.%s%s%s',
			$strBegin, $tableAlias, $strEnd,
			$strBegin, $this->name, $strEnd
		);
	}

	public function getColumnAlias(QueryBuilder $queryBuilder, bool $expandSelection = false, ?QQCondition $joinCondition = null, ?QQSelect $select = null) {
		$strTableAlias = $this->getTableAlias($queryBuilder, $expandSelection, $joinCondition, $select);
		// Pull the Begin and End Escape Identifiers from the Database Adapter
		return $this->makeColumnAlias($queryBuilder, $strTableAlias);
	}

	protected function addJoinTable(QueryBuilder $queryBuilder, $strJoinTableAlias, $strParentAlias, ?QQCondition $objJoinCondition = null) {
		$queryBuilder->addJoinItem($this->tableName, $strJoinTableAlias, $strParentAlias, $this->name, $this->primaryKey, $objJoinCondition);
	}

	public function getColumnAliasHelper(QueryBuilder $queryBuilder, bool $expandSelection, ?QQSelect $select = null) {
		// Are we at the Parent Node?
		if ($this->parentNode === null) {
			// Yep -- Simply return the Parent Node Name
			return $this->name;
		}

		try {
			// No -- First get the Parent Alias
			$strParentAlias = $this->parentNode->getColumnAliasHelper($queryBuilder, $expandSelection, $select ? QQ::select() : null);

			$strJoinTableAlias = $strParentAlias . '__' . $this->alias;
			// Next, Join the Appropriate Table
			$this->addJoinTable($queryBuilder, $strJoinTableAlias, $strParentAlias);
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}

		// Next, Expand the Selection Fields for this Table (if applicable)
		if ($expandSelection) {
			call_user_func([$this->classNameQualified, 'getSelectFields'], $queryBuilder, $strJoinTableAlias, $select);
		}

		// Return the Parent Alias
		return $strParentAlias . '__' . $this->alias;
	}


	// Helpers for Orm-generated DataGrids
	protected function getDataGridHtmlHelper(array $nodeLabel, int $index) {
		if (($index + 1) === \count($nodeLabel)) {
			return $nodeLabel[$index];
		}

		return sprintf('(%s ? %s : null)', $nodeLabel[$index], $this->getDataGridHtmlHelper($nodeLabel, $index + 1));
	}

	public function getDataGridItem() {
		// Array-ify Node Hierarchy
		$nodeArray = [];

		$nodeArray[] = $this;
		while ($nodeArray[\count($nodeArray) - 1]->parentNode) {
			$nodeArray[] = $nodeArray[\count($nodeArray) - 1]->parentNode;
		}

		$nodeArray = array_reverse($nodeArray, false);

		// Go through the objNodeArray to build out the DataGridHtml

		// Error Behavior
		if (count($nodeArray) < 2) {
			throw new Exception('Invalid Cog\Query\QQNode to getDataGridHtml on');
		}
		if (count($nodeArray) === 2) { // Simple Two-Step Node
			$toReturn = '$_ITEM->' . $nodeArray[1]->propertyName;
		}

		// Complex N-Step Node
		else {
			$nodeLabelArray[0] = '$_ITEM->' . $nodeArray[1]->propertyName;
			$count = \count($nodeArray);
			for ($i = 2; $i < $count; $i++) {
				$nodeLabelArray[$i - 1] = $nodeLabelArray[$i - 2] . '->' . $nodeArray[$i]->propertyName;
			}

			$toReturn = $this->getDataGridHtmlHelper($nodeLabelArray, 0);
		}

		return $toReturn;
	}

	public function getDataGridHtml() {
		$toReturn = $this->getDataGridItem();

		if ($this->type === FieldType::TIME) {
			return sprintf('(%s) ? %s->toTimeString() : null', $toReturn, $toReturn);
		}

		if ($this->type === FieldType::DATE) {
			return sprintf('(%s) ? %s->toDateString() : null', $toReturn, $toReturn);
		}

		if ($this->type === FieldType::BIT) {
			return sprintf('(null === %s)? "" : ((%s)? "%s" : "%s")', $toReturn, $toReturn, 'true', 'false');
		}

		if (class_exists($this->classNameQualified)) {
			return sprintf('(%s) ? %s->__toString() : null;', $toReturn, $toReturn);
		}

		return $toReturn;
	}

	public function getDataGridOrderByNode() {
		if ($this instanceof QQReverseReferenceNode) {
			return $this->primaryKeyNode;
		}
		return $this;
	}
}
