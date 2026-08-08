<?php

namespace Cog\Codegen;

use Cog;
use Cog\Base;
use Cog\Exceptions\CogException;
use Cog\Type;
use Cog\Util\ConvertNotation;
use Symfony\Component\String\ByteString;

/**
 * Used by the Code Generator to describe a table's column
 * @package Codegen
 *
 * @property TableBase $ownerTable Cog\Codegen\Table The table in which this column exists.
 * @property bool $primaryKey Specifies whether the column is a Primary Key
 * @property string $name Name of the column as defined in the database So for example, "first_name"
 * @property string $propertyName Name of the column as an object Property
 * @property string $variableName Name of the column as an object protected Member Variable So for "first_name VARCHAR(50)", it would be strFirstName
 * @property string $variableType The type of the protected member variable (uses one of the string constants from the Type class)
 * @property-read string $variableTyped The type declaration of the member variable
 * @property-read string $variableTypeJs The JS type declaration of the member variable
 * @property string $variableTypeAsConstant The type of the protected member variable (uses the actual constant from the Type class)
 * @property string $dbType The actual type of the column in the database (uses one of the string constants from the DatabaseType class)
 * @property integer $length Length of the column as defined in the database
 * @property mixed $default The default value for the column as defined in the database
 * @property bool $notNull Specifies whether the column is specified as "NOT NULL"
 * @property bool $identity Specifies whether the column is an identity column (like auto_increment)
 * @property bool $indexed Specifies whether the column is a single-column Index
 * @property bool $unique Specifies whether the column is a unique
 * @property bool $timestamp Specifies whether the column is a system-updated "timestamp" column
 * @property Reference $reference If the table column is foreign keyed off another column, then this Column instance would be a reference to another object
 * @property string $comment The string value of the comment field in the database.
 *
 * @property-read string $propertyNameUppercase
 * @property-read string $variableNameUppercase
 * @property-read string $constantPropertyName
 */
class Column extends Base {

	/** @var TableBase The table in which this column exists. */
	private TableBase $ownerTable;

	/** @var bool Specifies whether the column is a Primary Key */
	private bool $primaryKey;

	/** @var string Name of the column as defined in the database so for example, "first_name" */
	private string $name;

	/** @var string Name of the column as an object Property So for "first_name", it would be FirstName */
	private string $propertyName;

	/** @var string Name of the column as an object protected Member Variable So for "first_name VARCHAR(50)", it would be strFirstName */
	private string $variableName;

	/** @var string The type of the protected member variable (uses one of the string constants from the Type class) */
	private string $variableType;

	/** @var string The type of the protected member variable (uses the actual constant from the Type class) */
	private string $variableTypeAsConstant;

	/** @var string The actual type of the column in the database (uses one of the string constants from the DatabaseType class) */
	private string $dbType;

	/** @var ?int Length of the column as defined in the database */
	private ?int $length;

	/** @var mixed The default value for the column as defined in the database */
	private mixed $default;

	/** @var bool Specifies whether the column is specified as "NOT NULL" */
	private bool $notNull = false;

	/** @var bool Specifies whether the column is an identity column (like auto_increment) */
	private bool $identity = false;

	/** @var bool Specifies whether the column is a single-column Index */
	private bool $indexed = false;

	/** @var bool Specifies whether the column is a unique */
	private bool $unique = false;

	/** @var bool Specifies whether the column is a system-updated "timestamp" column */
	private bool $timestamp = false;

	/** @var Reference|null If the table column is foreign keyed off another column, then this Column instance would be a reference to another object */
	private ?Reference $reference = null;

	/** @var string The string value of the comment field in the database. */
	private string $comment;


	function getDefaultAsString(): string {
		if (null === $this->default && $this->variableType === Type::STRING) {
			return "''";
		} elseif ($this->timestamp) {
			return 'null';
		} elseif (null === $this->default) {
			return 'null';
		} elseif ($this->variableType === Type::DATETIME) {
			if ($this->hasCurrentTimestampDefault()) {
				return 'new Carbon()';
			}
			return 'null';
		} elseif ($this->variableType === Type::BOOLEAN) {
			return ($this->default) ? 'true' : 'false';
		} elseif (is_numeric($this->default)) {
			return $this->default;
		} else {
			return "'" . Cog\Util\StringUtils::addslashes($this->default) . "'";
		}
	}

