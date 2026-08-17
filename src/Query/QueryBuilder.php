<?php

namespace Cog\Query;

use Cog;

/**
 * Cog\Query\QueryBuilder class
 * @property-read Cog\Database\Base $database
 * @property-read string $rootTableName
 * @property-read string[] $columnAliasArray
 * @property-read QQBaseNode $expandAsArrayNode
 * @property-read bool $suppressSelectExpansion
 */
class QueryBuilder extends Cog\Base {
	/** @var string[] */
	protected array $selectArray = [];

	/** @var string[] plain column expressions that must join the GROUP BY under ONLY_FULL_GROUP_BY */
	protected array $groupBySelectArray = [];

	/** @var string[] */
	protected array $columnAliasArray = [];
	protected int $columnAliasCount = 0;

	/** @var string[] */
	protected array $tableAliasArray = [];
	protected int $tableAliasCount = 0;

	/** @var string[] */
	protected array $fromArray = [];
	/** @var string[] */
	protected array $joinArray = [];
	/** @var string[] */
	protected array $joinConditionArray = [];
	/** @var string[] */
	protected array $whereArray = [];
	/** @var string[] */
	protected array $orderByArray = [];
	/** @var string[] */
	protected array $groupByArray = [];
	/** @var string[] */
	protected array $havingArray = [];
	/** @var QQVirtualNode[] */
	protected array $virtualNodeArray = [];
	/** @var string */
	protected $limitInfo;
	/** @var bool */
	protected $distinctFlag;
	/** @var QQBaseNode */
	protected $expandAsArrayNode;
	/** @var bool */
	protected $countOnlyFlag;
	/** @var bool */
	protected $aggregationFlag;

	/** @var Cog\Database\Base */
	protected $database;

	/** @var string */
	protected $rootTableName;

	/** @var string */
	protected $escapeIdentifierBegin;
	/** @var string */
	protected $escapeIdentifierEnd;

	/**
	 * QueryBuilder constructor.
	 * @param Cog\Database\Base $database
	 * @param $rootTableName
	 */
	public function __construct(Cog\Database\Base $database, $rootTableName) {
		$this->database = $database;
		$this->escapeIdentifierBegin = $database->escapeIdentifierBegin;
		$this->escapeIdentifierEnd = $database->escapeIdentifierEnd;
		$this->rootTableName = $rootTableName;
	}

	/**
	 * @param string $tableName
	 * @param string $columnName
	 * @param string $fullAlias
	 */
	public function addSelectItem($tableName, $columnName, $fullAlias): void {
		$tableAlias = $this->getTableAlias($tableName);

		if (array_key_exists($fullAlias, $this->columnAliasArray)) {
			$columnAlias = $this->columnAliasArray[$fullAlias];
		} else {
			$columnAlias = 'a' . $this->columnAliasCount++;
			$this->columnAliasArray[$fullAlias] = $columnAlias;
		}

		$expression = sprintf('%s%s%s.%s%s%s',
			$this->escapeIdentifierBegin, $tableAlias, $this->escapeIdentifierEnd,
			$this->escapeIdentifierBegin, $columnName, $this->escapeIdentifierEnd);

		// Under ONLY_FULL_GROUP_BY every selected plain column has to appear in the
		// GROUP BY clause; getStatement() merges these in. The query methods only add
		// columns to an aggregate query when the full primary key is grouped, so the
		// extra grouping terms leave the groups unchanged.
		if ($this->aggregationFlag && $this->database->onlyFullGroupBy) {
			$this->groupBySelectArray[] = $expression;
		}

		$this->selectArray[$fullAlias] = sprintf('%s AS %s%s%s',
			$expression,
			$this->escapeIdentifierBegin, $columnAlias, $this->escapeIdentifierEnd);
	}

	/**
	 * @param string $functionName
	 * @param string $columnName
	 * @param string $fullAlias
	 */
	public function addSelectFunction($functionName, $columnName, $fullAlias): void {
		$this->selectArray[$fullAlias] = sprintf(
			'%s(%s) AS %s__%s%s',
			$functionName,
			$columnName,
			$this->escapeIdentifierBegin,
			$fullAlias,
			$this->escapeIdentifierEnd
		);
	}

	/**
	 * @param string $tableName
	 */
	public function addFromItem($tableName): void {
		$tableAlias = $this->getTableAlias($tableName);

		$this->fromArray[$tableName] = sprintf('%s%s%s AS %s%s%s',
			$this->escapeIdentifierBegin, $tableName, $this->escapeIdentifierEnd,
			$this->escapeIdentifierBegin, $tableAlias, $this->escapeIdentifierEnd);
	}

	/**
	 * @param string $tableName
	 * @return mixed|string
	 */
	public function getTableAlias($tableName) {
		if (!array_key_exists($tableName, $this->tableAliasArray)) {
			$tableAlias = 't' . $this->tableAliasCount++;
			$this->tableAliasArray[$tableName] = $tableAlias;
			return $tableAlias;
		}

		return $this->tableAliasArray[$tableName];
	}

