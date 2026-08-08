<?php

namespace Cog\Command;

use Cog\Console\RunnerDirTrait;
use Cog\Util\FileSystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CodegenCleanCommand extends Command {

	use RunnerDirTrait;

	protected function configure(): void {
		$this
			->setName('db:clean')
			->setDescription('Cleans code generated files (only those overwritten by codegen)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$output->writeln('Cleaning DataGen (' . FileSystem::cleanDirectory($this->getRunnerDir() . '/generated/Data') . ')');
			$output->writeln('Cleaning TypeGen (' . FileSystem::cleanDirectory($this->getRunnerDir() . '/generated/Type') . ')');
			$output->writeln('Cleaning NodeGen (' . FileSystem::cleanDirectory($this->getRunnerDir() . '/generated/Node') . ')');
			return self::SUCCESS;
		} catch (\Exception $exception) {
			$output->writeln('<error>error: ' . trim($exception->getMessage()) . '</error>');
			return self::FAILURE;
		}
	}
}
