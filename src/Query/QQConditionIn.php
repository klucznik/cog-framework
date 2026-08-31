<?php

namespace Cog\Query;

use Cog;
use Cog\Exceptions\InvalidCastException;
use Cog\Type;

class QQConditionIn extends QQConditionComparison {

	public function __construct(QQNode $queryNode, $valuesArray) {
		$this->queryNode = $queryNode;
		if (!$queryNode->isColumnBased()) {
			throw new InvalidCastException('Unable to cast "' . $queryNode->getNodeName() . '" table to Column-based QQNode', 3);
		}

		if ($valuesArray instanceof QQNamedValue) {
			$this->operand = $valuesArray;
		} elseif ($valuesArray instanceof QQSubQueryNode) {
			$this->operand = $valuesArray;
		} else {
			try {
				$this->operand = Type::cast($valuesArray, Type::ARRAY);
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
			foreach ($operand as $parameter) {
				$parameters[] = $queryBuilder->database->sqlVariable($parameter);
			}
			if (\count($parameters)) {
				$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' IN (' . implode(',', $parameters) . ')');
			} else {
				$queryBuilder->addWhereItem('1=0');
			}
		}
	}
}
