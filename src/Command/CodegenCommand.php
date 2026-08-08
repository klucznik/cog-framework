<?php

namespace Cog\Command;

use Cog\Codegen\CodeGenRunner;
use Cog\Console\RunnerDirTrait;
use Cog\Console\StopwatchTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CodegenCommand extends Command {

	use RunnerDirTrait;
	use StopwatchTrait;

	protected function configure(): void {
		$this
			->setName('db:codegen')
			->setAliases(['codegen'])
			->setDescription('Code generates your ORM-layer (optional [config] path, defaults to codegen.xml)')
			->addArgument(
				'config',
				InputArgument::OPTIONAL,
				'Path to the codegen config file. Relative paths are resolved against the directory containing the cog runner',
				'codegen.xml'
			)
			->addUsage('config/codegen.xml')
			->addUsage('/absolute/path/to/codegen.xml')
			->setHelp(<<<'HELP'
The <info>%command.name%</info> command generates your ORM-layer using an XML config file.

  <info>%command.full_name%</info>
  <info>%command.full_name% config/codegen.xml</info>
  <info>%command.full_name% /absolute/path/to/codegen.xml</info>

If no <comment>config</comment> argument is given, <comment>codegen.xml</comment> next to the cog runner is used.
A relative path is resolved against the directory containing the cog runner, so it
does not matter which directory you invoke the command from. Absolute paths are used as-is.
HELP);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->startStopwatch();

		$config = trim((string)$input->getArgument('config'));

		$settingsXmlFilePath = str_starts_with($config, '/')
			? $config
			: $this->getRunnerDir() . '/' . $config;

		if (!is_file($settingsXmlFilePath)) {
			$output->writeln('<error>config file not found: ' . $settingsXmlFilePath . '</error>');
			return self::FAILURE;
		}

		try {
			$this->getApplication()->find('db:clean')->run(new ArrayInput([]), $output);
		} catch (CommandNotFoundException $e) {}

		try {
			CodeGenRunner::run($this->getRunnerDir(), $settingsXmlFilePath);

			if ($errors = CodeGenRunner::$rootErrors) {
				$output->writeln( sprintf("<error>The following ROOT ERRORS were reported:\n%s</error>\n\n", $errors));
			} else {
				$output->writeln('<comment>Cog\Codegen\CodeGen settings:</comment>');
				$output->writeln(CodeGenRunner::getSettingsXml() . "\n");
			}

			foreach (CodeGenRunner::$codegenArray as $codegen) {
				$output->writeln('<comment>' . $codegen->getTitle() . '</comment>');
				$output->writeln('---------------------------------------------------------------------');
				$output->writeln($codegen->getReportLabel());
				$output->writeln($codegen->generateAll());

				if ($errors = $codegen->errors) {
					$output->writeln('<comment>The following errors were reported:</comment>');
					$output->writeln('<error>' . $errors . '</error>');
				}

				if ($warnings = $codegen->warnings) {
					$output->writeln('<comment>The following warnings were reported:</comment>');
					$output->writeln('<info>' . $warnings . '</info>');
				}
			}

			foreach (CodeGenRunner::generateAggregate() as $message) {
				$output->writeln('<info>' . sprintf("%s\n\n", $message) . '</info>');
			}

			try {
				$this->getApplication()->find('cache:clean')->run(new ArrayInput([]), $output);
			} catch (CommandNotFoundException $e) {}

			$this->getStopwatchStats($output);

			return self::SUCCESS;
		} catch (\Throwable $exception) {
			$output->writeln('<error>error: ' . trim($exception->getMessage()) . '</error>');
			return self::FAILURE;
		}
	}
}
