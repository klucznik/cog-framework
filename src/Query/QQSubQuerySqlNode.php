<?php

namespace Cog\Query;

class QQSubQuerySqlNode extends QQSubQueryNode {

	protected string $sql;

	/** @var QQNode[]|null */
	protected ?array $parentQueryNodes = null;

	/**
	 * QQSubQuerySqlNode constructor.
	 * @param string $sql
	 * @param QQNode[]|null $parentQueryNodes
	 */
	public function __construct($sql, $parentQueryNodes = null) {
		$this->parentQueryNodes = $parentQueryNodes;
		$this->sql = $sql;
	}

	/** Has no parent, but resolves to an expression of its own. */
	public function isColumnBased(): bool {
		return true;
	}

	public function getColumnAlias(QueryBuilder $queryBuilder, bool $expandSelection = false, ?QQCondition $joinCondition = null, ?QQSelect $select = null) {
		$sql = $this->sql;

		$count = count($this->parentQueryNodes);
		for ($index = 1; $index < $count; $index++) {
			if (null !== $this->parentQueryNodes[$index]) {
				$sql = str_replace('{' . $index . '}', $this->parentQueryNodes[$index]->getColumnAlias($queryBuilder), $sql);
			}
		}
		return '(' . $sql . ')';
	}
}
