<?php

namespace Cog\Query;

use Cog\Base;

abstract class QQClause extends Base {

	/**
	 * @param QueryBuilder $queryBuilder
	 * @return void
	 */
	abstract public function updateQueryBuilder(QueryBuilder $queryBuilder): void;

	/**
	 * @return string
	 */
	abstract public function __toString(): string;
}
