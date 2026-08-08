<?php

namespace Cog\Test;

use Cog\BaseApplication;
use Cog\BaseConfig;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\ErrorHandler\ErrorHandler;

/**
 * Exposes the pieces of BaseApplication that are protected, and stands in for
 * the App-level subclass that a real application would provide.
 *
 * BaseApplication::$config and ::$container are declared on the base class and
 * not redeclared here, so writing them through this class writes the very
 * statics the framework reads. TestBaseApplication is responsible for putting
 * back what the suite's bootstrap installed.
 */
final class MockedApplication extends BaseApplication {

	/** Directories getRoutes() scans; the base class returns none. */
	public static array $routesDirs = [];

	public static function getRoutesDirs(): array {
		return static::$routesDirs;
	}

	public static function setConfig(BaseConfig $config): void {
		static::$config = $config;
	}

	public static function setContainer(?Container $container): void {
		static::$container = $container;
	}

	public static function callBuildContainer(): ContainerBuilder {
		return static::buildContainer();
	}

	public static function callInitializeContainer(): void {
		static::initializeContainer();
	}

	public static function callInitializeErrorHandling(): ErrorHandler {
		return static::initializeErrorHandling();
	}
}
