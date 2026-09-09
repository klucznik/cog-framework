<?php

namespace Cog\Query;

use Carbon\Carbon;
use Cog\Exceptions\InvalidCastException;

class QQConditionBetween extends QQConditionComparison {

	protected mixed $operandTwo = null;

	public function __construct(QQNode $queryNode, $minValue, $maxValue) {

		$this->queryNode = $queryNode;

		if (!$queryNode->isColumnBased()) {
			throw new InvalidCastException('Unable to cast "' . $queryNode->getNodeName() . '" table to Column-based QQNode', 3);
		}

		$this->operand = self::bound($minValue);
		$this->operandTwo = self::bound($maxValue);
	}

	/**
	 * A bound is kept as given so that sqlVariable() formats it for its type - an int stays
	 * unquoted and a Carbon becomes a datetime literal - instead of being cast to a string.
	 */
	private static function bound(mixed $value): mixed {
		if ($value instanceof QQNamedValue || $value instanceof Carbon || $value === null || is_scalar($value)) {
			return $value;
		}
		throw new InvalidCastException('Unable to cast ' . get_debug_type($value) . ' to a BETWEEN bound', 4);
	}

	protected function boundSql(mixed $bound, QueryBuilder $queryBuilder): string {
		return $bound instanceof QQNamedValue ? $bound->parameter() : $queryBuilder->database->sqlVariable($bound);
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' BETWEEN ' . $this->boundSql($this->operand, $queryBuilder) . ' AND ' . $this->boundSql($this->operandTwo, $queryBuilder));
	}
}
