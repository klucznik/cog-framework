<?php

use Cog\Enum\Environment;
use Cog\ExampleApp\CogApplication;

if (!defined('PREPEND')) { // Ensure prepend.inc.php is only executed once
	define('PREPEND', 'PREPEND'); // Version information

	require_once __DIR__ . '/vendor/autoload.php'; // Load the composer autoloader

	$environment = Environment::DEV;

	$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '.env.' . $environment->value);
	$dotenv->load();

	$dotenv->required([
			'DEBUG',
			'CACHE'
	])->isBoolean();

	CogApplication::initialize($environment, ($_ENV['DEBUG'] === 'true'), ($_ENV['CACHE'] === 'true')); // Initialize the CogApplication
}
