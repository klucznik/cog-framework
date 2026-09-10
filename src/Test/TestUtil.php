<?php

namespace Cog\Test;

use Cog\Util\Utils;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TestUtil extends TestCase {

	/** @var array|null the HTTP_HOST in place before the isHost tests doctored it */
	private ?array $server = null;

	public function setUp(): void {
		$this->server = $_SERVER;
	}

	public function tearDown(): void {
		$_SERVER = $this->server;
	}

	public function testExtendArray() {
		$toExtend = ['sample', 'array'];

		$this->assertSame($toExtend, Utils::extendArray($toExtend, null));
		$this->assertSame(['sample', 'array', false], Utils::extendArray($toExtend, false));
		$this->assertSame(array_merge($toExtend, $toExtend), Utils::extendArray($toExtend, $toExtend));
		$this->assertSame(array_merge($toExtend, ['string']), Utils::extendArray($toExtend, ['string']));
		$this->assertSame(array_merge($toExtend, ['string']), Utils::extendArray($toExtend, 'string'));
	}

	public function testPeriod() {
		$this->assertSame(2419200, Utils::getTimePeriodInSeconds('28 days'));
		$this->assertSame(2419201, Utils::getTimePeriodInSeconds('28 days 1 second'));
		$this->assertSame(0, Utils::getTimePeriodInSeconds(''));
		$this->assertSame(3600, Utils::getTimePeriodInSeconds('1 hour'));
		$this->assertSame(0, Utils::getTimePeriodInSeconds(543));
	}

	public function testIsHostWithoutRequest() {
		unset($_SERVER['HTTP_HOST']);
		$this->assertFalse(Utils::isHost('example.com'));

		$_SERVER['HTTP_HOST'] = '';
		$this->assertFalse(Utils::isHost('example.com'));
	}

	public function testIsHost() {
		$_SERVER['HTTP_HOST'] = 'example.com';

		$this->assertTrue(Utils::isHost('example.com'));
		$this->assertFalse(Utils::isHost('other.com'));
		$this->assertFalse(Utils::isHost(''));
	}

	public function testIsHostIgnoresPortAndCase() {
		$_SERVER['HTTP_HOST'] = 'Example.COM:4000';
		$this->assertTrue(Utils::isHost('example.com'));

		$_SERVER['HTTP_HOST'] = 'example.com:80';
		$this->assertTrue(Utils::isHost('example.com'));
	}

	public function testIsHostMatchesSubdomains() {
		$_SERVER['HTTP_HOST'] = 'www.example.com';
		$this->assertTrue(Utils::isHost('example.com'));

		$_SERVER['HTTP_HOST'] = 'deep.nested.example.com';
		$this->assertTrue(Utils::isHost('example.com'));
		$this->assertTrue(Utils::isHost('nested.example.com'));
	}

	/** The suffix match is on a dot boundary, so a lookalike domain must not match. */
	public function testIsHostRejectsLookalikeDomains() {
		$_SERVER['HTTP_HOST'] = 'evilexample.com';
		$this->assertFalse(Utils::isHost('example.com'));

		$_SERVER['HTTP_HOST'] = 'example.com.evil.net';
		$this->assertFalse(Utils::isHost('example.com'));
	}

	/**
	 * Generated getIterator() hands datetime columns to json_encode through this, so
	 * the format is fixed: ISO-8601, normalised to UTC, microseconds, literal Z.
	 */
	public function testDateTimeToJson() {
		$this->assertNull(Utils::dateTimeToJson(null));
		$this->assertSame('2020-07-02T01:04:05.000000Z', Utils::dateTimeToJson(new DateTimeImmutable('2020-07-02 03:04:05', new DateTimeZone('+02:00'))));
		$this->assertSame('2020-01-02T03:04:05.123456Z', Utils::dateTimeToJson(new DateTimeImmutable('2020-01-02 03:04:05.123456', new DateTimeZone('UTC'))));
	}

	/** A mutable DateTime is formatted without being shifted to UTC in place. */
	public function testDateTimeToJsonLeavesMutableInputAlone() {
		$mutable = new DateTime('2020-07-02 03:04:05', new DateTimeZone('+02:00'));

		$this->assertSame('2020-07-02T01:04:05.000000Z', Utils::dateTimeToJson($mutable));
		$this->assertSame('+02:00', $mutable->getTimezone()->getName());
	}

	/**
	 * Generated classes decode JSON columns through this. Objects stay objects so {} and []
	 * survive being encoded again, and big integers keep their digits.
	 */
	public function testDecodeJsonColumn() {
		$this->assertNull(Utils::decodeJsonColumn(null));
		$this->assertSame('{"a":{},"b":[]}', json_encode(Utils::decodeJsonColumn('{"a":{},"b":[]}')));
		$this->assertInstanceOf(\stdClass::class, Utils::decodeJsonColumn('{"theme":"dark"}'));
		$this->assertSame('dark', Utils::decodeJsonColumn('{"theme":"dark"}')->theme);
		$this->assertSame('123456789012345678901234567890', Utils::decodeJsonColumn('123456789012345678901234567890'), 'an integer too large for PHP keeps its digits');
		$this->assertNull(Utils::decodeJsonColumn('null'), 'the JSON literal null decodes to null as well');
	}

	public function testDecodeJsonColumnRejectsInvalidJson() {
		$this->expectException(JsonException::class);

		Utils::decodeJsonColumn('{not json');
	}

	/** null is SQL NULL rather than the JSON literal, slashes and unicode are stored as-is, and floats keep their fraction. */
	public function testEncodeJsonColumn() {
		$this->assertNull(Utils::encodeJsonColumn(null));
		$this->assertSame(
			"{\"url\":\"https://example.com/\u{17C}\",\"ratio\":1.0,\"list\":[1,2]}",
			Utils::encodeJsonColumn(['url' => "https://example.com/\u{17C}", 'ratio' => 1.0, 'list' => [1, 2]])
		);
		$this->assertSame('{}', Utils::encodeJsonColumn(new \stdClass()));
	}

	public function testEncodeJsonColumnRejectsUnencodableValues() {
		$this->expectException(JsonException::class);

		Utils::encodeJsonColumn(NAN);
	}

	/**
	 * Only null, an empty array or Countable, and a string or Stringable that is empty once
	 * trimmed count as no value.
	 * Everything else is a value, including the ones PHP treats as falsy - 0, 0.0, '0' and
	 * false - which is the point of the helper over a plain truthiness check.
	 */
	public static function hasValueProvider(): array {
		return [
			'null' => [null, false],
			'empty string' => ['', false],
			'whitespace only' => [" \t\n\r\0\x0B", false],
			'string' => ['a', true],
			'padded string' => ['  a  ', true],
			'string zero' => ['0', true],
			'empty array' => [[], false],
			'array' => [[1], true],
			'array holding only null' => [[null], true],
			'int zero' => [0, true],
			'int' => [7, true],
			'negative int' => [-1, true],
			'float zero' => [0.0, true],
			'false' => [false, true],
			'true' => [true, true],
			'object' => [new \stdClass(), true],
			'empty countable' => [new \ArrayObject(), false],
			'countable' => [new \ArrayObject([1]), true],
			'empty stringable' => [new class implements \Stringable { public function __toString(): string { return ''; } }, false],
			'whitespace stringable' => [new class implements \Stringable { public function __toString(): string { return "  \t"; } }, false],
			'stringable' => [new class implements \Stringable { public function __toString(): string { return 'a'; } }, true],
			'empty countable that is also stringable' => [new class implements \Countable, \Stringable {
				public function count(): int { return 0; }
				public function __toString(): string { return 'not empty'; }
			}, false],
		];
	}

	#[DataProvider('hasValueProvider')]
	public function testHasValue(mixed $value, bool $expected) {
		$this->assertSame($expected, Utils::hasValue($value));
	}
}
