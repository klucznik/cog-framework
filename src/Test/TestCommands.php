<?php declare(strict_types=1);

namespace Cog\Test;

use Cog\Command\CodegenCleanCommand;
use Cog\Command\CodegenCommand;
use Cog\Command\ContainerDebugCommand;
use Cog\Command\DumpConfigCommand;
use Cog\Command\Md5Command;
use Cog\Command\MigrateCommand;
use Cog\Command\PsyshCommand;
use Cog\Command\RollbackCommand;
use Cog\Command\SeedCommand;
use Cog\Command\Sha1Command;
use Cog\Command\StatusCommand;
use Cog\Command\WhiteCharsCommand;
use Cog\Console\CommandApplication;
use Cog\BaseApplication;
use Cog\BaseConfig;
use Cog\Util\FileSystem;
use League\Container\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver;
use Symfony\Component\HttpKernel\HttpKernel;

/**
 * Tests for the shipped console commands.
 *
 * Two things are worth pinning here. The first is each command's identity - name
 * and aliases - because discovery is by directory scan and a renamed command
 * fails by simply not existing, with nothing to catch it. The second is the
 * behaviour that is actually ours: the commands wrapping Phinx and PsySH
 * contribute only configuration, so that is asserted rather than the
 * third-party work underneath.
 *
 * CodegenCommand's success path is deliberately not exercised: it drives the
 * generator, which TestCodegen already covers end to end, and running it here
 * would clobber the static state CodegenFixture holds. What is covered is the
 * argument resolution in front of it, which is where its bugs would live.
 */
class TestCommands extends TestCase {

	/** @var string a scratch directory created per test and removed in tearDown */
	private string $workDirectory;

	/** @var string|null the SCRIPT_FILENAME to restore, when a test doctored it */
	private ?string $originalScriptFilename = null;

	private bool $scriptFilenameWasSet = false;

	/** @var string|false the working directory to restore, when a test changed it */
	private string|false $originalWorkingDirectory = false;

	public function setUp(): void {
		$this->workDirectory = sys_get_temp_dir() . '/cog-command-test-' . bin2hex(random_bytes(8));
		mkdir($this->workDirectory);
	}

	public function tearDown(): void {
		if ($this->originalWorkingDirectory !== false) {
			chdir($this->originalWorkingDirectory);
			$this->originalWorkingDirectory = false;
		}

		if ($this->scriptFilenameWasSet) {
			if ($this->originalScriptFilename === null) {
				unset($_SERVER['SCRIPT_FILENAME']);
			} else {
				$_SERVER['SCRIPT_FILENAME'] = $this->originalScriptFilename;
			}
			$this->scriptFilenameWasSet = false;
		}

		if (is_dir($this->workDirectory)) {
			FileSystem::removeDirectory($this->workDirectory);
		}
	}

	/**
	 * Points the RunnerDirTrait at the scratch directory. The trait realpath's
	 * SCRIPT_FILENAME and takes its dirname, so the file has to exist.
	 */
	private function useScratchAsRunnerDir(): void {
		$this->originalScriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? null;
		$this->scriptFilenameWasSet = true;

		$runner = $this->workDirectory . '/cog';
		file_put_contents($runner, "#!/usr/bin/env php\n");
		$_SERVER['SCRIPT_FILENAME'] = $runner;
	}

	private function changeToScratchDirectory(): void {
		$this->originalWorkingDirectory = getcwd();
		chdir($this->workDirectory);
	}

	/** A tester for a command that needs an application to resolve sibling commands. */
	private function tester(Command $command, Command ...$siblings): CommandTester {
		$application = new CommandApplication();
		$application->setAutoExit(false);
		$application->addCommand($command);
		foreach ($siblings as $sibling) {
			$application->addCommand($sibling);
		}

		return new CommandTester($command);
	}

	//
	// crypt:md5 and crypt:sha1
	//

	public function testMd5CommandHashesItsArgument() {
		$tester = $this->tester(new Md5Command());

		$this->assertSame(Command::SUCCESS, $tester->execute(['inputString' => 'cog']));
		$this->assertSame(md5('cog'), trim($tester->getDisplay()));
	}

