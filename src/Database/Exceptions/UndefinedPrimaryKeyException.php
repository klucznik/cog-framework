<?php

namespace Cog\Database\Exceptions;

use Cog;

class UndefinedPrimaryKeyException extends Cog\Exceptions\CogException {
	public function __construct($message) {
		parent::__construct($message, 2);
	}
}
