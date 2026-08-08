<?php

namespace Cog\Test;

use Cog\Exceptions\CogException;
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

	public function testMissingPropertyOverride() {
		$this->expectException(UndefinedPropertyException::class);
		$this->testObject->overrideAttributes(['missingProperty' => 'Setting value']);
	}

	public function testArrayOverride() {
		$this->assertNull($this->testObject->overrideProperty);
		$this->testObject->overrideAttributes(['overrideProperty' => 'Setting value']);
		$this->assertEquals('Setting value', $this->testObject->overrideProperty);

		$this->testObject->overrideAttributes(['overrideProperty' => null]);
		$this->assertNull($this->testObject->overrideProperty);

		$this->testObject->overrideAttributes(['overrideProperty' => '']);
		$this->assertEmpty($this->testObject->overrideProperty);
	}

	public function testStringOverride() {
		$this->assertNull($this->testObject->overrideProperty);
		$this->testObject->overrideAttributes(['overrideProperty=Unquoted value']);
		$this->assertEquals('Unquoted value', $this->testObject->overrideProperty);

		$this->testObject->overrideAttributes(['overrideProperty=""']);
		$this->assertEmpty($this->testObject->overrideProperty);

		$this->testObject->overrideAttributes(['overrideProperty="Double quoted value"']);
		$this->assertEquals('Double quoted value', $this->testObject->overrideProperty);

		$this->testObject->overrideAttributes(["overrideProperty=''"]);
		$this->assertEmpty($this->testObject->overrideProperty);

		$this->testObject->overrideAttributes(["overrideProperty='Single quoted value'"]);
		$this->assertEquals('Single quoted value', $this->testObject->overrideProperty);
	}

	public function testStringOverrideValidity() {
		$this->expectException(CogException::class);
		$this->testObject->overrideAttributes(['overrideProperty="value']);
	}

	public function testStringOverrideValidity2() {
		$this->expectException(CogException::class);
		$this->testObject->overrideAttributes(["overrideProperty='value"]);
	}

	public function testStringOverrideValidity3() {
		$this->expectException(CogException::class);
		$this->testObject->overrideAttributes(["overridePropertyvalue"]);
	}

	public function testMagicProperty() {
		$this->assertNull($this->testObject->MagicProperty);

		$this->assertTrue(isset($this->testObject->MagicProperty));
		$this->assertFalse(isset($this->testObject->MissingProperty));

		$this->testObject->MagicProperty = 'Value';
		$this->assertEquals('Value', $this->testObject->MagicProperty);
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
