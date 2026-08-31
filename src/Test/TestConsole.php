<?php declare(strict_types=1);

namespace Cog\Test;

use Cog\Console\CommandApplication;
use Cog\Console\RunnerDirTrait;
use Cog\Console\StopwatchTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Unit tests for Cog\Console: the directory-scanning command application and the
 * two traits commands opt into.
 *
 * The scan is the part worth pinning. Command discovery is non-recursive over a
 * directory whose filenames must match the class names (see CLAUDE.md), and every
 * skip branch - abstract class, non-command class, subdirectory, non-php file -
 * fails silently by design. Nothing tells you a command stopped being registered
 * except the command no longer existing, so each branch is asserted here against
 * the fixtures in fixtures/Command.
 */
class TestConsole extends TestCase {

	private const string FIXTURE_DIR = __DIR__ . '/fixtures/Command';
	private const string FIXTURE_NAMESPACE = 'Cog\Test\fixtures\Command';

	/**
	 * The scan registers the one concrete command in the fixture directory and
	 * skips everything beside it: the abstract command, the class that is not a
	 * command at all, the .txt file, and the command one directory down.
	 */
	public function testAddCommandDirRegistersOnlyConcreteCommands() {
		$application = new CommandApplication();
		$application->addCommandDir(self::FIXTURE_DIR, self::FIXTURE_NAMESPACE);

		$this->assertTrue($application->has('fixture:concrete'));
		$this->assertFalse($application->has('fixture:nested'));
	}

	/** Non-recursive: a command in a subdirectory is not discovered. */
	public function testAddCommandDirDoesNotRecurse() {
		$application = new CommandApplication();
		$application->addCommandDir(self::FIXTURE_DIR, self::FIXTURE_NAMESPACE);

		$names = array_keys($application->all());

		$this->assertContains('fixture:concrete', $names);
		$this->assertNotContains('fixture:nested', $names);
	}

	/**
	 * A directory that does not exist is not an error - an application may list a
	 * command directory it does not ship.
	 */
	public function testAddCommandDirIgnoresMissingDirectory() {
		$application = new CommandApplication();
		$before = count($application->all());

		$application->addCommandDir(self::FIXTURE_DIR . '/does-not-exist', self::FIXTURE_NAMESPACE);

		$this->assertCount($before, $application->all());
	}

	/** Both entry points are fluent, so an application can chain them. */
	public function testAddCommandDirIsFluent() {
		$application = new CommandApplication();

		$this->assertSame($application, $application->addCommandDir(self::FIXTURE_DIR, self::FIXTURE_NAMESPACE));
		$this->assertSame($application, $application->addCommandDirs([]));
	}

	/** The plural form applies the singular one to each namespace => directory pair. */
	public function testAddCommandDirsScansEveryDirectory() {
		$application = new CommandApplication();
		$application->addCommandDirs([
			self::FIXTURE_NAMESPACE => self::FIXTURE_DIR,
			'Cog\Command' => dirname(__DIR__) . '/Command',
		]);

		$this->assertTrue($application->has('fixture:concrete'));
		$this->assertTrue($application->has('crypt:md5'));
	}

	/**
	 * The framework's own command directory is what ./cog scans, so the shipped
	 * commands are asserted here rather than trusting the fixture alone.
	 */
	public function testShippedCommandsAreDiscoverable() {
		$application = new CommandApplication();
		$application->addCommandDir(dirname(__DIR__) . '/Command', 'Cog\Command');

		foreach (['crypt:md5', 'crypt:sha1', 'dump:path', 'db:clean', 'db:codegen', 'util:whitechars', 'lint:yaml', 'shell'] as $name) {
			$this->assertTrue($application->has($name), sprintf('%s was not discovered', $name));
		}
	}

	//
	// RunnerDirTrait
	//

	/**
	 * Relative codegen paths resolve against the directory holding ./cog, which the
	 * trait derives from $_SERVER['SCRIPT_FILENAME'].
	 */
	public function testRunnerDirIsTheDirectoryOfTheRunningScript() {
		$original = $_SERVER['SCRIPT_FILENAME'] ?? null;
		$host = new ConsoleTraitHost();

		try {
			$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/bootstrap.php';

			$this->assertSame(__DIR__, $host->callGetRunnerDir());
		} finally {
			if ($original === null) {
				unset($_SERVER['SCRIPT_FILENAME']);
			} else {
				$_SERVER['SCRIPT_FILENAME'] = $original;
			}
		}
	}

	/** The path is realpath'd, so a runner reached through a relative path still resolves. */
	public function testRunnerDirResolvesRelativePaths() {
		$original = $_SERVER['SCRIPT_FILENAME'] ?? null;
		$host = new ConsoleTraitHost();

		try {
			$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/fixtures/../bootstrap.php';

			$this->assertSame(__DIR__, $host->callGetRunnerDir());
		} finally {
			if ($original === null) {
				unset($_SERVER['SCRIPT_FILENAME']);
			} else {
				$_SERVER['SCRIPT_FILENAME'] = $original;
			}
		}
	}

	//
	// StopwatchTrait
	//

	public function testStopwatchReportsTimeAndMemory() {
		$host = new ConsoleTraitHost();
		$output = new BufferedOutput();

		$host->callStartStopwatch();
		$host->callGetStopwatchStats($output);

		$reported = $output->fetch();

		$this->assertStringContainsString('Command time', $reported);
		$this->assertStringContainsString('Peak memory usage', $reported);
		$this->assertStringContainsString('maximum allocation', $reported);
	}

	/** startStopwatch() has to run first - the stats read the event it started. */
	public function testStopwatchStatsWithoutStartFails() {
		$host = new ConsoleTraitHost();

		$this->expectException(\Throwable::class);

		$host->callGetStopwatchStats(new BufferedOutput());
	}

	/** Each start is a fresh Stopwatch, so a command can be run twice in one process. */
	public function testStopwatchCanBeRestarted() {
		$host = new ConsoleTraitHost();
		$output = new BufferedOutput();

		$host->callStartStopwatch();
		$first = $host->stopwatch;
		$host->callGetStopwatchStats($output);

		$host->callStartStopwatch();
		$host->callGetStopwatchStats($output);

		$this->assertNotSame($first, $host->stopwatch);
		$this->assertSame(2, substr_count($output->fetch(), 'Command time'));
	}
}

/**
 * A host for the two console traits, exposing their protected methods. Declared
 * here rather than in its own file because it exists only for this test -
 * src/Test/ files are listed one by one in phpunit.xml.dist, and this is not a
 * test class.
 */
class ConsoleTraitHost {

	use RunnerDirTrait;
	use StopwatchTrait;

	public function callGetRunnerDir(): string {
		return $this->getRunnerDir();
	}

	public function callStartStopwatch(): void {
		$this->startStopwatch();
	}

	public function callGetStopwatchStats(\Symfony\Component\Console\Output\OutputInterface $output): void {
		$this->getStopwatchStats($output);
	}
}
