<?php

namespace Cog\Console;

use Cog\Util\StringUtils;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Trait StopwatchTrait
 * @package Cog\Console
 *
 * Timing and memory reporting for console commands. Lives in a trait rather than
 * on a shared base class so that commands extending a third-party command class
 * still report the same footer.
 */
trait StopwatchTrait {

	public Stopwatch $stopwatch;

	protected function startStopwatch(): void {
		$this->stopwatch = new Stopwatch();
		$this->stopwatch->start('command');
	}

	protected function getStopwatchStats(OutputInterface $output): void {
		$event = $this->stopwatch->stop('command');
		$output->writeln('');

		$output->writeln( sprintf(
			'Command time <comment>%ss%s</comment>',
			$event->getDuration()/1000,
			ini_get('max_execution_time') ? '(' . ini_get('max_execution_time') . 'maximum)' : ''
		));

		$output->writeln(sprintf(
			'Peak memory usage <comment>%s</comment> (%s maximum allocation)',
			StringUtils::getByteSize($event->getMemory()),
			ini_get('memory_limit')
		));
	}
}
