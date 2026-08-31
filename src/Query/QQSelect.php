<?php

namespace Cog\Query;

class QQSelect extends QQClause {

	protected array $nodeArray = [];
	protected bool $skipPrimaryKey = false;

	public function __construct($nodeArray) {
		$this->nodeArray = $nodeArray;
	}

	public function addSelectItems(QueryBuilder $builder, $tableName, $aliasPrefix): void {
		foreach ($this->nodeArray as $node) {
			$builder->addSelectItem($tableName, $node->name, $aliasPrefix . $node->name);
		}
	}

	public function merge(?QQSelect $select = null): void {
		if ($select) {
			foreach ($select->nodeArray as $node) {
				$this->nodeArray[] = $node;
			}
			if ($select->skipPrimaryKey) {
				$this->skipPrimaryKey = true;
			}
		}
	}

	/**
	 * @return boolean
	 */
	public function skipPrimaryKey(): bool {
		return $this->skipPrimaryKey;
	}

	/**
	 * @param boolean $skipPrimaryKey
	 */
	public function setSkipPrimaryKey($skipPrimaryKey): void {
		$this->skipPrimaryKey = $skipPrimaryKey;
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {}

	/** @inheritdoc */
	public function __toString(): string {
		return 'QQSelectColumn Clause';
	}
}
