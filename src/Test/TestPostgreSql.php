<?php declare(strict_types=1);

namespace Cog\Test;

use Carbon\Carbon;
use Cog\Database\Adapters\PostgreSqlAdapter;
use Cog\Database\Database;
use Cog\Database\FieldType;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * The PostgreSQL adapter, held to the same expectations as TestDatabase holds
 * the MySQL one, against the parallel fixture in cog_framework_test_pg.sql.
 *
 * Every test skips rather than fails when PostgreSQL is not reachable: the
 * pgsql extension is optional, the server is a second piece of local setup on
 * top of the MySQL one the rest of the suite needs, and neither is worth
 * turning into a red suite for someone who only works on the MySQL side.
 */
class TestPostgreSql extends TestCase {

	public ?PostgreSqlAdapter $database = null;

	/** Cached across tests so an unreachable server is only probed once. */
	private static ?string $skipReason = null;
	private static bool $probed = false;

	private static function connectionConfig(array $overrides = []): array {
		return array_merge([
			'adapter' => 'PostgreSql',
			'server' => getenv('COG_TEST_PG_SERVER') ?: '127.0.0.1',
			'port' => getenv('COG_TEST_PG_PORT') ?: '5432',
			'encoding' => 'UTF8',
			'database' => getenv('COG_TEST_PG_NAME') ?: 'cog_framework_test',
			'username' => getenv('COG_TEST_PG_USER') ?: get_current_user(),
			'password' => getenv('COG_TEST_PG_PASSWORD') ?: '',
			'profiling' => false,
		], $overrides);
	}

	/**
	 * Both halves of the setup are checked here - the server being up and the
	 * fixture being loaded - so a half-prepared environment reports which half
	 * is missing instead of failing an assertion about a table count.
	 */
	private static function skipReason(): ?string {
		if (self::$probed) {
			return self::$skipReason;
		}
		self::$probed = true;

		if (!function_exists('pg_connect')) {
			return self::$skipReason = 'the pgsql extension is not loaded';
		}

		try {
			Database::initializeConnection(self::connectionConfig());
			$database = Database::$databases[array_key_last(Database::$databases)];

			if (!in_array('person', $database->getTables(), true)) {
				self::$skipReason = 'the PostgreSQL fixture is not loaded - see src/Test/cog_framework_test_pg.sql';
			}

			$database->close();
		} catch (Throwable $exception) {
			self::$skipReason = 'no PostgreSQL server: ' . $exception->getMessage();
		}

		self::closeAll();

		return self::$skipReason;
	}

	private static function closeAll(): void {
		foreach (Database::$databases as $index => $database) {
			$database->close();
			unset(Database::$databases[$index]);
		}
	}

	public function setUp(): void {
		if ($reason = self::skipReason()) {
			$this->markTestSkipped($reason);
		}

		self::closeAll();
		Database::initializeConnection(self::connectionConfig());
		$this->database = Database::$databases[0];
	}

	public function tearDown(): void {
		self::closeAll();
	}

	public function testGetTables(): void {
		$tables = $this->database->getTables();

		$this->assertCount(12, $tables, 'the fixture defines twelve tables');
		$this->assertContains('person', $tables);
		$this->assertContains('tag_obj_assn', $tables);
		$this->assertNotContains('information_schema', $tables);
	}

	public function testGetFieldsForTable(): void {
		$fields = $this->database->getFieldsForTable('person');

		$this->assertCount(5, $fields);
		$this->assertSame(['id', 'name', 'email', 'email_verified', 'password'], array_map(
			static fn($field) => $field->name,
			$fields
		), 'fields come back in ordinal position order');
	}

	public function testFieldFlags(): void {
		$fields = [];
		foreach ($this->database->getFieldsForTable('person') as $field) {
			$fields[$field->name] = $field;
		}

		$this->assertTrue($fields['id']->identity, 'an IDENTITY column is the identity field');
		$this->assertTrue($fields['id']->primaryKey);
		$this->assertTrue($fields['email']->unique, 'a single column UNIQUE constraint marks the column unique');
		$this->assertFalse($fields['password']->unique);
		$this->assertTrue($fields['email_verified']->notNull);
		$this->assertSame(255, $fields['name']->maxLength);
		$this->assertNull($fields['id']->maxLength, 'an integer has no character length');
	}

	public function testFieldTypes(): void {
		$fields = [];
		foreach ($this->database->getFieldsForTable('person') as $field) {
			$fields[$field->name] = $field;
		}

		$this->assertSame(FieldType::INTEGER, $fields['id']->type);
		$this->assertSame(FieldType::VARCHAR, $fields['name']->type);
		$this->assertSame(FieldType::BIT, $fields['email_verified']->type, 'a real boolean maps onto Bit');

		$objFields = [];
		foreach ($this->database->getFieldsForTable('obj') as $field) {
			$objFields[$field->name] = $field;
		}
		$this->assertSame(FieldType::DATETIME, $objFields['creation_date']->type);
	}

