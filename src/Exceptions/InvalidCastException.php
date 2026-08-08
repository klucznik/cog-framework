<?php

namespace Cog\Exceptions;

/**
 * The exception that is thrown by Type::Cast
 * if an invalid cast is performed.  InvalidCastException
 * derives from CallerException, and therefore should be handled
 * similar to how CallerExceptions are handled (e.g. incrementOffset should
 * be called whenever an InvalidCastException is caught and rethrown).
 */
class InvalidCastException extends CogException {

	public function __construct($message, $offset = 2) {
		parent::__construct($message, $offset);
	}
}
