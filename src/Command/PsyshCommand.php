<?php declare(strict_types=1);

namespace Cog\Command;

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

	protected function execute(InputInterface $input, OutputInterface $output): int {
		// Reset input & output if they are the default ones used. Indeed,
		// We call Psysh Application here which will do the necessary bootstrapping.
		// If we don't we would force the regular Symfony Application
		// bootstrapping instead not allowing the Psysh one to kick in at all.
		if ($input instanceof ArgvInput) {
			$input = null;
		}

		if ($output instanceof ConsoleOutput) {
			$output = null;
		}

		return (new Shell())->run($input, $output);
	}
}
