<?php

namespace Cog\Test;

use Cog\Path;

/**
 * Exposes Path's protected web initialiser so TestPath can drive it with a
 * doctored $_SERVER. Path::initialize() picks the CLI branch under PHPUnit and
 * the web branch is otherwise unreachable from a test.
 *
 * The initialiser writes to Path's own static properties, so TestPath is
 * responsible for restoring them.
 */
final class MockedPath extends Path {

	public static function initializeWebRoot(): void {
		parent::initializeWeb();
	}
}
