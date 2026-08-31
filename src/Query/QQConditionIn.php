<?php

namespace Cog\Query;

use Cog;
use Cog\Exceptions\InvalidCastException;
use Cog\Type;

class QQConditionIn extends QQConditionComparison {

	public function __construct(QQNode $queryNode, $mixValuesArray) {
		$this->queryNode = $queryNode;
		if (!$queryNode->isColumnBased()) {
			throw new InvalidCastException('Unable to cast "' . $queryNode->getNodeName() . '" table to Column-based QQNode', 3);
		}

		if ($mixValuesArray instanceof QQNamedValue) {
			$this->operand = $mixValuesArray;
		} elseif ($mixValuesArray instanceof QQSubQueryNode) {
			$this->operand = $mixValuesArray;
		} else {
			try {
				$this->operand = Type::cast($mixValuesArray, Type::ARRAY);
			} catch (Cog\Exceptions\CogException $exception) {
				$exception->incrementOffset();
				$exception->incrementOffset();
				throw $exception;
			}
		}
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$operand = $this->operand;

		if ($operand instanceof QQNamedValue) {
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' IN (' . $operand->parameter() . ')');
		} else if ($operand instanceof QQSubQueryNode) {
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' IN ' . $operand->getColumnAlias($queryBuilder));
		} else {
			$parameters = [];
			foreach ($operand as $mixParameter) {
				$parameters[] = $queryBuilder->database->sqlVariable($mixParameter);
			}
			if (\count($parameters)) {
				$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' IN (' . implode(',', $parameters) . ')');
			} else {
				$queryBuilder->addWhereItem('1=0');
			}
		}
	}
}
