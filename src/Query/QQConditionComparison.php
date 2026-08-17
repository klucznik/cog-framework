<?php

namespace Cog\Query;

use Cog\Exceptions\InvalidCastException;

abstract class QQConditionComparison extends QQCondition {

	public $queryNode;
	public $mixOperand;

	public function __construct(QQNode $queryNode, $operand) {
		$this->queryNode = $queryNode;
		if (!$queryNode->parentNode) {
			throw new InvalidCastException('Unable to cast "' . $queryNode->getNodeName() . '" table to Column-based QQNode', 3);
		}

		if ($operand instanceof QQNamedValue) {
			$this->mixOperand = $operand;
		} elseif ($operand instanceof QQAssociationNode) {
			throw new InvalidCastException('Comparison operand cannot be an Association-based QQNode', 3);
		} elseif ($operand instanceof QQCondition) {
			throw new InvalidCastException('Comparison operand cannot be a QQCondition', 3);
		} elseif ($operand instanceof QQClause) {
			throw new InvalidCastException('Comparison operand cannot be a QQClause', 3);
		} elseif (!($operand instanceof QQNode)) {
			$this->mixOperand = $operand;
		} else {
			if (!$operand->parentNode) {
				throw new InvalidCastException('Unable to cast "' . $operand->getNodeName() . '" table to Column-based QQNode', 3);
			}
			$this->mixOperand = $operand;
		}
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . $this->operator . $this->queryNode->getValue($this->mixOperand, $queryBuilder));
	}
}
