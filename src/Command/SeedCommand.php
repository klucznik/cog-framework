<?php

namespace Cog\Command;

class SeedCommand extends PhinxCommand {

	protected function configure(): void {
		parent::configure();

		$this
			->setName('db:seed')
			->setAliases(['seed']);
	}

	protected function phinxCommandName(): string {
		return 'seed:run';
	}

	protected function regeneratesOrm(): bool {
		return true;
	}
}
