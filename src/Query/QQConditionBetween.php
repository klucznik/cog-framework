<?php

namespace Cog\Query;

use Cog;
use Cog\Exceptions\InvalidCastException;
use Cog\Type;

class QQConditionBetween extends QQConditionComparison {

	protected mixed $operandTwo = null;

	public function __construct(QQNode $queryNode, $minValue, $maxValue) {

		$this->queryNode = $queryNode;

		if (!$queryNode->isColumnBased()) {
			throw new InvalidCastException('Unable to cast "' . $queryNode->getNodeName() . '" table to Column-based QQNode', 3);
		}

		if ($minValue instanceof QQNamedValue) {
			$this->operand = $minValue;
		} else {
			try {
				$this->operand = Type::cast($minValue, Type::STRING);
			} catch (Cog\Exceptions\CogException $exception) {
				$exception->incrementOffset();
				$exception->incrementOffset();
				throw $exception;
			}
		}

		if ($maxValue instanceof QQNamedValue) {
			$this->operandTwo = $maxValue;
		} else {
			try {
				$this->operandTwo = Type::cast($maxValue, Type::STRING);
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
		$operandTwo = $this->operandTwo;
		if ($operand instanceof QQNamedValue) {
			/** @var QQNamedValue $operand */
			/** @var QQNamedValue $operandTwo */
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' BETWEEN ' . $operand->parameter() . ' AND ' . $operandTwo->parameter());
		} else {
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' BETWEEN ' . $queryBuilder->database->sqlVariable($operand) . ' AND ' . $queryBuilder->database->sqlVariable($operandTwo));
		}
	}
}
