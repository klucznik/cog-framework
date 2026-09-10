<?php declare(strict_types=1);

namespace Cog\Command;

use Cog\BaseApplication;
use Cog\Database\Database;
use Psy\Shell;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Starts an interactive PsySH shell.
 *
 * PsySH is a dev dependency, so the shell is only created when the command runs
 * and the command disables itself when the package is not installed. Discovery
 * instantiates every command class, so an eager `new Shell()` would break every
 * command listing in an install without dev packages.
 */
final class PsyshCommand extends Command {
	protected function configure(): void {
		$this
			->setName('shell')
			->setDescription('Start PsySH');
	}

	public function isEnabled(): bool {
		return class_exists(Shell::class);
	}

	/**
	 * Variables predefined in the shell. The application is already initialized
	 * by the runner's prepend.inc.php before any command executes, so these are
	 * the live objects, not fresh ones.
	 * @return array<string, mixed>
	 */
	public function scopeVariables(): array {
		return [
			'container' => BaseApplication::$container,
			'config' => BaseApplication::config(),
			'databases' => Database::$databases,
		];
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		// Shell::run() always replaces the input with its own, and builds its own
		// output (pager, shell formatting) only when given null. The default
		// ArgvInput and ConsoleOutput are dropped so PsySH's take their place;
		// anything else, such as a tester's buffered output, is passed through.
		if ($input instanceof ArgvInput) {
			$input = null;
		}

		if ($output instanceof ConsoleOutput) {
			$output = null;
		}

		$shell = new Shell();
		$shell->setScopeVariables($this->scopeVariables());

		return $shell->run($input, $output);
	}
}
