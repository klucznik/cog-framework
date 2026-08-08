<?php

namespace Cog\Test;

use Cog\Exceptions\CogException;
use Cog\Exceptions\InvalidCastException;
use Cog\Exceptions\RedirectException;
use Cog\Exceptions\UndefinedPropertyException;
use PHPUnit\Framework\TestCase;

/**
 * Covers the exception hierarchy, in particular CogException's offset
 * mechanism: the offset picks the frame in the backtrace that gets reported as
 * the file and line responsible for the exception, so that the CALLER of a
 * method is blamed rather than the throw statement inside it.
 */
class TestExceptions extends TestCase {

	public function testMessageAndDefaultOffset() {
		$exception = new CogException('Something went wrong');

		$this->assertEquals('Something went wrong', $exception->getMessage());
		$this->assertEquals(1, $exception->offset);
	}

	public function testExplicitOffset() {
		$this->assertEquals(0, (new CogException('message', 0))->offset);
		$this->assertEquals(3, (new CogException('message', 3))->offset);
	}

	/** Offset 0 is the frame that constructed the exception, so this method's own line. */
	public function testOffsetZeroPointsAtTheThrowSite() {
		$line = __LINE__ + 1;
		$exception = new CogException('message', 0);

		$this->assertEquals(__FILE__, $exception->getFile());
		$this->assertEquals($line, $exception->getLine());
	}

	/** Offset 1 is the caller, so a throw inside a helper is blamed on this method. */
	public function testOffsetOnePointsAtTheCaller() {
		$line = __LINE__ + 1;
		$exception = $this->makeException(1);

		$this->assertEquals(__FILE__, $exception->getFile());
		$this->assertEquals($line, $exception->getLine());
	}

	public function testIncrementAndDecrementOffset() {
		$exception = $this->makeException(0);

		$this->assertEquals(0, $exception->offset);
		$file = $exception->getFile();
		$line = $exception->getLine();

		$exception->incrementOffset();
		$this->assertEquals(1, $exception->offset);
		$this->assertNotEquals($line, $exception->getLine());

		$exception->decrementOffset();
		$this->assertEquals(0, $exception->offset);
		$this->assertEquals($file, $exception->getFile());
		$this->assertEquals($line, $exception->getLine());
	}

	/** An offset past the end of the backtrace leaves file and line cleared rather than erroring. */
	public function testOffsetBeyondTheBacktrace() {
		$exception = new CogException('message', 0);

		for ($i = 0; $i < 200; $i++) {
			$exception->incrementOffset();
		}

		$this->assertEquals('', $exception->getFile());
		$this->assertEquals(0, $exception->getLine());
	}

	public function testMagicGet() {
		$exception = new CogException('message');

		$this->assertIsInt($exception->offset);
		$this->assertIsArray($exception->traceArray);
		$this->assertIsString($exception->backTrace);
	}

	public function testMagicGetUndefinedProperty() {
		$exception = new CogException('message');

		$this->expectException(UndefinedPropertyException::class);
		$exception->missingProperty;
	}

	public function testMagicSetAlwaysThrows() {
		$exception = new CogException('message');

		$this->expectException(UndefinedPropertyException::class);
		$exception->offset = 5;
	}

	public function testMagicIsset() {
		$exception = new CogException('message');

		$this->assertTrue(isset($exception->offset));
		$this->assertTrue(isset($exception->traceArray));
		$this->assertTrue(isset($exception->backTrace));
		$this->assertFalse(isset($exception->missingProperty));
	}

	public function testUndefinedPropertyException() {
		$exception = new UndefinedPropertyException('GET', 'Cog\Test\MockedBaseObject', 'missingProperty');

		$this->assertInstanceOf(CogException::class, $exception);
		$this->assertEquals(
			'Undefined GET property or variable in "Cog\Test\MockedBaseObject" class: missingProperty',
			$exception->getMessage()
		);
		$this->assertEquals(2, $exception->offset);
	}

	public function testInvalidCastException() {
		$exception = new InvalidCastException('Unable to cast');

		$this->assertInstanceOf(CogException::class, $exception);
		$this->assertEquals('Unable to cast', $exception->getMessage());
		$this->assertEquals(2, $exception->offset);
		$this->assertEquals(4, (new InvalidCastException('Unable to cast', 4))->offset);
	}

	public function testRedirectException() {
		$exception = new RedirectException('/somewhere');

		$this->assertEquals('/somewhere', $exception->location);
		$this->assertEquals(302, $exception->status);
		$this->assertEquals('Redirect exception', $exception->getMessage());

		$this->assertEquals(301, (new RedirectException('/permanent', 301))->status);
	}

	/** Throws from one frame down, so offset 1 resolves to this helper's caller. */
	private function makeException(int $offset): CogException {
		return new CogException('message', $offset);
	}
}
