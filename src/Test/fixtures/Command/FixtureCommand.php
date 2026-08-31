<?php declare(strict_types=1);

namespace Cog\Test\fixtures\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A concrete command for the directory scan in Cog\Console\CommandApplication to
 * find. Its siblings in this directory are the cases the scan must skip.
 */
class FixtureCommand extends Command {

	protected function configure(): void {
		$this
			->setName('fixture:concrete')
			->setDescription('A fixture command that the directory scan should register');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->writeln('fixture ran');

		return self::SUCCESS;
	}
}
