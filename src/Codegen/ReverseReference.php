<?php

namespace Cog\Codegen;

use Cog;
use Cog\Exceptions\CogException;
use Cog\Type;
use Symfony\Component\String\ByteString;

/**
 * Used by the Code Generator to describe a column reference from
 * the table's perspective (aka a Foreign Key from the referenced Table's point of view)
 * @package Codegen
 *
 *  @property string $keyName
 * 	@property string $table
 * 	@property string $column
 * 	@property bool $notNull
 * 	@property bool $unique
 *  @property string $variableName
 *  @property string $variableType
 * 	@property string $propertyName
 * 	@property string $objectDescription
 * 	@property string $objectDescriptionPlural
 * 	@property string $objectMemberVariable
 * 	@property string $objectPropertyName
 *
 *	@property-read string $variableNameUppercase
 *  @property-read string $propertyNameUppercase
 *  @property-read string objectDescriptionUppercase
 *  @property-read string objectDescriptionPluralUppercase
 */
class ReverseReference extends Cog\Base {

	/** @var string Name of the foreign key object itself, as defined in the database or create script */
	protected string $keyName;

	/** @var string Name of the referencing table (the table that owns the column that is the foreign key) */
	protected string $table;

	/** @var string Name of the referencing column (the column that owns the foreign key) */
	protected string $column;

	/** @var bool Specifies whether the referencing column is specified as "NOT NULL" */
	protected bool $notNull;

	/** @var bool Specifies whether the referencing column is unique */
	protected bool $unique;

	/**
	 * Name of the reverse-referenced object as an function parameter. So if this is a reverse reference
	 * to "person" via "report.person_id", the variableName would be "objReport"
	 * @var string
	 */
	protected string $variableName;

	/**
	 * Type of the reverse-referenced object as a class. So if this is a reverse reference
	 * to "person" via "report.person_id", the variableName would be "report"
	 * @var string
	 */
	protected string $variableType;

	/**
	 * Property Name of the referencing column (the column that owns the foreign key)
	 * in the associated Class.  So if this is a reverse reference to the "person" table
	 * via the table/column "report.owner_person_id", the propertyName would be "OwnerPersonId"
	 * @var string
	 */
	protected string $propertyName;

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
	 * A member variable name to be used by classes that contain the local member variable
	 * for this unique reverse reference.  Only aggregated when blnUnique is true.
	 * @var string
	 */
	protected string $objectMemberVariable;

	/**
	 * A property name to be used by classes that contain the property
	 * for this unique reverse reference.  Only aggregated when blnUnique is true.
	 * @var string
	 */
	protected string $objectPropertyName;


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
			case 'notNull':
				return $this->notNull;
			case 'unique':
				return $this->unique;
			case 'variableName':
				return $this->variableName;
			case 'variableType':
				return $this->variableType;
			case 'variableTyped':
				return Type::getDeclarationType($this->variableType);
			case 'propertyName':
				return $this->propertyName;
			case 'objectDescription':
				return $this->objectDescription;
			case 'objectDescriptionPlural':
				return $this->objectDescriptionPlural;
			case 'objectMemberVariable':
				return $this->objectMemberVariable;
			case 'objectPropertyName':
				return $this->objectPropertyName;

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
				case 'notNull':
					return $this->notNull = Type::cast($value, Type::BOOLEAN);
				case 'unique':
					return $this->unique = Type::cast($value, Type::BOOLEAN);
				case 'variableName':
					return $this->variableName = Type::cast($value, Type::STRING);
				case 'variableType':
					return $this->variableType = Type::cast($value, Type::STRING);
				case 'propertyName':
					return $this->propertyName = Type::cast($value, Type::STRING);
				case 'objectDescription':
					return $this->objectDescription = Type::cast($value, Type::STRING);
				case 'objectDescriptionPlural':
					return $this->objectDescriptionPlural = Type::cast($value, Type::STRING);
				case 'objectMemberVariable':
					return $this->objectMemberVariable = Type::cast($value, Type::STRING);
				case 'objectPropertyName':
					return $this->objectPropertyName = Type::cast($value, Type::STRING);
				default:
					return parent::__set($name, $value);
			}
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
	}
}
