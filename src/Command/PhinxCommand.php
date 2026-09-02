<?php declare(strict_types=1);

namespace Cog\Command;

use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for the commands that wrap Phinx.
 *
 * Phinx runs as a nested application: the wrapped command is looked up in a
 * PhinxApplication and run with the input this command received, so Phinx keeps
 * its own options, output and exit codes while Cog contributes the command name
 * and what happens afterwards. The wrapped command's definition is copied onto
 * this one so the options survive the outer application's input binding, and
 * `--configuration`, which Phinx declares on its application rather than on the
 * commands, is redeclared here so it reaches the nested run.
 *
 * Discovery skips abstract classes, so this file is not registered as a command.
 */
abstract class PhinxCommand extends Command {

	/** the name of the wrapped command, as PhinxApplication knows it */
	abstract protected function phinxCommandName(): string;

	/** whether the ORM has to be regenerated after the wrapped command succeeds */
	protected function regeneratesOrm(): bool {
		return false;
	}

	protected function configure(): void {
		$phinx = $this->phinxCommand();
		$native = $phinx->getNativeDefinition();

		$definition = new InputDefinition(array_merge($native->getArguments(), $native->getOptions()));
		$definition->addOption(new InputOption('--configuration', '-c', InputOption::VALUE_REQUIRED, 'The Phinx configuration file to load'));

		$this
			->setDefinition($definition)
			->setDescription($phinx->getDescription());
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$exitCode = $this->phinxCommand()->run($input, $output);

		if ($exitCode !== self::SUCCESS || !$this->regeneratesOrm()) {
			return $exitCode;
		}

		return $this->getApplication()->find('db:codegen')->run(new ArrayInput([]), $output);
	}

	/** The wrapped command, fresh from a PhinxApplication so its own definition is in place. */
	protected function phinxCommand(): Command {
		return (new PhinxApplication())->find($this->phinxCommandName());
	}
}
