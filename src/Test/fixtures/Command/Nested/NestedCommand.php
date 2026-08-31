<?php declare(strict_types=1);

namespace Cog\Test\fixtures\Command\Nested;

use Symfony\Component\Console\Command\Command;

/** In a subdirectory: the scan is non-recursive, so this must never register. */
class NestedCommand extends Command {

	protected function configure(): void {
		$this->setName('fixture:nested');
	}
}
