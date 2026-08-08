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

	public function __get($name) {
		switch ($name) {
			case 'queryBuilder':
				return $this->queryBuilder;
			default:
				try {
					return parent::__get($name);
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}

	public function __set($name, $value) {
		switch ($name) {
			case 'queryBuilder':
				try {
					return ($this->queryBuilder = Type::cast($value, QueryBuilder::class));
				} catch (InvalidCastException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
			default:
				try {
					return parent::__set($name, $value);
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}
}
