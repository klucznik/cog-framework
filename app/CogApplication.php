<?php

namespace Cog\ExampleApp;

use Cog\BaseApplication;
use Cog\Database\Database;
use Cog\Enum\Environment;
use League\Container\Argument\Literal\ArrayArgument;
use League\Container\Argument\Literal\CallableArgument;
use League\Container\Container;
use Symfony\Component\Routing\Router;

/**
 * This abstract class should never be instantiated. It contains the
 * application-specific pieces that Cog\BaseApplication (the framework base
 * class it extends) deliberately leaves out because they reach into the
 * App namespace
 */
abstract class CogApplication extends BaseApplication {

	/**
	 * The encoding type for the application (e.g. UTF-8, ISO-8859-1, etc.)
	 * @var string
	 */
	public static string $encodingType = 'UTF-8';

	/** @inheritDoc */
	public static function getRoutesDirs(): array {
		$annotDirs = [
			__DIR__ . '/Controller',
		];

		if (self::config()->debug && is_dir(__DIR__ . '/Dev')) {
			$annotDirs[] = __DIR__ . '/Dev';
		}

		return $annotDirs;
	}

	/**
	 * How an application customises the container: call parent::buildContainer()
	 * and add to, extend or replace what it registered. Here the router is
	 * re-registered with the app's own options; the base wiring only refers to it
	 * by id, so nothing has resolved the original yet.
	 * @inheritDoc
	 */
	protected static function buildContainer(): Container {
		$container = parent::buildContainer();

		$container->addShared('router', Router::class, overwrite: true)
			->addArguments([
				'routes_loader_closure',
				new CallableArgument(static::getRoutes(...)),
				new ArrayArgument(self::config()->cache ? ['cache_dir' => self::config()->dirCache . '/routes'] : []),
				'context',
			]);

		return $container;
	}

	/**
	 * @param Environment $environment
	 * @param bool $debug
	 * @param bool $cache
	 * @return void
	 */
	public static function initialize(Environment $environment, bool $debug = true, bool $cache = false): void {
		parent::initialize($environment, $debug, $cache);

		if (!ini_get('date.timezone')) {
			date_default_timezone_set('UTC');
		}

		self::initializeDatabaseConnection();
	}

	public static function initializeDatabaseConnection(): void {
		$config = [
			'adapter' => $_ENV['ADAPTER'],
			'server' => $_ENV['SERVER'],
			'port' => $_ENV['PORT'] === 'null' ? null : $_ENV['PORT'],
			'encoding' => $_ENV['ENCODING'],
			'database' => $_ENV['DATABASE'],
			'username' => $_ENV['USERNAME'],
			'password' => $_ENV['PASSWORD'],
			'profiling' => $_ENV['PROFILING'] === 'true',
			'timezone' => $_ENV['TIMEZONE'],
		];

		Database::initializeConnection($config, 1);
		// the same value Database declares as its default - restated because this is the line an
		// application overrides when its own copy of the profiling page lives somewhere else.
		Database::$urlProfilePage = '/dev/profile';
	}
}
