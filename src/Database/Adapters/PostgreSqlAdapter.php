<?php declare(strict_types=1);

namespace Cog\Database\Adapters;

use Cog;
use Cog\Codegen\ForeignKey;
use Cog\Codegen\Index;
use Cog\Exceptions\CogException;
use PgSql\Connection as PgSqlConnection;
use PgSql\Result as PgSqlResult;

/**
 * @property-read integer $affectedRows
 */
class PostgreSqlAdapter extends Cog\Database\Base {
	public const string ADAPTER = 'PostgreSQL Database Adapter';

	protected PgSqlConnection $postgreSql;

	/** pg_affected_rows() is a property of a result, not of the connection. */
	protected ?PgSqlResult $lastResult = null;

	public function connect(): void {
		if ($this->connectedFlag) {
			return;
		}

		$connectionString = sprintf(
			"host=%s port=%s dbname=%s user=%s password=%s",
			$this->server,
			$this->port ?: 5432,
			$this->database,
			$this->username,
			$this->password
		);

		// pg_connect() emits a warning and returns false rather than throwing,
		// so the failure has to be caught by inspecting the return value.
		$connection = @pg_connect($connectionString);

		if ($connection === false) {
			throw new PostgreSqlException('Unable to connect to Database', -1, '');
		}

		$this->postgreSql = $connection;
		$this->connectedFlag = true;

		if (array_key_exists('encoding', $this->configArray) && $this->configArray['encoding']) {
			pg_set_client_encoding($this->postgreSql, $this->configArray['encoding']);
		}

		if (array_key_exists('timezone', $this->configArray) && $this->configArray['timezone']) {
			$this->nonQuery(sprintf("SET TIME ZONE '%s';", $this->escapeString($this->configArray['timezone'])));
		}

		// PostgreSQL always enforces the equivalent of ONLY_FULL_GROUP_BY, so the
		// query layer must never add ungrouped columns to an aggregate select.
		$this->onlyFullGroupBy = true;
	}

	public function close(): void {
		if ($this->connectedFlag) {
			pg_close($this->postgreSql);
			$this->connectedFlag = false;
		}
	}

