<?php

namespace Cog\Database\Exceptions;

use Cog;
use Cog\Exceptions\UndefinedPropertyException;

/**
 * @property-read int $errorNumber The number of error provided by the SQL server
 * @property-read string $query The query caused the error
 * @package DatabaseAdapters
 */
abstract class DatabaseExceptionBase extends Cog\Exceptions\CogException {
	protected int $errorNumber;
	protected string $query;

	public function __get(string $name): mixed {
		return match ($name) {
			'errorNumber' => $this->errorNumber,
			'query' => $this->query,
			default => throw new UndefinedPropertyException('GET', static::class, $name),
		};
	}
}
