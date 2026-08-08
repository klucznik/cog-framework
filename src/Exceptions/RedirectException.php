<?php declare(strict_types=1);

namespace Cog\Exceptions;

use Exception;

class RedirectException extends Exception {
	/**
	 * @param string $location
	 * @param int $status The status code to use for the Response
	 */
	public function __construct(public string $location, public int $status = 302) {
		parent::__construct('Redirect exception');
	}
}
