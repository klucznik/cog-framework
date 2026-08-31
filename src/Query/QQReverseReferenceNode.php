<?php

namespace Cog\Query;

use Cog;

class QQReverseReferenceNode extends QQNode {

	protected ?string $foreignKey = null;

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

	protected function addJoinTable(QueryBuilder $queryBuilder, $joinTableAlias, $parentAlias, ?QQCondition $joinCondition = null) {
		$queryBuilder->addJoinItem($this->tableName, $joinTableAlias,
			$parentAlias, $this->parentNode->primaryKey, $this->foreignKey, $joinCondition);
	}

	public function getColumnAlias(QueryBuilder $queryBuilder, bool $expandSelection = false, ?QQCondition $joinCondition = null, ?QQSelect $select = null) {
		$this->getTableAlias($queryBuilder, $expandSelection, $joinCondition, $select);
		return null;
	}

}