	public function testFieldComment(): void {
		foreach ($this->database->getFieldsForTable('person') as $field) {
			if ($field->name === 'email') {
				$this->assertSame('primary contact address', $field->comment);
				return;
			}
		}

		$this->fail('person.email was not returned');
	}

	/**
	 * PostgreSQL has no self-updating timestamp column, and the trigger the
	 * fixture uses instead cannot be told apart from any other trigger through
	 * the catalog. The generator's optimistic locking is driven entirely by
	 * this flag, so it is off for every PostgreSQL column - asserted here so
	 * the limitation stays visible rather than being rediscovered.
	 */
	public function testNoColumnIsAnOptimisticLockingToken(): void {
		foreach ($this->database->getFieldsForTable('blog_post') as $field) {
			$this->assertFalse($field->timestamp, $field->name . ' must not be reported as a locking token');
		}
	}

	public function testGetIndexesForTable(): void {
		$indexes = $this->database->getIndexesForTable('person');

		$this->assertCount(4, $indexes);
		$this->assertTrue($indexes[0]->primaryKey, 'the primary key comes first');
		$this->assertSame(['id'], $indexes[0]->columnNameArray);

		$this->assertSame('email', $indexes[1]->keyName);
		$this->assertTrue($indexes[1]->unique);
		$this->assertFalse($indexes[1]->primaryKey);
		$this->assertSame(['email'], $indexes[1]->columnNameArray);

		$this->assertSame('name', $indexes[2]->keyName);
		$this->assertFalse($indexes[2]->unique);
	}

	public function testGetIndexesForTableMultiColumn(): void {
		$indexes = $this->database->getIndexesForTable('tag_obj_assn');

		$this->assertTrue($indexes[0]->primaryKey);
		$this->assertSame(['tag_id', 'obj_id'], $indexes[0]->columnNameArray,
			'a composite key keeps its column order');
	}

	public function testGetForeignKeysForTable(): void {
		$foreignKeys = $this->database->getForeignKeysForTable('obj');

		$this->assertCount(1, $foreignKeys);
		$this->assertSame('obj_person_id', $foreignKeys[0]->keyName);
		$this->assertSame(['person_id'], $foreignKeys[0]->columnNameArray);
		$this->assertSame('person', $foreignKeys[0]->referenceTableName);
		$this->assertSame(['id'], $foreignKeys[0]->referenceColumnNameArray);
	}

	public function testGetForeignKeysForTableSelfReference(): void {
		$keyNames = array_map(
			static fn($foreignKey) => $foreignKey->keyName,
			$this->database->getForeignKeysForTable('category')
		);

		$this->assertCount(3, $keyNames);
		$this->assertContains('category_parent_id', $keyNames, 'a table may reference itself');
		$this->assertContains('category_owner', $keyNames, 'a foreign key column need not end in _id');
	}

	public function testQueryAndRowCasting(): void {
		$result = $this->database->query('SELECT * FROM person ORDER BY id');
		$this->assertSame(3, $result->countRows());

		$row = $result->getNextRow();
		$this->assertSame('Adam Kluczyk', $row->getColumn('name', FieldType::VARCHAR));
		$this->assertSame(1, $row->getColumn('id', FieldType::INTEGER));
		$this->assertTrue($row->columnExists('email'));
		$this->assertNull($row->getColumn('nonexistent'));
	}

	/**
	 * libpq renders booleans as 't' and 'f', which the MySQL adapter's ord()
	 * based test would read as true for both.
	 */
	public function testBooleanColumnCasting(): void {
		$result = $this->database->query('SELECT * FROM person ORDER BY id');

		$this->assertFalse($result->getNextRow()->getColumn('email_verified', FieldType::BIT));
		$this->assertTrue($result->getNextRow()->getColumn('email_verified', FieldType::BIT));
	}

	public function testDateTimeColumnCasting(): void {
		$row = $this->database->query('SELECT * FROM obj ORDER BY id')->getNextRow();
		$creationDate = $row->getColumn('creation_date', FieldType::DATETIME);

		$this->assertInstanceOf(Carbon::class, $creationDate);
		$this->assertSame('2024-01-15 10:00:00', $creationDate->toDateTimeString());
	}

	public function testFetchField(): void {
		$result = $this->database->query('SELECT id, name FROM person LIMIT 1');

		$field = $result->fetchField();
		$this->assertSame('id', $field->name);
		$this->assertSame(FieldType::INTEGER, $field->type);
		$this->assertSame('person', $field->originalTable);

		$this->assertSame('name', $result->fetchField()->name);
		$this->assertNull($result->fetchField(), 'the cursor stops at the last column');
	}

