<?php

namespace Cog\Query;

class QQSelect extends QQClause {

	protected array $nodeArray = [];
	protected bool $skipPrimaryKey = false;

	public function __construct($nodeArray) {
		$this->nodeArray = $nodeArray;
	}

	public function addSelectItems(QueryBuilder $objBuilder, $strTableName, $strAliasPrefix) {
		foreach ($this->nodeArray as $node) {
			$objBuilder->addSelectItem($strTableName, $node->name, $strAliasPrefix . $node->name);
		}
	}

	public function merge(?QQSelect $objSelect = null) {
		if ($objSelect) {
			foreach ($objSelect->nodeArray as $node) {
				$this->nodeArray[] = $node;
			}
			if ($objSelect->skipPrimaryKey) {
				$this->skipPrimaryKey = true;
			}
		}
	}

	/**
	 * @return boolean
	 */
	public function skipPrimaryKey() {
		return $this->skipPrimaryKey;
	}

	/**
	 * @param boolean $skipPrimaryKey
	 */
	public function setSkipPrimaryKey($skipPrimaryKey) {
		$this->skipPrimaryKey = $skipPrimaryKey;
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {}

	/** @inheritdoc */
	public function __toString(): string {
		return 'QQSelectColumn Clause';
	}
}
