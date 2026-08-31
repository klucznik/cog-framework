<?php

namespace Cog\Test;

use Carbon\Carbon;
use Cog\Codegen\ForeignKey;
use Cog\Codegen\Index;
use Cog\Database\Adapters\MySqliException;
use Cog\Database\Database;
use Cog\Exceptions\CogException;
use Cog\Exceptions\UndefinedPropertyException;
use Cog\Query\QQNamedValue;
use PHPUnit\Framework\TestCase;

class TestDatabase extends TestCase {

	/** @var Database */
	public $database;

	/**
	 * Connection settings for the test database, taken from the environment so
	 * no credentials live in the repository. Defaults match phpunit.xml.dist.
	 *
	 * @param array $overrides values replacing the defaults, for tests that
	 *                         need a deliberately broken configuration
	 * @return array
	 */
	private static function connectionConfig(array $overrides = []): array {
		return array_merge([
			'adapter' => 'MySqli',
			'server' => getenv('COG_TEST_DB_SERVER') ?: 'localhost',
			'encoding' => 'UTF8',
			'database' => getenv('COG_TEST_DB_NAME') ?: 'cog_framework_test',
			'username' => getenv('COG_TEST_DB_USER') ?: 'root',
			'password' => getenv('COG_TEST_DB_PASSWORD') ?: '',
			'profiling' => true
		], $overrides);
	}

	public function setUp():void {
		foreach (Database::$databases as $index => $database) {
			Database::$databases[$index]->close();
			unset(Database::$databases[$index]);
		}

		Database::initializeConnection(self::connectionConfig());

		$this->database = Database::$databases[0];
	}

	public function tearDown():void {
		foreach (Database::$databases as $index => $database) {
			Database::$databases[$index]->close();
			unset(Database::$databases[$index]);
		}
	}

	public function testProfilingEnable() {
		$this->database->close();

		$this->assertTrue(Database::isAnyProfilingEnabled());
		$this->database->disableProfiling();
		$this->assertFalse(Database::isAnyProfilingEnabled());
		$this->database->enableProfiling();

		$this->database->connect();

		$this->assertTrue(Database::isAnyProfilingEnabled());
		$this->database->disableProfiling();
		$this->assertFalse(Database::isAnyProfilingEnabled());
		$this->database->enableProfiling();
	}

	public function testGetTables() {
		$tables = $this->database->getTables();
		$this->assertIsArray($tables);
		$this->assertCount(7, $tables);

		foreach (range(0, 6) as $index) {
			$this->assertArrayHasKey($index, $tables);
		}

		$this->assertContainsEquals('asset', $tables);
		$this->assertContainsEquals('blog_post', $tables);
		$this->assertContainsEquals('blog_type', $tables);
		$this->assertContainsEquals('obj', $tables);
		$this->assertContainsEquals('person', $tables);
		$this->assertContainsEquals('tag', $tables);
		$this->assertContainsEquals('tag_obj_assn', $tables);
	}

	public function testQueryFetchAssoc() {
		$result = $this->database->query('SELECT * FROM `person`;');
		$resultArrayAssoc = $result->fetchArrayAssoc();

		$this->assertIsArray($resultArrayAssoc);
		$this->assertCount(5, $resultArrayAssoc);
		$this->assertArrayHasKey('id', $resultArrayAssoc);
		$this->assertArrayHasKey('name', $resultArrayAssoc);
		$this->assertArrayHasKey('email', $resultArrayAssoc);
		$this->assertArrayHasKey('email_verified', $resultArrayAssoc);
		$this->assertArrayHasKey('password', $resultArrayAssoc);

		$this->assertEquals('1', $resultArrayAssoc['id']);
		$this->assertEquals('Adam Kluczyk', $resultArrayAssoc['name']);
		$this->assertEquals('klucznik@test.net', $resultArrayAssoc['email']);
		$this->assertEquals('0', $resultArrayAssoc['email_verified']);
		$this->assertEquals('f0af0f1e34c0c5f', $resultArrayAssoc['password']);
	}

	public function testQueryFetch() {
		$result = $this->database->query('SELECT * FROM `person`;');
		$resultArray = $result->fetchArray();

		$this->assertIsArray($resultArray);
		$this->assertCount(5 * 2, $resultArray);
		$this->assertArrayHasKey(0, $resultArray);
		$this->assertArrayHasKey(1, $resultArray);
		$this->assertArrayHasKey(2, $resultArray);
		$this->assertArrayHasKey(3, $resultArray);
		$this->assertArrayHasKey(4, $resultArray);

		$this->assertEquals('1', $resultArray['id']);
		$this->assertEquals('Adam Kluczyk', $resultArray['name']);
		$this->assertEquals('klucznik@test.net', $resultArray['email']);
		$this->assertEquals('0', $resultArray['email_verified']);
		$this->assertEquals('f0af0f1e34c0c5f', $resultArray['password']);
	}

