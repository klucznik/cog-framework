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
		// Share the alias state and the joins with the parent. A condition may reach a table the
		// parent has not joined yet; that join, and the alias counter that names it, must land in
		// the parent's statement, since this builder only ever contributes its WHERE text.
		$this->columnAliasArray = &$queryBuilder->columnAliasArray;
		$this->columnAliasCount = &$queryBuilder->columnAliasCount;
		$this->tableAliasArray = &$queryBuilder->tableAliasArray;
		$this->tableAliasCount = &$queryBuilder->tableAliasCount;
		$this->joinArray = &$queryBuilder->joinArray;
		$this->joinConditionArray = &$queryBuilder->joinConditionArray;
		$this->virtualNodeArray = &$queryBuilder->virtualNodeArray;
	}

	public function getWhereStatement(): string {
		return implode(' ', $this->whereArray);
	}

	public function getFromStatement(): string {
		return implode(' ', $this->fromArray) . ' ' . implode(' ', $this->joinArray);
	}
}
