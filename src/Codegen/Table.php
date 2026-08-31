<?php

namespace Cog\Codegen;

use Cog\Exceptions\CogException;
use Cog\Type;

/**
 * Used by the Code Generator to describe a database Table
 * @package Codegen
 *
 * @property int $ownerDbIndex

 */
class Table extends TableBase {

	/** @var int DB Index to which it belongs in the configuration.inc.php and codegen_settings.xml files. */
	protected int $ownerDbIndex;

	/**
	 * Override method to perform a property "Get" This will get the value of $name
	 * @param string $name Name of the property to get
	 * @return mixed
	 * @throws CogException
	 *
	 */
	public function __get($name) {
		switch ($name) {
			case 'ownerDbIndex':
				return $this->ownerDbIndex;
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
				case 'ownerDbIndex':
					return $this->ownerDbIndex = Type::cast($value, Type::INTEGER);
				default:
					return parent::__set($name, $value);
			}
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
	}
}
