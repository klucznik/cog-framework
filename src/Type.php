<?php

namespace Cog;

use Carbon\Carbon;
use Cog\Exceptions\InvalidCastException;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\String\ByteString;
use Symfony\Component\String\CodePointString;
use Symfony\Component\String\UnicodeString;

/**
 * Type Library to add some support for strongly named types.
 *
 * PHP does not support strongly named types.  The type library
 * and typing in general attempts to bring some structure to types
 * when passing in values, properties, parameters to/from framework objects
 * and methods.
 *
 * The Type library attempts to allow as much flexibility as possible to
 * set and cast variables to other types, similar to how PHP does it natively,
 * but simply adds a big more structure to it.
 *
 * For example, regardless if a variable is an integer, boolean, or string,
 * Type::Cast will allow the flexibility of those values to interchange with
 * each other with little to no issue.
 *
 * In addition to value objects (integers, booleans, floats, strings), the Type library
 * also supports object casting.  While technically casting one object to another
 * is not a true cast, Type::Cast does at least ensure that the tap being "casted"
 * to is a legitimate subclass of the object being "cast".  So if you have ParentClass,
 * and you have a ChildClass that extends ParentClass,
 *        $childClass = new ChildClass();
 *        $parentClass = new ParentClass();
 *        Type::cast($childClass, 'ParentClass'); // is a legal cast
 *        Type::cast($parentClass, 'ChildClass'); // will throw an InvalidCastException
 *
 * For values, specifically int to string conversion, one different between
 * Type::Cast and PHP (in order to add structure) is that if an integer contains
 * alpha characters, PHP would normally allow that through w/o complaint, simply
 * ignoring any numeric characters past the first alpha character. Type::Cast
 * would instead throw an InvalidCastException to let the developer immediately
 * know that something does not look right.
 *
 * In theory, the type library should maintain the same level of flexibility
 * PHP developers are accustomed to, while providing a mechanism to limit
 * careless coding errors and tough to figure out mistakes due to PHP sometimes
 * overly relaxed type conversions.
 */
abstract class Type {

	public const STRING = 'string';
	public const INTEGER = 'integer';
	public const FLOAT = 'double';
	public const BOOLEAN = 'boolean';
	public const OBJECT = 'object';
	public const ARRAY = 'array';
	public const DATETIME = 'Carbon';

	/**
	 * @param mixed $item
	 * @param string $type
	 * @return bool | string | mixed
	 * @throws InvalidCastException
	 */
	private static function castObjectTo(mixed $item, string $type): mixed {
		try {
			$reflection = new ReflectionClass($item);
		} catch (ReflectionException $exception) { // @codeCoverageIgnore
			throw new InvalidCastException('Object is not an instance of any class'); // @codeCoverageIgnore
		}

		try {
			if ($reflection->getName() === 'SimpleXMLElement') {
				switch ($type) {
					case self::STRING:
						return (string)$item;

					case self::INTEGER:
						try {
							return self::cast((string)$item, self::INTEGER);
						} catch (InvalidCastException $exception) {
							$exception->incrementOffset();
							throw $exception;
						}

					case self::BOOLEAN:
						return self::castValueTo(strtolower(trim((string)$item)), self::BOOLEAN);
				}
			}
		} catch (InvalidCastException $exception) {}

		if ($type === self::DATETIME && $item instanceof Carbon) {
			return $item;
		}

		//convert string wrapper classes to string
		if ($type === self::STRING && ($item instanceof ByteString || ($item instanceof UnicodeString) || $item instanceof CodePointString)) {
			return (string)$item;
		}

		if ($item instanceof $type) {
			return $item;
		}

		throw new InvalidCastException(sprintf('Unable to cast %s object to %s', $reflection->getName(), $type));
	}

	/**
	 * @param mixed $item
	 * @param string $type
	 * @return mixed
	 * @throws InvalidCastException
	 */
	private static function castValueTo(mixed $item, string $type): mixed {
		$itemType = gettype($item);

		switch ($type) {
			case self::BOOLEAN:
				if ($itemType === self::BOOLEAN) {
					return $item;
				}

				if ($item === '') {
					return false;
				}

				if (strtolower($item) === 'false') {
					return false;
				}

				if (strtolower($item) === 'true') {
					return true;
				}

				settype($item, $type);
				return $item;

			case self::INTEGER:
			case self::FLOAT:
				if ($item === '') {
					return null;
				}

				$original = $item;
				settype($item, $type);

				// Check to make sure the value hasn't changed significantly
				$mixTest = $item;
				settype($mixTest, gettype($original));

				// Has it?
				if ($mixTest != $original) {
					// Yes -- therefore this is an invalid cast
					throw new InvalidCastException(sprintf('Unable to cast %s value to %s: %s', $itemType, $type, $original));
				}

				return $item;

			case self::STRING:
				settype($item, $type);
				return $item;

			default:
				throw new InvalidCastException(sprintf('Unable to cast %s value to %s', $itemType, $type));
		}
	}

