<?php

namespace Cog\Database\Adapters;

use Carbon\Carbon;
use Cog;
use Cog\Database\FieldType;
use Cog\Type;

class MySqliRow extends Cog\Database\RowBase {

	/** @var string[] */
	protected array $columnArray;

	public function __construct(array $columnArray) {
		$this->columnArray = $columnArray;
	}

	/** @inheritdoc */
	public function getColumn(string $columnName, ?string $columnType = null): mixed {
		if (array_key_exists($columnName, $this->columnArray)) {
			if ($this->columnArray[$columnName] === null) {
				return null;
			}

			switch ($columnType) {
				case FieldType::BIT:
					// Account for single bit value
					$chrBit = $this->columnArray[$columnName];
					if ((strlen($chrBit) === 1) && (ord($chrBit) === 0)) {
						return false;
					}

					// Otherwise, use PHP conditional to determine true or false
					return $this->columnArray[$columnName] ? true : false;

				case FieldType::BLOB:
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
		
		return null;
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