	public function testSha1CommandHashesItsArgument() {
		$tester = $this->tester(new Sha1Command());

		$this->assertSame(Command::SUCCESS, $tester->execute(['inputString' => 'cog']));
		$this->assertSame(sha1('cog'), trim($tester->getDisplay()));
	}

	public function testHashCommandsRequireTheirArgument() {
		$tester = $this->tester(new Md5Command());

		$this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);

		$tester->execute([]);
	}

	public function testHashCommandIdentities() {
		$this->assertSame('crypt:md5', (new Md5Command())->getName());
		$this->assertSame('crypt:sha1', (new Sha1Command())->getName());
	}

	//
	// db:clean
	//

	public function testCodegenCleanRemovesGeneratedFiles() {
		$this->useScratchAsRunnerDir();

		foreach (['Data', 'Type', 'Node'] as $subdirectory) {
			mkdir($this->workDirectory . '/generated/' . $subdirectory, 0777, true);
			file_put_contents($this->workDirectory . '/generated/' . $subdirectory . '/OneGen.php', '<?php');
			file_put_contents($this->workDirectory . '/generated/' . $subdirectory . '/TwoGen.php', '<?php');
		}

		$tester = $this->tester(new CodegenCleanCommand());

		$this->assertSame(Command::SUCCESS, $tester->execute([]));

		$display = $tester->getDisplay();
		$this->assertStringContainsString('Cleaning DataGen (2)', $display);
		$this->assertStringContainsString('Cleaning TypeGen (2)', $display);
		$this->assertStringContainsString('Cleaning NodeGen (2)', $display);

		$this->assertCount(0, glob($this->workDirectory . '/generated/Data/*.php'));
	}

	/** A missing generated directory is reported as a failure rather than a fatal. */
	public function testCodegenCleanFailsWhenDirectoryIsMissing() {
		$this->useScratchAsRunnerDir();

		$tester = $this->tester(new CodegenCleanCommand());

		$this->assertSame(Command::FAILURE, $tester->execute([]));
		$this->assertStringContainsString('error:', $tester->getDisplay());
	}

	public function testCodegenCleanIdentity() {
		$this->assertSame('db:clean', (new CodegenCleanCommand())->getName());
	}

	//
	// util:whitechars
	//

	/**
	 * The scan globs one directory level below the working directory, so the
	 * fixtures go in a subdirectory of the scratch dir.
	 */
	public function testWhiteCharsReportsLeadingAndTrailingWhitespace() {
		mkdir($this->workDirectory . '/src');
		file_put_contents($this->workDirectory . '/src/Leading.php', "\n\n<?php echo 1;");
		file_put_contents($this->workDirectory . '/src/Trailing.php', "<?php echo 1; ?>\n\n");
		file_put_contents($this->workDirectory . '/src/Clean.php', '<?php echo 1;');

		$this->changeToScratchDirectory();

		$tester = $this->tester(new WhiteCharsCommand());

		$this->assertSame(Command::SUCCESS, $tester->execute([]));

		$display = $tester->getDisplay();
		$this->assertStringContainsString('Leading.php', $display);
		$this->assertStringContainsString('Trailing.php', $display);
		$this->assertStringNotContainsString('Clean.php', $display);
	}

	/** Every stopwatch-using command ends with the shared timing footer. */
	public function testWhiteCharsReportsTimingFooter() {
		mkdir($this->workDirectory . '/src');
		$this->changeToScratchDirectory();

		$tester = $this->tester(new WhiteCharsCommand());
		$tester->execute([]);

		$display = $tester->getDisplay();
		$this->assertStringContainsString('Command time', $display);
		$this->assertStringContainsString('Peak memory usage', $display);
	}

	public function testWhiteCharsIdentity() {
		$this->assertSame('util:whitechars', (new WhiteCharsCommand())->getName());
	}

	//
	// db:codegen
	//

	public function testCodegenCommandIdentity() {
		$command = new CodegenCommand();

		$this->assertSame('db:codegen', $command->getName());
		$this->assertSame(['codegen'], $command->getAliases());
	}

	/** With no argument the runner directory's own codegen.xml is used. */
	public function testCodegenCommandDefaultsToCodegenXml() {
		$definition = (new CodegenCommand())->getDefinition();

		$this->assertTrue($definition->hasArgument('config'));
		$this->assertSame('codegen.xml', $definition->getArgument('config')->getDefault());
		$this->assertFalse($definition->getArgument('config')->isRequired());
	}

	/**
	 * A relative config path is resolved against the runner directory, not the
	 * working directory, so the command can be invoked from anywhere.
	 */
	public function testCodegenCommandResolvesRelativeConfigAgainstRunnerDir() {
		$this->useScratchAsRunnerDir();

		$tester = $this->tester(new CodegenCommand());

		$this->assertSame(Command::FAILURE, $tester->execute(['config' => 'config/codegen.xml']));
		$this->assertStringContainsString(
			$this->workDirectory . '/config/codegen.xml',
			$tester->getDisplay()
		);
	}

	/** An absolute path is used as-is, with no runner directory prefixed onto it. */
	public function testCodegenCommandUsesAbsoluteConfigPathAsGiven() {
		$this->useScratchAsRunnerDir();

		$tester = $this->tester(new CodegenCommand());

		$this->assertSame(Command::FAILURE, $tester->execute(['config' => '/nowhere/codegen.xml']));

		$display = $tester->getDisplay();
		$this->assertStringContainsString('/nowhere/codegen.xml', $display);
		$this->assertStringNotContainsString($this->workDirectory . '/nowhere', $display);
	}

	public function testCodegenCommandReportsMissingConfig() {
		$this->useScratchAsRunnerDir();

		$tester = $this->tester(new CodegenCommand());

		$this->assertSame(Command::FAILURE, $tester->execute([]));
		$this->assertStringContainsString('config file not found', $tester->getDisplay());
	}

	//
	// The Phinx wrappers
	//
	// Each runs a Phinx command as a nested application under a Cog name and
	// alias, and the ones that change the schema chain db:codegen afterwards.
	// The naming is what discovery depends on; the chaining is what is ours.
	//

	public static function phinxCommandProvider(): array {
		return [
			'migrate' => [MigrateCommand::class, 'db:migrate', 'migrate'],
			'rollback' => [RollbackCommand::class, 'db:rollback', 'rollback'],
			'seed' => [SeedCommand::class, 'db:seed', 'seed'],
			'status' => [StatusCommand::class, 'db:status', 'status'],
		];
	}

	#[DataProvider('phinxCommandProvider')]
	public function testPhinxWrapperIdentities(string $class, string $name, string $alias) {
		$command = new $class();

		$this->assertSame($name, $command->getName());
		$this->assertSame([$alias], $command->getAliases());
	}

	/**
	 * The wrappers copy the Phinx command's definition, so a caller can still pass
	 * the environment through, and add --configuration, which Phinx declares on its
	 * application rather than on the command.
	 */
	public static function phinxCommandClassProvider(): array {
		return [
			'migrate' => [MigrateCommand::class],
			'rollback' => [RollbackCommand::class],
			'seed' => [SeedCommand::class],
			'status' => [StatusCommand::class],
		];
	}

	#[DataProvider('phinxCommandClassProvider')]
	public function testPhinxWrapperKeepsParentDefinition(string $class) {
		$definition = (new $class())->getDefinition();

		$this->assertTrue($definition->hasOption('environment'));
		$this->assertTrue($definition->hasOption('configuration'));
	}

	/** Without a Phinx configuration in reach the nested command fails before touching anything. */
	public function testPhinxWrapperRunsPhinx() {
		$this->changeToScratchDirectory();
		$codegen = $this->codegenSpy();
		$tester = $this->tester(new StatusCommand(), $codegen);

		try {
			$tester->execute(['--environment' => 'development']);
			$this->fail('Phinx should have failed to locate its configuration');
		} catch (\InvalidArgumentException $exception) {
			$this->assertStringContainsString('phinx', $exception->getMessage());
		}

		$this->assertFalse($codegen->ran);
	}

	/**
	 * A migration that changes the schema is followed by db:codegen, which must be
	 * run with its own defaults rather than the Phinx options it does not know.
	 */
	public function testPhinxWrapperChainsCodegenAfterSuccess() {
		$codegen = $this->codegenSpy();
		$tester = $this->tester($this->migrateWithPhinxStub(Command::SUCCESS), $codegen);

		$this->assertSame(Command::SUCCESS, $tester->execute(['--environment' => 'development']));
		$this->assertTrue($codegen->ran);
		$this->assertSame('codegen.xml', $codegen->config);
	}

	/** A failed Phinx run is reported as-is and the ORM is left alone. */
	public function testPhinxWrapperSkipsCodegenAfterFailure() {
		$codegen = $this->codegenSpy();
		$tester = $this->tester($this->migrateWithPhinxStub(3), $codegen);

		$this->assertSame(3, $tester->execute([]));
		$this->assertFalse($codegen->ran);
	}

	/** Status only reports, so it never regenerates the ORM. */
	public function testStatusCommandDoesNotChainCodegen() {
		$codegen = $this->codegenSpy();
		$status = new class extends StatusCommand {
			protected function phinxCommand(): Command {
				return TestCommands::phinxStub(Command::SUCCESS);
			}
		};
		$tester = $this->tester($status, $codegen);

		$this->assertSame(Command::SUCCESS, $tester->execute([]));
		$this->assertFalse($codegen->ran);
	}

	/** A MigrateCommand whose nested Phinx command is replaced by a stub exiting with $exitCode. */
	private function migrateWithPhinxStub(int $exitCode): MigrateCommand {
		return new class($exitCode) extends MigrateCommand {
			public function __construct(private int $exitCode) {
				parent::__construct();
			}

			protected function phinxCommand(): Command {
				return TestCommands::phinxStub($this->exitCode);
			}
		};
	}

	/**
	 * Stands in for a Phinx command: declares --environment like the real ones and
	 * exits with $exitCode. Like the real one it belongs to an application, whose
	 * definition supplies the "command" argument when the stub is run.
	 */
	public static function phinxStub(int $exitCode): Command {
		$stub = new class($exitCode) extends Command {
			public function __construct(private int $exitCode) {
				parent::__construct('phinx-stub');
			}

			protected function configure(): void {
				$this->addOption('environment', 'e', InputOption::VALUE_REQUIRED, 'The target environment');
			}

			protected function execute(InputInterface $input, OutputInterface $output): int {
				return $this->exitCode;
			}
		};
		$stub->setApplication(new Application());

		return $stub;
	}

	/** A db:codegen stand-in that records whether it ran and which config it was given. */
	private function codegenSpy(): Command {
		return new class extends Command {
			public bool $ran = false;
			public ?string $config = null;

			protected function configure(): void {
				$this
					->setName('db:codegen')
					->addArgument('config', InputArgument::OPTIONAL, '', 'codegen.xml');
			}

			protected function execute(InputInterface $input, OutputInterface $output): int {
				$this->ran = true;
				$this->config = $input->getArgument('config');

				return self::SUCCESS;
			}
		};
	}

	//
	// shell
	//

	/**
	 * Only the configuration is asserted: executing this command hands control to
	 * an interactive PsySH shell.
	 */
	public function testPsyshCommandIdentity() {
		$command = new PsyshCommand();

		$this->assertSame('shell', $command->getName());
		$this->assertSame('Start PsySH', $command->getDescription());
	}

	/**
	 * PsySH is a dev dependency, so the command hides itself from discovery when
	 * the package is absent instead of failing every command listing. Under the
	 * suite's own dev install it has to be enabled.
	 */
	public function testPsyshCommandIsEnabledWhenPsyshIsInstalled() {
		$this->assertTrue(class_exists(\Psy\Shell::class));
		$this->assertTrue((new PsyshCommand())->isEnabled());
	}

	//
	// dump:config
	//

	public function testDumpConfigIdentity() {
		$this->assertSame('dump:config', (new DumpConfigCommand())->getName());
	}

	/** The config is written through the command's own output as a key/value table; json-encoded values keep booleans, null and paths readable. */
	public function testDumpConfigWritesTheConfigToTheOutput() {
		$original = MockedApplication::config();
		MockedApplication::setConfig(new BaseConfig(dirCache: '/cog-dump-config-test'));

		try {
			$tester = $this->tester(new DumpConfigCommand());
			$tester->execute([]);
		} finally {
			MockedApplication::setConfig($original);
		}

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertMatchesRegularExpression('~\|\s*Key\s*\|\s*Value\s*\|~', $tester->getDisplay());
		$this->assertMatchesRegularExpression('~\|\s*dirCache\s*\|\s*"/cog-dump-config-test"\s*\|~', $tester->getDisplay());
		$this->assertMatchesRegularExpression('~\|\s*debug\s*\|\s*true\s*\|~', $tester->getDisplay());
	}

	//
	// debug:container
	//

	public function testDebugContainerIdentity() {
		$this->assertSame('debug:container', (new ContainerDebugCommand())->getName());
	}

	/** One row per definition: id, the concrete class, whether it is shared, and its tags. */
	public function testDebugContainerListsTheDefinitions() {
		$original = BaseApplication::$container;
		$container = new Container();
		$container->addShared('kernel', HttpKernel::class);
		$container->add('resolver.default', DefaultValueResolver::class)->addTag('controller.argument_value_resolver')->addTag('extra');
		MockedApplication::setContainer($container);

		try {
			$tester = $this->tester(new ContainerDebugCommand());
			$tester->execute([]);
		} finally {
			MockedApplication::setContainer($original);
		}

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertMatchesRegularExpression('~\|\s*Service\s*\|\s*Class\s*\|\s*Shared\s*\|\s*Tags\s*\|~', $tester->getDisplay());
		$this->assertMatchesRegularExpression('~\|\s*kernel\s*\|\s*Symfony\\\\Component\\\\HttpKernel\\\\HttpKernel\s*\|\s*yes\s*\|\s*\|~', $tester->getDisplay());
		$this->assertMatchesRegularExpression('~\|\s*resolver\.default\s*\|\s*' . preg_quote(DefaultValueResolver::class, '~') . '\s*\|\s*no\s*\|\s*controller\.argument_value_resolver, extra\s*\|~', $tester->getDisplay());
	}

	/** A closure factory has no class to name, so the row says so instead of printing nothing. */
	public function testDebugContainerNamesClosureFactories() {
		$original = BaseApplication::$container;
		$container = new Container();
		$container->addShared('service_container', static fn() => $container);
		MockedApplication::setContainer($container);

		try {
			$tester = $this->tester(new ContainerDebugCommand());
			$tester->execute([]);
		} finally {
			MockedApplication::setContainer($original);
		}

		$this->assertMatchesRegularExpression('~\|\s*service_container\s*\|\s*closure\s*\|\s*yes\s*\|~', $tester->getDisplay());
	}

	/** The rows come out sorted by id, so the listing is stable regardless of registration order. */
	public function testDebugContainerSortsById() {
		$original = BaseApplication::$container;
		$container = new Container();
		$container->add('zeta', HttpKernel::class);
		$container->add('alpha', HttpKernel::class);
		MockedApplication::setContainer($container);

		try {
			$tester = $this->tester(new ContainerDebugCommand());
			$tester->execute([]);
		} finally {
			MockedApplication::setContainer($original);
		}

		$this->assertLessThan(strpos($tester->getDisplay(), 'zeta'), strpos($tester->getDisplay(), 'alpha'));
	}

	//
	// Every shipped command, as the runner sees them
	//

	/** No two commands may claim the same name or alias, or discovery order decides who wins. */
	public function testShippedCommandNamesAreUnique() {
		$application = new CommandApplication();
		$application->addCommandDir(dirname(__DIR__) . '/Command', 'Cog\Command');

		$seen = [];
		foreach (array_keys($application->all()) as $name) {
			$this->assertArrayNotHasKey($name, $seen, sprintf('%s is registered twice', $name));
			$seen[$name] = true;
		}

		$this->assertNotEmpty($seen);
	}
}
