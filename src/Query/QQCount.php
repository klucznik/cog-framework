<?php

namespace Cog\Query;

class QQCount extends QQAggregationClause {

	protected string $functionName = 'COUNT';

	/** @inheritdoc */
	public function __toString(): string {
		return 'Cog\Query\QQCount Clause';
	}
}
