<?php

namespace Cog\Query;

class QQSelect extends QQClause {

	protected $arrNodeObj = [];
	protected $blnSkipPrimaryKey = false;

	public function __construct($arrNodeObj) {
		$this->arrNodeObj = $arrNodeObj;
	}

	public function addSelectItems(QueryBuilder $objBuilder, $strTableName, $strAliasPrefix) {
		foreach ($this->arrNodeObj as $objNode) {
			$objBuilder->addSelectItem($strTableName, $objNode->name, $strAliasPrefix . $objNode->name);
		}
	}

	public function merge(?QQSelect $objSelect = null) {
		if ($objSelect) {
			foreach ($objSelect->arrNodeObj as $objNode) {
				$this->arrNodeObj[] = $objNode;
			}
			if ($objSelect->blnSkipPrimaryKey) {
				$this->blnSkipPrimaryKey = true;
			}
		}
	}

	/**
	 * @return boolean
	 */
	public function skipPrimaryKey() {
		return $this->blnSkipPrimaryKey;
	}

	/**
	 * @param boolean $blnSkipPrimaryKey
	 */
	public function setSkipPrimaryKey($blnSkipPrimaryKey) {
		$this->blnSkipPrimaryKey = $blnSkipPrimaryKey;
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {}

	/** @inheritdoc */
	public function __toString(): string {
		return 'QQSelectColumn Clause';
	}
}
