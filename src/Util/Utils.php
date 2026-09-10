<?php

namespace Cog\Util;

use Countable;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonException;
use Stringable;

/**
 * Other helpful functions
 */
abstract class Utils {

	/**
	 * This function merges two arrays or adds the value to the and of the given array.
	 * Return false if first param is not an array
	 * @param array $toBeExtended
	 * @param array | mixed $object
	 * @return array
	 */
	public static function extendArray(array $toBeExtended, mixed $object): array {
		if ($object === null) {
			return $toBeExtended;
		}

		if (is_array($object)) {
			$toBeExtended = array_merge($toBeExtended, $object);
		} else {
			$toBeExtended[] = $object;
		}

		return $toBeExtended;
	}

	/**
	 * Converts a human-readable period to a number of seconds.
	 * For example "1 year", "2 months 1 second", "1 hour 1 second" etc.
	 * @param string $period
	 * @return integer number in seconds
	 */
	public static function getTimePeriodInSeconds(string $period = ''): int {
		try {
			return abs((new DateTime($period))->getTimestamp() - (new DateTime)->getTimestamp());
		} catch (\Exception $e) {
			return 0;
		}
	}

	/**
	 * Formats a datetime the way generated getIterator() emits it for json_encode:
	 * ISO-8601 normalised to UTC with microseconds and a literal Z, for example
	 * 2020-07-02T01:04:05.000000Z. Null stays null.
	 * @param ?DateTimeInterface $dateTime
	 * @return ?string
	 */
	public static function dateTimeToJson(?DateTimeInterface $dateTime): ?string {
		if ($dateTime === null) {
			return null;
		}

		return DateTimeImmutable::createFromInterface($dateTime)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
	}

	/**
	 * Decodes the text of a JSON column for a generated class. A JSON object stays a stdClass
	 * rather than becoming an array, so {} and [] survive being encoded again, and an integer
	 * too large for PHP becomes a string instead of losing digits. SQL NULL stays null.
	 * @param ?string $json
	 * @return mixed
	 * @throws JsonException when the text is not valid JSON
	 */
	public static function decodeJsonColumn(?string $json): mixed {
		if ($json === null) {
			return null;
		}

		return json_decode($json, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
	}

	/**
	 * Encodes a value as the text of a JSON column. null is SQL NULL, not the JSON literal null,
	 * and a float keeps its fraction, so 1.0 is not stored as the integer 1.
	 * @param mixed $value
	 * @return ?string
	 * @throws JsonException when the value cannot be encoded
	 */
	public static function encodeJsonColumn(mixed $value): ?string {
		if ($value === null) {
			return null;
		}

		return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
	}

	public static function isHost(string $needle): bool {
		if (!array_key_exists('HTTP_HOST', $_SERVER)) {
			return false;
		}
		$host = $_SERVER['HTTP_HOST'] ?? '';
		if (!is_string($host) || $host === '') {
			return false;
		}

		$host = strtolower(preg_replace('/:\d+$/', '', $host)); // strip port, normalise case
		return $host === $needle || str_ends_with($host, '.' . $needle);
	}

	public static function hasValue($value): bool {
		if ($value === null) {
			return false;
		}

		if (is_string($value)) {
			return trim($value) !== '';
		}

		// Checked before Stringable, so an object that is both is judged by what it holds
		if (is_array($value) || $value instanceof Countable) {
			return count($value) > 0;
		}

		if ($value instanceof Stringable) {
			return trim((string)$value) !== '';
		}

		return true; // 0, 0.0, false are values
	}
}
