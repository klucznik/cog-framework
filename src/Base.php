<?php declare(strict_types=1);

namespace Cog;

use Cog\Exceptions\UndefinedPropertyException;
use ReflectionClass;
use ReflectionException;

/**
 * This is the Base Class for ALL classes in the system.  It provides
 * proper error handling of property getters and setters.
 */
abstract class Base {
	/**
	 * Override method to perform a property "Get" This will get the value of $name
	 * All inherited objects that call __get() should always fall through
	 * to calling parent::__get().
	 *
	 * @param string $name Name of the property to get
	 * @return mixed the returned property
	 * @throws UndefinedPropertyException
	 */
	public function __get($name): mixed {
		try {
			$reflection = new ReflectionClass($this);
			throw new UndefinedPropertyException('GET', $reflection->getName(), $name);
		} catch (ReflectionException $exception) {}

		return null; // @codeCoverageIgnore
	}

	/**
	 * Override method to perform a property "set"
	 * This will set the property $name to be $value
	 * All inherited objects that call __set() should always fall through
	 * to calling parent::__set().
	 *
	 * @param string $name Name of the property to set
	 * @param mixed $value New value of the property
	 * @throws UndefinedPropertyException
	 * @return mixed
	*/
	public function __set($name, $value) {
		try {
			$reflection = new ReflectionClass($this);
			throw new UndefinedPropertyException('SET', $reflection->getName(), $name);
		} catch (ReflectionException $exception) {}

		return null; // @codeCoverageIgnore
	}
}
