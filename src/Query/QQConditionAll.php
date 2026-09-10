<?php

namespace Cog\Query;

use Cog;
use Cog\Exceptions\CogException;

class QQConditionAll extends QQCondition {

	public function __construct($parameterArray) {
		if (count($parameterArray)) {
			throw new CogException('All clause takes in no parameters', 3);
		}
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addWhereItem('1=1');
	}
}
