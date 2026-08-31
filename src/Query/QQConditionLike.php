<?php

namespace Cog\Query;

use Cog;
use Cog\Exceptions\InvalidCastException;
use Cog\Type;

class QQConditionLike extends QQConditionComparison {

	public function __construct(QQNode $queryNode, $value) {
		$this->queryNode = $queryNode;
		if (!$queryNode->isColumnBased()) {
			throw new InvalidCastException('Unable to cast "' . $queryNode->getNodeName() . '" table to Column-based QQNode', 3);
		}

		if ($value instanceof QQNamedValue) {
			$this->operand = $value;
		} else {
			try {
				$this->operand = Type::cast($value, Type::STRING);
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
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' LIKE ' . $operand->parameter());
		} else {
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' LIKE ' . $queryBuilder->database->sqlVariable($operand));
		}
	}
}
