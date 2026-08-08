<?php

namespace Cog\Query;

class QQSubQuerySqlNode extends QQSubQueryNode {

	/** @var string */
	protected $sql;

	/** @var QQNode[]|null */
	protected $objParentQueryNodes;

	/**
	 * QQSubQuerySqlNode constructor.
	 * @param string $sql
	 * @param QQNode[]|null $parentQueryNodes
	 */
	public function __construct($sql, $parentQueryNodes = null) {
		$this->parentNode = true;
		$this->objParentQueryNodes = $parentQueryNodes;
		$this->sql = $sql;
	}

	public function getColumnAlias(QueryBuilder $queryBuilder, bool $expandSelection = false, ?QQCondition $joinCondition = null, ?QQSelect $select = null) {
		$strSql = $this->sql;

		$count = count($this->objParentQueryNodes);
		for ($intIndex = 1; $intIndex < $count; $intIndex++) {
			if (null !== $this->objParentQueryNodes[$intIndex]) {
				$strSql = str_replace('{' . $intIndex . '}', $this->objParentQueryNodes[$intIndex]->getColumnAlias($queryBuilder), $strSql);
			}
		}
		return '(' . $strSql . ')';
	}
}
