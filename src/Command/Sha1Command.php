<?php

namespace Cog\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Sha1Command extends Command {

	protected function configure(): void {
		$this
			->setName('crypt:sha1')
			->setDescription('Encodes a string using sha1')
			->addArgument(
				'inputString',
				InputArgument::REQUIRED,
				'String to encode'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->writeln(sha1($input->getArgument('inputString')));

		return self::SUCCESS;
	}
}
