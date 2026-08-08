<?php

namespace Cog\Command;

use Cog\Console\StopwatchTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WhiteCharsCommand extends Command {

	use StopwatchTrait;

	protected function configure(): void {
		$this
			->setName('util:whitechars')
			->setDescription('Checks php code for extraneous white chars');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->startStopwatch();

		foreach (glob('.//**/*.php') as $file) {
			//pre
			if (preg_match("#^[\n\r|\n\r|\n|\r|\s]+<\?php#", file_get_contents($file))) {
				$output->writeln($file);
			}

			//post
			if (preg_match("#\?>[\n\r|\n\r|\n|\r|\s]+$#", file_get_contents($file))) {
				$output->writeln($file);
			}
		}

		$this->getStopwatchStats($output);

		return self::SUCCESS;
	}
}
