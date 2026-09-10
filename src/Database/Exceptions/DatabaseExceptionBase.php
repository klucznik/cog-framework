<?php

namespace Cog\Database\Exceptions;

use Cog;
use Throwable;

abstract class DatabaseExceptionBase extends Cog\Exceptions\CogException {

	/**
	 * @param string $message
	 * @param int $errorNumber the error number reported by the SQL server, also exposed as the exception code
	 * @param string $query the query that caused the error, empty when there is none
	 * @param Throwable|null $previous the driver exception this one translates, if any
	 */
	public function __construct(
		string $message,
		public readonly int $errorNumber,
		public readonly string $query,
		?Throwable $previous = null,
	) {
		parent::__construct($message, $errorNumber, $previous);
	}
}
