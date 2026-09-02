<?php

namespace Cog\Command;

class RollbackCommand extends PhinxCommand {

	protected function configure(): void {
		parent::configure();

		$this
			->setName('db:rollback')
			->setAliases(['rollback']);
	}

	protected function phinxCommandName(): string {
		return 'rollback';
	}

	protected function regeneratesOrm(): bool {
		return true;
	}
}