	public function testGetRows() {
		$result = $this->database->query('SELECT * FROM `asset`;');

		$this->assertEquals(2, $result->countRows());

		$array = $result->getRows();

		$result->close();

		$this->assertIsArray($array);
		$this->assertEquals(2, count($array));
	}

	public function testMissingServer() {
		$this->expectException(CogException::class);
		Database::initializeConnection(self::connectionConfig(['server' => '']));
	}

	public function testMissingAdapter() {
		$this->expectException(CogException::class);
		Database::initializeConnection(self::connectionConfig(['adapter' => '']));
	}

	public function testWrongAdapter() {
		$this->expectException(CogException::class);
		Database::initializeConnection(self::connectionConfig(['adapter' => 'Mysql']));
	}

	public function testExplainSql() {
		$result = $this->database->explainStatement('SELECT * FROM `person`;');
		$array = $result->fetchArrayAssoc();
		$this->assertIsArray($array);
	}

	public function testTableIndexes() {
		$array = $this->database->getIndexesForTable('person');

		$this->assertIsArray($array);
		$this->assertCount(4, $array);
		$this->assertArrayHasKey(1, $array);
		$this->assertInstanceOf(Index::class, $array[1]);
		$this->assertEquals('email', $array[1]->keyName);
	}

	public function testFieldsForTable() {
		$result = $this->database->getFieldsForTable('person');

		//dump($result);
		$this->assertIsArray($result);
		$this->assertCount(5, $result);
		$this->assertArrayHasKey(0, $result);
		$this->assertArrayHasKey(1, $result);
		$this->assertArrayHasKey(2, $result);
		$this->assertArrayHasKey(3, $result);
		$this->assertArrayHasKey(4, $result);
	}

	public function testForeignFieldsForTable() {
		$array = $this->database->getForeignKeysForTable('person');
		$this->assertIsArray($array);
		$this->assertEquals(0, count($array));

		$array = $this->database->getForeignKeysForTable('obj');
		$this->assertIsArray($array);
		$this->assertCount(1, $array);
		$this->assertArrayHasKey(0, $array);
		$this->assertInstanceOf(ForeignKey::class, $array[0]);
		$this->assertEquals('person', $array[0]->referenceTableName);
	}

	public function testTransactions() {
		$this->database->transactionBegin();

		$this->database->nonQuery(sprintf(
	'INSERT INTO `person` (
				`name`,
				`email`,
				`email_verified`,
				`password`
			)
			VALUES (%s, %s, %s, %s);',
			$this->database->sqlVariable('Transaction person'),
			$this->database->sqlVariable('sample@sample.com'),
			$this->database->sqlVariable(false),
			$this->database->sqlVariable('711ae8b01f7728295d9feaa447fa4e37')
		));

		$this->assertGreaterThan(0, $this->database->insertId());

		$this->database->transactionRollback();

		$result = $this->database->query("SELECT * FROM `person` WHERE `email` = 'sample@sample.com';");

