<?php

namespace Cog\Database\Exceptions;

use Cog;

class OptimisticLockingException extends Cog\Exceptions\CogException {
	public function __construct($strClass) {
		parent::__construct(sprintf('Optimistic Locking constraint when trying to update %s object. To update anyway, call ->save() with $forceUpdate set to true', $strClass), 2);
	}
}
