<?php

namespace Cog\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Md5Command extends Command {

	/**
	 * {@inheritdoc}
	 */
	protected function configure(): void {
		$this
			->setName('crypt:md5')
			->setDescription('Encodes a string using md5')
			->addArgument(
				'inputString',
				InputArgument::REQUIRED,
				'String to encode'
			);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->writeln(md5($input->getArgument('inputString')));

		return self::SUCCESS;
	}
}
