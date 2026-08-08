<?php

namespace Cog\Query;

use Cog;

class QQConditionNot extends QQCondition {

	protected $objCondition;

	public function __construct(QQCondition $objCondition) {
		$this->objCondition = $objCondition;
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addWhereItem('(NOT');
		try {
			$this->objCondition->updateQueryBuilder($queryBuilder);
		} catch (Cog\Exceptions\CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
		$queryBuilder->addWhereItem(')');
	}
}
