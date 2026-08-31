<?php

namespace Cog\Query;

use Cog;
use Cog\Type;

/**
 * Class QQLimitInfo
 * @package Cog\Query
 *
 * @property-read int $maxRowCount
 * @property-read int $offset
 */
class QQLimitInfo extends QQClause {

	protected int $maxRowCount = 0;
	protected int $offset = 0;

	/**
	 * QQLimitInfo constructor.
	 * @param int $maxRowCount
	 * @param int $offset
	 * @throws \Cog\Exceptions\CogException
	 */
	public function __construct($maxRowCount, $offset = 0) {
		try {
			$this->maxRowCount = Type::cast($maxRowCount, Type::INTEGER);
			$this->offset = Type::cast($offset, Type::INTEGER);
		} catch (Cog\Exceptions\CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		if ($this->offset) {
			$queryBuilder->setLimitInfo($this->offset . ',' . $this->maxRowCount);
		} else {
			$queryBuilder->setLimitInfo($this->maxRowCount);
		}
	}

	/** @inheritdoc */
	public function __toString(): string {
		return 'Cog\Query\QQLimitInfo Clause';
	}

	public function __get($name): mixed {
		switch ($name) {
			case 'maxRowCount':
				return $this->maxRowCount;
			case 'offset':
				return $this->offset;
			default:
				try {
					return parent::__get($name);
				} catch (Cog\Exceptions\CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}
}
