<?php

namespace Cog\Database\Adapters;

use Cog;

class MySqliException extends Cog\Database\Exceptions\DatabaseExceptionBase {

	public function __construct(string $message, int $number, string $query) {
		parent::__construct(sprintf('MySqli Error: %s', $message), 2);
		$this->errorNumber = $number;
		$this->query = $query;
	}
}
