<?php

namespace Cog\Query;

use Cog;
use Cog\Exceptions\InvalidCastException;
use Cog\Type;

class QQConditionBetween extends QQConditionComparison {

	protected $mixOperandTwo;

	public function __construct(QQNode $queryNode, $strMinValue, $strMaxValue) {

		$this->queryNode = $queryNode;

		if (!$queryNode->parentNode) {
			throw new InvalidCastException('Unable to cast "' . $queryNode->getNodeName() . '" table to Column-based QQNode', 3);
		}

		if ($strMinValue instanceof QQNamedValue) {
			$this->mixOperand = $strMinValue;
		} else {
			try {
				$this->mixOperand = Type::cast($strMinValue, Type::STRING);
			} catch (Cog\Exceptions\CogException $exception) {
				$exception->incrementOffset();
				$exception->incrementOffset();
				throw $exception;
			}
		}

		if ($strMaxValue instanceof QQNamedValue) {
			$this->mixOperandTwo = $strMaxValue;
		} else {
			try {
				$this->mixOperandTwo = Type::cast($strMaxValue, Type::STRING);
			} catch (Cog\Exceptions\CogException $exception) {
				$exception->incrementOffset();
				$exception->incrementOffset();
				throw $exception;
			}
		}
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$mixOperand = $this->mixOperand;
		$mixOperandTwo = $this->mixOperandTwo;
		if ($mixOperand instanceof QQNamedValue) {
			/** @var QQNamedValue $mixOperand */
			/** @var QQNamedValue $mixOperandTwo */
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' BETWEEN ' . $mixOperand->parameter() . ' AND ' . $mixOperandTwo->parameter());
		} else {
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' BETWEEN ' . $queryBuilder->database->sqlVariable($mixOperand) . ' AND ' . $queryBuilder->database->sqlVariable($mixOperandTwo));
		}
	}
}