	public function testFetchFields(): void {
		$fields = $this->database->query('SELECT * FROM person')->fetchFields();

		$this->assertCount(5, $fields);
		$this->assertSame('email_verified', $fields[3]->name);
		$this->assertSame(FieldType::BIT, $fields[3]->type);
	}

	public function testFetchFieldOnAComputedColumn(): void {
		$field = $this->database->query('SELECT count(*) AS total FROM person')->fetchField();

		$this->assertSame('total', $field->name);
		$this->assertSame('', $field->originalTable, 'a computed column belongs to no table');
	}

	public function testEscapeString(): void {
		$this->assertSame("O''Brien", $this->database->escapeString("O'Brien"));
		$this->assertSame("'O''Brien'", $this->database->sqlVariable("O'Brien"));
	}

	/**
	 * The generic implementation emits the integer literals MySQL wants, which
	 * PostgreSQL rejects with "operator does not exist: boolean = integer".
	 */
	public function testSqlVariableBoolean(): void {
		$this->assertSame('TRUE', $this->database->sqlVariable(true));
		$this->assertSame('FALSE', $this->database->sqlVariable(false));
		$this->assertSame('!= FALSE', $this->database->sqlVariable(true, true));
		$this->assertSame('= FALSE', $this->database->sqlVariable(false, true));
		$this->assertSame('= FALSE', $this->database->sqlVariable(true, true, true));
		$this->assertSame('IS NULL', $this->database->sqlVariable(null, true));
	}

	public function testBooleanPredicateExecutes(): void {
		$sql = 'SELECT count(*) FROM person WHERE email_verified ' . $this->database->sqlVariable(true, true);

		$this->assertSame('2', $this->database->query($sql)->fetchRow()[0]);
	}

	/**
	 * QQLimitInfo hands the adapter MySQL's "offset,count", which has to be
	 * turned around for PostgreSQL.
	 */
	public function testLimitTranslation(): void {
		$this->assertNull($this->database->sqlLimitVariablePrefix('1,2'));
		$this->assertSame('LIMIT 2 OFFSET 1', $this->database->sqlLimitVariableSuffix('1,2'));
		$this->assertSame('LIMIT 1', $this->database->sqlLimitVariableSuffix('1'));
		$this->assertNull($this->database->sqlLimitVariableSuffix(''));
	}

	public function testLimitRejectsInjection(): void {
		$this->expectException(\Exception::class);
		$this->database->sqlLimitVariableSuffix('1; DROP TABLE person');
	}

	public function testLimitApplies(): void {
		$result = $this->database->query(
			'SELECT id FROM person ORDER BY id ' . $this->database->sqlLimitVariableSuffix('1,2')
		);

		$this->assertSame([['2'], ['3']], [$result->fetchRow(), $result->fetchRow()]);
	}

	public function testSortByVariable(): void {
		$this->assertSame('ORDER BY id ASC', $this->database->sqlSortByVariable('id ASC'));
		$this->assertNull($this->database->sqlSortByVariable(''));
	}

	public function testTransactionRollback(): void {
		$this->database->transactionBegin();
		$this->database->nonQuery("INSERT INTO tag (name) VALUES ('rollback-me')");
		$this->assertSame(1, $this->database->affectedRows);
		$this->database->transactionRollback();

		$this->assertSame('0', $this->database->query(
			"SELECT count(*) FROM tag WHERE name = 'rollback-me'"
		)->fetchRow()[0]);
	}

	public function testTransactionCommit(): void {
		$this->database->transactionBegin();
		$this->database->nonQuery("INSERT INTO tag (name) VALUES ('commit-me')");
		$this->database->transactionCommit();

		$this->assertSame('1', $this->database->query(
			"SELECT count(*) FROM tag WHERE name = 'commit-me'"
		)->fetchRow()[0]);

		$this->database->nonQuery("DELETE FROM tag WHERE name = 'commit-me'");
	}

	public function testInsertId(): void {
		$this->database->nonQuery("INSERT INTO tag (name) VALUES ('insert-id-test')");

		$insertId = $this->database->insertId('tag', 'id');
		$this->assertIsInt($insertId);
		$this->assertGreaterThan(3, $insertId);
		$this->assertSame($insertId, $this->database->insertId(), 'lastval() is the no-argument fallback');

		$this->database->nonQuery('DELETE FROM tag WHERE id = ' . $insertId);
	}

	public function testFailedQueryCarriesTheQuery(): void {
		try {
			$this->database->query('SELECT * FROM no_such_table');
			$this->fail('a failing query must throw');
		} catch (\Cog\Database\Adapters\PostgreSqlException $exception) {
			$this->assertSame('SELECT * FROM no_such_table', $exception->query);
			$this->assertStringContainsString('no_such_table', $exception->getMessage());
		}
	}

	public function testOnlyFullGroupBy(): void {
		$this->assertTrue($this->database->onlyFullGroupBy,
			'PostgreSQL always enforces the equivalent of ONLY_FULL_GROUP_BY');
	}
}
