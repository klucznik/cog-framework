<?php

namespace Cog\Database\Adapters;

use Cog;
use Throwable;

class MySqliException extends Cog\Database\Exceptions\DatabaseExceptionBase {

	public function __construct(string $message, int $number, string $query, ?Throwable $previous = null) {
		parent::__construct(sprintf('MySqli Error: %s', $message), $number, $query, $previous);
	}
}