		$this->assertEquals(0, $result->countRows());
	}

	public function testSqlVariable() {
		$this->assertEquals("'Transaction person'", $this->database->sqlVariable('Transaction person'));
		$this->database->sqlVariable('sample@sample.com');
		$this->database->sqlVariable(false);
		$this->database->sqlVariable('711ae8b01f7728295d9feaa447fa4e37');
	}

	public function testSqlVariableScalars() {
		$this->assertEquals('NULL', $this->database->sqlVariable(null));
		$this->assertEquals('1', $this->database->sqlVariable(true));
		$this->assertEquals('0', $this->database->sqlVariable(false));
		$this->assertEquals('5', $this->database->sqlVariable(5));
		$this->assertEquals('-5', $this->database->sqlVariable(-5));
		$this->assertEquals('1.5', $this->database->sqlVariable(1.5));
		$this->assertEquals("''", $this->database->sqlVariable(''));
	}

	/** NULL needs IS/IS NOT rather than =/!=, and a boolean compares against 0. */
	public function testSqlVariableEquality() {
		$this->assertEquals('IS NULL', $this->database->sqlVariable(null, true));
		$this->assertEquals('IS NOT NULL', $this->database->sqlVariable(null, true, true));

		$this->assertEquals('= 5', $this->database->sqlVariable(5, true));
		$this->assertEquals('!= 5', $this->database->sqlVariable(5, true, true));

		$this->assertEquals("= 'name'", $this->database->sqlVariable('name', true));
		$this->assertEquals("!= 'name'", $this->database->sqlVariable('name', true, true));

		// TRUE means "not zero", so the operators look inverted
		$this->assertEquals('!= 0', $this->database->sqlVariable(true, true));
		$this->assertEquals('= 0', $this->database->sqlVariable(true, true, true));
		$this->assertEquals('= 0', $this->database->sqlVariable(false, true));
		$this->assertEquals('!= 0', $this->database->sqlVariable(false, true, true));
	}

	public function testSqlVariableEscapesQuotes() {
		$this->assertEquals("'O\\'Brien'", $this->database->sqlVariable("O'Brien"));
		$this->assertEquals("'back\\\\slash'", $this->database->sqlVariable('back\\slash'));
		$this->assertEquals("'\\\"quoted\\\"'", $this->database->sqlVariable('"quoted"'));

		// A classic injection payload ends up inert inside the quoted literal
		$this->assertEquals(
			"'\\' OR 1=1 --'",
			$this->database->sqlVariable("' OR 1=1 --")
		);
	}

	public function testSqlVariableWithCarbon() {
		$this->assertEquals(
			"'2020-01-02 03:04:05'",
			$this->database->sqlVariable(Carbon::parse('2020-01-02 03:04:05'))
		);
	}

	public function testPrepareStatement() {
		$delimiter = chr(QQNamedValue::DELIMITER_CODE);

		$this->assertEquals(
			"SELECT * FROM `person` WHERE `id` = 1 AND `name` = 'Adam Kluczyk'",
			$this->database->prepareStatement(
				sprintf('SELECT * FROM `person` WHERE `id` = %1$s{id} AND `name` = %1$s{name}', $delimiter),
				['id' => 1, 'name' => 'Adam Kluczyk']
			)
		);

		// Nothing to substitute leaves the query untouched
		$this->assertEquals('SELECT 1', $this->database->prepareStatement('SELECT 1', []));
	}

	public function testPrepareStatementWithEquality() {
		$delimiter = chr(QQNamedValue::DELIMITER_CODE);

		$this->assertEquals(
			"SELECT * FROM `person` WHERE `email` IS NULL AND `name` != 'Adam Kluczyk'",
			$this->database->prepareStatement(
				sprintf('SELECT * FROM `person` WHERE `email` %1$s{=email=} AND `name` %1$s{!name!}', $delimiter),
				['email' => null, 'name' => 'Adam Kluczyk']
			)
		);
	}

	/** An array parameter expands to a comma separated list. */
	public function testPrepareStatementWithArray() {
		$delimiter = chr(QQNamedValue::DELIMITER_CODE);

		$this->assertEquals(
			'SELECT * FROM `person` WHERE `id` IN (1,2,3)',
			$this->database->prepareStatement(
				sprintf('SELECT * FROM `person` WHERE `id` IN (%s{ids})', $delimiter),
				['ids' => [1, 2, 3]]
			)
		);
	}

	public function testPrepareStatementEscapesParameters() {
		$delimiter = chr(QQNamedValue::DELIMITER_CODE);

		$prepared = $this->database->prepareStatement(
			sprintf('SELECT * FROM `person` WHERE `name` = %s{name}', $delimiter),
			['name' => "'; DROP TABLE `person`; --"]
		);

		$this->assertEquals("SELECT * FROM `person` WHERE `name` = '\\'; DROP TABLE `person`; --'", $prepared);

		// The query is inert - the person table is still there afterwards
		$this->database->query($prepared);
		$this->assertContains('person', $this->database->getTables());
	}

	public function testNonQueryAndInsertId() {
		$this->database->transactionBegin();

		try {
			$this->database->nonQuery(sprintf(
				'INSERT INTO `asset` (`obj_id`, `filename`, `mime_type`, `size`) VALUES (%s, %s, %s, %s);',
				$this->database->sqlVariable(1),
				$this->database->sqlVariable('inserted.txt'),
				$this->database->sqlVariable('text/plain'),
				$this->database->sqlVariable(1024)
			));

			$insertId = $this->database->insertId();
			$this->assertGreaterThan(0, $insertId);

			// The table and column arguments are accepted and give the same answer
			$this->assertEquals($insertId, $this->database->insertId('asset', 'id'));

			$result = $this->database->query(sprintf(
				'SELECT `filename` FROM `asset` WHERE `id` = %s;',
				$this->database->sqlVariable($insertId)
			));
			$this->assertEquals(['filename' => 'inserted.txt'], $result->fetchArrayAssoc());
		} finally {
			$this->database->transactionRollback();
		}

		$result = $this->database->query("SELECT * FROM `asset` WHERE `filename` = 'inserted.txt';");
		$this->assertEquals(0, $result->countRows());
	}

	public function testTransactionCommit() {
		$this->database->transactionBegin();
		$this->database->query('SELECT 1;');
		$this->database->transactionCommit();

		// The connection is usable again once the transaction is closed
		$this->assertEquals(7, count($this->database->getTables()));
	}

	public function testProfilingOutput() {
		$this->database->query('SELECT 1;');

		$profile = $this->database->outputProfiling();
		$this->assertIsArray($profile);
		$this->assertNotEmpty($profile);

		$last = end($profile);
		$this->assertArrayHasKey('query', $last);
		$this->assertArrayHasKey('backtrace', $last);
		$this->assertEquals('SELECT 1;', $last['query']);
	}

	/** Runs of whitespace collapse to a single space; a lone newline is left alone. */
	public function testProfilingOutputWithoutLineBreaks() {
		$this->database->query("SELECT\n\t1\n\tFROM\n\t`person`;");

		$profile = $this->database->outputProfilingWithoutLineBreaks();
		$this->assertIsArray($profile);

		$last = end($profile);
		$this->assertEquals('SELECT 1 FROM `person`;', $last['query']);
	}

	public function testProfilingOutputWhenDisabled() {
		$this->database->disableProfiling();

		$this->assertNull($this->database->outputProfiling());
		$this->assertNull($this->database->outputProfilingWithoutLineBreaks());

		$this->database->enableProfiling();
	}

	public function testMagicGet() {
		$this->assertEquals('MySql Improved Database Adapter (MySqli)', $this->database->adapter);
		$this->assertEquals(getenv('COG_TEST_DB_NAME') ?: 'cog_framework_test', $this->database->database);
		$this->assertEquals(getenv('COG_TEST_DB_SERVER') ?: 'localhost', $this->database->server);
		$this->assertEquals('`', $this->database->escapeIdentifierBegin);
		$this->assertEquals('`', $this->database->escapeIdentifierEnd);
		$this->assertEquals(0, $this->database->databaseIndex);
		$this->assertTrue($this->database->profiling);
		$sqlMode = $this->database->query('SELECT @@SESSION.sql_mode;')->fetchRow()[0];
		$this->assertSame(str_contains($sqlMode, 'ONLY_FULL_GROUP_BY'), $this->database->onlyFullGroupBy);
		$this->assertIsArray($this->database->profile);
	}

	public function testMagicGetUndefinedProperty() {
		$this->expectException(UndefinedPropertyException::class);
		$this->database->missingProperty;
	}

	public function testMalformedQuery() {
		try {
			$this->database->query('SELECT * FROM `no_such_table`;');
			$this->fail('A query against a missing table should raise a MySqliException');
		} catch (MySqliException $exception) {
			$this->assertStringContainsString('MySqli Error', $exception->getMessage());
			$this->assertStringContainsString('no_such_table', $exception->getMessage());
			$this->assertEquals('SELECT * FROM `no_such_table`;', $exception->query);
			$this->assertGreaterThan(0, $exception->errorNumber);
		}
	}

	public function testMalformedNonQuery() {
		$this->expectException(MySqliException::class);
		$this->database->nonQuery('DELETE FROM `no_such_table`;');
	}

	/** MySQL puts the row limit at the end of the statement, so there is no prefix. */
	public function testSqlLimitAndSortVariables() {
		$this->assertNull($this->database->sqlLimitVariablePrefix('1,2'));
		$this->assertEquals('LIMIT 1,2', $this->database->sqlLimitVariableSuffix('1,2'));
		$this->assertEquals('ORDER BY id ASC', $this->database->sqlSortByVariable('id ASC'));
		$this->assertNull($this->database->sqlSortByVariable(''));
	}

	/** Escaping goes through the driver, so it follows the connection charset. */
	public function testEscapeString() {
		$this->assertEquals("O\\'Brien", $this->database->escapeString("O'Brien"));
		$this->assertEquals('say \\"what\\"', $this->database->escapeString('say "what"'));
		$this->assertEquals('a\\\\b', $this->database->escapeString('a\\b'));
	}

	public function testSqlSortByVariableRejectsSemicolon() {
		$this->expectException(\Exception::class);
		$this->database->sqlSortByVariable('id; DROP TABLE person');
	}

	/**
	 * Indexes are derived from the highest one in use rather than the count, so
	 * dropping a connection cannot make the next one overwrite a connection that
	 * is still open.
	 */
	public function testConnectionIndexes() {
		Database::initializeConnection(self::connectionConfig());
		Database::initializeConnection(self::connectionConfig());
		$this->assertEquals([0, 1, 2], array_keys(Database::$databases));

		// Drop the middle connection; the next index still clears the highest
		Database::$databases[1]->close();
		unset(Database::$databases[1]);

		Database::initializeConnection(self::connectionConfig());
		$this->assertEquals([0, 2, 3], array_keys(Database::$databases));
		$this->assertSame($this->database, Database::$databases[0]);
	}

	public function testExplicitConnectionIndex() {
		Database::initializeConnection(self::connectionConfig(), 7);

		$this->assertArrayHasKey(7, Database::$databases);
		$this->assertEquals(7, Database::$databases[7]->databaseIndex);
	}
}
