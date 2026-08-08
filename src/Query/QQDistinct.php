<?php

namespace Cog\Query;

class QQDistinct extends QQClause {

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->setDistinctFlag();
	}

	/** @inheritdoc */
	public function __toString(): string {
		return 'Cog\Query\QQDistinct Clause';
	}
}
