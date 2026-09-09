<?php

namespace Cog\Test;

use Cog\Util\Utils;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
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
}
