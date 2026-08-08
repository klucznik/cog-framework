<?php

namespace Cog\Command;

use Phinx\Console\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RollbackCommand extends Command\Rollback {
	/**
	 * {@inheritdoc}
	 * @throws InvalidArgumentException
	 */
	protected function configure(): void {
		parent::configure();

		$this
			->setName('db:rollback')
			->setAliases(['rollback']);
	}

	/**
	 * {@inheritdoc}
	 * @throws \Exception;
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		parent::execute($input, $output);

		return $this->getApplication()->find('db:codegen')->run($input, $output);
	}
}
