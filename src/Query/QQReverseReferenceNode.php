<?php

namespace Cog\Query;

use Cog;

class QQReverseReferenceNode extends QQNode {

	/** @var QQBaseNode|null */
	protected $foreignKey;

	/**
	 * QQReverseReferenceNode constructor.
	 * @param QQBaseNode $parentNode
	 * @param string $name
	 * @param string $type
	 * @param QQBaseNode|null $foreignKey
	 * @param string|null $propertyName
	 * @throws \Cog\Exceptions\CogException
	 */
	public function __construct( QQBaseNode $parentNode, $name, $type, $foreignKey, $propertyName = null) {

		$this->parentNode = $parentNode;
		if ($parentNode) {
			$this->rootTableName = $parentNode->rootTableName;
		} else {
			throw new Cog\Exceptions\CogException('ReverseReferenceNodes must have a Parent Node');
		}
		$this->name = $name;
		$parentNode->childNodeArray[$name] = $this;
		$this->alias = $name;
		$this->type = $type;
		$this->foreignKey = $foreignKey;
		$this->propertyName = $propertyName;
	}

	protected function addJoinTable(QueryBuilder $queryBuilder, $strJoinTableAlias, $strParentAlias, ?QQCondition $objJoinCondition = null) {
		$queryBuilder->addJoinItem($this->tableName, $strJoinTableAlias,
			$strParentAlias, $this->parentNode->primaryKey, $this->foreignKey, $objJoinCondition);
	}

	public function getColumnAlias(QueryBuilder $queryBuilder, bool $expandSelection = false, ?QQCondition $joinCondition = null, ?QQSelect $select = null) {
		$this->getTableAlias($queryBuilder, $expandSelection, $joinCondition, $select);
		return null;
	}

	public function getExpandArrayAlias() {
//			$objNode = $this;
//			$objChildTableNode = $this->_childTableNode;
//			$strToReturn = $objChildTableNode->name . '__' . $objChildTableNode->primaryKey;
		$strToReturn = $this->name . '__' . $this->primaryKey;

		$objNode = $this->parentNode;
		while ($objNode) {
			$strToReturn = $objNode->name . '__' . $strToReturn;
			$objNode = $objNode->parentNode;
		}

		return $strToReturn;
	}
}
