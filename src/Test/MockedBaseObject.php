<?php

namespace Cog\Test;


use Cog\Base;
use Cog\Exceptions\CogException;

/**
 * @property string $MagicProperty
 */
final class MockedBaseObject extends Base {

	private $property;

	/**
	 * @param string $name Name of the property to get
	 * @return mixed
	 * @throws CogException
	 */
	public function __get($name): mixed {
		switch ($name) {
			case 'MagicProperty':
				return $this->property;

			default:
				return parent::__get($name);
		}
	}

	public function __isset($name) {
		switch ($name) {
			case 'MagicProperty':
				return true;

			default:
				// Base declares no __isset: an unknown magic property is simply not
				// set, which is what lets ?? fall through to __get.
				return false;
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
				return $this->property = $value;

			default:
				return parent::__set($name, $value);
		}
	}
}
