<?php

namespace Cog\Query;

class QQMinimum extends QQAggregationClause {

	protected $functionName = 'MIN';

	/** @inheritdoc */
	public function __toString(): string {
		return 'Cog\Query\QQMinimum Clause';
	}
}
