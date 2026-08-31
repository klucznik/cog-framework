<?php

namespace Cog\Database\Exceptions;

use Cog;

class OptimisticLockingException extends Cog\Exceptions\CogException {
	public function __construct($class) {
		parent::__construct(sprintf('Optimistic Locking constraint when trying to update %s object. To update anyway, call ->save() with $forceUpdate set to true', $class), 2);
	}
}
