<?php

namespace Cog\Query;

use Cog;

class QQConditionNone extends QQCondition {

	public function __construct($parameterArray) {
		if (\count($parameterArray)) {
			throw new Cog\Exceptions\CogException('None clause takes in no parameters', 3);
		}
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addWhereItem('1=0');
	}
}
