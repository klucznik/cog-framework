<?php

namespace Cog\Test;

use Carbon\Carbon;
use Cog\Exceptions\InvalidCastException;
use Cog\Type;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;
use stdClass;

class TestType extends TestCase {

	public function testShortcuts() {
		$this->assertEquals(['gffdsg'], Type::castArray(['gffdsg']));

		$this->assertEquals([], Type::castArrayStrict(null));
		$this->assertFalse(Type::castBoolStrict(null, false));

		$this->assertNull(Type::castArray(null));
		$this->assertNull(Type::castBool(null));
	}

	public function testNullCast() {
		$this->assertFalse(Type::cast(null, Type::BOOLEAN, false));
		$this->assertEmpty(Type::cast(null, Type::STRING, false));
		$this->assertEquals(0, Type::cast(null, Type::INTEGER, false));
		$this->assertEquals(0.0, Type::cast(null, Type::FLOAT, false));
		$this->assertEquals([], Type::cast(null, Type::ARRAY, false));
		$this->assertNull(Type::cast(null, Type::OBJECT, false));
	}

	public function testNullCastWithPreservation() {
		$this->assertNull(Type::cast(null, Type::BOOLEAN));
		$this->assertNull(Type::cast(null, Type::STRING));
		$this->assertNull(Type::cast(null, Type::INTEGER));
		$this->assertNull(Type::cast(null, Type::FLOAT));
		$this->assertNull(Type::cast(null, Type::ARRAY));
		$this->assertNull(Type::cast(null, Type::OBJECT));
	}

	public function testBooleanCast() {
		$this->assertTrue(Type::cast('string', Type::BOOLEAN));
		$this->assertTrue(Type::cast(1, Type::BOOLEAN));
		$this->assertFalse(Type::cast(0, Type::BOOLEAN));
		$this->assertFalse(Type::cast(null, Type::BOOLEAN, false));
	}

	public function testStringCast() {
		$this->assertEquals('string', Type::cast('string', Type::STRING));
		$this->assertEquals("string", Type::cast('string', Type::STRING));
		$this->assertEquals('1', Type::cast(1, Type::STRING));
		$this->assertEquals('0', Type::cast(0, Type::STRING));
		$this->assertEquals('', Type::cast(null, Type::STRING, false));
	}

	public function testConstantCodeGenerator() {
		$this->assertEquals('Type::STRING', Type::constant('string'));
		$this->assertEquals('Type::OBJECT', Type::constant('object'));
		$this->assertEquals('Type::INTEGER', Type::constant('integer'));
		$this->assertEquals('Type::FLOAT', Type::constant('double'));
		$this->assertEquals('Type::BOOLEAN', Type::constant('boolean'));
		$this->assertEquals('Type::ARRAY', Type::constant('array'));
		$this->assertEquals('Type::DATETIME', Type::constant('Carbon'));
	}

	public function testConstantCodeGeneratorException() {
		$this->expectException(InvalidCastException::class);
		Type::constant('missing');
	}

	public function testArrayCast() {
		$array = ['array', 'with', 'stuff'];
		$this->assertEquals(Type::cast($array, Type::ARRAY), $array);

		$this->expectException(InvalidCastException::class);
		Type::cast($array, Type::BOOLEAN);
	}

	public function testObjectCast() {
		$obj = new stdClass();

		$this->expectException(InvalidCastException::class);
		Type::cast($obj, Type::ARRAY);
	}

	public function testObjectCast2() {
		$obj = new stdClass();
		$this->assertEquals(Type::cast($obj, 'stdClass'), $obj);
	}

	public function testInvalidCast() {
		$this->expectException(InvalidCastException::class);
		Type::cast('sgdgd', Type::ARRAY);
	}

	public function testCastValueTo() {
		$this->assertFalse(Type::cast(false, Type::BOOLEAN));
		$this->assertFalse(Type::cast('', Type::BOOLEAN));
		$this->assertFalse(Type::cast('false', Type::BOOLEAN));
		$this->assertTrue(Type::cast('true', Type::BOOLEAN));

		$this->assertEquals('string', Type::cast('string', Type::STRING));

		$this->assertEquals('1.324', Type::cast('1.324', Type::FLOAT));
		$this->assertNull(Type::cast('', Type::FLOAT));

		$this->expectException(InvalidCastException::class);
		$this->assertEquals('1.32443767654765655475674756747423223432', Type::cast('1.32443767654765655475674756747423223432', Type::FLOAT));
	}