	/**
	 * Used to cast a variable to another type. Allows for moderate support of strongly-named types.
	 *
	 * Will throw an exception if the cast fails, causes unexpected side effects,
	 * if attempting to cast an object to a value (or vice versa), or if an object
	 * is being cast to a class that isn't a subclass (e.g. parent). The exception
	 * thrown will be an InvalidCastException, which extends CallerException.
	 *
	 * @param mixed $item the value, array or object that you want to cast
	 * @param string $type the type to cast to. Can be a Type::XXX constant (e.g. Type::INTEGER), or the name of a Class
	 * @param bool $preserveNull By default, preserve the null value of the $item, if set to false it will cast null to a given type
	 * @return mixed the passed in value/array/object that has been cast to given type
	 * @throws InvalidCastException
	 */
	final public static function cast(mixed $item, string $type, bool $preserveNull = true): mixed {
		// return nulls if needed
		if ($preserveNull && $item === null) {
			return null;
		}

		// Figure out what PHP thinks the type is
		switch (gettype($item)) {
			case self::OBJECT:
				try {
					return self::castObjectTo($item, $type);
				} catch (InvalidCastException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}

			case self::STRING:
			case self::INTEGER:
			case self::FLOAT:
			case self::BOOLEAN:
				try {
					return self::castValueTo($item, $type);
				} catch (InvalidCastException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}

			case self::ARRAY:
				if ($type === self::ARRAY) {
					return $item;
				}
				throw new InvalidCastException(sprintf('Unable to cast Array to %s', $type));

			case 'NULL':
				switch ($type) {
					case self::STRING:
						return '';
					case self::INTEGER:
						return 0;
					case self::FLOAT:
						return 0.0;
					case self::BOOLEAN:
						return false;
					case self::ARRAY:
						return [];

					default:
						return null;
				}

			default:
				throw new InvalidCastException(sprintf('Unable to determine type of item to be cast: %s', $item)); // @codeCoverageIgnore
		}
	}

	/**
	 * Shortcut for casting variable to array trough cast function.
	 * This function will not preserve null value.
	 * @param mixed $item the value, array or object that you want to cast
	 * @return array
	 * @throws InvalidCastException
	 */
	final public static function castArrayStrict(mixed $item): array {
		return self::cast($item, self::ARRAY, false);
	}

	/**
	 * Shortcut for casting variable to array trough cast function.
	 * This function will preserve null value.
	 * @param mixed $item the value, array or object that you want to cast
	 * @return array|null
	 * @throws InvalidCastException
	 */
	final public static function castArray(mixed $item): ?array {
		return self::cast($item, self::ARRAY);
	}

	/**
	 * Shortcut for casting variable to bool trough cast function.
	 * This function will not preserve null value.
	 * @param mixed $item the value, array or object that you want to cast
	 * @return bool
	 * @throws InvalidCastException
	 */
	final public static function castBoolStrict(mixed $item): bool {
		return self::cast($item, self::BOOLEAN, false);
	}

	/**
	 * Shortcut for casting variable to bool trough cast function.
	 * This function will not preserve null value.
	 * @param mixed $item the value, array or object that you want to cast
	 * @return bool|null
	 * @throws InvalidCastException
	 */
	final public static function castBool(mixed $item): ?bool {
		return self::cast($item, self::BOOLEAN);
	}

	/**
	 * Used by the Code Generator to allow for the code generation of
	 * the actual "Type::XXX" constant, instead of the text of the constant,
	 * in generated code.
	 * It is rare for Constant to be used manually outside of Code Generation.
	 *
	 * @param string $type the type to convert to 'constant' form
	 * @return string the text of the Type:XXX Constant
	 * @throws InvalidCastException
	 */
	final public static function constant(string $type): string {
		switch ($type) {
			case self::OBJECT:
				return 'Type::OBJECT';
			case self::STRING:
				return 'Type::STRING';
			case self::INTEGER:
				return 'Type::INTEGER';
			case self::FLOAT:
				return 'Type::FLOAT';
			case self::BOOLEAN:
				return 'Type::BOOLEAN';
			case self::ARRAY:
				return 'Type::ARRAY';
			case self::DATETIME:
				return 'Type::DATETIME';

			default:
				// Could not determine type
				throw new InvalidCastException(sprintf('Unable to determine type of item to lookup its constant: %s', $type));
		}
	}

	/**
	 * Converts type of variable obtained from gettype() to type that can be used in type declaration
	 * @param string $type the type
	 * @return string type declaration
	 */
	final public static function getDeclarationType(string $type): string {
		$toReturn = $type;

		if ($toReturn === self::BOOLEAN) {
			$toReturn = 'bool';
		}

		if ($toReturn === self::INTEGER) {
			$toReturn = 'int';
		}

		if ($toReturn === self::FLOAT) {
			$toReturn = 'float';
		}

		return $toReturn;
	}
}
