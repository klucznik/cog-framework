<?php declare(strict_types=1);

namespace Cog\Database\Adapters;

use Cog;
use Cog\Database\FieldType;
use PgSql\Result as PgSqlResult;

/**
 * A field is built from one of two sources, which is why the constructor takes a
 * normalized array rather than a driver value:
 *
 *  * fromCatalogRow()        - a row of PostgreSqlAdapter::getFieldsForTable()'s
 *                              information_schema query. This is the codegen path
 *                              and the only one with flags, defaults and comments.
 *  * fromResultDescriptor()  - the column descriptor of an arbitrary result set,
 *                              which carries a name, a type and (sometimes) a
 *                              source table, and nothing else.
 */
class PostgreSqlField extends Cog\Database\FieldBase {

	public function __construct(array $data) {
		$this->name = $data['name'];
		$this->originalName = $data['originalName'] ?? $data['name'];
		$this->table = $data['table'] ?? '';
		$this->originalTable = $data['originalTable'] ?? $this->table;
		$this->default = $data['default'] ?? null;
		$this->maxLength = $data['maxLength'] ?? null;
		$this->comment = $data['comment'] ?? '';

		$this->identity = $data['identity'] ?? false;
		$this->notNull = $data['notNull'] ?? false;
		$this->primaryKey = $data['primaryKey'] ?? false;
		$this->unique = $data['unique'] ?? false;

		// PostgreSQL has no auto-updating timestamp column, so nothing coming out
		// of the catalog can be treated as an optimistic-locking token the way
		// MySQL's TIMESTAMP is. Maintaining one requires a trigger, which the
		// catalog cannot distinguish from any other trigger.
		$this->timestamp = false;

		$this->setFieldType($data['udtName']);
	}

	/**
	 * @param array $row one row of the getFieldsForTable() catalog query
	 */
	public static function fromCatalogRow(array $row, string $tableName): self {
		$isSerial = $row['column_default'] !== null && str_starts_with($row['column_default'], 'nextval(');

		return new self([
			'name' => $row['column_name'],
			'table' => $tableName,
			'default' => $row['column_default'],
			'maxLength' => $row['character_maximum_length'] !== null ? (int)$row['character_maximum_length'] : null,
			'comment' => $row['column_comment'] ?? '',
			'identity' => $row['is_identity'] === 'YES' || $isSerial,
			'notNull' => $row['is_nullable'] === 'NO',
			'primaryKey' => $row['is_primary_key'] === 't',
			'unique' => $row['is_unique'] === 't',
			'udtName' => $row['udt_name'],
		]);
	}

	/**
	 * The result descriptor knows the column's type and, for a plain column
	 * reference, the table it came from - pg_field_table() returns false for
	 * anything computed.
	 */
	public static function fromResultDescriptor(PgSqlResult $result, int $index): self {
		$table = pg_field_table($result, $index);

		return new self([
			'name' => pg_field_name($result, $index),
			'table' => $table === false ? '' : (string)$table,
			'udtName' => pg_field_type($result, $index),
		]);
	}

	protected function setFieldType(string $udtName): void {
		switch ($udtName) {
			case 'int2':
			case 'int4':
			case 'int8':
			case 'smallint':
			case 'integer':
			case 'bigint':
				$this->type = FieldType::INTEGER;
				break;

			case 'numeric':
			case 'float4':
			case 'real':
				$this->type = FieldType::FLOAT;
				break;

			case 'float8':
			case 'double precision':
				// Mirrors the MySqli adapter's treatment of MYSQLI_TYPE_DOUBLE:
				// PHP cannot hold a double-precision value without loss, so it is
				// carried as text to preserve the precision.
				$this->type = FieldType::VARCHAR;
				break;

			case 'bool':
			case 'boolean':
				$this->type = FieldType::BIT;
				break;

			case 'bpchar':
			case 'char':
				$this->type = FieldType::CHAR;
				break;

			case 'varchar':
			case 'text':
			case 'name':
			case 'uuid':
			case 'json':
			case 'jsonb':
			case 'inet':
			case 'cidr':
				$this->type = FieldType::VARCHAR;
				break;

			case 'date':
				$this->type = FieldType::DATE;
				break;

			case 'time':
			case 'timetz':
				$this->type = FieldType::TIME;
				break;

			case 'timestamp':
			case 'timestamptz':
				$this->type = FieldType::DATETIME;
				break;

			case 'bytea':
				$this->type = FieldType::BLOB;
				break;

			default:
				throw new \Exception('Unable to determine PostgreSql Field Type: ' . $udtName);
		}
	}
}
