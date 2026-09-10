<?php

namespace Cog\Database;

use Cog;
use Cog\Exceptions\CogException;
use Cog\Exceptions\InvalidCastException;
use Cog\Query\QueryBuilder;
use Cog\Type;

/**
 * @property QueryBuilder $queryBuilder
 */
abstract class ResultBase extends Cog\Base {
	// Allows attaching QueryBuilder object to use the result object as cursor resource for cursor queries.
	protected QueryBuilder $queryBuilder;

	abstract public function fetchArray(): ?array;

	abstract public function fetchArrayAssoc(): ?array;

	abstract public function fetchRow(): ?array;

	/**
	 * @return FieldBase|null
	 */
	abstract public function fetchField(): ?object;

	/**
	 * @return FieldBase[]
	 */
	abstract public function fetchFields(): array;

	abstract public function countRows(): int;

	/**
	 * @return RowBase|null
	 */
	abstract public function getNextRow(): ?object;

	abstract public function getRows(): array;

	abstract public function close(): void;

	public function __get($name): mixed {
		switch ($name) {
			case 'queryBuilder':
				return $this->queryBuilder;
			default:
				return parent::__get($name);
		}
	}

	public function __set($name, $value) {
		switch ($name) {
			case 'queryBuilder':
				return ($this->queryBuilder = Type::cast($value, QueryBuilder::class));
			default:
				return parent::__set($name, $value);
		}
	}
}
