<?php

namespace Cog\Codegen;

use Cog;
use Cog\Exceptions\CogException;
use Cog\Type;

/**
 * Used by the Code Generator to describe a database Table
 * @package Codegen
 *
 * @property string $name
 * @property string $className
 * @property-read string $classNameCamelCase
 * @property string $classNamePlural
 * @property Column[] $columnArray
 * @property-read Column[] $primaryKeyColumnArray
 * @property Index[] $indexArray
 * @property ReverseReference[] $reverseReferenceArray
 * @property ManyToManyReference[] $manyToManyReferenceArray
 * @property-read int $referenceCount
 */
class TableBase extends Cog\Base {

	/** @var string Name of the table (as defined in the database) */
	protected string $name;

	/** @var string Name as a PHP Class */
	protected string $className;

	/** @var string Pluralized Name as a collection of objects of this PHP Class */
	protected string $classNamePlural;

	/** @var Column[] Array of Column objects (as indexed by Column name) */
	protected array $columnArray = [];

	/** @var Index[] Array of Index objects (indexed numerically) */
	protected array $indexArray = [];

	/**
	 * Default Constructor.  Simply sets up the TableName and ensures that ReverseReferenceArray is a blank array.
	 * @param string $name Name of the Table
	 */
	public function __construct(string $name) {
		$this->name = $name;
	}


	/**
	 * return the Cog\Codegen\Column object related to that column name
	 * @param string $columnName
	 * @return Column|null
	 */
	public function getColumnByName(string $columnName): ?Column {
		if ($this->columnArray) {
			foreach ($this->columnArray as $column) {
				if ($column->name === $columnName) {
					return $column;
				}
			}
		}

		return null;
	}

	/**
	 * Search within the table's columns for the given column
	 * @param string $columnName
	 * @return boolean
	 */
	public function hasColumn(string $columnName): bool {
		return ($this->getColumnByName($columnName) !== null);
	}

	/**
	 * Return the property name for a given column name (false if it does not exists)
	 * @param string $columnName
	 * @return string|null
	 */
	public function lookupColumnPropertyName(string $columnName): ?string {
		$column = $this->getColumnByName($columnName);
		return $column?->propertyName;
	}

	/** @var ReverseReference[] Array of ReverseReverence objects (indexed numerically) */
	protected array $reverseReferenceArray = [];

	/** @var ManyToManyReference[] Array of ManyToManyReference objects (indexed numerically) */
	protected array $manyToManyReferenceArray = [];

	public function hasImmediateArrayExpansions(): bool {
		$count = count($this->manyToManyReferenceArray);
		foreach ($this->reverseReferenceArray as $reverseReference) {
			if (!$reverseReference->unique) {
				$count++;
			}
		}
		return $count > 0;
	}

	public function hasExtendedArrayExpansions(DatabaseCodeGen $codeGen, array $checkedTableArray = []): bool {
		$checkedTableArray[] = $this;
		foreach ($this->columnArray as $column) {
			if (($reference = $column->reference) && !$reference->isType && $table2 = $codeGen->getTable($reference->table)) {
				if ($table2->hasImmediateArrayExpansions()) {
					return true;
				}
				if (
					!in_array($table2, $checkedTableArray, false) &&    // watch out for circular references
					$table2->hasExtendedArrayExpansions($codeGen, $checkedTableArray)
				) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Override method to perform a property "Get" This will get the value of $name
	 * @param string $name Name of the property to get
	 * @return mixed
	 * @throws CogException
	 *
	 */
	public function __get($name) {
		switch ($name) {
			case 'name':
				return $this->name;
			case 'classNamePlural':
				return $this->classNamePlural;
			case 'className':
				return $this->className;
			case 'classNameCamelCase':
				return lcfirst($this->className);
			case 'columnArray':
				return $this->columnArray;
			case 'primaryKeyColumnArray':
				if ($this->columnArray) {
					$toReturn = [];
					foreach ($this->columnArray as $column) {
						if ($column->primaryKey) {
							$toReturn[] = $column;
						}
					}
					return $toReturn;
				}
				return null;
			case 'indexArray':
				return $this->indexArray;
			case 'reverseReferenceArray':
				return $this->reverseReferenceArray;
			case 'manyToManyReferenceArray':
				return $this->manyToManyReferenceArray;
			case 'referenceCount':
				$intCount = count($this->manyToManyReferenceArray);
				foreach ($this->columnArray as $column) {
					if ($column->reference) {
						$intCount++;
					}
				}
				return $intCount;
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
				case 'name':
					return $this->name = Type::cast($value, Type::STRING);
				case 'className':
					return $this->className = Type::cast($value, Type::STRING);
				case 'classNamePlural':
					return $this->classNamePlural = Type::cast($value, Type::STRING);
				case 'columnArray':
					return $this->columnArray = Type::cast($value, Type::ARRAY);
				case 'indexArray':
					return $this->indexArray = Type::cast($value, Type::ARRAY);
				case 'reverseReferenceArray':
					return $this->reverseReferenceArray = Type::cast($value, Type::ARRAY);
				case 'manyToManyReferenceArray':
					return $this->manyToManyReferenceArray = Type::cast($value, Type::ARRAY);
				default:
					return parent::__set($name, $value);
			}
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
	}
}
