<?php

namespace Cog\Codegen;

use Cog;
use Cog\Exceptions\CogException;
use Cog\Type;

/**
 * Used by the Code Generator to describe a table Index
 * @package Codegen
 *
 * @property null|string $keyName
 * @property bool $unique
 * @property bool $primaryKey
 * @property string[] $columnNameArray
 */
class Index extends Cog\Base {

	/** @var string|null Name of the index object, as defined in the database or create script */
	protected ?string $keyName;

	/** @var bool Specifies whether the index is unique */
	protected bool $unique = false;

	/** @var bool Specifies whether the column is the Primary Key index */
	protected bool $primaryKey = false;

	/** @var string[] Array of strings containing the names of the columns that this index indexes (indexed numerically) */
	protected array $columnNameArray = [];

	public function __construct(?string $keyName) {
		$this->keyName = $keyName;
	}

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
			case 'unique':
				return $this->unique;
			case 'primaryKey':
				return $this->primaryKey;
			case 'columnNameArray':
				return $this->columnNameArray;
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
	 * Override method to perform a property "Set" this will set the property $name to be $value
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
				case 'unique':
					return $this->unique = Type::cast($value, Type::BOOLEAN);
				case 'primaryKey':
					return $this->primaryKey = Type::cast($value, Type::BOOLEAN);
				case 'columnNameArray':
					return $this->columnNameArray = Type::cast($value, Type::ARRAY);
				default:
					return parent::__set($name, $value);
			}
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
	}
}
