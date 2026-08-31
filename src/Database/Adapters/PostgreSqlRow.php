<?php declare(strict_types=1);

namespace Cog\Database\Adapters;

use Carbon\Carbon;
use Cog;
use Cog\Database\FieldType;
use Cog\Type;

class PostgreSqlRow extends Cog\Database\RowBase {

	/** @var string[] */
	protected array $columnArray;

	public function __construct(array $columnArray) {
		$this->columnArray = $columnArray;
	}

	/** @inheritdoc */
	public function getColumn(string $columnName, ?string $columnType = null): mixed {
		if (!array_key_exists($columnName, $this->columnArray)) {
			return null;
		}

		if ($this->columnArray[$columnName] === null) {
			return null;
		}

		switch ($columnType) {
			case FieldType::BIT:
				// libpq renders booleans as the single characters 't' and 'f',
				// so the MySqli adapter's ord() test does not apply here.
				return $this->columnArray[$columnName] === 't';

			case FieldType::BLOB:
				// bytea arrives in the hex/escape output format and has to be
				// decoded before it is a byte string again.
				return pg_unescape_bytea($this->columnArray[$columnName]);

			case FieldType::CHAR:
			case FieldType::VARCHAR:
				return Type::cast($this->columnArray[$columnName], Type::STRING);

			case FieldType::DATE:
			case FieldType::DATETIME:
			case FieldType::TIME:
			case FieldType::TIMESTAMP:
				return new Carbon($this->columnArray[$columnName]);

			case FieldType::FLOAT:
				return Type::cast($this->columnArray[$columnName], Type::FLOAT);

			case FieldType::INTEGER:
				return Type::cast($this->columnArray[$columnName], Type::INTEGER);

			default:
				return $this->columnArray[$columnName];
		}
	}

	/** @inheritdoc */
	public function columnExists(string $columnName): bool {
		return array_key_exists($columnName, $this->columnArray);
	}

	/** @inheritdoc */
	public function getColumnNameArray(): array {
		return $this->columnArray;
	}
}
