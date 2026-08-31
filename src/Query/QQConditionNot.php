<?php

namespace Cog\Query;

use Cog;

class QQConditionNot extends QQCondition {

	protected QQCondition $condition;

	public function __construct(QQCondition $condition) {
		$this->condition = $condition;
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addWhereItem('(NOT');
		try {
			$this->condition->updateQueryBuilder($queryBuilder);
		} catch (Cog\Exceptions\CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
		$queryBuilder->addWhereItem(')');
	}
}
