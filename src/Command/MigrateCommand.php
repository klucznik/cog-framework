<?php

namespace Cog\Command;

class MigrateCommand extends PhinxCommand {

	protected function configure(): void {
		parent::configure();

		$this
			->setName('db:migrate')
			->setAliases(['migrate']);
	}

	protected function phinxCommandName(): string {
		return 'migrate';
	}

	protected function regeneratesOrm(): bool {
		return true;
	}
}
