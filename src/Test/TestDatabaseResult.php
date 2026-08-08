<?php

namespace Cog\Test;

use Carbon\Carbon;
use Cog\Database\Adapters\MySqliField;
use Cog\Database\Adapters\MySqliResult;
use Cog\Database\Adapters\MySqliRow;
use Cog\Database\Database;
use Cog\Database\FieldType;
use Cog\Exceptions\UndefinedPropertyException;
use PHPUnit\Framework\TestCase;

/**
 * Covers the result, row and field objects returned by the MySqli adapter.
 * Everything here reads from the cog_test fixture; nothing writes.
 */
class TestDatabaseResult extends TestCase {

	/** @var \Cog\Database\Base */
	private $database;

	public function setUp(): void {
		foreach (Database::$databases as $index => $database) {
			$database->close();
			unset(Database::$databases[$index]);
		}

		Database::initializeConnection([
			'adapter' => 'MySqli',
			'server' => getenv('COG_TEST_DB_SERVER') ?: 'localhost',
			'encoding' => 'UTF8',
			'database' => getenv('COG_TEST_DB_NAME') ?: 'cog_test',
			'username' => getenv('COG_TEST_DB_USER') ?: 'root',
			'password' => getenv('COG_TEST_DB_PASSWORD') ?: '',
			'profiling' => true
		]);

		$this->database = Database::$databases[0];
	}

	public function tearDown(): void {
		foreach (Database::$databases as $index => $database) {
			$database->close();
			unset(Database::$databases[$index]);
		}
	}

	public function testResultType() {
		$result = $this->database->query('SELECT * FROM `person` ORDER BY `id`;');
		$this->assertInstanceOf(MySqliResult::class, $result);
	}

	public function testCountRows() {
		$this->assertEquals(
			3,
			$this->database->query('SELECT * FROM `person`;')->countRows()
		);
		$this->assertEquals(
			1,
			$this->database->query('SELECT * FROM `person` WHERE `id` = 1;')->countRows()
		);
		$this->assertEquals(
			0,
			$this->database->query('SELECT * FROM `person` WHERE `id` = 0;')->countRows()
		);
	}

	/** fetchArray() gives both numeric and associative keys, fetchRow() only numeric. */
	public function testFetchShapes() {
		$row = $this->database->query('SELECT `id`, `name` FROM `person` WHERE `id` = 1;')->fetchArray();
		$this->assertEquals('1', $row[0]);
		$this->assertEquals('1', $row['id']);

		$row = $this->database->query('SELECT `id`, `name` FROM `person` WHERE `id` = 1;')->fetchRow();
		$this->assertEquals(['1', 'Adam Kluczyk'], $row);

		$row = $this->database->query('SELECT `id`, `name` FROM `person` WHERE `id` = 1;')->fetchArrayAssoc();
		$this->assertEquals(['id' => '1', 'name' => 'Adam Kluczyk'], $row);
	}

	public function testFetchPastTheEnd() {
		$result = $this->database->query('SELECT `id` FROM `person` WHERE `id` = 1;');

		$this->assertNotNull($result->fetchArray());
		$this->assertNull($result->fetchArray());
		$this->assertNull($result->fetchRow());
		$this->assertNull($result->fetchArrayAssoc());
		$this->assertNull($result->getNextRow());
	}

	public function testGetNextRow() {
		$result = $this->database->query('SELECT * FROM `person` ORDER BY `id`;');

		$row = $result->getNextRow();
		$this->assertInstanceOf(MySqliRow::class, $row);
		$this->assertEquals('Adam Kluczyk', $row->getColumn('name'));
	}

	public function testGetRowsAreAllRowObjects() {
		$rows = $this->database->query('SELECT * FROM `asset` ORDER BY `id`;')->getRows();

		$this->assertCount(2, $rows);
		foreach ($rows as $row) {
			$this->assertInstanceOf(MySqliRow::class, $row);
		}
		$this->assertEquals('logo.png', $rows[0]->getColumn('filename'));
	}

	public function testClose() {
		$result = $this->database->query('SELECT `id` FROM `person`;');
		$result->close();

		// Closing twice would be a use-after-free; one close is all this asserts
		$this->assertInstanceOf(MySqliResult::class, $result);
	}

	public function testResultUndefinedProperty() {
		$result = $this->database->query('SELECT `id` FROM `person`;');

		$this->expectException(UndefinedPropertyException::class);
		$result->missingProperty;
	}

	public function testResultUndefinedPropertySet() {
		$result = $this->database->query('SELECT `id` FROM `person`;');

		$this->expectException(UndefinedPropertyException::class);
		$result->missingProperty = 'value';
	}

