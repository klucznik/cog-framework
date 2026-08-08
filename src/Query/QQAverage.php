<?php

namespace Cog\Query;

class QQAverage extends QQAggregationClause {
	protected $functionName = 'AVG';

	public function __toString(): string {
		return 'Cog\Query\QQAverage Clause';
	}
}
