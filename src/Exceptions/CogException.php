<?php

namespace Cog\Exceptions;

use RuntimeException;

/**
 * Base class of every exception the framework throws, so that callers can
 * catch them as a group.
 */
class CogException extends RuntimeException {
}
