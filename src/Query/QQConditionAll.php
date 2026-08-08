<?php

namespace Cog\Query;

use Cog;

class QQConditionAll extends QQCondition {

	public function __construct($mixParameterArray) {
		if (count($mixParameterArray)) {
			throw new Cog\Exceptions\CogException('All clause takes in no parameters', 3);
		}
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addWhereItem('1=1');
	}
}
