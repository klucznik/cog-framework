<?php declare(strict_types=1);

namespace Cog\Command;

use Psy\Shell;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class PsyshCommand extends Command {
	private Shell $psysh;

	public function __construct() {
		parent::__construct();

		$this->psysh = new Shell();
	}

	protected function configure(): void {
		$this
			->setName('shell')
			->setDescription('Start PsySH');
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

		return $this->psysh->run($input, $output);
	}
}
