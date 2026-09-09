<?php declare(strict_types=1);

namespace Cog\Test;

use Cog\Codegen\ForeignKey;
use Cog\Codegen\Index;
use Cog\Database\Base;
use Cog\Database\ResultBase;
use LogicException;

/**
 * A database adapter that answers the schema questions the code generator asks
 * from arrays built in a test, so DatabaseCodeGen's analysis can be driven with
 * schemas the fixture database does not have - malformed association tables,
 * reserved-word names, foreign keys without an index - and without a server.
 *
 * Only the schema methods are real. Everything that would need a connection
 * throws, so a test that reaches one fails loudly rather than pretending.
 */
class FakeSchemaAdapter extends Base {

	public const string ADAPTER = 'Fake Schema Adapter';

	/** @var array<string, FakeSchemaField[]> */
	private array $fields = [];
	/** @var array<string, Index[]> */
	private array $indexes = [];
	/** @var array<string, ForeignKey[]> */
	private array $foreignKeys = [];
	/** @var array<string, array[]> positional rows answered to SELECT * FROM <table> */
	private array $rows = [];

	public function __construct(int $databaseIndex) {
		parent::__construct($databaseIndex, [
			'adapter' => 'FakeSchema',
			'server' => 'fake',
			'port' => null,
			'database' => 'fake_db',
			'username' => '',
			'password' => '',
			'profiling' => false,
		]);
	}

	/**
	 * @param FakeSchemaField[] $fields in column order
	 * @param Index[] $indexes secondary indexes only; the generator derives the primary key index from the fields
	 * @param ForeignKey[] $foreignKeys
	 * @param array[] $rows what SELECT * FROM the table returns, one positional array per row
	 */
	public function addTable(string $name, array $fields, array $indexes = [], array $foreignKeys = [], array $rows = []): static {
		$this->fields[$name] = $fields;
		$this->indexes[$name] = $indexes;
		$this->foreignKeys[$name] = $foreignKeys;
		$this->rows[$name] = $rows;

		return $this;
	}

	public function getTables(): array {
		return array_keys($this->fields);
	}

	public function getFieldsForTable(string $tableName): array {
		return $this->fields[$tableName] ?? [];
	}

	public function getIndexesForTable(string $tableName): array {
		return $this->indexes[$tableName] ?? [];
	}

	public function getForeignKeysForTable(string $tableName): array {
		return $this->foreignKeys[$tableName] ?? [];
	}

	/** Answers the `SELECT * FROM <table>` the generator issues to read a type table's rows. */
	public function query(string $query, bool $saveProfilingInfo = true): ResultBase {
		if (!preg_match('/^\s*SELECT \* FROM `?(\w+)`?\s*$/i', $query, $match) || !array_key_exists($match[1], $this->rows)) {
			throw new LogicException('FakeSchemaAdapter only answers SELECT * FROM <known table>, not: ' . $query);
		}

		return new FakeSchemaResult($this->rows[$match[1]]);
	}

	public function connect(): void {
		$this->connectedFlag = true;
	}

	public function close(): void {
		$this->connectedFlag = false;
	}

	public function nonQuery(string $sql, bool $saveProfilingInfo = true): void {
		throw self::unsupported(__FUNCTION__);
	}

	public function insertId(?string $tableName = null, ?string $columnName = null): mixed {
		throw self::unsupported(__FUNCTION__);
	}

	public function transactionBegin(): void {
		throw self::unsupported(__FUNCTION__);
	}

	public function transactionCommit(): void {
		throw self::unsupported(__FUNCTION__);
	}

	public function transactionRollback(): void {
		throw self::unsupported(__FUNCTION__);
	}

	public function sqlLimitVariablePrefix(string $limitInfo): ?string {
		throw self::unsupported(__FUNCTION__);
	}

	public function sqlLimitVariableSuffix(string $limitInfo): ?string {
		throw self::unsupported(__FUNCTION__);
	}

	public function sqlSortByVariable(string $sortByInfo): ?string {
		throw self::unsupported(__FUNCTION__);
	}

	public function escapeString(string $text): string {
		throw self::unsupported(__FUNCTION__);
	}

	private static function unsupported(string $method): LogicException {
		return new LogicException(sprintf('FakeSchemaAdapter::%s() is not supported: the fake only describes a schema', $method));
	}
}
