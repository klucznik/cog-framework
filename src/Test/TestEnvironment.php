<?php declare(strict_types=1);

namespace Cog\Test;

use Cog\Enum\Environment;
use PHPUnit\Framework\TestCase;

/**
 * The Environment enum is a contract with anything that persists or reads an
 * environment name: BaseApplication::initialize() takes a case, and the backing
 * values are what an application's own config writes down. Renaming a case or
 * changing a backing value silently breaks callers that pass the string form, so
 * both halves are pinned here.
 */
class TestEnvironment extends TestCase {

	public function testBackingValues() {
		$this->assertSame('development', Environment::DEV->value);
		$this->assertSame('testing', Environment::TEST->value);
		$this->assertSame('production', Environment::PROD->value);
	}

	public function testCasesAreExactlyThese() {
		$this->assertSame(
			['DEV', 'TEST', 'PROD'],
			array_map(static fn (Environment $case): string => $case->name, Environment::cases())
		);
	}

	public function testFromBackingValue() {
		$this->assertSame(Environment::DEV, Environment::from('development'));
		$this->assertSame(Environment::TEST, Environment::from('testing'));
		$this->assertSame(Environment::PROD, Environment::from('production'));
	}

	public function testFromRejectsUnknownValue() {
		$this->expectException(\ValueError::class);

		Environment::from('staging');
	}

	/** tryFrom is what config parsing should use - an unknown name reads as null. */
	public function testTryFromUnknownValueIsNull() {
		$this->assertNull(Environment::tryFrom('staging'));
		$this->assertNull(Environment::tryFrom(''));

		// The case name is not the backing value
		$this->assertNull(Environment::tryFrom('DEV'));
	}
}
