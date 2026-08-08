<?php

namespace Cog\Util;

use DateTime;

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
