<?php declare(strict_types=1);

namespace Cog\Database\Adapters;

use Cog;
use PgSql\Result as PgSqlResult;

class PostgreSqlResult extends Cog\Database\ResultBase {

	protected PgSqlResult $postgreSqlResult;
	protected PostgreSqlAdapter $database;

	/**
	 * pg_fetch_field() has no equivalent to mysqli's stateful fetch_field(), so
	 * the cursor fetchField() advances is kept here.
	 */
	protected int $fieldCursor = 0;

	public function __construct(PgSqlResult $result, PostgreSqlAdapter $database) {
		$this->postgreSqlResult = $result;
		$this->database = $database;
	}

	public function fetchArray(): ?array {
		return pg_fetch_array($this->postgreSqlResult) ?: null;
	}

	public function fetchArrayAssoc(): ?array {
		return pg_fetch_assoc($this->postgreSqlResult) ?: null;
	}

	public function fetchRow(): ?array {
		return pg_fetch_row($this->postgreSqlResult) ?: null;
	}

	/**
	 * @return PostgreSqlField[]
	 */
	public function fetchFields(): array {
		$toReturn = [];

		while ($field = $this->fetchField()) {
			$toReturn[] = $field;
		}

		return $toReturn;
	}

	public function fetchField(): ?PostgreSqlField {
		if ($this->fieldCursor >= pg_num_fields($this->postgreSqlResult)) {
			return null;
		}

		return PostgreSqlField::fromResultDescriptor($this->postgreSqlResult, $this->fieldCursor++);
	}

	public function countRows(): int {
		return pg_num_rows($this->postgreSqlResult);
	}

	public function close(): void {
		pg_free_result($this->postgreSqlResult);
	}

	/**
	 * @return PostgreSqlRow|null
	 */
	public function getNextRow(): ?object {
		$columnArray = $this->fetchArray();

		if ($columnArray) {
			return new PostgreSqlRow($columnArray);
		}

		return null;
	}

	public function getRows(): array {
		$rows = [];

		while ($row = $this->getNextRow()) {
			$rows[] = $row;
		}

		return $rows;
	}
}
