<?php

namespace Cog\ExampleApp;

use Cog\BaseApplication;
use Cog\BaseConfig;
use Cog\Database\Database;
use Cog\Enum\Environment;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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

	/** @inheritDoc */
	protected static function buildContainer(): ContainerBuilder {
		$container = parent::buildContainer();

		$container->getDefinition('router')
			->setArgument('$resource', [self::class, 'getRoutes'])
			->setArgument('$options', self::config()->cache ? ['cache_dir' => self::config()->dirCache . '/routes'] : []);

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

		static::$config = new BaseConfig(
			$environment,
			$debug,
			$cache,
			__DIR__ . '/cache',
			__DIR__ . '/templates'
		);

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
		Database::$urlProfilePage = '/assets/php/_core/profile.php';
	}
}
