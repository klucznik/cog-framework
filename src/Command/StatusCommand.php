<?php declare(strict_types=1);

namespace Cog\Command;

class StatusCommand extends PhinxCommand {

	protected function configure(): void {
		parent::configure();

		$this
			->setName('db:status')
			->setAliases(['status']);
	}

	protected function phinxCommandName(): string {
		return 'status';
	}
}