	/**
	 * @param string $joinTableName
	 * @param string $joinTableAlias
	 * @param string $tableName
	 * @param string $columnName
	 * @param string $linkedColumnName
	 * @param QQCondition|null $joinCondition
	 * @throws \Cog\Exceptions\CogException
	 */
	public function addJoinItem($joinTableName, $joinTableAlias, $tableName, $columnName, $linkedColumnName, ?QQCondition $joinCondition = null): void {
		$joinItem = sprintf('LEFT JOIN %s%s%s AS %s%s%s ON %s%s%s.%s%s%s = %s%s%s.%s%s%s',
			$this->escapeIdentifierBegin, $joinTableName, $this->escapeIdentifierEnd,
			$this->escapeIdentifierBegin, $this->getTableAlias($joinTableAlias), $this->escapeIdentifierEnd,

			$this->escapeIdentifierBegin, $this->getTableAlias($tableName), $this->escapeIdentifierEnd,
			$this->escapeIdentifierBegin, $columnName, $this->escapeIdentifierEnd,

			$this->escapeIdentifierBegin, $this->getTableAlias($joinTableAlias), $this->escapeIdentifierEnd,
			$this->escapeIdentifierBegin, $linkedColumnName, $this->escapeIdentifierEnd);

		$joinIndex = $joinItem;
		try {
			$conditionClause = null;
			if ($joinCondition && $conditionClause = $joinCondition->getWhereClause($this, false)) {
				$joinItem .= ' AND ' . $conditionClause;
			}
		} catch (Cog\Exceptions\CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}

		/* If this table has already been joined, then we need to check for the following:
			1. Condition wasn't specified before and we aren't specifying one now
				Do Nothing --b/c nothing was changed or updated
			2. Condition wasn't specified before but we ARE specifying one now
				Update the indexed item in the joinArray with the new JoinItem WITH Condition
			3. Condition WAS specified before but we aren't specifying one now
				Do Nothing -- we need to keep the old condition intact
			4. Condition WAS specified before and we are specifying the SAME one now
				Do Nothing --b/c nothing was changed or updated
			5. Condition WAS specified before and we are specifying a DIFFERENT one now
				Do Nothing -- we need to keep the old condition intact
				TODO: throw an exception of mismatched conditions -- but this could be too intensive from a code processing standpoint
		*/
		if (array_key_exists($joinIndex, $this->joinArray)) {
			// Case 1 and 2
			if (!array_key_exists($joinIndex, $this->joinConditionArray)) {

				// Case 1
				if (!$conditionClause) {
					return;
				}

				// Case 2
				$this->joinArray[$joinIndex] = $joinItem;
				$this->joinConditionArray[$joinIndex] = $conditionClause;
				return;
			}

			// Case 3
			if (!$conditionClause) {
				return;
			}

			// Case 4
			if ($conditionClause === $this->joinConditionArray[$joinIndex]) {
				return;
			}

			// Case 5
			throw new Cog\Exceptions\CogException('You have two different Join Conditions on the same Expanded Table: ' . $joinIndex . "\r\n[" . $this->joinConditionArray[$joinIndex] . ']   vs.   [' . $conditionClause . ']');
		}

		// Create the new JoinItem in the JoinArray
		$this->joinArray[$joinIndex] = $joinItem;

		// If there is a condition, record that condition against this JoinIndex
		if ($conditionClause) {
			$this->joinConditionArray[$joinIndex] = $conditionClause;
		}
	}

	/**
	 * @param string $joinTableName
	 * @param string $joinTableAlias
	 * @param QQCondition $joinCondition
	 * @throws \Cog\Exceptions\CogException
	 */
	public function addJoinCustomItem($joinTableName, $joinTableAlias, QQCondition $joinCondition): void {
		$joinItem = sprintf('LEFT JOIN %s%s%s AS %s%s%s ON ',
			$this->escapeIdentifierBegin, $joinTableName, $this->escapeIdentifierEnd,
			$this->escapeIdentifierBegin, $this->getTableAlias($joinTableAlias), $this->escapeIdentifierEnd
		);

		try {
			if ($conditionClause = $joinCondition->getWhereClause($this, true)) {
				$joinItem .= ' AND ' . $conditionClause;
			}
		} catch (Cog\Exceptions\CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}

		$this->joinArray[$joinItem] = $joinItem;
	}

	/**
	 * @param string $sql
	 */
	public function addJoinCustomSqlItem($sql): void {
		$this->joinArray[$sql] = $sql;
	}

	/**
	 * @param string $item
	 */
	public function addWhereItem($item): void {
		$this->whereArray[] = $item;
	}

	/**
	 * @param string $item
	 */
	public function addOrderByItem($item): void {
		$this->orderByArray[] = $item;
	}

