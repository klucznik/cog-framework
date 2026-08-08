<?php

namespace Cog\Test;

use Cog\Path;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * Path::initialize() has already run by the time the suite boots (it is called
 * at the bottom of Path.php), so the CLI side is asserted against the live
 * values. The web side is driven directly through MockedPath, which exposes the
 * protected initialiser and lets us feed it a doctored $_SERVER.
 */
class TestPath extends TestCase {

	private array $server;
	private string $webRoot;
	private string $appRoot;
	private ?string $scriptFilename;
	private ?string $scriptName;

	public function setUp(): void {
		$this->server = $_SERVER;
		$this->webRoot = Path::$webRoot;
		$this->appRoot = Path::$appRoot;
		$this->scriptFilename = Path::$scriptFilename;
		$this->scriptName = Path::$scriptName;
	}

	public function tearDown(): void {
		$_SERVER = $this->server;
		Path::$webRoot = $this->webRoot;
		Path::$appRoot = $this->appRoot;
		Path::$scriptFilename = $this->scriptFilename;
		Path::$scriptName = $this->scriptName;
	}

	public function testCliInitialization() {
		$this->assertTrue(Path::isCLI());
		$this->assertDirectoryExists(Path::$webRoot);
		$this->assertEquals('public', basename(Path::$webRoot));
		$this->assertEquals(dirname(Path::$webRoot), Path::$appRoot);
	}

	public function testDump() {
		$dump = Path::dump();

		$this->assertEquals(
			['appRoot', 'webRoot', 'scriptFilename', 'scriptName', 'cli', 'PHP include path'],
			array_keys($dump)
		);
		$this->assertEquals(Path::$appRoot, $dump['appRoot']);
		$this->assertEquals(Path::$webRoot, $dump['webRoot']);
		$this->assertTrue($dump['cli']);
	}

	public function testWebInitialization() {
		$_SERVER['SCRIPT_FILENAME'] = '/var/www/htdocs/public/index.php';
		$_SERVER['SCRIPT_NAME'] = '/index.php';

		MockedPath::initializeWebRoot();

		$this->assertEquals('/var/www/htdocs/public', Path::$webRoot);
		$this->assertEquals('/var/www/htdocs/public/index.php', Path::$scriptFilename);
		$this->assertEquals('/index.php', Path::$scriptName);
	}

	/** SCRIPT_NAME wins, but PHP_SELF is used when it is missing. */
	public function testWebInitializationFallsBackToPhpSelf() {
		$_SERVER['SCRIPT_FILENAME'] = '/var/www/htdocs/public/folder/script.php';
		unset($_SERVER['SCRIPT_NAME']);
		$_SERVER['PHP_SELF'] = '/folder/script.php';

		MockedPath::initializeWebRoot();

		$this->assertEquals('/var/www/htdocs/public', Path::$webRoot);
	}

	public function testWebInitializationWithWindowsPaths() {
		$_SERVER['SCRIPT_FILENAME'] = 'c:\inetpub\wwwroot\folder\script.php';
		$_SERVER['SCRIPT_NAME'] = '/folder/script.php';

		MockedPath::initializeWebRoot();

		$this->assertEquals('c:\inetpub\wwwroot', Path::$webRoot);
	}

	public function testWebInitializationTrimsTrailingSeparator() {
		$_SERVER['SCRIPT_FILENAME'] = '/var/www/htdocs/public//index.php';
		$_SERVER['SCRIPT_NAME'] = '/index.php';

		MockedPath::initializeWebRoot();

		$this->assertEquals('/var/www/htdocs/public', Path::$webRoot);
	}

	public function testWebInitializationWithoutScriptName() {
		$_SERVER['SCRIPT_FILENAME'] = '/var/www/htdocs/public/index.php';
		unset($_SERVER['SCRIPT_NAME'], $_SERVER['PHP_SELF']);

		$this->expectException(UnexpectedValueException::class);
		MockedPath::initializeWebRoot();
	}

	public function testWebInitializationWithoutScriptFilename() {
		unset($_SERVER['SCRIPT_FILENAME']);
		$_SERVER['SCRIPT_NAME'] = '/index.php';

		$this->expectException(UnexpectedValueException::class);
		MockedPath::initializeWebRoot();
	}

	/** Neither a leading /, a leading . nor a drive letter - the file system type is unknowable. */
	public function testWebInitializationWithUnrecognisedFileSystem() {
		$_SERVER['SCRIPT_FILENAME'] = 'var/www/htdocs/public/index.php';
		$_SERVER['SCRIPT_NAME'] = '/index.php';

		$this->expectException(UnexpectedValueException::class);
		MockedPath::initializeWebRoot();
	}

	public function testWebInitializationWithIdenticalFilenameAndName() {
		$_SERVER['SCRIPT_FILENAME'] = '/index.php';
		$_SERVER['SCRIPT_NAME'] = '/index.php';

		$this->expectException(UnexpectedValueException::class);
		MockedPath::initializeWebRoot();
	}

	public function testWebInitializationWithMismatchedFilenameAndName() {
		$_SERVER['SCRIPT_FILENAME'] = '/var/www/htdocs/public/index.php';
		$_SERVER['SCRIPT_NAME'] = '/elsewhere/other.php';

		$this->expectException(UnexpectedValueException::class);
		MockedPath::initializeWebRoot();
	}
}
