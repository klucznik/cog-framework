<?php

namespace Cog;

use Cog\Controller\AttributeRouteControllerLoader;
use Cog\Database\Database;
use Cog\Enum\Environment;
use Cog\Util\StringUtils;
use Cog\Util\Url;
use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactory;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorageFactory;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\BackedEnumValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\QueryParameterValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ServiceValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\SessionValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\UidValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\VariadicValueResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\DependencyInjection\ControllerArgumentValueResolverPass;
use Symfony\Component\HttpKernel\EventListener\ResponseListener;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\EventListener\SessionListener;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Mime\MimeTypesInterface;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;
use Symfony\Component\String\Inflector\EnglishInflector;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Framework-level base for the application entry point.
 *
 * This holds the compiled service container and every piece of lifecycle
 * logic that doesn't need to know about the App namespace: container
 * assembly, error handling, database connection bootstrapping, request
 * access, and dev profiling output.
 *
 * Anything that has to reach into App\ (route discovery under the app/
 * directory, translations, the app's own Kernel and CORS listener) is
 * deliberately left out of this class - it lives on App\CogApplication,
 * which extends this class and overrides/implements those specific hooks.
 */
abstract class BaseApplication extends Base {

	public const string FRAMEWORK_VERSION = '0.9.0';

	protected static BaseConfig $config;

	public static function config(): BaseConfig {
		return static::$config;
	}

	/**
	 * The application-wide service container.
	 * @var ?Container
	 */
	public static ?Container $container = null;

	/**
	 * The encoding type for the application (e.g. UTF-8, ISO-8859-1, etc.)
	 * @var string
	 */
	public static string $encodingType = 'UTF-8';

	/**
	 * Called by initialize() to route errors, uncaught exceptions and fatals
	 * through Symfony's error handler.
	 *
	 * Rendering is picked by SAPI: a var-dump on the CLI, an HTML page otherwise,
	 * detailed when DEBUG is on and generic when it isn't. Custom exception
	 * properties - the query and error number on a database exception,are included.
	 * @return ErrorHandler
	 */
	protected static function initializeErrorHandling(): ErrorHandler {
		return ErrorHandler::register(new ErrorHandler(null, static::config()->debug));
	}

	/**
	 * Builds the config object installed on static::$config before any lifecycle
	 * step runs. Subclasses override this to supply their own BaseConfig subclass
	 * with additional directories - initializeErrorHandling() and
	 * initializeContainer() both read config(), so it has to exist by then.
	 *
	 * @param Environment $environment
	 * @param bool $debug
	 * @param bool $cache
	 * @return BaseConfig
	 */
	protected static function createConfig(Environment $environment, bool $debug, bool $cache): BaseConfig {
		return new BaseConfig(
			$environment,
			$debug,
			$cache,
			__DIR__ . '/cache',
			__DIR__ . '/templates'
		);
	}

	/**
	 * This should be the first call to initialize all the static variables
	 * The application object also has static methods that are miscellaneous web
	 * development utilities, etc.
	 * It also will make a call to InitializeDatabaseConnections()
	 *
	 * @param Environment $environment
	 * @param bool $debug
	 * @param bool $cache
	 * @return void
	 */
	public static function initialize(Environment $environment, bool $debug = true, bool $cache = false): void {
		static::$config = static::createConfig($environment, $debug, $cache);

		static::initializeErrorHandling();
		static::initializeContainer();
	}

	protected static function initializeContainer(): void {
		$file = static::config()->dirCache . '/container/ProjectServiceContainer.php';

		// if container exist in cache use it
		if (static::config()->cache && file_exists($file)) {
			self::$container = require_once $file;
		}

		if (self::$container == null) {
			self::$container = static::buildContainer();

			self::$container->compile();

			// cache compiled container if cache is enabled
			if (static::config()->cache) {
				//dump(self::$container->getCompiler()->getLog());
				$dumper = new PhpDumper(self::$container);

				$content = $dumper->dump([
					'as_files' => true,
					'build_time' => time(),
				]);

				$dir = static::config()->dirCache . '/container/';
				$fs = new Filesystem();

				foreach ($content as $file => $code) {
					$fs->dumpFile($dir.$file, $code);
					@chmod($dir.$file, 0666 & ~umask());
				}
			}
		}
	}

	/**
	 * Builds the base service container shared by every Cog application:
	 * routing, sessions, argument resolvers, and the response/router event
	 * listeners. App\CogApplication::buildContainer() calls this via
	 * parent::buildContainer() and adds the app's own kernel and CORS
	 * listener before the container is compiled.
	 * @return ContainerBuilder
	 */
	protected static function buildContainer(): ContainerBuilder {
		$container = new ContainerBuilder();

		$container->register('kernel', Kernel::class)
			->setArgument('$dispatcher', new Reference('dispatcher'))
			->setArgument('$resolver', new Reference('controller_resolver'))
			->setArgument('$argumentResolver', new Reference('argument_resolver'))
			->setArgument('$requestStack', new Reference('request_stack'))
			->setPublic(true);

		$container->setParameter('kernel.debug', false);
		$container->addCompilerPass(new ControllerArgumentValueResolverPass());

		$container->register('context', RequestContext::class);

		$container->register('matcher', UrlMatcher::class)
			->setArgument('$routes', [static::class, 'getRoutes'])
			->setArgument('$context', new Reference('context'));

		$container->register('request_stack', RequestStack::class)
			->setAutowired(true)
			->setPublic(true);

		$container->register('controller_resolver', ControllerResolver::class);
		$container->register('argument_metadata_factory', ArgumentMetadataFactory::class);
		$container->register('argument_resolver', ArgumentResolver::class)
			->setArgument('$argumentMetadataFactory', new Reference('argument_metadata_factory'))
			->setArgument(1, new AbstractArgument('argument value resolvers'))
            ->setArgument(2, new AbstractArgument('targeted value resolvers'));

		$container->register('argument_resolver.backed_enum_resolver', BackedEnumValueResolver::class)
			->addTag('controller.argument_value_resolver', ['priority' => 100, 'name' => BackedEnumValueResolver::class]);

		$container->register('argument_resolver.uid', UidValueResolver::class)
			->addTag('controller.argument_value_resolver', ['priority' => 100, 'name' => UidValueResolver::class]);

		$container->register('argument_resolver.request_attribute', RequestAttributeValueResolver::class)
			->addTag('controller.argument_value_resolver', ['priority' => 100, 'name' => RequestAttributeValueResolver::class]);

		$container->register('argument_resolver.request', RequestValueResolver::class)
			->addTag('controller.argument_value_resolver', ['priority' => 50, 'name' => RequestValueResolver::class]);

		$container->register('argument_resolver.session', SessionValueResolver::class)
			->addTag('controller.argument_value_resolver', ['priority' => 50, 'name' => SessionValueResolver::class]);

		$container->register('argument_resolver.service', ServiceValueResolver::class)
			->setArgument('$container', new Reference('service_container'))
			->addTag('controller.argument_value_resolver', ['priority' => -50, 'name' => ServiceValueResolver::class]);

		$container->register('argument_resolver.variadic', VariadicValueResolver::class)
			->addTag('controller.argument_value_resolver', ['priority' => -150, 'name' => VariadicValueResolver::class]);

		$container->register('argument_resolver.default', DefaultValueResolver::class)
			->addTag('controller.argument_value_resolver', ['priority' => -100, 'name' => DefaultValueResolver::class]);

		$container->register('argument_resolver.query_parameter_value_resolver', QueryParameterValueResolver::class)
			->addTag('controller.targeted_value_resolver', ['name' => QueryParameterValueResolver::class]);

		$container->register('listener.session', SessionListener::class)
			->setArgument('$container', new Reference('service_container'));

		$container->register('session_storage', NativeSessionStorageFactory::class);

		$container->register('session_factory', SessionFactory::class)
			->setArgument('$storageFactory', new Reference('session_storage'))
			->setArgument('$requestStack', new Reference('request_stack'));

		$container->register('listener.response', ResponseListener::class)
			->setArgument('$charset', static::$encodingType);

		$container->register('listener.router', RouterListener::class)
			->setArgument('$matcher', new Reference('router'))
			->setArgument('$requestStack', new Reference('request_stack'));

		$container->register('dispatcher', EventDispatcher::class)
			->addMethodCall('addSubscriber', [new Reference('listener.router')])
			->addMethodCall('addSubscriber', [new Reference('listener.session')])
			->addMethodCall('addSubscriber', [new Reference('listener.response')]);

		$container->register('routes_loader_closure', ClosureLoader::class);

		$container->register('router', Router::class)
			->setArgument('$loader',  new Reference('routes_loader_closure'))
			->setArgument('$context', new Reference('context'))
			->setPublic(true);

		$container->register('inflector', EnglishInflector::class)
			->setAutowired(true)
			->setPublic(true);

		$container->register('mime', MimeTypes::class)
			->setAutowired(true)
			->setPublic(true);
		$container->setAlias(MimeTypesInterface::class, 'mime');

		$container->register('slugger', AsciiSlugger::class)
			->setAutowired(true)
			->setPublic(true);
		$container->setAlias(SluggerInterface::class, 'slugger');

		$container->register('filesystem', Filesystem::class)
			->setAutowired(true)
			->setPublic(true);

		return $container;
	}

	/**
	 * Returns the directories the console scans for commands, as a map of
	 * PSR-4 namespace prefix => absolute path. Apps override this and merge
	 * their own directories into the framework's.
	 * @return array<string, string>
	 */
	public static function getCommandDirs(): array {
		return [
			'Cog\\Command' => __DIR__ . '/Command',
		];
	}

	/**
	 * Adds the app's own directory containing routes.
	 * @return array<string>
	 */
	public static function getRoutesDirs(): array {
		return [];
	}

	/**
	 * Returns all the routes used by the app. The framework has no notion
	 * of where an app's controllers live, so this is left for the
	 * application layer to implement.
	 * @return RouteCollection
	 * @throws Exception
	 */
	public static function getRoutes(): RouteCollection {
		$toReturn = new RouteCollection();

		$attributeRouteControllerLoader = new AttributeRouteControllerLoader();

		$loader = new LoaderResolver();
		$loader->addLoader(new AttributeDirectoryLoader(new FileLocator(), $attributeRouteControllerLoader));

		$annotDirs = static::getRoutesDirs();

		foreach ($annotDirs as $annotDir) {
			$resolvedLoader = $loader->resolve($annotDir);
			$collection = $resolvedLoader->load($annotDir);
			//dump($resolvedLoader);
			//dump($collection->all());
			$toReturn->addCollection($collection);
		}

		return $toReturn;
	}

	/**
	 * @return HttpFoundation\Request|null
	 * This returns Request object, should be used when the request is not available in other way
	 */
	public static function getCurrentRequest(): ?HttpFoundation\Request {
		/** @var RequestStack $requestStack */
		try {
			$requestStack = self::$container->get('request_stack');
		} catch (Exception) {
			return null;
		}
		return $requestStack->getCurrentRequest();
	}


	/**
	 * This function displays helpful development info like queries sent to database and memory usage.
	 * By default, it shows only if database profiling is enabled in any configured database connections.
	 *
	 * If forced to show when profiling is disabled you can monitor memory usage more accurately,
	 * as collecting database profiling information tends to noticeable bigger memory consumption.
	 *
	 * @param boolean $forceDisplay optional parameter, set true to always display info even if DB profiling is disabled
	 * @return void
	 */
	public static function displayProfiling(bool $forceDisplay = false): void {
		if ($forceDisplay || static::config()->debug) {
			echo '<div style="display: flex; position: fixed; bottom: 0; right: 0; z-index: 99999; padding: 8px; text-align: left;
				color: white; font-size: 13px; border-top-left-radius: 0.75rem; background-color: #45645b; align-items: center;">';

			echo '<div style="padding: 0 8px; border-right: 1px solid #648a80;">';
			echo '<a href="' . Url::getForRoute('devDump') . '" style="text-decoration: none; color: white; font-weight: bold;">';
			echo static::config()->environment->value;
			echo '</a>';
			echo '</div>';

			echo '<div style="padding: 0 8px; border-right: 1px solid #648a80;">';

			// Output DB Profiling Data
			Database::displayProfiling();
			echo '</div>';

			// Output runtime statistics / settings
			$controller = '';
			$route = '';
			$request = static::getCurrentRequest();
			if ($request instanceof HttpFoundation\Request) {
				$controller = $request->attributes->get('_controller');
				if (is_array($controller)) {
					$controller = implode('::', $controller);
				}

				$route = $request->attributes->get('_route');
			}

			echo '<div style="padding: 0 8px; border-right: 1px solid #648a80;" title="' . $controller . '">';
			echo $route;
			echo '</div>';

			echo '<div style="padding: 0 8px; border-right: 1px solid #648a80;" title="' . (ini_get('memory_limit') == -1 ? '' : ini_get('memory_limit')) . '">';
			echo '<i class="icon-before fa-icon icon-memory" style="margin-right: 4px"></i>' . StringUtils::getByteSize(memory_get_peak_usage(true));
			echo '</div>';

			echo '<div style="padding: 0 8px; border-right: 1px solid #648a80;" title="' . static::$encodingType . '">';
			echo '<i class="icon-before fa-icon icon-gear" style="margin-right: 4px"></i>' . self::FRAMEWORK_VERSION;
			echo '</div>';

			$titlePhp = 'max_execution_time: ' . ini_get('max_execution_time') . "s\n";
			$titlePhp .= 'max_input_time: ' . ini_get('max_input_time') . "s\n";
			$titlePhp .= 'post_max_size: ' . ini_get('post_max_size') . "\n";
			$titlePhp .= 'upload_max_filesize: ' . ini_get('upload_max_filesize') . "\n";

			echo '<div style="padding: 0 8px" title="' . $titlePhp . '">';
			echo '<a href="' . Url::getForRoute('devPhpInfo') . '" style="text-decoration: none; color: white">';
			echo 'php ' . phpversion();
			echo '</a>';
			echo '</div>';
 			echo '</div>';
		}
	}
}