	/**
	 * @param string $item
	 */
	public function addGroupByItem($item): void {
		$this->groupByArray[] = $item;
	}

	/**
	 * @param $item
	 */
	public function addHavingItem($item): void {
		$this->havingArray[] = $item;
	}

	/**
	 * @param string $limitInfo
	 */
	public function setLimitInfo($limitInfo): void {
		$this->limitInfo = $limitInfo;
	}

	public function setDistinctFlag(): void {
		$this->distinctFlag = true;
	}

	public function setCountOnlyFlag(): void {
		$this->countOnlyFlag = true;
	}

	public function setAggregationFlag(): void {
		$this->aggregationFlag = true;
	}

	/**
	 * @param string $name
	 * @param QQSubQueryNode $node
	 */
	public function setVirtualNode($name, QQSubQueryNode $node): void {
		$this->virtualNodeArray[strtolower(trim($name))] = $node;
	}

	/**
	 * @param string $name
	 * @return QQVirtualNode|mixed
	 * @throws \Cog\Exceptions\CogException
	 */
	public function getVirtualNode($name) {
		$name = strtolower(trim($name));
		if (array_key_exists($name, $this->virtualNodeArray)) {
			return $this->virtualNodeArray[$name];
		}
		throw new Cog\Exceptions\CogException('Undefined Virtual Node: ' . $name);
	}


	/**
	 * @param QQReverseReferenceNode|QQAssociationNode|QQBaseNode $node
	 * @throws \Cog\Exceptions\CogException
	 */
	public function addExpandAsArrayNode($node): void {

		// build child nodes and find top node of given node
		$node->expandAsArray = true;
		while ($node->parentNode) {
			$node = $node->parentNode;
		}

		if ($this->expandAsArrayNode) {
			// integrate the information into current nodes
			$this->expandAsArrayNode->mergeExpansionNode($node);
		} else {
			$this->expandAsArrayNode = $node;
		}
	}

	/**
	 * @return string
	 */
	public function getStatement(): string {
		// SELECT Clause
		if ($this->countOnlyFlag) {
			if ($this->distinctFlag) {
				$sql = "SELECT\r\n    COUNT(*) AS q_row_count\r\n" . 'FROM    (SELECT DISTINCT ';
				$sql .= '    ' . implode(",\r\n    ", $this->selectArray);
			} else {
				$sql = "SELECT\r\n    COUNT(*) AS q_row_count\r\n";
			}
		} else {
			$sql = "SELECT\r\n";

			if ($this->distinctFlag) {
				$sql = "SELECT DISTINCT\r\n";
			}

			if ($this->limitInfo) {
				$sql .= $this->database->sqlLimitVariablePrefix($this->limitInfo) . "\r\n";
			}
			$sql .= '    ' . implode(",\r\n    ", $this->selectArray);
		}

		// FROM and JOIN Clauses
		$sql .= sprintf("\r\nFROM\r\n    %s\r\n    %s",
			implode(",\r\n    ", $this->fromArray),
			implode("\r\n    ", $this->joinArray));

		// WHERE Clause
		if (count($this->whereArray)) {
			$where = implode("\r\n    ", $this->whereArray);
			if (trim($where) !== '1=1') {
				$sql .= "\r\nWHERE\r\n    " . $where;
			}
		}

		// Additional Ordering/Grouping/Having clauses
		if (count($this->groupByArray)) {
			$groupByArray = $this->groupByArray;
			foreach ($this->groupBySelectArray as $expression) {
				if (!in_array($expression, $groupByArray, true)) {
					$groupByArray[] = $expression;
				}
			}
			$sql .= "\r\nGROUP BY\r\n    " . implode(",\r\n    ", $groupByArray);
		}
		if (count($this->havingArray)) {
			$having = implode("\r\n    ", $this->havingArray);
			$sql .= "\r\nHaving\r\n    " . $having;
		}
		if (count($this->orderByArray)) {
			$sql .= "\r\nORDER BY\r\n    " . implode(",\r\n    ", $this->orderByArray);
		}

		// Limit Suffix (if applicable)
		if ($this->limitInfo) {
			$sql .= "\r\n" . $this->database->sqlLimitVariableSuffix($this->limitInfo);
		}

		// For Distinct Count Queries
		if ($this->countOnlyFlag && $this->distinctFlag) {
			$sql .= "\r\n) as q_count_table";
		}

		return $sql;
	}

	/** @inheritDoc */
	public function __get($name) {
		switch ($name) {
			case 'database':
				return $this->database;
			case 'rootTableName':
				return $this->rootTableName;
			case 'columnAliasArray':
				return $this->columnAliasArray;
			case 'expandAsArrayNode':
				return $this->expandAsArrayNode;
			case 'suppressSelectExpansion':
				return $this->aggregationFlag && $this->database->onlyFullGroupBy;

			default:
				try {
					return parent::__get($name);
				} catch (Cog\Exceptions\CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}
}