	/**
	 * Whether the column defaults to the moment the row is created, i.e. one of
	 * the SQL expressions MySQL reports as the column default for
	 * `DEFAULT CURRENT_TIMESTAMP`: current_timestamp, current_timestamp(),
	 * current_timestamp(3), now(), localtime, localtimestamp.
	 *
	 * Such a default cannot be expressed as a literal, so it is emitted as a
	 * run-time expression by getDefaultAsPhp() rather than by
	 * getDefaultAsString().
	 *
	 * @return bool
	 */
	public function hasCurrentTimestampDefault(): bool {
		if ($this->variableType !== Type::DATETIME || $this->default === null) {
			return false;
		}

		// A `timestamp` column is maintained by the database itself (and used here for optimistic locking), so it is left alone
		if ($this->timestamp) {
			return false;
		}

		$default = strtolower(trim((string)$this->default));

		//stripping trailing parentheses
		$default = preg_replace('/\s*\(\s*\d*\s*\)$/', '', $default);

		return in_array($default, ['current_timestamp', 'now', 'localtime', 'localtimestamp'], true);
	}

	/**
	 * Override method to perform a property "Get"
	 * This will get the value of $strName
	 * @param string $name Name of the property to get
	 * @return mixed
	 * @throws CogException
	 */
	public function __get($name) {
		switch ($name) {
			case 'ownerTable':
				return $this->ownerTable;
			case 'primaryKey':
				return $this->primaryKey;
			case 'name':
				return $this->name;
			case 'propertyName':
				return $this->propertyName;
			case 'variableName':
				return $this->variableName;
			case 'variableType':
				return $this->variableType;
			case 'variableTyped':
				return Type::getDeclarationType($this->variableType);
			case 'variableTypeJs':
				switch($this->variableType) {
					case 'double':
					case 'integer':
						return 'number';

					case 'boolean': return 'boolean';

					default: return 'string';
				}
			case 'variableTypeAsConstant':
				return $this->variableTypeAsConstant;
			case 'dbType':
				return $this->dbType;
			case 'length':
				return $this->length;
			case 'default':
				return $this->default;
			case 'notNull':
				return $this->notNull;
			case 'identity':
				return $this->identity;
			case 'indexed':
				return $this->indexed;
			case 'unique':
				return $this->unique;
			case 'timestamp':
				return $this->timestamp;
			case 'reference':
				return $this->reference;
			case 'comment':
				return $this->comment;

			case 'propertyNameUppercase':
				return (new ByteString($this->propertyName))->title();
			case 'variableNameUppercase':
				return (new ByteString($this->variableName))->title();
			case 'constantPropertyName':
				return strtoupper(ConvertNotation::snakeCase($this->propertyName));

			default:
				try {
					return parent::__get($name);
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}

	/**
	 * Override method to perform a property "Set"
	 * This will set the property $name to be $value
	 * @param string $name Name of the property to set
	 * @param mixed $value New value of the property
	 * @return mixed
	 * @throws CogException
	 */
	public function __set($name, $value) {
		try {
			switch ($name) {
				case 'ownerTable':
					return $this->ownerTable = Type::cast($value, Cog\Codegen\TableBase::class);
				case 'primaryKey':
					return $this->primaryKey = Type::cast($value, Type::BOOLEAN);
				case 'name':
					return $this->name = Type::cast($value, Type::STRING);
				case 'propertyName':
					return $this->propertyName = Type::cast($value, Type::STRING);
				case 'variableName':
					return $this->variableName = Type::cast($value, Type::STRING);
				case 'variableType':
					return $this->variableType = Type::cast($value, Type::STRING);
				case 'variableTypeAsConstant':
					return $this->variableTypeAsConstant = Type::cast($value, Type::STRING);
				case 'dbType':
					return $this->dbType = Type::cast($value, Type::STRING);
				case 'length':
					return $this->length = Type::cast($value, Type::INTEGER);
				case 'default':
					if ($value === null || (($value === '0000-00-00 00:00:00' || $value === '0000-00-00') && !$this->notNull)) {
						return $this->default = null;
					}
					if (is_int($value)) {
						return $this->default = Type::cast($value, Type::INTEGER);
					}
					if (is_numeric($value)) {
						return $this->default = Type::cast($value, Type::FLOAT);
					}
					return $this->default = Type::cast($value, Type::STRING);

				case 'notNull':
					return $this->notNull = Type::cast($value, Type::BOOLEAN);
				case 'identity':
					return $this->identity = Type::cast($value, Type::BOOLEAN);
				case 'indexed':
					return $this->indexed = Type::cast($value, Type::BOOLEAN);
				case 'unique':
					return $this->unique = Type::cast($value, Type::BOOLEAN);
				case 'timestamp':
					return $this->timestamp = Type::cast($value, Type::BOOLEAN);
				case 'reference':
					return $this->reference = Type::cast($value, Cog\Codegen\Reference::class);
				case 'comment':
					return $this->comment = Type::cast($value, Type::STRING);
				default:
					return parent::__set($name, $value);
			}
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
	}
}
