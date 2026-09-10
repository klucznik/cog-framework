<?php

namespace Cog\Test;

use Cog\Exceptions\UndefinedPropertyException;
use PHPUnit\Framework\TestCase;

class TestBase extends TestCase {

	public $testObject;

	public function setUp(): void {
		$this->testObject = new MockedBaseObject();
	}

	public function tearDown(): void {
		$this->testObject = null;
	}

	public function testMagicProperty() {
		$this->assertNull($this->testObject->MagicProperty);

		$this->assertTrue(isset($this->testObject->MagicProperty));
		$this->assertFalse(isset($this->testObject->MissingProperty));

		$this->testObject->MagicProperty = 'Value';
		$this->assertEquals('Value', $this->testObject->MagicProperty);
	}

	/**
	 * Null-coalescing has to reach __get.
	 *
	 * Base used to declare an __isset() returning false unconditionally, which made
	 * PHP treat every magic property as unset - so `$obj->prop ?? $default` handed
	 * back the default even when the property held a value, silently and with no
	 * error. Declaring no __isset at all is what lets ?? fall through to __get.
	 * QQHavingClause hid a reference to a property that never existed this way.
	 */
	public function testNullCoalescingReachesTheMagicGetter() {
		$this->testObject->MagicProperty = 'Value';

		$this->assertSame('Value', $this->testObject->MagicProperty ?? 'fallback');
	}

	/** An genuinely undefined property still yields the fallback rather than throwing. */
	public function testNullCoalescingOnAnUndefinedPropertyUsesTheFallback() {
		$this->assertSame('fallback', $this->testObject->MissingProperty ?? 'fallback');
	}

	public function testUndefinedProperty() {
		$this->expectException(UndefinedPropertyException::class);
		$this->testObject->MissingProperty;
	}

	public function testUndefinedPropertySet() {
		$this->expectException(UndefinedPropertyException::class);
		$this->testObject->MissingProperty = 'Something';
	}
}
