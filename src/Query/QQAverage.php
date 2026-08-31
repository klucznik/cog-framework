<?php

namespace Cog\Query;

class QQAverage extends QQAggregationClause {
	protected string $functionName = 'AVG';

	public function __toString(): string {
		return 'Cog\Query\QQAverage Clause';
	}
}
