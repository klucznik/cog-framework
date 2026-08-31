<?php

namespace Cog\Query;

use Cog;
use Cog\Exceptions\InvalidCastException;
use Cog\Type;

class QQConditionLike extends QQConditionComparison {

	public function __construct(QQNode $queryNode, $strValue) {
		$this->queryNode = $queryNode;
		if (!$queryNode->isColumnBased()) {
			throw new InvalidCastException('Unable to cast "' . $queryNode->getNodeName() . '" table to Column-based QQNode', 3);
		}

		if ($strValue instanceof QQNamedValue) {
			$this->mixOperand = $strValue;
		} else {
			try {
				$this->mixOperand = Type::cast($strValue, Type::STRING);
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
		if ($mixOperand instanceof QQNamedValue) {
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' LIKE ' . $mixOperand->parameter());
		} else {
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' LIKE ' . $queryBuilder->database->sqlVariable($mixOperand));
		}
	}
}
