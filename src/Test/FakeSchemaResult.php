<?php declare(strict_types=1);

namespace Cog\Test;

use Cog\Database\ResultBase;
use LogicException;

/**
 * The result FakeSchemaAdapter::query() hands back: a list of positional rows,
 * consumed by fetchRow(), which is all the code generator uses when it reads a
 * type table.
 */
class FakeSchemaResult extends ResultBase {

	/** @param array[] $rows */
	public function __construct(private array $rows) {}

	public function fetchRow(): ?array {
		return array_shift($this->rows);
	}

	public function fetchArray(): ?array {
		return $this->fetchRow();
	}

	public function countRows(): int {
		return count($this->rows);
	}

	public function close(): void {}

	public function fetchArrayAssoc(): ?array {
		throw self::unsupported(__FUNCTION__);
	}

	public function fetchField(): ?object {
		throw self::unsupported(__FUNCTION__);
	}

	public function fetchFields(): array {
		throw self::unsupported(__FUNCTION__);
	}

	public function getNextRow(): ?object {
		throw self::unsupported(__FUNCTION__);
	}

	public function getRows(): array {
		throw self::unsupported(__FUNCTION__);
	}

	private static function unsupported(string $method): LogicException {
		return new LogicException(sprintf('FakeSchemaResult::%s() is not supported: only positional rows are faked', $method));
	}
}
