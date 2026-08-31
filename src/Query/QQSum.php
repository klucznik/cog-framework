<?php

namespace Cog\Query;

class QQSum extends QQAggregationClause {

	protected string $functionName = 'SUM';

	/** @inheritdoc */
	public function __toString(): string {
		return 'Cog\Query\QQSum Clause';
	}
}
