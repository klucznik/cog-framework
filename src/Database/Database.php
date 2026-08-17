<?php

namespace Cog\Database;

use Cog;
use Cog\Exceptions\CogException;
use Cog\Util\StringUtils;

abstract class Database {

	/** @var string url pointing to the database queries profile page */
	public static string $urlProfilePage = '/profile.php';

	/**
	 * An array of Cog\Database\Database objects, as initialized by initializeDatabaseConnections()
	 * @var Cog\Database\Base[]
	 */
	public static array $databases = [];

	/**
	 * Checks if any database configured has profiling turned on
	 * @return boolean
	 */
	public static function isAnyProfilingEnabled(): bool {
		foreach (self::$databases as $database) {
			if ($database->profiling === true) {
				return true;
			}
		}

		return false;
	}

	/**
	 * This call will initialize the database connection using given database details
	 * @param array $configArray
	 * @param int|null $index
	 * @return void
	 * @throws CogException
	 */
	public static function initializeConnection(array $configArray, ?int $index = null): void {
		// Expected Keys to be Set
		$expectedKeys = ['adapter', 'server', 'port', 'database', 'username', 'password', 'profiling'];

		// Set All Expected Keys
		foreach ($expectedKeys as $expectedKey) {
			if (!array_key_exists($expectedKey, $configArray)) {
				$configArray[$expectedKey] = null;
			}
		}

		if (!$configArray['adapter']) {
			throw new CogException('No Adapter Defined: ' . var_export($configArray, true));
		}

		if (!$configArray['server']) {
			throw new CogException('No Server Defined: ' . var_export($configArray, true));
		}

		$databaseType = "Cog\\Database\\Adapters\\" . $configArray['adapter'] . 'Adapter';
		if (!class_exists($databaseType)) {
			throw new CogException('Database Type is not valid: ' . $configArray['adapter']);
		}

		if ($index === null) {
			// Derive the next index from the highest one in use rather than the
			// count, so closing a connection can't make the next one overwrite
			// a connection that is still open.
			$index = self::$databases ? max(array_keys(self::$databases)) + 1 : 0;
		}
		self::$databases[$index] = new $databaseType($index, $configArray);
	}

	/**
	 * This function displays helpful development info like queries sent to database and memory usage.
	 * By default, it shows only if database profiling is enabled in any configured database connections.
	 *
	 * If forced to show when profiling is disabled you can monitor memory usage more accurately,
	 * as collecting database profiling information tends to noticeable bigger memory consumption.
	 *
	 * @return void
	 */
	public static function displayProfiling(): void {
		// Output DB Profiling Data
		foreach (self::$databases as $index => $database) {
			if ($database->profiling === true) {
				self::displayProfilingHelper($database->outputProfiling(), $index);
			} else {
				echo 'Profiling off';
			}
		}
	}

	/**
	 * Displays the DatabaseProfiling results, plus a link which will pop up the details of the profiling.
	 * @param array|null $profileArray
	 * @param int $index
	 * @return void
	 */
	protected static function displayProfilingHelper(?array $profileArray = null, int $index = 0): void {
		$totalTime = 0;
		foreach($profileArray as $profile) {
			if (isset($profile['timeInfo']['total time'])) {
				$totalTime += floatval($profile['timeInfo']['total time']);
			}
		}

		printf('
			<form method="post" id="frmDbProfile%s" action="%s" target="_blank" style="margin:0; padding:0; display: inline;">
				<input type="hidden" name="profileData" value="%s"/>
				<input type="hidden" name="databaseIndex" value="%s"/>
				<input type="hidden" name="referrer" value="%s"/>
				<a
					href="#"
					onclick="document.getElementById(\'frmDbProfile%s\').submit(); return false;"
					style="text-decoration: none; color: white;"
					title="%s"
				><i class="icon-before fa-icon icon-database" style="margin-right: 4px"></i>%s</a>
			</form>',
			$index,
			self::$urlProfilePage,
			base64_encode(json_encode($profileArray)),
			$index,
			StringUtils::htmlEntities(array_key_exists('REQUEST_URI', $_SERVER) ? $_SERVER['REQUEST_URI'] : ''),
			$index,
			$totalTime * 1000 . 'ms',
			count($profileArray)
		);
	}

	/**
	 * For development purposes, this static method outputs all the Databases configuration
	 * @return void
	 */
	public static function dumpConfig(): void {
		foreach (self::$databases as $index => $database) {
			dump([
				'index' => $index,
				'adapter' => $database->adapter,
				'server' => $database->server,
				'port' => $database->port,
				'database' => $database->database,
				'username' => $database->username,
				'password' => '********', // Don't display database password
				'profiling' => $database->profiling,
			]);
		}
	}
}
