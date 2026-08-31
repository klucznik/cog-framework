<?php

namespace Cog\Query;

/*
 * Allows a custom sql injection as a having clause. Its up to you to make sure its correct, but you can use subquery placeholders
 * to expand column names. Standard SQL has limited Having capabilities, but many SQL engines have useful extensions.
 */
class QQHavingClause extends QQClause {

	protected QQSubQueryNode $node;

	public function __construct(QQSubQueryNode $subQueryDefinition) {
		$this->node = $subQueryDefinition;
	}

	public function getAttributeName() {
		return $this->strName ?? '';
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addHavingItem(
			$this->node->getColumnAlias($queryBuilder)
		);
	}

	/** @inheritdoc */
	public function __toString(): string {
		return 'Having Clause';
	}
}
