<?php

namespace Cog\Query;

/**
 * Subclasses QueryBuilder to handle the building of conditions for conditional expansions, sub queries, etc.
 * Since regular queries use WhereClauses for conditions, we just use the where clause portion, and
 * only build a condition clause appropriate for a conditional expansion.
 */
class PartialQueryBuilder extends QueryBuilder {

	protected QueryBuilder $parentBuilder;

	public function __construct(QueryBuilder $queryBuilder) {
		parent::__construct($queryBuilder->database, $queryBuilder->rootTableName);

		$this->parentBuilder = $queryBuilder;
		$this->columnAliasArray = &$queryBuilder->columnAliasArray;
		$this->tableAliasArray = &$queryBuilder->tableAliasArray;
	}

	public function getWhereStatement() {
		return implode(' ', $this->whereArray);
	}

	public function getFromStatement() {
		return implode(' ', $this->fromArray) . ' ' . implode(' ', $this->joinArray);
	}
}
