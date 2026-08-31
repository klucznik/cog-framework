<?php

namespace Cog;

use Cog\Exceptions\CogException;
use Cog\Exceptions\UndefinedPropertyException;
use ReflectionClass;
use ReflectionException;

/**
 * This is the DatabaseExceptionBase Class for ALL classes in the system.  It provides
 * proper error handling of property getters and setters.  It also
 * provides the OverrideAttribute functionality.
 */
abstract class Base {
	/**
	 * Override method to perform a property "Get" This will get the value of $name
	 * All inherited objects that call __get() should always fall through
	 * to calling parent::__get() in a try/catch statement catching for CallerExceptions.
	 *
	 * @param string $name Name of the property to get
	 * @return mixed the returned property
	 * @throws UndefinedPropertyException
	 */
	public function __get($name) {
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
	 * to calling parent::__set() in a try/catch statement catching for CallerExceptions.
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

	public function __isset($name) {
		return false;
	}

	/**
	 * This allows you to set any properties, given by a name-value pair list in $overrideArray.
	 *
	 * Each item in mixOverrideArray needs to be either a string in the format
	 * of Property=Value or an array in the format of [Property => Value].
	 * OverrideAttributes() will basically call
	 * $this->Property = Value for each string element in the array.
	 *
	 * Value can be surrounded by quotes... but this is optional.
	 *
	 * @param array|null $overrideArray the array of name-value pair items of properties/attributes to override
	 * @return void
	 * @throws CogException
	 */
	final public function overrideAttributes(?array $overrideArray = null): void {
		if (is_array($overrideArray)) {
			// Iterate through the OverrideAttribute Array
			foreach ($overrideArray as $key => $overrideItem) {
				if (is_int($key)) {
					$this->applyStringOverride($overrideItem);
				} else {
					$this->applyOverrideAttributes($key, $overrideItem);
				}
			}
		}
	}

	/**
	 * @throws CogException
	 */
	private function applyStringOverride($overrideString): void {

		// Extract the Key and Value for this OverrideAttributes
		$overrideString = trim($overrideString);
		$position = strpos($overrideString, '=');
		if ($position === false) {
			throw new CogException(sprintf('Improperly formatted OverrideAttribute: %s', $overrideString));
		}

		$key = substr($overrideString, 0, $position);
		$value = substr($overrideString, $position + 1);

		// Ensure that the Value is properly formatted (unquoted, single-quoted, or double-quoted)
		if (str_starts_with($value, "'")) {
			if (str_ends_with($value, "'") === false) {
				throw new CogException(sprintf('Improperly formatted OverrideAttribute: %s', $overrideString));
			}
			$value = substr($value, 1, -1);
		} elseif (str_starts_with($value,'"')) {
			if (str_ends_with($value, '"') === false) {
				throw new CogException(sprintf('Improperly formatted OverrideAttribute: %s', $overrideString));
			}
			$value = substr($value, 1, -1);
		}

		$this->applyOverrideAttributes($key, $value);
	}

	/**
	 * Apply the override
	 * @param string $key
	 * @param mixed $value
	 * @throws CogException
	 */
	private function applyOverrideAttributes(string $key, mixed $value): void {
		try {
			$this->$key = $value;
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
	}
}
