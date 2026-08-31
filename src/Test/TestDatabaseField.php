<?php declare(strict_types=1);

namespace Cog\Test;

use Cog\Database\Adapters\MySqliField;
use Cog\Database\FieldType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the MySQL type to Cog type mapping in MySqliField.
 *
 * This mapping decides the PHP type of every generated property, so getting a
 * case wrong produces an ORM that loads and silently hands back the wrong type.
 * Most of the switch was uncovered simply because the test schema does not use
 * every MySQL type - there is no BLOB, TIME or YEAR column in
 * cog_framework_test.sql, and there never should be just to exercise a branch.
 *
 * Instead the field metadata is synthesised. MySqliField only reaches for the
 * database when originalTable is set, so an empty originalTable exercises the
 * whole type switch with no connection at all.
 *
 * The types that throw are as important as the ones that map: ENUM and SET are
 * refused on purpose, to push you towards a type table.
 */
class TestDatabaseField extends TestCase {

	/**
	 * Field metadata shaped like what mysqli_result::fetch_field() returns.
	 * originalTable is deliberately empty so the constructor skips its
	 * SHOW FULL FIELDS lookup.
	 */
	private function fieldData(int $type, int $flags = 0, string $originalName = 'id'): object {
		return (object)[
			'name' => 'id',
			'orgname' => $originalName,
			'table' => 'person',
			'orgtable' => '',
			'def' => null,
			'flags' => $flags,
			'type' => $type,
		];
	}

	public static function fieldTypeProvider(): array {
		return [
			'bit' => [MYSQLI_TYPE_BIT, FieldType::BIT],
			'smallint' => [MYSQLI_TYPE_SHORT, FieldType::INTEGER],
			'int' => [MYSQLI_TYPE_LONG, FieldType::INTEGER],
			'bigint' => [MYSQLI_TYPE_LONGLONG, FieldType::INTEGER],
			'mediumint' => [MYSQLI_TYPE_INT24, FieldType::INTEGER],
			'year' => [MYSQLI_TYPE_YEAR, FieldType::INTEGER],
			'decimal' => [MYSQLI_TYPE_DECIMAL, FieldType::FLOAT],
			'newdecimal' => [MYSQLI_TYPE_NEWDECIMAL, FieldType::FLOAT],
			'float' => [MYSQLI_TYPE_FLOAT, FieldType::FLOAT],
			'date' => [MYSQLI_TYPE_DATE, FieldType::DATE],
			'time' => [MYSQLI_TYPE_TIME, FieldType::TIME],
			'datetime' => [MYSQLI_TYPE_DATETIME, FieldType::DATETIME],
			'tinyblob' => [MYSQLI_TYPE_TINY_BLOB, FieldType::BLOB],
			'mediumblob' => [MYSQLI_TYPE_MEDIUM_BLOB, FieldType::BLOB],
			'longblob' => [MYSQLI_TYPE_LONG_BLOB, FieldType::BLOB],
			'blob' => [MYSQLI_TYPE_BLOB, FieldType::BLOB],
			'string' => [MYSQLI_TYPE_STRING, FieldType::VARCHAR],
			'varstring' => [MYSQLI_TYPE_VAR_STRING, FieldType::VARCHAR],
		];
	}

	#[DataProvider('fieldTypeProvider')]
	public function testFieldTypeMapping(int $mySqlType, string $expected) {
		$field = new MySqliField($this->fieldData($mySqlType));

		$this->assertSame($expected, $field->type);
	}

	/**
	 * A double maps to VarChar rather than Float: PHP cannot hold full
	 * double precision, so the value is kept as text to avoid losing digits.
	 */
	public function testDoubleIsKeptAsText() {
		$field = new MySqliField($this->fieldData(MYSQLI_TYPE_DOUBLE));

		$this->assertSame(FieldType::VARCHAR, $field->type);
	}

	/**
	 * A timestamp is both typed as Timestamp and flagged as one - the flag is what
	 * drives optimistic locking in the generated classes, and what excludes the
	 * column from the CURRENT_TIMESTAMP default handling.
	 */
	public function testTimestampSetsTheTimestampFlag() {
		$field = new MySqliField($this->fieldData(MYSQLI_TYPE_TIMESTAMP));

		$this->assertSame(FieldType::TIMESTAMP, $field->type);
		$this->assertTrue((bool)$field->timestamp);
	}

