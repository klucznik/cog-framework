<?php

namespace Cog\Codegen;

use Cog;
use Cog\Exceptions\CogException;
use Cog\Type;
use Symfony\Component\String\ByteString;

/**
 * Used by the Code Generator to describe a column reference (aka a Foreign Key)
 * @package Codegen
 *
 * 	@property string $keyName
 * 	@property string $table
 * 	@property string $column
 * 	@property string $propertyName
 * 	@property string $variableName
 * 	@property string $variableType
 *	@property bool $isType
 *
 *  @property-read string $propertyNameUppercase
 *  @property-read string $variableNameUppercase
 */
class Reference extends Cog\Base {

	/** @var string Name of the foreign key object, as defined in the database or create script */
	private string $keyName;

	/** @var string Name of the table that is being referenced */
	private string $table;

	/** @var string Name of the column that is being referenced */
	private string $column;

	/**
	 * Name of the referenced object as a class Property So if the column that this reference points from is named
	 * "primary_annual_report_id", it would be primaryAnnualReport
	 * @var string Name of the referenced object as a class property
	 */
	private string $propertyName;

	/**
	 * Name of the referenced object as a class protected Member object
	 * So if the column that this reference points from is named
	 * "primary_annual_report_id", it would be objPrimaryAnnualReport
	 * @var string Name of the referenced object as a class protected Member object
	 */
	private string $variableName;

	/**
	 * The type of the protected member object (should be based off of $this->strTable)
	 * So if referencing the table "annual_report", it would be AnnualReport
	 * @var string The type of the protected member object
	 */
	private string $variableType;

	/** @var bool If the table that this reference points to is a type table, then this is true */
	private bool $isType;


	/**
	 * Override method to perform a property "Get" This will get the value of $name
	 * @param string $name Name of the property to get
	 * @return mixed
	 * @throws CogException
	 */
	public function __get($name) {
		switch ($name) {
			case 'keyName':
				return $this->keyName;
			case 'table':
				return $this->table;
			case 'column':
				return $this->column;
			case 'propertyName':
				return $this->propertyName;
			case 'variableName':
				return $this->variableName;
			case 'variableType':
				return $this->variableType;
			case 'variableTyped':
				return Type::getDeclarationType($this->variableType);
			case 'isType':
				return $this->isType;

			case 'propertyNameUppercase':
				return (new ByteString($this->propertyName))->title();
			case 'variableNameUppercase':
				return (new ByteString($this->variableName))->title();

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
				case 'propertyName':
					return $this->propertyName = Type::cast($value, Type::STRING);
				case 'variableName':
					return $this->variableName = Type::cast($value, Type::STRING);
				case 'variableType':
					return $this->variableType = Type::cast($value, Type::STRING);
				case 'isType':
					return $this->isType = Type::cast($value, Type::BOOLEAN);
				default:
					return parent::__set($name, $value);
			}
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
	}
}
