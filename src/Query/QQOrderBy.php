<?php

namespace Cog\Query;

use Cog\Exceptions\CogException;
use Cog\Exceptions\InvalidCastException;

class QQOrderBy extends QQClause {

	/** @var QQNode[] */
	protected array $nodeArray;

	public function __construct($parameterArray) {
		$this->nodeArray = $this->collapseNodes($parameterArray);
	}

	/**
	 * @param $parameterArray
	 * @return array
	 * @throws CogException
	 * @throws InvalidCastException
	 */
	protected function collapseNodes($parameterArray): array {

		/** @var QQNode[] $nodeArray */
		$nodeArray = [];

		foreach ($parameterArray as $parameter) {
			if (is_array($parameter)) {
				$nodeArray = array_merge($nodeArray, $parameter);
			} else {
				$nodeArray[] = $parameter;
			}
		}

		$previousIsNode = false;
		foreach ($nodeArray as $node) {
			if (($node instanceof QQNode || $node instanceof QQCondition) === false) {
				if (!$previousIsNode) {
					throw new CogException('orderBy clause parameters must all be QQNode or QQCondition objects followed by an optional true/false "Ascending Order" option', 3);
				}
				$previousIsNode = false;
			} else {
				if ($node instanceof QQReverseReferenceNode) {
					throw new InvalidCastException('Cannot order by a ReverseReferenceNode: ' . $node->name, 4);
				}
				if ($node instanceof QQNode && !$node->isColumnBased()) {
					throw new InvalidCastException('Unable to cast "' . $node->getNodeName() . '" table to Column-based QQNode', 4);
				}
				$previousIsNode = true;
			}
		}

		if (count($nodeArray)) {
			return $nodeArray;
		}

		throw new CogException('No parameters passed in to orderBy clause', 3);
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$length = count($this->nodeArray);

		for ($index = 0; $index < $length; $index++) {
			$node = $this->nodeArray[$index];
			if ($node instanceof QQNode) {
				$orderByCommand = $node->getColumnAlias($queryBuilder);
			} else if ($node instanceof QQCondition) {
				$orderByCommand = $node->getWhereClause($queryBuilder);
			} else {
				$orderByCommand = '';
			}

			// Check to see if they want a ASC/DESC declarator
			if (($index + 1) < $length && !$this->nodeArray[$index + 1] instanceof QQNode) {
				if (!$this->nodeArray[$index + 1] || strtoupper(trim($this->nodeArray[$index + 1])) === 'DESC') {
					$orderByCommand .= ' DESC';
				} else {
					$orderByCommand .= ' ASC';
				}
				$index++;
			}

			$queryBuilder->addOrderByItem($orderByCommand);
		}
	}

	/** @inheritdoc */
	public function __toString(): string {
		return 'Cog\Query\QQOrderBy Clause';
	}

}
