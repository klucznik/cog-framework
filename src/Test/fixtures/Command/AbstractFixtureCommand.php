<?php declare(strict_types=1);

namespace Cog\Test\fixtures\Command;

use Symfony\Component\Console\Command\Command;

/** Abstract, so the scan must skip it rather than trying to instantiate it. */
abstract class AbstractFixtureCommand extends Command {
}
