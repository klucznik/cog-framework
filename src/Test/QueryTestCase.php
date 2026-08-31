<?php

namespace Cog\Test;

use Cog\Database\Base;
use Cog\Database\Database;
use PHPUnit\Framework\TestCase;

/**
 * Base for the tests that exercise the query layer through the generated ORM.
 *
 * The generated classes bind to CodegenFixture::DATABASE_INDEX, and the fixture
 * closes that connection once generation finishes, so each test class has to
 * open it again - otherwise the first call to a generated getDatabase() fails
 * on a missing index.
 *
 * Profiling is on, which is what makes the emitted SQL observable:
 * buildQueryStatement() is protected, but every statement it produces passes
 * through the connection and lands in the profile.
 */
abstract class QueryTestCase extends TestCase {

	protected Base $database;

	public function setUp(): void {
		// No-op once TestCodegen has run, but it is what makes these classes
		// runnable on their own: the generated ORM is not built by the bootstrap.
		CodegenFixture::generate();

		self::closeConnections();

		Database::initializeConnection([
			'adapter' => 'MySqli',
			'server' => getenv('COG_TEST_DB_SERVER') ?: 'localhost',
			'encoding' => 'UTF8',
			'database' => getenv('COG_TEST_DB_NAME') ?: 'cog_framework_test',
			'username' => getenv('COG_TEST_DB_USER') ?: 'root',
			'password' => getenv('COG_TEST_DB_PASSWORD') ?: '',
			'profiling' => true
		], CodegenFixture::DATABASE_INDEX);

		$this->database = Database::$databases[CodegenFixture::DATABASE_INDEX];

		// Connecting issues SET AUTOCOMMIT and SET NAMES, and those are profiled
		// too. Getting them out of the way here keeps queryCount() deltas equal to
		// the number of statements a test actually caused.
		$this->database->connect();
	}

	public function tearDown(): void {
		self::closeConnections();
	}

	private static function closeConnections(): void {
		foreach (Database::$databases as $index => $database) {
			$database->close();
			unset(Database::$databases[$index]);
		}
	}

	/** The statement most recently sent to the database, with runs of whitespace collapsed. */
	protected function lastQuery(): string {
		$profile = $this->database->outputProfilingWithoutLineBreaks();

		$this->assertNotEmpty($profile, 'no query has been profiled yet');

		return end($profile)['query'];
	}

	/** @return int how many statements have been sent on this connection */
	protected function queryCount(): int {
		return count($this->database->outputProfiling() ?? []);
	}

	protected function assertQueryContains(string $fragment, string $message = ''): void {
		$this->assertStringContainsString($fragment, $this->lastQuery(), $message);
	}

	protected function assertQueryNotContains(string $fragment, string $message = ''): void {
		$this->assertStringNotContainsString($fragment, $this->lastQuery(), $message);
	}

	/**
	 * Reduces a result set to a sorted list of one property, so a test can state
	 * what it expects without depending on the order rows happen to come back in.
	 *
	 * @param object[] $items
	 * @return string[]
	 */
	protected static function pluck(array $items, string $property): array {
		$values = array_map(static fn(object $item) => (string)$item->$property, $items);
		sort($values);

		return $values;
	}
}
