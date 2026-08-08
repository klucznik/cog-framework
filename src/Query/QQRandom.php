<?php

namespace Cog\Query;

class QQRandom extends QQClause {

	public function getAsManualSql() {
		return ' RAND()';
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addOrderByItem(' RAND()');
	}

	/** @inheritdoc */
	public function __toString(): string {
		return 'Cog\Query\QQRandom Clause';
	}
}
