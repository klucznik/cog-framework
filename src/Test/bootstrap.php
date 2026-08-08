<?php

// PHPUnit bootstrap. Loads the autoloader and builds the service container, so
// tests can reach container-backed helpers such as StringUtils::pluralize() and
// FileSystem::getMimeType(). Container caching is off - the suite should never
// pick up a container dumped by a previous run.

use Cog\BaseApplication;
use Cog\Enum\Environment;
use Cog\Test\CodegenFixture;

require_once __DIR__ . '/../../vendor/autoload.php';

BaseApplication::initialize(Environment::DEV, true, false);

// Only the autoloader is installed here - it has to be in place before any
// generated class is referenced. Generation itself is deliberately NOT done
// from the bootstrap: code run before PHPUnit starts a test is invisible to
// code coverage, which used to report the whole generator as dead.
//
// CodegenFixture::generate() is instead called from the setUp() of the classes
// that need it (TestCodegen and QueryTestCase). It is idempotent, so the first
// test to ask for it pays the cost and the rest get it for free, and failures
// are still recorded rather than thrown - TestCodegen, the first file in the
// suite, turns them into assertions.
CodegenFixture::registerAutoloader();
