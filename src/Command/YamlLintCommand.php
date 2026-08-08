<?php

namespace Cog\Command;

use Cog\Console\StopwatchTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Command\LintCommand;

/**
 * Class YamlLintCommand
 * @package Cog\Command
 *
 * Thin wrapper around Symfony's own lint:yaml so the command is picked up by the
 * directory scan in Cog\Console\CommandApplication, which requires one class per
 * file under src/Command with a matching filename. The linting itself is
 * Symfony's; only the timing footer shared with the other Cog commands is ours.
 *
 * The AsCommand attribute is repeated because PHP attributes are not inherited,
 * so without it the parent's name never reaches the subclass.
 */
#[AsCommand(name: 'lint:yaml', description: 'Lint a YAML file and outputs encountered errors')]
class YamlLintCommand extends LintCommand {

	use StopwatchTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->startStopwatch();

		$result = parent::execute($input, $output);

		$this->getStopwatchStats($output);

		return $result;
	}
}
