<?php

namespace Cog\Database;

use Cog;
use Cog\Exceptions\CogException;
use Cog\Exceptions\InvalidCastException;

/**
 * @package DatabaseAdapters
 */
abstract class RowBase extends Cog\Base {

	/**
	 * @param string $columnName
	 * @param string|null $columnType
	 * @return mixed
	 * @throws CogException
	 * @throws InvalidCastException
	 */
	abstract public function getColumn(string $columnName, ?string $columnType = null): mixed;

	/**
	 * @param string $columnName
	 * @return bool
	 */
	abstract public function columnExists(string $columnName): bool;

	/**
	 * @return string[]
	 */
	abstract public function getColumnNameArray(): array;
}
