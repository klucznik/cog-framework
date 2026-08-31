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

	public function isTopLevelLeafNode() {
		return (\get_class($this) === 'Cog\Query\QQNode' && null === $this->parentNode->type);
	}

	public function getTable(QueryBuilder $queryBuilder, bool $expandSelection = false, ?QQCondition $joinCondition = null, ?QQSelect $select = null) {
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
			$parentAlias = $this->parentNode->getColumnAliasHelper($queryBuilder, $expandSelection, $select ? QQ::select() : null);

			if ($this->tableName) {
				$joinTableAlias = $parentAlias . '__' . ($this->alias ?: $this->name);
				// Next, Join the Appropriate Table
				$this->addJoinTable($queryBuilder, $joinTableAlias, $parentAlias, $joinCondition);

				if ($expandSelection && !$queryBuilder->suppressSelectExpansion) {
					call_user_func([$this->classNameQualified, 'getSelectFields'], $queryBuilder, $joinTableAlias, $select);
				}
			}
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}

		return $parentAlias;
	}

	public function getTableAlias(QueryBuilder $queryBuilder, bool $expandSelection = false, ?QQCondition $joinCondition = null, ?QQSelect $select = null) {
		$table = $this->getTable($queryBuilder, $expandSelection, $joinCondition, $select);
		return $queryBuilder->getTableAlias($table);
	}

	/**
	 * @param QueryBuilder $queryBuilder
	 * @param string $tableAlias
	 * @return string
	 */
	public function makeColumnAlias(QueryBuilder $queryBuilder, $tableAlias) {
		$begin = $queryBuilder->database->escapeIdentifierBegin;
		$end = $queryBuilder->database->escapeIdentifierEnd;

		return sprintf('%s%s%s.%s%s%s',
			$begin, $tableAlias, $end,
			$begin, $this->name, $end
		);
	}

	public function getColumnAlias(QueryBuilder $queryBuilder, bool $expandSelection = false, ?QQCondition $joinCondition = null, ?QQSelect $select = null) {
		$tableAlias = $this->getTableAlias($queryBuilder, $expandSelection, $joinCondition, $select);
		// Pull the Begin and End Escape Identifiers from the Database Adapter
		return $this->makeColumnAlias($queryBuilder, $tableAlias);
	}

	protected function addJoinTable(QueryBuilder $queryBuilder, $joinTableAlias, $parentAlias, ?QQCondition $joinCondition = null) {
		$queryBuilder->addJoinItem($this->tableName, $joinTableAlias, $parentAlias, $this->name, $this->primaryKey, $joinCondition);
	}

	public function getColumnAliasHelper(QueryBuilder $queryBuilder, bool $expandSelection, ?QQSelect $select = null) {
		// Are we at the Parent Node?
		if ($this->parentNode === null) {
			// Yep -- Simply return the Parent Node Name
			return $this->name;
		}

		try {
			// No -- First get the Parent Alias
			$parentAlias = $this->parentNode->getColumnAliasHelper($queryBuilder, $expandSelection, $select ? QQ::select() : null);

			$joinTableAlias = $parentAlias . '__' . $this->alias;
			// Next, Join the Appropriate Table
			$this->addJoinTable($queryBuilder, $joinTableAlias, $parentAlias);
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}

		// Next, Expand the Selection Fields for this Table (if applicable)
		if ($expandSelection && !$queryBuilder->suppressSelectExpansion) {
			call_user_func([$this->classNameQualified, 'getSelectFields'], $queryBuilder, $joinTableAlias, $select);
		}

		// Return the Parent Alias
		return $parentAlias . '__' . $this->alias;
	}

}
