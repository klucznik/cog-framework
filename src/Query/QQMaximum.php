<?php

namespace Cog\Query;

class QQMaximum extends QQAggregationClause {

	protected $functionName = 'MAX';

	/** @inheritdoc */
	public function __toString(): string {
		return 'Cog\Query\QQMaximum Clause';
	}
}
