<?php

namespace Cog\Command;

use Cog\BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
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
	 * Values are json-encoded so booleans, null, enums and paths all render readably.
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$table = new Table($output);
		$table->setHeaders(['Key', 'Value']);

		foreach (BaseApplication::config()->dump() as $key => $value) {
			$table->addRow([$key, json_encode($value, JSON_UNESCAPED_SLASHES)]);
		}

		$table->render();

		return self::SUCCESS;
	}
}
