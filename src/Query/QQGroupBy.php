<?php

namespace Cog\Query;

use Cog;
use Cog\Exceptions\InvalidCastException;

class QQGroupBy extends QQClause {

	/** @var QQBaseNode[] */
	protected $objNodeArray;

	/** @inheritdoc */
	public function __construct($mixParameterArray) {
		$this->objNodeArray = $this->collapseNodes($mixParameterArray);
	}

	/**
	 * @param $mixParameterArray
	 * @return array
	 * @throws \Cog\Exceptions\CogException
	 * @throws InvalidCastException
	 */
	protected function collapseNodes($mixParameterArray): array {

		$nodeArray = [];
		foreach ($mixParameterArray as $mixParameter) {
			if (is_array($mixParameter)) {
				$nodeArray = array_merge($nodeArray, $mixParameter);
			} else {
				$nodeArray[] = $mixParameter;
			}
		}

		$finalNodeArray = [];
		foreach ($nodeArray as $node) {
			/** @var QQBaseNode $node */
			if ($node instanceof QQAssociationNode) {
				throw new Cog\Exceptions\CogException('GroupBy clause parameter cannot be an association table\'s QQNode, itself', 3);
			}

			if (!($node instanceof QQNode)) {
				throw new Cog\Exceptions\CogException('GroupBy clause parameters must all be QQNode objects', 3);
			}

			if (!$node->parentNode) {
				throw new InvalidCastException('Unable to cast "' . $node->getNodeName() . '" table to Column-based QQNode', 4);
			}

			if ($node->primaryKeyNode) {
				$finalNodeArray[] = $node->primaryKeyNode;
			} else {
				$finalNodeArray[] = $node;
			}
		}

		if (count($finalNodeArray)) {
			return $finalNodeArray;
		}

		throw new Cog\Exceptions\CogException('No parameters passed in to Expand clause', 3);
	}

	/**
	 * Names of the grouped columns that live directly on the query's root table.
	 * Lets the query methods decide whether the root table's fields stay functionally
	 * dependent on the grouping (i.e. the full primary key is grouped) under ONLY_FULL_GROUP_BY.
	 * @return string[]
	 */
	public function rootColumnNames(): array {
		$names = [];
		foreach ($this->objNodeArray as $node) {
			if ($node->isTopLevelLeafNode()) {
				$names[] = $node->name;
			}
		}
		return $names;
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$length = count($this->objNodeArray);

		for ($index = 0; $index < $length; $index++) {
			$queryBuilder->addGroupByItem($this->objNodeArray[$index]->getColumnAlias($queryBuilder));
		}
	}

	/** @inheritdoc */
	public function __toString(): string {
		return 'Cog\Query\QQGroupBy Clause';
	}
}
