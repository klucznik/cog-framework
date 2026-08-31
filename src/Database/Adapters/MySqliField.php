<?php

namespace Cog\Database\Adapters;

use Cog;
use Cog\Database\FieldType;

class MySqliField extends Cog\Database\FieldBase {

	public function __construct(object $fieldData, ?MySqliAdapter $database = null) {
		$this->name = $fieldData->name;
		$this->originalName = $fieldData->orgname;
		$this->table = $fieldData->table;
		$this->originalTable = $fieldData->orgtable;
		$this->default = $fieldData->def;
		$this->maxLength = null;
		$this->comment = '';

		//set originalName to name if it isn't set
		if (!$this->originalName) {
			$this->originalName = $this->name;
		}

		if ($this->originalTable) {
			$descriptionResult = $database->query(sprintf('SHOW FULL FIELDS FROM `%s`', $this->originalTable));
			while ($row = $descriptionResult->fetchArray()) {
				if ($row['Field'] === $this->originalName) {

					$this->default = $row['Default'];
					// Calculate MaxLength of this column (e.g. if it's a varchar, calculate length of varchar
					// NOTE: $fieldData->max_length in the MySQL spec is **DIFFERENT**
					$lengthArray = explode('(', $row['Type']);
					if (count($lengthArray) > 1 && strtolower($lengthArray[0]) !== 'enum' && strtolower($lengthArray[0]) !== 'set' ) {
						$lengthArray = explode(')', $lengthArray[1]);
						$this->maxLength = (int)$lengthArray[0];

						// If the length is something like (7,2), then let's pull out just the "7"
						$commaPosition = strpos($this->maxLength, ',');
						if ($commaPosition !== false) {
							$this->maxLength = (int)substr($this->maxLength, 0, $commaPosition);
						}

						if (!is_numeric($this->maxLength)) {
							throw new \Exception('Not a valid Column Length: ' . $row['Type']);
						}
					}

					// Get the field comment
					$this->comment = $row['Comment'];
				}
			}
		}

		$this->identity = $fieldData->flags & MYSQLI_AUTO_INCREMENT_FLAG;
		$this->notNull = $fieldData->flags & MYSQLI_NOT_NULL_FLAG;
		$this->primaryKey = $fieldData->flags & MYSQLI_PRI_KEY_FLAG;
		$this->unique = $fieldData->flags & MYSQLI_UNIQUE_KEY_FLAG;

		$this->setFieldType($fieldData->type);
	}

	protected function setFieldType(string $mySqlFieldType): void {
		switch ($mySqlFieldType) {
			case MYSQLI_TYPE_BIT:
				$this->type = FieldType::BIT;
				break;

			case MYSQLI_TYPE_TINY:
				if ($this->maxLength === 1) {
					$this->type = FieldType::BIT;
				} else {
					$this->type = FieldType::INTEGER;
				}
				break;

			case MYSQLI_TYPE_SHORT:
			case MYSQLI_TYPE_LONG:
			case MYSQLI_TYPE_LONGLONG:
			case MYSQLI_TYPE_INT24:
				$this->type = FieldType::INTEGER;
				break;

			case MYSQLI_TYPE_NEWDECIMAL:
			case MYSQLI_TYPE_DECIMAL:
			case MYSQLI_TYPE_FLOAT:
				$this->type = FieldType::FLOAT;
				break;

			case MYSQLI_TYPE_DOUBLE:
				// NOTE: PHP does not offer full support of double-precision floats.
				// Value will be set as a VarChar which will guarantee that the precision will be maintained.
				//    However, you will not be able to support full typing control (e.g. you would
				//    not be able to use a QFloatTextBox -- only a regular QTextBox)
				$this->type = FieldType::VARCHAR;
				break;

			case MYSQLI_TYPE_TIMESTAMP:
				// System-generated Timestamp values need to be treated as plain text
				$this->type = FieldType::TIMESTAMP;
				$this->timestamp = true;
				break;

			case MYSQLI_TYPE_DATE:
				$this->type = FieldType::DATE;
				break;

			case MYSQLI_TYPE_TIME:
				$this->type = FieldType::TIME;
				break;

			case MYSQLI_TYPE_DATETIME:
				$this->type = FieldType::DATETIME;
				break;

			case MYSQLI_TYPE_TINY_BLOB:
			case MYSQLI_TYPE_MEDIUM_BLOB:
			case MYSQLI_TYPE_LONG_BLOB:
			case MYSQLI_TYPE_BLOB:
				$this->type = FieldType::BLOB;
				break;

			case MYSQLI_TYPE_STRING:
			case MYSQLI_TYPE_VAR_STRING:
				$this->type = FieldType::VARCHAR;
				break;

			case MYSQLI_TYPE_CHAR:
				$this->type = FieldType::CHAR;
				break;

			// There is deliberately no MYSQLI_TYPE_INTERVAL case: the constant does
			// not exist in the mysqli extension, so naming it made the switch throw
			// "Undefined constant" for every type reaching this far - YEAR and the
			// unsupported types below included - instead of the message meant for it.

			case MYSQLI_TYPE_NULL:
				throw new \Exception('MySqli library: MYSQLI_TYPE_NULL is not supported');
				break;

			case MYSQLI_TYPE_YEAR:
				$this->type = FieldType::INTEGER;
				break;

			case MYSQLI_TYPE_NEWDATE:
				throw new \Exception('MySqli library: MYSQLI_TYPE_NEWDATE is not supported');
				break;

			case MYSQLI_TYPE_ENUM:
				throw new \Exception('MySqli library: MYSQLI_TYPE_ENUM is not supported. Use TypeTables instead.');
				break;

			case MYSQLI_TYPE_SET:
				throw new \Exception('MySqli library: MYSQLI_TYPE_SET is not supported. Use TypeTables instead.');
				break;

			case MYSQLI_TYPE_GEOMETRY:
				throw new \Exception('MySqli library: MYSQLI_TYPE_GEOMETRY is not supported');
				break;

			default:
				throw new \Exception('Unable to determine MySqli Field Type: ' . $mySqlFieldType);
				break;
		}
	}
}
