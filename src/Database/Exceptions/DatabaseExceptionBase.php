<?php

namespace Cog\Database\Exceptions;

use Cog;

/**
 * @property-read int $errorNumber The number of error provided by the SQL server
 * @property-read string $query The query caused the error
 * @package DatabaseAdapters
 */
abstract class DatabaseExceptionBase extends Cog\Exceptions\CogException {
	protected int $errorNumber;
	protected string $query;

	public function __get(string $name) {
		switch ($name) {
			case 'errorNumber':
				return $this->errorNumber;
			case 'query':
				return $this->query;
			default:
				return parent::__get($name);
		}
	}
}
