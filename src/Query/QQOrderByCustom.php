<?php

namespace Cog\Query;

class QQOrderByCustom extends QQClause {

	/** @var string */
	protected $custom;

	public function __construct($custom) {
		$this->custom = $custom;
	}

	public function getAsManualSql() {
		return ' ' . $this->custom;
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$queryBuilder->addOrderByItem(' ' . $this->custom);
	}

	/** @inheritdoc */
	public function __toString(): string {
		return 'Cog\Query\QQOrderByCustom Clause';
	}
}
