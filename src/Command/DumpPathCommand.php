<?php

namespace Cog\Command;

use Cog\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DumpPathCommand extends Command {

	/**
	 * {@inheritdoc}
	 */
	protected function configure(): void {
		$this
			->setName('dump:path')
			->setDescription('dumps paths detected by framework');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		dump(Path::dump());
		return self::SUCCESS;
	}
}
