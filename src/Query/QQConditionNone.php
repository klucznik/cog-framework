<?php

namespace Cog\Query;

use Cog;
use Cog\Exceptions\CogException;

class QQConditionNone extends QQCondition {

	public function __construct($parameterArray) {
		if (\count($parameterArray)) {
			throw new CogException('None clause takes in no parameters', 3);
		}
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addWhereItem('1=0');
	}
}