	public function __get($name): mixed {
		switch ($name) {
			case 'affectedRows':
				return $this->lastResult === null ? 0 : pg_affected_rows($this->lastResult);

			default:
				try {
					return parent::__get($name);
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}

	public function query(string $query, bool $saveProfilingInfo = true): PostgreSqlResult {
		$result = @pg_query($this->postgreSql, $query);

		if ($result === false) {
			throw new PostgreSqlException(pg_last_error($this->postgreSql), -1, $query);
		}

		$this->lastResult = $result;

		if ($saveProfilingInfo) {
			$this->logQuery($query);
		}

		return new PostgreSqlResult($result, $this);
	}

	/** @inheritdoc */
	public function nonQuery(string $sql, bool $saveProfilingInfo = true): void {
		$result = @pg_query($this->postgreSql, $sql);

		if ($result === false) {
			throw new PostgreSqlException(pg_last_error($this->postgreSql), -1, $sql);
		}

		$this->lastResult = $result;

		if ($this->profiling && $saveProfilingInfo) {
			$this->logQuery($sql);
		}
	}

	public function escapeString(string $text): string {
		// The connection argument is not optional: omitting it is deprecated as
		// of PHP 8.1 and the escaping would ignore the connection's encoding.
		return pg_escape_string($this->postgreSql, $text);
	}

	/**
	 * PostgreSQL has a real boolean type, so the integer literals the generic
	 * implementation emits ("= 0") fail with "operator does not exist:
	 * boolean = integer". Everything else is left to the parent.
	 */
	public function sqlVariable(mixed $data, bool $includeEquality = false, bool $reverseEquality = false): string {
		if (is_bool($data)) {
			if (!$includeEquality) {
				return $data ? 'TRUE' : 'FALSE';
			}

			if ($reverseEquality) {
				return $data ? '= FALSE' : '!= FALSE';
			}

			return $data ? '!= FALSE' : '= FALSE';
		}

		return parent::sqlVariable($data, $includeEquality, $reverseEquality);
	}

	public function sqlLimitVariablePrefix(string $limitInfo): ?string {
		// PostgreSQL limits by suffix only.
		return null;
	}

	public function sqlLimitVariableSuffix(string $limitInfo): ?string {
		if ($limitInfo === '') {
			return null;
		}

		// QQLimitInfo hands over MySQL's "offset,count" (or a bare "count"),
		// which has to be turned around for PostgreSQL's LIMIT ... OFFSET.
		$parts = explode(',', $limitInfo);

		foreach ($parts as $part) {
			if (!is_numeric(trim($part))) {
				throw new \Exception('Invalid LIMIT Info: ' . $limitInfo);
			}
		}

		if (count($parts) === 2) {
			return sprintf('LIMIT %s OFFSET %s', trim($parts[1]), trim($parts[0]));
		}

		return sprintf('LIMIT %s', trim($parts[0]));
	}

	public function sqlSortByVariable(string $sortByInfo): ?string {
		if ($sortByInfo === '') {
			return null;
		}

		if (str_contains($sortByInfo, ';')) {
			throw new \Exception('Invalid Semicolon in ORDER BY Info');
		}

		return 'ORDER BY ' . $sortByInfo;
	}

	public function transactionBegin(): void {
		$this->nonQuery('BEGIN;');
	}

	public function transactionCommit(): void {
		$this->nonQuery('COMMIT;');
	}

	public function transactionRollback(): void {
		$this->nonQuery('ROLLBACK;');
	}

	/**
	 * Without a table and column there is nothing to name a sequence with, so
	 * this falls back to the session's last nextval() - which is what MySQL's
	 * insert_id offers and what the generated ORM calls.
	 */
	public function insertId(?string $tableName = null, ?string $columnName = null): mixed {
		if ($tableName !== null && $columnName !== null) {
			$result = $this->query(sprintf(
				"SELECT currval(pg_get_serial_sequence('%s', '%s'))",
				$this->escapeString($tableName),
				$this->escapeString($columnName)
			), false);
		} else {
			$result = $this->query('SELECT lastval()', false);
		}

		$row = $result->fetchRow();

		return $row === null ? null : (int)$row[0];
	}

	public function getTables(): array {
		$result = $this->query(sprintf("
			SELECT
				table_name
			FROM
				information_schema.tables
			WHERE
				table_type = 'BASE TABLE' AND
				table_schema = current_schema()
			ORDER BY
				table_name
		"));

		$toReturn = [];
		while ($rowArray = $result->fetchRow()) {
			$toReturn[] = $rowArray[0];
		}

		return $toReturn;
	}

	/**
	 * One query per table rather than the three-per-column the qcubed adapter
	 * this was adapted from issues: the primary-key and unique flags are folded
	 * in as EXISTS subqueries.
	 *
	 * @return PostgreSqlField[]
	 */
	public function getFieldsForTable(string $tableName): array {
		$result = $this->query(sprintf("
			SELECT
				c.column_name,
				c.udt_name,
				c.character_maximum_length,
				c.column_default,
				c.is_nullable,
				c.is_identity,
				col_description(cls.oid, c.ordinal_position) AS column_comment,
				EXISTS (
					SELECT 1 FROM pg_index i
					WHERE i.indrelid = cls.oid AND i.indisprimary
						AND c.ordinal_position::smallint = ANY (i.indkey::int2[])
				) AS is_primary_key,
				EXISTS (
					SELECT 1 FROM pg_index i
					WHERE i.indrelid = cls.oid AND i.indisunique
						AND i.indnkeyatts = 1
						AND c.ordinal_position::smallint = ANY (i.indkey::int2[])
				) AS is_unique
			FROM
				information_schema.columns c
				JOIN pg_class cls ON cls.relname = c.table_name
				JOIN pg_namespace ns ON ns.oid = cls.relnamespace AND ns.nspname = c.table_schema
			WHERE
				c.table_name = '%s' AND
				c.table_schema = current_schema()
			ORDER BY
				c.ordinal_position
		", $this->escapeString($tableName)));

		$toReturn = [];
		while ($row = $result->fetchArrayAssoc()) {
			$toReturn[] = PostgreSqlField::fromCatalogRow($row, $tableName);
		}

		return $toReturn;
	}

	/**
	 * Index columns are read out of pg_attribute rather than parsed back out of
	 * pg_get_indexdef()'s text, which is what makes index names containing
	 * commas or parentheses safe here.
	 *
	 * @return Index[]
	 */
	public function getIndexesForTable(string $tableName): array {
		$result = $this->query(sprintf("
			SELECT
				ic.relname AS index_name,
				i.indisprimary,
				i.indisunique,
				(
					SELECT string_agg(a.attname, ',' ORDER BY k.ord)
					FROM unnest(i.indkey::int2[]) WITH ORDINALITY AS k(attnum, ord)
					JOIN pg_attribute a ON a.attrelid = tc.oid AND a.attnum = k.attnum
					WHERE k.ord <= i.indnkeyatts
				) AS column_names
			FROM
				pg_index i
				JOIN pg_class tc ON tc.oid = i.indrelid
				JOIN pg_class ic ON ic.oid = i.indexrelid
				JOIN pg_namespace ns ON ns.oid = tc.relnamespace
			WHERE
				tc.relname = '%s' AND
				ns.nspname = current_schema()
			ORDER BY
				i.indexrelid
		", $this->escapeString($tableName)));

		$indexArray = [];
		while ($row = $result->fetchArrayAssoc()) {
			// An expression index has no pg_attribute row to name, and there is
			// no column list for the generator to key an object off.
			if ($row['column_names'] === null) {
				continue;
			}

			$index = new Index($row['index_name']);
			$index->primaryKey = $row['indisprimary'] === 't';
			$index->unique = $row['indisunique'] === 't';
			$index->columnNameArray = explode(',', $row['column_names']);

			$indexArray[] = $index;
		}

		return $indexArray;
	}

	/**
	 * @return ForeignKey[]
	 */
	public function getForeignKeysForTable(string $tableName): array {
		$result = $this->query(sprintf("
			SELECT
				con.conname AS key_name,
				(
					SELECT string_agg(a.attname, ',' ORDER BY k.ord)
					FROM unnest(con.conkey) WITH ORDINALITY AS k(attnum, ord)
					JOIN pg_attribute a ON a.attrelid = con.conrelid AND a.attnum = k.attnum
				) AS column_names,
				fc.relname AS reference_table_name,
				(
					SELECT string_agg(a.attname, ',' ORDER BY k.ord)
					FROM unnest(con.confkey) WITH ORDINALITY AS k(attnum, ord)
					JOIN pg_attribute a ON a.attrelid = con.confrelid AND a.attnum = k.attnum
				) AS reference_column_names
			FROM
				pg_constraint con
				JOIN pg_class c ON c.oid = con.conrelid
				JOIN pg_namespace ns ON ns.oid = c.relnamespace
				JOIN pg_class fc ON fc.oid = con.confrelid
			WHERE
				con.contype = 'f' AND
				c.relname = '%s' AND
				ns.nspname = current_schema()
			ORDER BY
				con.oid
		", $this->escapeString($tableName)));

		$foreignKeyArray = [];
		while ($row = $result->fetchArrayAssoc()) {
			$foreignKeyArray[] = new ForeignKey(
				$row['key_name'],
				explode(',', $row['column_names']),
				$row['reference_table_name'],
				explode(',', $row['reference_column_names'])
			);
		}

		return $foreignKeyArray;
	}
}