	public function testDeclarationType() {
		$this->assertEquals('bool', Type::getDeclarationType(Type::BOOLEAN));
		$this->assertEquals('int', Type::getDeclarationType(Type::INTEGER));
		$this->assertEquals('float', Type::getDeclarationType(Type::FLOAT));

		// Everything else is already usable in a type declaration and passes through
		$this->assertEquals('string', Type::getDeclarationType(Type::STRING));
		$this->assertEquals('array', Type::getDeclarationType(Type::ARRAY));
		$this->assertEquals('object', Type::getDeclarationType(Type::OBJECT));
		$this->assertEquals('Carbon', Type::getDeclarationType(Type::DATETIME));
		$this->assertEquals('Cog\Test\MockedBaseObject', Type::getDeclarationType(MockedBaseObject::class));
	}

	public function testDateTimeCast() {
		$carbon = Carbon::parse('2020-01-02 03:04:05');

		$this->assertSame($carbon, Type::cast($carbon, Type::DATETIME));
		$this->assertSame($carbon, Type::cast($carbon, Carbon::class));

		$this->assertNull(Type::cast(null, Type::DATETIME));
		$this->assertNull(Type::cast(null, Type::DATETIME, false));
	}

	/** Carbon is an object, and objects only cast to a class they are an instance of. */
	public function testDateTimeCastToStringIsRejected() {
		$this->expectException(InvalidCastException::class);
		Type::cast(Carbon::parse('2020-01-02 03:04:05'), Type::STRING);
	}

	public function testStringToDateTimeCastIsRejected() {
		$this->expectException(InvalidCastException::class);
		Type::cast('2020-01-02', Type::DATETIME);
	}

	public function testIntegerCast() {
		$this->assertSame(42, Type::cast('42', Type::INTEGER));
		$this->assertSame(42, Type::cast(42, Type::INTEGER));
		$this->assertSame(-7, Type::cast('-7', Type::INTEGER));
		$this->assertNull(Type::cast('', Type::INTEGER));
	}

	/** A cast that silently loses information is treated as invalid. */
	public function testLossyIntegerCastIsRejected() {
		$this->expectException(InvalidCastException::class);
		Type::cast('42.5', Type::INTEGER);
	}

	public function testNonNumericStringToIntegerIsRejected() {
		$this->expectException(InvalidCastException::class);
		Type::cast('twelve', Type::INTEGER);
	}

	public function testStrictShortcuts() {
		$this->assertSame(['a', 'b'], Type::castArrayStrict(['a', 'b']));
		$this->assertSame([], Type::castArrayStrict(null));

		$this->assertTrue(Type::castBoolStrict('true'));
		$this->assertFalse(Type::castBoolStrict('false'));
		$this->assertFalse(Type::castBoolStrict(0));
		$this->assertFalse(Type::castBoolStrict(null));

		$this->assertTrue(Type::castBool('true'));
		$this->assertNull(Type::castBool(null));
	}

	public function testSimpleXml() {
		$xml = new SimpleXMLElement('<foo>bar</foo>');

		$this->assertEquals('bar', Type::cast($xml, Type::STRING));
		$this->assertTrue(Type::cast($xml, Type::BOOLEAN));
		$this->assertTrue(Type::cast(new SimpleXMLElement('<foo>true</foo>'), Type::BOOLEAN));
		$this->assertFalse(Type::cast(new SimpleXMLElement('<foo>false</foo>'), Type::BOOLEAN));

		$this->assertEquals(2, Type::cast(new SimpleXMLElement('<foo>2</foo>'), Type::INTEGER));

		$this->expectException(InvalidCastException::class);
		$this->assertEquals(2, Type::cast(new SimpleXMLElement('<foo>string</foo>'), Type::INTEGER));
	}
}
