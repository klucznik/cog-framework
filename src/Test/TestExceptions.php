<?php

namespace Cog\Test;

use Cog\Database\Adapters\MySqliException;
use Cog\Exceptions\CogException;
use Cog\Exceptions\InvalidCastException;
use Cog\Exceptions\RedirectException;
use Cog\Exceptions\UndefinedPropertyException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TestExceptions extends TestCase {

	public function testCogExceptionIsAPlainRuntimeException() {
		$previous = new RuntimeException('cause');
		$exception = new CogException('Something went wrong', 42, $previous);

		$this->assertInstanceOf(RuntimeException::class, $exception);
		$this->assertEquals('Something went wrong', $exception->getMessage());
		$this->assertEquals(42, $exception->getCode());
		$this->assertSame($previous, $exception->getPrevious());
	}

	public function testCogExceptionReportsTheThrowSite() {
		$line = __LINE__ + 1;
		$exception = new CogException('message');

		$this->assertEquals(__FILE__, $exception->getFile());
		$this->assertEquals($line, $exception->getLine());
	}

	public function testUndefinedPropertyException() {
		$exception = new UndefinedPropertyException('GET', 'Cog\Test\MockedBaseObject', 'missingProperty');

		$this->assertInstanceOf(CogException::class, $exception);
		$this->assertEquals(
			'Undefined GET property or variable in "Cog\Test\MockedBaseObject" class: missingProperty',
			$exception->getMessage()
		);
	}

	public function testInvalidCastException() {
		$exception = new InvalidCastException('Unable to cast');

		$this->assertInstanceOf(CogException::class, $exception);
		$this->assertEquals('Unable to cast', $exception->getMessage());
	}

	public function testDatabaseExceptionProperties() {
		$exception = new MySqliException('boom', 1064, 'SELECT 1');

		$this->assertInstanceOf(CogException::class, $exception);
		$this->assertSame(1064, $exception->errorNumber);
		$this->assertSame(1064, $exception->getCode());
		$this->assertSame('SELECT 1', $exception->query);
	}

	public function testDatabaseExceptionUndefinedProperty() {
		$exception = new MySqliException('boom', 1064, 'SELECT 1');

		$this->expectException(UndefinedPropertyException::class);
		$exception->missingProperty;
	}

	public function testRedirectException() {
		$exception = new RedirectException('/somewhere');

		$this->assertEquals('/somewhere', $exception->location);
		$this->assertEquals(302, $exception->status);
		$this->assertEquals('Redirect exception', $exception->getMessage());

		$this->assertEquals(301, (new RedirectException('/permanent', 301))->status);
	}
}
