<?php

namespace Cog\Query;

use Cog\Exceptions\CogException;

class QQVirtualNode extends QQNode {

	protected ?QQSubQueryNode $subQueryDefinition = null;

	public function __construct($name, ?QQSubQueryNode $subQueryDefinition = null) {
		$this->name = strtolower(trim($name));
		$this->subQueryDefinition = $subQueryDefinition;
	}

	/** Has no parent, but resolves to an expression of its own. */
	public function isColumnBased(): bool {
		return true;
	}

	/**
	 * @param QueryBuilder $queryBuilder
	 * @param bool $expandSelection
	 * @param QQCondition|null $joinCondition
	 * @param QQSelect|null $select
	 * @return string
	 * @throws CogException
	 */
	public function getColumnAlias(QueryBuilder $queryBuilder, bool $expandSelection = false, ?QQCondition $joinCondition = null, ?QQSelect $select = null) {
		if ($this->subQueryDefinition) {
			$queryBuilder->setVirtualNode($this->name, $this->subQueryDefinition);
			return $this->subQueryDefinition->getColumnAlias($queryBuilder);
		}

		try {
			return $queryBuilder->getVirtualNode($this->name)->getColumnAlias($queryBuilder);
		} catch (CogException $exception) {
			$exception->incrementOffset();
			$exception->incrementOffset();
			throw $exception;
		}
	}

	/**
	 * @return string
	 */
	public function getAttributeName() {
		return $this->name;
	}
}
