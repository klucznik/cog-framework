<?php

namespace Cog\Test;

use Cog\Exceptions\CogException;
use Cog\Util\Template;
use PHPUnit\Framework\TestCase;

class TestTemplate extends TestCase {

	private string $fixtureDirectory;

	public function setUp(): void {
		$this->fixtureDirectory = __DIR__ . '/fixtures';
	}

	public function testRenderWithTokens() {
		$rendered = Template::render($this->fixtureDirectory . '/template.tpl.php', [
			'greeting' => 'Hello',
			'name' => 'Kahless'
		]);

		$this->assertEquals("Hello, Kahless!\n", $rendered);
	}

	public function testRenderWithoutTokens() {
		$rendered = Template::render($this->fixtureDirectory . '/static.tpl.php');
		$this->assertEquals("Nothing to substitute here.\n", $rendered);
	}

	public function testRenderDoesNotEmitOutput() {
		$this->expectOutputString('');
		Template::render($this->fixtureDirectory . '/static.tpl.php');
	}

	public function testMissingTemplate() {
		$this->expectException(CogException::class);
		Template::render($this->fixtureDirectory . '/does-not-exist.tpl.php');
	}

	/**
	 * render() clears the output buffer to capture the template, so anything
	 * already buffered by the caller has to be put back afterwards.
	 */
	public function testAlreadyBufferedOutputIsPreserved() {
		$level = ob_get_level();

		ob_start();
		echo 'buffered before render';

		$rendered = Template::render($this->fixtureDirectory . '/static.tpl.php');

		$buffered = ob_get_clean();

		$this->assertEquals($level, ob_get_level());
		$this->assertEquals("Nothing to substitute here.\n", $rendered);
		$this->assertEquals('buffered before render', $buffered);
	}
}
