<?php

namespace Cog\Util;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

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
}