	/**
	 * Without the length the database would have supplied, a tinyint is an integer.
	 * The tinyint(1) to Bit narrowing needs maxLength, which only the
	 * SHOW FULL FIELDS lookup provides - TestDatabase covers that path against the
	 * real person.email_verified column.
	 */
	public function testTinyIntWithoutLengthIsAnInteger() {
		$field = new MySqliField($this->fieldData(MYSQLI_TYPE_TINY));

		$this->assertSame(FieldType::INTEGER, $field->type);
	}

	public static function unsupportedTypeProvider(): array {
		return [
			'null' => [MYSQLI_TYPE_NULL, 'MYSQLI_TYPE_NULL is not supported'],
			'newdate' => [MYSQLI_TYPE_NEWDATE, 'MYSQLI_TYPE_NEWDATE is not supported'],
			'geometry' => [MYSQLI_TYPE_GEOMETRY, 'MYSQLI_TYPE_GEOMETRY is not supported'],
		];
	}

	#[DataProvider('unsupportedTypeProvider')]
	public function testUnsupportedTypesThrow(int $mySqlType, string $message) {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessageIsOrContains($message);

		new MySqliField($this->fieldData($mySqlType));
	}

	/** ENUM and SET are refused with advice rather than a bare failure. */
	public function testEnumPointsAtTypeTables() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessageIsOrContains('Use TypeTables instead');

		new MySqliField($this->fieldData(MYSQLI_TYPE_ENUM));
	}

	public function testSetPointsAtTypeTables() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessageIsOrContains('Use TypeTables instead');

		new MySqliField($this->fieldData(MYSQLI_TYPE_SET));
	}

	public function testUnknownTypeThrows() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessageIsOrContains('Unable to determine MySqli Field Type');

		new MySqliField($this->fieldData(30_000));
	}

	//
	// The flag decoding beside the type switch
	//

	public function testFlagsAreDecoded() {
		$flags = MYSQLI_AUTO_INCREMENT_FLAG | MYSQLI_NOT_NULL_FLAG | MYSQLI_PRI_KEY_FLAG;
		$field = new MySqliField($this->fieldData(MYSQLI_TYPE_LONG, $flags));

		$this->assertTrue((bool)$field->identity);
		$this->assertTrue((bool)$field->notNull);
		$this->assertTrue((bool)$field->primaryKey);
		$this->assertFalse((bool)$field->unique);
	}

	public function testUniqueFlagIsDecoded() {
		$field = new MySqliField($this->fieldData(MYSQLI_TYPE_STRING, MYSQLI_UNIQUE_KEY_FLAG));

		$this->assertTrue((bool)$field->unique);
		$this->assertFalse((bool)$field->identity);
	}

	public function testNoFlagsLeavesEverythingOff() {
		$field = new MySqliField($this->fieldData(MYSQLI_TYPE_STRING));

		$this->assertFalse((bool)$field->identity);
		$this->assertFalse((bool)$field->notNull);
		$this->assertFalse((bool)$field->primaryKey);
		$this->assertFalse((bool)$field->unique);
	}

	/**
	 * A computed column comes back with no original name - an aggregate or an
	 * expression - and falls back to the alias it was selected under.
	 */
	public function testOriginalNameFallsBackToName() {
		$field = new MySqliField($this->fieldData(MYSQLI_TYPE_LONG, 0, ''));

		$this->assertSame('id', $field->originalName);
	}

	public function testOriginalNameIsKeptWhenPresent() {
		$field = new MySqliField($this->fieldData(MYSQLI_TYPE_LONG, 0, 'person_id'));

		$this->assertSame('person_id', $field->originalName);
		$this->assertSame('id', $field->name);
	}

	/** With no original table there is nothing to look up, so length and comment stay empty. */
	public function testWithoutOriginalTableNoLookupHappens() {
		$field = new MySqliField($this->fieldData(MYSQLI_TYPE_STRING));

		$this->assertNull($field->maxLength);
		$this->assertSame('', $field->comment);
		$this->assertSame('person', $field->table);
	}
}
