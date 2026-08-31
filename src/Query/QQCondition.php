<?php

namespace Cog\Query;

use Cog;

abstract class QQCondition extends Cog\Base {

	protected string $operator = '';

	/** Whether updateQueryBuilder() has already run, for the process-once clauses. */
	protected bool $processed = false;

	/**
	 * @param QueryBuilder $queryBuilder
	 * @return void
	 * @throws \Cog\Exceptions\CogException
	 */
	abstract public function updateQueryBuilder(QueryBuilder $queryBuilder): void;

	/**
	 * @return string
	 */
	public function __toString() {
		return 'Cog\Query\QQCondition Object';
	}

	/**
	 * Used internally by Query to get an individual where clause for a given condition
	 * Mostly used for conditional joins.
	 *
	 * @param QueryBuilder $queryBuilder
	 * @param boolean $processOnce
	 * @return string|null
	 * @throws \Cog\Exceptions\CogException
	 */
	public function getWhereClause(QueryBuilder $queryBuilder, $processOnce = false) {
		if ($processOnce && $this->processed) {
			return null;
		}

		$this->processed = true;

		try {
			$conditionBuilder = new PartialQueryBuilder($queryBuilder);
			$this->updateQueryBuilder($conditionBuilder);
			return $conditionBuilder->getWhereStatement();
		} catch (Cog\Exceptions\CogException $exception) {
			$exception->incrementOffset();
			$exception->incrementOffset();
			throw $exception;
		}
	}
}
