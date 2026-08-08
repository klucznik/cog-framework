<?php

namespace Cog\Util;

use Cog\BaseApplication;

/**
 * An abstract utility class to handle string manipulation.  All methods
 * are statically available.
 */
abstract class StringUtils {
	/**
	 * Returns the first character of a given string, or null if the given
	 * string is null.
	 * @param string $string
	 * @return string | null the first character, or null
	 */
	final public static function firstCharacter(string $string): ?string {
		if ($string != '') {
			return mb_substr($string, 0, 1);
		}
		return null;
	}

	/**
	 * Returns the last character of a given string, or null if the given string is null.
	 * @param string $string
	 * @return string | null the last character, or null
	 */
	final public static function lastCharacter(string $string): ?string  {
		if ($string != '') {
			return mb_substr($string, -1);
		}
		return null;
	}

	/**
	 * Escapes the string so that it can be safely used in as an Xml Node (basically, adding CDATA if needed)
	 * @param string $string string to escape
	 * @return string the XML Node-safe StringUtils
	 */
	final public static function xmlEscape(string $string): string {
		if (str_contains($string, '<')|| str_contains($string, '&')) {
			$string = str_replace(']]>', ']]]]><![CDATA[>', $string);
			$string = sprintf('<![CDATA[%s]]>', $string);
		}

		return $string;
	}

	/**
	 * Given an integer that represents a byte size, this will return a string
	 * displaying the value in bytes, KB, MB, GB, TB or PB
	 * @param int|null $bytes
	 * @param int $numberOfTenths
	 * @return string
	 */
	public static function getByteSize(?int $bytes, int $numberOfTenths = 1): string {
		if (null === $bytes) {
			return 'N/A';
		}

		if ($bytes === 0) {
			return '0 bytes';
		}

		$toReturn = '';

		if ($bytes < 0) {
			$bytes *= -1;
			$toReturn = '-';
		}

		if ($bytes === 1) {
			$toReturn .= '1 byte';
		} elseif ($bytes < 1024) {
			$toReturn .= $bytes . ' bytes';
		} elseif ($bytes < (1024 * 1024)) {
			$toReturn .= sprintf('%.' . $numberOfTenths . 'f KB', $bytes / 1024);
		} elseif ($bytes < (1024 * 1024 * 1024)) {
			$toReturn .= sprintf('%.' . $numberOfTenths . 'f MB', $bytes / (1024 * 1024));
		} elseif ($bytes < (1024 * 1024 * 1024 * 1024)) {
			$toReturn .= sprintf('%.' . $numberOfTenths . 'f GB', $bytes / (1024 * 1024 * 1024));
		} elseif ($bytes < (1024 * 1024 * 1024 * 1024 * 1024)) {
			$toReturn .= sprintf('%.' . $numberOfTenths . 'f TB', $bytes / (1024 * 1024 * 1024 * 1024));
		} else {
			$toReturn .= sprintf('%.' . $numberOfTenths . 'f PB', $bytes / (1024 * 1024 * 1024 * 1024 * 1024));
		}

		return $toReturn;
	}

	/**
	 * Checks if text length is between given bounds
	 * @param string $string Text to be checked
	 * @param integer $minimumLength Minimum acceptable length
	 * @param integer $maximumLength Maximum acceptable length
	 * @return boolean
	 */
	public static function isLengthBetween(string $string, int $minimumLength, int $maximumLength): bool {
		$length = mb_strlen($string);
		return ($length >= $minimumLength && $length <= $maximumLength);
	}

	/**
	 * Global/Central HtmlEntities command to perform the PHP equivalent of htmlentities.
	 * Feel free to override to specify encoding/quoting specific preferences (e.g. ENT_QUOTES/ENT_NOQUOTES, etc.)
	 *
	 * @param string|null $text text string to perform html escaping
	 * @return string the html escaped string
	 */
	public static function htmlEntities(?string $text): string {
		if ($text === null) {
			return '';
		}

		return htmlentities($text, ENT_IGNORE, 'UTF-8');
	}

	/**
	 * Quote string with slashes
	 *
	 * @param string|null $text text string to add slashes
	 * @return string the escaped string.
	 */
	public static function addslashes(?string $text): string {
		if ($text === null) {
			return '';
		}

		return addslashes($text);
	}

	/**
	 * Strip whitespace (or other characters) from the beginning and end of a string
	 * @link https://php.net/manual/en/function.trim.php
	 * @param string|null $string $string The string that will be trimmed.
	 * @param string $characters [optional] Optionally, the stripped characters can also be specified using the charlist parameter.
	 *     Simply list all characters that you want to be stripped. With .. you can specify a range of characters.
	 * @return string The trimmed string.
	 */
	public static function trim(?string $string, string $characters = " \t\n\r\0\x0B"): string {
		if ($string === null) {
			return '';
		}

		return trim($string, $characters);
	}

	/**
	 * Wraps every occurrence of the given words in <b> tags. Matching is case
	 * insensitive, and the text that was matched is kept as it was written -
	 * highlighting "here" in "Here is" gives "<b>Here</b> is", not "<b>here</b> is".
	 *
	 * @param string $string input string
	 * @param string | string[] $highlightWords words to highlight in array or a single word in string
	 * @return string
	 */
	public static function highlightWords(string $string, array|string $highlightWords): string {
		if (is_string($highlightWords)) {
			$highlightWords = [$highlightWords];
		}

		foreach ($highlightWords as $word) {
			if ($word === '') { // an empty needle would match at every position
				continue;
			}

			$string = preg_replace('/' . preg_quote($word, '/') . '/iu', '<b>$0</b>', $string) ?? $string;
		}

		return $string;
	}

	public static function pluralize(string $string): string {
		$inflector = BaseApplication::$container->get('inflector');
		$pluralization = $inflector->pluralize($string);
		return array_shift($pluralization);
	}
}
