<?php

namespace Cog\Codegen;

use Cog;
use Cog\Exceptions\CogException;
use Cog\Type;
use Symfony\Component\String\ByteString;

/**
 * Used by the Code Generator to describe a column reference from
 * the table's perspective (aka a Foreign Key from the referenced Table's point of view)
 *
 * 	@property string $keyName
 * 	@property string $table
 * 	@property string $column
 * 	@property string $oppositeColumn
 * 	@property string $oppositeVariableType
 * 	@property-read string $oppositeVariableTyped
 * 	@property string $oppositeVariableName
 * 	@property string $oppositePropertyName
 * 	@property string $oppositeObjectDescription
 * 	@property string $associatedTable
 * 	@property string $variableName
 * 	@property string $variableType
 * 	@property string $objectDescription
 * 	@property string $objectDescriptionPlural
 * 	@property Column[] $columnArray
 *
 *  @property-read string $variableNameUppercase
 *  @property-read string $propertyNameUppercase
 *  @property-read string objectDescriptionUppercase
 *  @property-read string objectDescriptionPluralUppercase
 *
 * @package Codegen
 */
class ManyToManyReference extends Cog\Base {

	/** @var string Name of the foreign key object itself, as defined in the database or create script */
	protected string $keyName;

	/** @var string Name of the association table, itself (the many-to-many table that maps the relationship for this ManyToManyReference) */
	protected string $table;

	/** @var string Name of the referencing column (the column that owns the foreign key to this table) */
	protected string $column;

	/** @var string Name of the opposite column (the column that owns the foreign key to the related table) */
	protected string $oppositeColumn;

	/**
	 * Type of the opposite column (the column that owns the foreign key to the related table)
	 * as a Variable type (for example, to be used to define the input parameter type to a Load function)
	 * @var string
	 */
	protected string $oppositeVariableType;

	/**
	 * Name of the opposite column (the column that owns the foreign key to the related table)
	 * as a Variable name (for example, to be used as an input parameter to a Load function)
	 * @var string
	 */
	protected string $oppositeVariableName;

	/**
	 * Name of the opposite column (the column that owns the foreign key to the related table)
	 * as a Property name (for example, to be used as a Cog\Query\QQAssociationNode parameter name for the
	 * column itself)
	 * @var string
	 */
	protected string $oppositePropertyName;

	/**
	 * Name of the opposite column (the column that owns the foreign key to the related table)
	 * as an Object Description (see "ObjectDescription" below)
	 * @var string
	 */
	protected string $oppositeObjectDescription;

	/**
	 * The name of the associated table (the table that the OTHER
	 * column in the association table points to)
	 * @var string
	 */
	protected string $associatedTable;

	/**
	 * Name of the reverse-referenced object as an function parameter.
	 * So if this is a reverse reference to "person" via "report.person_id",
	 * the variableName would be "objReport"
	 * @var string
	 */
	protected string $variableName;

	/**
	 * Type of the reverse-referenced object as a class.
	 * So if this is a reverse reference to "person" via "report.person_id",
	 * the variableName would be "Report"
	 * @var string
	 */
	protected string $variableType;

	/**
	 * Singular object description used in the function names for the
	 * reverse reference.  See documentation for more details.
	 * @var string
	 */
	protected string $objectDescription;

	/**
	 * Plural object description used in the function names for the
	 * reverse reference.  See documentation for more details.
	 * @var string
	 */
	protected string $objectDescriptionPlural;

	/**
	 * Array of non-FK Column objects (as indexed by Column name)
	 * @var Column[]
	 */
	protected array $columnArray;


	/**
	 * Override method to perform a property "Get" This will get the value of $name
	 * @param string $name Name of the property to get
	 * @return mixed
	 * @throws CogException
	 */
	public function __get($name): mixed {
		switch ($name) {
			case 'keyName':
				return $this->keyName;
			case 'table':
				return $this->table;
			case 'column':
				return $this->column;
			case 'oppositeColumn':
				return $this->oppositeColumn;
			case 'oppositeVariableType':
				return $this->oppositeVariableType;
			case 'oppositeVariableTyped':
				return Type::getDeclarationType($this->oppositeVariableType);
			case 'oppositeVariableName':
				return $this->oppositeVariableName;
			case 'oppositePropertyName':
				return $this->oppositePropertyName;
			case 'oppositeObjectDescription':
				return $this->oppositeObjectDescription;
			case 'associatedTable':
				return $this->associatedTable;
			case 'variableName':
				return $this->variableName;
			case 'variableType':
				return $this->variableType;
			case 'variableTyped':
				return Type::getDeclarationType($this->variableType);
			case 'objectDescription':
				return $this->objectDescription;
			case 'objectDescriptionPlural':
				return $this->objectDescriptionPlural;
			case 'columnArray':
				return $this->columnArray;

			case 'propertyNameUppercase':
				return (new ByteString($this->propertyName))->title();
			case 'variableNameUppercase':
				return (new ByteString($this->variableName))->title();
			case 'objectDescriptionUppercase':
				return (new ByteString($this->objectDescription))->title();
			case 'objectDescriptionPluralUppercase':
				return (new ByteString($this->objectDescriptionPlural))->title();

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
	 * Override method to perform a property "Set" This will set the property $name to be $value
	 * @param string $name Name of the property to set
	 * @param string $value New value of the property
	 * @return mixed
	 * @throws CogException
	 */
	public function __set($name, $value) {
		try {
			switch ($name) {
				case 'keyName':
					return $this->keyName = Type::cast($value, Type::STRING);
				case 'table':
					return $this->table = Type::cast($value, Type::STRING);
				case 'column':
					return $this->column = Type::cast($value, Type::STRING);
				case 'oppositeColumn':
					return $this->oppositeColumn = Type::cast($value, Type::STRING);
				case 'oppositeVariableType':
					return $this->oppositeVariableType = Type::cast($value, Type::STRING);
				case 'oppositeVariableName':
					return $this->oppositeVariableName = Type::cast($value, Type::STRING);
				case 'oppositePropertyName':
					return $this->oppositePropertyName = Type::cast($value, Type::STRING);
				case 'oppositeObjectDescription':
					return $this->oppositeObjectDescription = Type::cast($value, Type::STRING);
				case 'associatedTable':
					return $this->associatedTable = Type::cast($value, Type::STRING);
				case 'variableName':
					return $this->variableName = Type::cast($value, Type::STRING);
				case 'variableType':
					return $this->variableType = Type::cast($value, Type::STRING);
				case 'objectDescription':
					return $this->objectDescription = Type::cast($value, Type::STRING);
				case 'objectDescriptionPlural':
					return $this->objectDescriptionPlural = Type::cast($value, Type::STRING);
				case 'columnArray':
					return $this->columnArray = Type::cast($value, Type::ARRAY);
				default:
					return parent::__set($name, $value);
			}
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
	}
}
