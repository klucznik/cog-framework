<?php

namespace Cog\Codegen;

use Cog\Exceptions\CogException;
use Cog\Type;

/**
 * Used by the Code Generator to describe a database Type Table
 * "Type" tables must be defined with at least two columns, the first one being an integer-based primary key,
 * and the second one being the name of the type.
 * @package Codegen
 *
 * 	@property string[] $nameArray
 * 	@property array $extraFieldNamesArray
 * 	@property array $extraPropertyArray
 *  @property string[] $tokenArray
 */
class TypeTable extends TableBase {

	/**
	 * Array of Type Names (as entered into the rows of this database table)
	 * This is indexed by integer which represents the ID in the database, starting with 1
	 * @var string[]
	 */
	protected array $nameArray;

	/** @var array Column names for extra properties (beyond the 2 basic columns), if any. */
	protected array $extraFieldNamesArray;

	/**
	 * Array of extra properties. This is a double-array - array of arrays. Example:
	 *      1 => ['col1' => 'valueA', 'col2 => 'valueB'],
	 *      2 => ['col1' => 'valueC', 'col2 => 'valueD'],
	 *      3 => ['col1' => 'valueC', 'col2 => 'valueD']
	 * @var array
	 */
	protected array $extraPropertyArray;

	/**
	 * Array of Type Names converted into Tokens (can be used as PHP Constants)
	 * This is indexed by integer which represents the ID in the database, starting with 1
	 * @var string[]
	 */
	protected array $tokenArray;

	/**
	 * Override method to perform a property "Get" This will get the value of $name
	 * @param string $name Name of the property to get
	 * @return mixed
	 * @throws CogException
	 */
	public function __get($name): mixed {
		switch ($name) {
			case 'nameArray':
				return $this->nameArray;
			case 'tokenArray':
				return $this->tokenArray;
			case 'extraPropertyArray':
				return $this->extraPropertyArray;
			case 'extraFieldNamesArray':
				return $this->extraFieldNamesArray;
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
	 *
	 * @param string $name Name of the property to set
	 * @param string $value New value of the property
	 *
	 * @return mixed
	 * @throws CogException
	 */
	public function __set($name, $value) {
		try {
			switch ($name) {
				case 'nameArray':
					return $this->nameArray = Type::cast($value, Type::ARRAY);
				case 'tokenArray':
					return $this->tokenArray = Type::cast($value, Type::ARRAY);
				case 'extraPropertyArray':
					return $this->extraPropertyArray = Type::cast($value, Type::ARRAY);
				case 'extraFieldNamesArray':
					return $this->extraFieldNamesArray = Type::cast($value, Type::ARRAY);
				default:
					return parent::__set($name, $value);
			}
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
	}
}
