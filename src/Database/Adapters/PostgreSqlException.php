<?php declare(strict_types=1);

namespace Cog\Database\Adapters;

use Cog;

class PostgreSqlException extends Cog\Database\Exceptions\DatabaseExceptionBase {

	public function __construct(string $message, int $number, string $query) {
		parent::__construct(sprintf('PostgreSql Error: %s', $message), 2);
		$this->errorNumber = $number;
		$this->query = $query;
	}
}