	public function testRowColumnExists() {
		$row = $this->database->query('SELECT * FROM `person` WHERE `id` = 1;')->getNextRow();

		$this->assertTrue($row->columnExists('id'));
		$this->assertTrue($row->columnExists('email'));
		$this->assertFalse($row->columnExists('no_such_column'));

		$this->assertNull($row->getColumn('no_such_column'));
	}

	public function testRowColumnNameArray() {
		$row = $this->database->query('SELECT `id`, `name` FROM `person` WHERE `id` = 1;')->getNextRow();

		$columns = $row->getColumnNameArray();
		$this->assertArrayHasKey('id', $columns);
		$this->assertArrayHasKey('name', $columns);
		$this->assertEquals('Adam Kluczyk', $columns['name']);
	}

	/** Without a column type the raw driver string comes back untouched. */
	public function testRowColumnWithoutType() {
		$row = $this->database->query('SELECT * FROM `person` WHERE `id` = 1;')->getNextRow();

		$this->assertSame('1', $row->getColumn('id'));
		$this->assertSame('Adam Kluczyk', $row->getColumn('name'));
	}

	public function testRowColumnCasting() {
		$row = $this->database->query('SELECT * FROM `asset` ORDER BY `id`;')->getNextRow();

		$this->assertSame(1, $row->getColumn('id', FieldType::INTEGER));
		$this->assertSame('logo.png', $row->getColumn('filename', FieldType::VARCHAR));
		$this->assertSame(20480.0, $row->getColumn('size', FieldType::FLOAT));

		$row = $this->database->query('SELECT * FROM `obj` ORDER BY `id`;')->getNextRow();

		$creationDate = $row->getColumn('creation_date', FieldType::DATETIME);
		$this->assertInstanceOf(Carbon::class, $creationDate);
		$this->assertEquals('2024-01-15 10:00:00', $creationDate->toDateTimeString());
	}

	public function testRowBitColumn() {
		$row = $this->database->query('SELECT * FROM `person` WHERE `id` = 1;')->getNextRow();

		$this->assertFalse($row->getColumn('email_verified', FieldType::BIT));

		$row = $this->database->query('SELECT * FROM `person` WHERE `email_verified` = 1 ORDER BY `id`;')->getNextRow();
		$this->assertTrue($row->getColumn('email_verified', FieldType::BIT));
	}

	public function testNullColumnIsNeverCast() {
		$row = $this->database->query('SELECT NULL AS `nothing`;')->getNextRow();

		$this->assertNull($row->getColumn('nothing'));
		$this->assertNull($row->getColumn('nothing', FieldType::INTEGER));
		$this->assertNull($row->getColumn('nothing', FieldType::DATETIME));
	}

	public function testFetchField() {
		$result = $this->database->query('SELECT `id`, `name` FROM `person`;');

		$field = $result->fetchField();
		$this->assertInstanceOf(MySqliField::class, $field);
		$this->assertEquals('id', $field->name);
		$this->assertEquals('person', $field->table);
		$this->assertEquals('person', $field->originalTable);
		$this->assertEquals(FieldType::INTEGER, $field->type);
		$this->assertTrue((bool)$field->primaryKey);
		$this->assertTrue((bool)$field->identity);
		$this->assertTrue((bool)$field->notNull);

		$this->assertEquals('name', $result->fetchField()->name);

		// Exhausted
		$this->assertNull($result->fetchField());
	}

	public function testFetchFields() {
		$fields = $this->database->query('SELECT * FROM `person`;')->fetchFields();

		$this->assertCount(5, $fields);
		$this->assertEquals(
			['id', 'name', 'email', 'email_verified', 'password'],
			array_map(static fn(MySqliField $field) => $field->name, $fields)
		);
	}

	/** An aliased column keeps the alias as its name and the column it came from as originalName. */
	public function testFieldAlias() {
		$field = $this->database->query('SELECT `name` AS `person_name` FROM `person`;')->fetchField();

		$this->assertEquals('person_name', $field->name);
		$this->assertEquals('name', $field->originalName);
	}

	public function testFieldMaxLength() {
		$fields = $this->database->query('SELECT * FROM `person`;')->fetchFields();
		$byName = [];
		foreach ($fields as $field) {
			$byName[$field->name] = $field;
		}

		$this->assertEquals(255, $byName['name']->maxLength);
		$this->assertEquals(255, $byName['email']->maxLength);
		$this->assertNull($byName['name']->default);
		$this->assertEquals('0', $byName['email_verified']->default);
		$this->assertTrue((bool)$byName['email']->unique);
	}

	public function testFieldUndefinedProperty() {
		$field = $this->database->query('SELECT `id` FROM `person`;')->fetchField();

		$this->expectException(UndefinedPropertyException::class);
		$field->missingProperty;
	}
}
