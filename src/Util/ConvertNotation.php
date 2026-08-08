<?php declare(strict_types=1);

namespace Cog\Util;

use Cog\Type;
use Symfony\Component\String\ByteString;

/**
 * ConvertNotation
 */
abstract class ConvertNotation {

	public static function prefixFromType(string $type): string {
		switch ($type) {
			case Type::OBJECT:
			case Type::ARRAY:
				return 'obj';

			case Type::BOOLEAN:
				return 'bln';

			case Type::DATETIME:
				return 'dtt';

			case Type::FLOAT:
				return 'flt';

			case Type::INTEGER:
				return 'int';

			case Type::STRING:
				return 'str';
		}

		return '';
	}

	public static function phpTypeFromType(string $type): string {
		switch ($type) {
			case Type::OBJECT:
				return 'object';

			case Type::ARRAY:
				return 'array';

			case Type::BOOLEAN:
				return 'bool';

			case Type::STRING:
			case Type::DATETIME:
				return 'string';

			case Type::FLOAT:
				return 'float';

			case Type::INTEGER:
				return 'int';
		}

		return '';
	}

	public static function pascalCase(string $name): string {
		return (new ByteString($name))->camel()->title()->toString();
	}

	public static function camelCase(string $name): string {
		return (new ByteString($name))->camel()->toString();
	}

	public static function snakeCase(string $name): string {
		return (new ByteString($name))->snake()->toString();
	}

	public static function wordsFromSnakeCase(string $name): string {
		$toReturn = trim(str_replace('_', ' ', $name));
		if (mb_strtolower($toReturn) === $toReturn) {
			return ucwords($toReturn);
		}
		return $toReturn;
	}

	public static function wordsFromCamelCase(string $name): string {
		if (strlen($name) === 0) {
			return '';
		}

		$toReturn = mb_strtoupper(StringUtils::firstCharacter($name));

		$length = strlen($name);
		for ($i = 1; $i < $length; $i++) {
			// Get the current character we're examining
			$char = mb_substr($name, $i, 1);

			// Get the character previous to this
			$prevChar = mb_substr($name, $i - 1, 1);

			// If an upper case letter
			if ((ord($char) >= ord('A')) && (ord($char) <= ord('Z'))) {
				$toReturn .= ' ' . $char; // Add a Space
			} elseif ( // If a digit, and the previous character is NOT a digit
				(ord($char) >= ord('0')) && (ord($char) <= ord('9')) &&
				((ord($prevChar) < ord('0')) || (ord($prevChar) > ord('9')))
			) {
				$toReturn .= ' ' . $char; // Add a space
			} elseif ( // If a letter, and the previous character is a digit
				ord($prevChar) >= ord('0') && ord($prevChar) <= ord('9') &&
				ord(mb_strtolower($char)) >= ord('a') && ord(mb_strtolower($char)) <= ord('z')
			) {
				$toReturn .= ' ' . $char; // Add a space
			} else {
				$toReturn .= $char; // otherwise don't add a space
			}
		}

		return StringUtils::firstCharacter($toReturn) . mb_strtolower(mb_substr($toReturn, 1));
	}

	public static function translationNameFromString(string $str): string {
		return self::camelCase(substr($str, 3));
	}
}
