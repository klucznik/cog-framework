<?php

namespace Cog\Test;


use Cog\Base;
use Cog\Exceptions\CogException;

/**
 * @property string $MagicProperty
 */
final class MockedBaseObject extends Base {

	private $property;
	public $overrideProperty;

	/**
	 * @param string $name Name of the property to get
	 * @return mixed
	 * @throws CogException
	 */
	public function __get($name) {
		switch ($name) {
			case 'MagicProperty':
				return $this->property;

			default:
				try {
					return parent::__get($name);
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}

	public function __isset($name) {
		switch ($name) {
			case 'MagicProperty':
				return true;

			default:
				try {
					return parent::__isset($name);
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}

	/**
	 * @param string $name Name of the property to set
	 * @param string $value New value of the property
	 * @return mixed
	 * @throws CogException
	 */
	public function __set($name, $value) {
		switch ($name) {
			case 'MagicProperty':
				try {
					return $this->property = $value;
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}

			default:
				try {
					return parent::__set($name, $value);
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}
}
