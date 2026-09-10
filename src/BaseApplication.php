<?php declare(strict_types=1);

namespace Cog;

use Cog\Controller\AttributeRouteControllerLoader;
use Cog\Database\Database;
use Cog\Enum\Environment;
use Cog\Enum\Runtime;
use Cog\EventListener\HttpExceptionListener;
use Cog\EventListener\NotFoundExceptionListener;
use Cog\EventListener\RedirectExceptionListener;
use Cog\Util\StringUtils;
use Cog\Util\Url;
use Exception;
use League\Container\Argument\Literal\ArrayArgument;
use League\Container\Argument\Literal\BooleanArgument;
use League\Container\Argument\Literal\CallableArgument;
use League\Container\Argument\Literal\StringArgument;
use League\Container\Container;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
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
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\VariadicValueResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\EventListener\ErrorListener;
use Symfony\Component\HttpKernel\EventListener\ResponseListener;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\EventListener\SessionListener;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;
use Symfony\Component\String\Inflector\EnglishInflector;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Framework-level base for the application entry point.
 *
 * This holds the service container and every piece of lifecycle
 * logic that doesn't need to know about the App namespace: container
 * assembly, error handling, database connection bootstrapping, request
 * access, and dev profiling output.
 *
 * Anything that has to reach into App\ (route discovery under the app/
 * directory, translations, the app's own Kernel and CORS listener) is
 * deliberately left out of this class - it lives on App\CogApplication,
 * which extends this class and overrides/implements those specific hooks.
 */
abstract class BaseApplication {

	public const string FRAMEWORK_VERSION = '0.9.2';

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
		$docroot = dirname(__DIR__);

		return new BaseConfig(
			$environment,
			$debug,
			$cache,

			$docroot,
			$docroot . '/app',
			$docroot . '/public',
			$docroot . '/cache',
			$docroot . '/templates',

			array_key_exists('SERVER_PROTOCOL', $_SERVER) ? Runtime::WEB : Runtime::CLI
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
		if (self::$container === null) {
			self::$container = static::buildContainer();
		}
	}

	/**
	 * Builds the service container shared by every Cog application: the kernel
	 * and its argument resolvers, routing, sessions, and the router/session/
	 * response event listeners. Every service is shared, so the kernel, the
	 * listeners and getCurrentRequest() all see the same request stack. An
	 * application that needs more overrides this, calls parent::buildContainer()
	 * and adds to what comes back.
	 *
	 * Arguments are positional. A plain string is looked up as a service id (or
	 * a tag), so literal values go through the Literal argument wrappers.
	 * @return Container
	 */
	protected static function buildContainer(): Container {
		$container = new Container();

		// the container itself, for the services that look other services up at runtime
		$container->addShared('service_container', static fn() => $container);

		// handleAllThrowables: an \Error reaches the exception listeners like any
		// exception does, instead of bypassing them on its way to the error handler
		$container->addShared('kernel', HttpKernel::class)
			->addArguments(['dispatcher', 'controller_resolver', 'request_stack', 'argument_resolver', new BooleanArgument(true)]);

		$container->addShared('context', RequestContext::class);
		$container->addShared('request_stack', RequestStack::class);
		$container->addShared('controller_resolver', ControllerResolver::class);
		$container->addShared('argument_metadata_factory', ArgumentMetadataFactory::class);

		// Registration order is the order the resolvers are tried in.
		$container->addShared('argument_resolver.backed_enum_resolver', BackedEnumValueResolver::class)
			->addTag('controller.argument_value_resolver');
		$container->addShared('argument_resolver.request_attribute', RequestAttributeValueResolver::class)
			->addTag('controller.argument_value_resolver');
		$container->addShared('argument_resolver.request', RequestValueResolver::class)
			->addTag('controller.argument_value_resolver');
		$container->addShared('argument_resolver.session', SessionValueResolver::class)
			->addTag('controller.argument_value_resolver');
		$container->addShared('argument_resolver.service', ServiceValueResolver::class)
			->addArgument('service_container')
			->addTag('controller.argument_value_resolver');
		$container->addShared('argument_resolver.default', DefaultValueResolver::class)
			->addTag('controller.argument_value_resolver');
		$container->addShared('argument_resolver.variadic', VariadicValueResolver::class)
			->addTag('controller.argument_value_resolver');

		// Not in the chain above: only an argument that names it through
		// #[MapQueryParameter] uses it, and the argument resolver looks it up by class.
		$container->addShared(QueryParameterValueResolver::class);

		$container->addShared('argument_resolver', ArgumentResolver::class)
			->addArguments(['argument_metadata_factory', 'controller.argument_value_resolver', 'service_container']);

		$container->addShared('session_storage', NativeSessionStorageFactory::class);
		$container->addShared('session_factory', SessionFactory::class)
			->addArguments(['request_stack', 'session_storage']);
		$container->addShared('listener.session', SessionListener::class)
			->addArgument('service_container');

		$container->addShared('listener.response', ResponseListener::class)
			->addArgument(new StringArgument(static::$encodingType));

		$container->addShared('listener.router', RouterListener::class)
			->addArguments(['router', 'request_stack']);

		// Without an error controller Symfony's ErrorListener only turns an exception
		// marked #[WithHttpStatus] into the matching HttpException, so the listeners
		// below it see the status the exception asked for.
		$container->addShared('listener.error', static fn() => new ErrorListener(null));
		$container->addShared('listener.redirect', RedirectExceptionListener::class);
		$container->addShared('listener.not_found', NotFoundExceptionListener::class);
		$container->addShared('listener.http_exception', HttpExceptionListener::class);

		$container->addShared('dispatcher', EventDispatcher::class)
			->addMethodCall('addSubscriber', ['listener.router'])
			->addMethodCall('addSubscriber', ['listener.session'])
			->addMethodCall('addSubscriber', ['listener.response'])
			->addMethodCall('addSubscriber', ['listener.error'])
			->addMethodCall('addSubscriber', ['listener.redirect'])
			->addMethodCall('addSubscriber', ['listener.not_found'])
			->addMethodCall('addSubscriber', ['listener.http_exception']);

		$container->addShared('routes_loader_closure', ClosureLoader::class);

		$container->addShared('router', Router::class)
			->addArguments([
				'routes_loader_closure',
				new CallableArgument(static::getRoutes(...)),
				new ArrayArgument(static::config()->cache ? ['cache_dir' => static::config()->dirCache . '/routes'] : []),
				'context',
			]);

		$container->addShared('inflector', EnglishInflector::class);
		$container->addShared('mime', MimeTypes::class);
		$container->addShared('slugger', AsciiSlugger::class);
		$container->addShared('filesystem', Filesystem::class);

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
