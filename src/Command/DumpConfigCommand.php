<?php

namespace Cog\Command;

use Cog\BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DumpConfigCommand extends Command {

	/**
	 * {@inheritdoc}
	 */
	protected function configure(): void {
		$this
			->setName('dump:config')
			->setDescription('dumps config used by framework');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		dump(BaseApplication::config()->dump());
		return self::SUCCESS;
	}
}
