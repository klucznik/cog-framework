<?php declare(strict_types=1);

namespace Cog\Test;

use Cog\Database\Database;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the development-facing helpers on Cog\Database\Database.
 *
 * These are the static helpers the dev toolbar calls: the profiling link that
 * posts collected queries to the profile page, and the configuration dump. The
 * profiling helpers write to the output buffer rather than returning anything,
 * which is why they had no coverage. dumpConfig() returns what it reports, and
 * the one assertion that matters most is that the returned configuration never
 * carries the database password.
 *
 * TestDatabase covers the connection itself and the per-connection profiling
 * accessors; this file covers only what Database prints or reports.
 */
class TestDatabaseProfiling extends TestCase {

	private string $originalProfilePage;

	/** Connection settings for the test database, matching TestDatabase. */
	private static function connectionConfig(array $overrides = []): array {
		return array_merge([
			'adapter' => 'MySqli',
			'server' => getenv('COG_TEST_DB_SERVER') ?: 'localhost',
			'encoding' => 'UTF8',
			'database' => getenv('COG_TEST_DB_NAME') ?: 'cog_framework_test',
			'username' => getenv('COG_TEST_DB_USER') ?: 'root',
			'password' => getenv('COG_TEST_DB_PASSWORD') ?: '',
			'profiling' => true,
		], $overrides);
	}

	public function setUp(): void {
		$this->closeEveryConnection();
		$this->originalProfilePage = Database::$urlProfilePage;

		Database::initializeConnection(self::connectionConfig());
	}

	public function tearDown(): void {
		Database::$urlProfilePage = $this->originalProfilePage;
		$this->closeEveryConnection();
	}

	private function closeEveryConnection(): void {
		foreach (Database::$databases as $index => $database) {
			$database->close();
			unset(Database::$databases[$index]);
		}
	}

	/** Runs a callable with output buffering on and returns what it printed. */
	private function capture(callable $callable): string {
		ob_start();

		try {
			$callable();
		} finally {
			$printed = ob_get_clean();
		}

		return $printed;
	}

	//
	// isAnyProfilingEnabled
	//

	/** The toolbar asks once for every connection, so a single one is enough to show it. */
	public function testIsAnyProfilingEnabledAcrossConnections() {
		Database::initializeConnection(self::connectionConfig(['profiling' => false]));

		$this->assertTrue(Database::isAnyProfilingEnabled());

		Database::$databases[0]->disableProfiling();

		$this->assertFalse(Database::isAnyProfilingEnabled());
	}

	public function testIsAnyProfilingEnabledWithNoConnections() {
		$this->closeEveryConnection();

		$this->assertFalse(Database::isAnyProfilingEnabled());
	}

	//
	// displayProfiling
	//

	public function testDisplayProfilingRendersTheProfileForm() {
		Database::$databases[0]->query('SELECT 1;');

		$printed = $this->capture(static fn () => Database::displayProfiling());

		$this->assertStringContainsString('id="frmDbProfile0"', $printed);
		$this->assertStringContainsString('name="profileData"', $printed);
		$this->assertStringContainsString('name="databaseIndex" value="0"', $printed);
	}

	/** The form posts to whatever page the application registered. */
	public function testDisplayProfilingPostsToTheConfiguredProfilePage() {
		Database::$urlProfilePage = '/custom/profile';
		Database::$databases[0]->query('SELECT 1;');

		$printed = $this->capture(static fn () => Database::displayProfiling());

		$this->assertStringContainsString('action="/custom/profile"', $printed);
	}

	/** The collected queries travel as base64-encoded JSON in a hidden field. */
	public function testDisplayProfilingCarriesTheCollectedQueries() {
		Database::$databases[0]->query('SELECT 1;');

		$printed = $this->capture(static fn () => Database::displayProfiling());

		$this->assertSame(1, preg_match('/name="profileData" value="([^"]*)"/', $printed, $matches));

		$decoded = json_decode(base64_decode($matches[1]), true);

		$this->assertIsArray($decoded);
		$this->assertNotEmpty($decoded);
		$this->assertSame('SELECT 1;', end($decoded)['query']);
	}

	/**
	 * The link's title is the total query time and its text the number of collected
	 * queries. That count is every query on the connection, including the
	 * SET AUTOCOMMIT the adapter issues on connect, so it is read off the profile
	 * rather than assumed from the two queries this test runs.
	 */
	public function testDisplayProfilingReportsQueryCountAndTime() {
		Database::$databases[0]->query('SELECT 1;');
		Database::$databases[0]->query('SELECT 2;');

		$collected = count(Database::$databases[0]->outputProfiling());
		$printed = $this->capture(static fn () => Database::displayProfiling());

		$this->assertMatchesRegularExpression('/title="[0-9.]+ms"/', $printed);
		$this->assertMatchesRegularExpression('/>' . $collected . '<\/a>/', $printed);
		$this->assertGreaterThanOrEqual(2, $collected);
	}

	public function testDisplayProfilingSaysSoWhenDisabled() {
		Database::$databases[0]->disableProfiling();

		$printed = $this->capture(static fn () => Database::displayProfiling());

		$this->assertSame('Profiling off', $printed);
	}

	/**
	 * The referrer is read straight off $_SERVER, which has no REQUEST_URI under
	 * CLI - the helper has to render anyway rather than warning.
	 */
	public function testDisplayProfilingWithoutRequestUri() {
		$original = $_SERVER['REQUEST_URI'] ?? null;
		unset($_SERVER['REQUEST_URI']);

		try {
			$printed = $this->capture(static fn () => Database::displayProfiling());

			$this->assertStringContainsString('name="referrer" value=""', $printed);
		} finally {
			if ($original !== null) {
				$_SERVER['REQUEST_URI'] = $original;
			}
		}
	}

	/** When there is a referrer it is html-escaped, since it lands in an attribute. */
	public function testDisplayProfilingEscapesTheReferrer() {
		$original = $_SERVER['REQUEST_URI'] ?? null;
		$_SERVER['REQUEST_URI'] = '/search?q="><script>';

		try {
			$printed = $this->capture(static fn () => Database::displayProfiling());

			$this->assertStringNotContainsString('<script>', $printed);
			$this->assertStringContainsString('&lt;script&gt;', $printed);
		} finally {
			if ($original === null) {
				unset($_SERVER['REQUEST_URI']);
			} else {
				$_SERVER['REQUEST_URI'] = $original;
			}
		}
	}

	//
	// dumpConfig
	//

	/** One entry per connection, keyed by its index in Database::$databases. */
	public function testDumpConfigReportsEveryConnection() {
		Database::initializeConnection(self::connectionConfig());

		$config = Database::dumpConfig();

		$this->assertSame([0, 1], array_keys($config));
		$this->assertSame(0, $config[0]['index']);
		$this->assertSame(1, $config[1]['index']);
		$this->assertSame(getenv('COG_TEST_DB_NAME') ?: 'cog_framework_test', $config[0]['database']);
	}

	/** The password is the whole reason this helper masks anything. */
	public function testDumpConfigMasksThePassword() {
		$config = Database::dumpConfig();

		$this->assertSame('********', $config[0]['password']);

		$configuredPassword = getenv('COG_TEST_DB_PASSWORD') ?: '';
		if ($configuredPassword !== '') {
			$this->assertNotSame($configuredPassword, $config[0]['password']);
		}
	}

	public function testDumpConfigWithNoConnectionsReturnsNothing() {
		$this->closeEveryConnection();

		$this->assertSame([], Database::dumpConfig());
	}
}
