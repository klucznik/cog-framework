<?php declare(strict_types=1);

namespace Cog\Database\Adapters;

use Cog;
use Throwable;

class PostgreSqlException extends Cog\Database\Exceptions\DatabaseExceptionBase {

	public function __construct(string $message, int $number, string $query, ?Throwable $previous = null) {
		parent::__construct(sprintf('PostgreSql Error: %s', $message), $number, $query, $previous);
	}
}
