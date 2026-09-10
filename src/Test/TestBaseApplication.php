<?php

namespace Cog\Test;

use Cog\BaseApplication;
use Cog\BaseConfig;
use Cog\Enum\Environment;
use Cog\Enum\Runtime;
use Cog\Kernel;
use Cog\Util\Url;
use League\Container\Argument\Literal\ArrayArgument;
use League\Container\Argument\Literal\CallableArgument;
use League\Container\Container;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\BackedEnumValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ServiceValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\SessionValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\VariadicValueResolver;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\ResponseListener;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Mime\MimeTypesInterface;
use Symfony\Component\Routing\Router;
use Symfony\Component\String\Inflector\EnglishInflector;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * BaseApplication is booted by src/Test/bootstrap.php, which means every line of
 * it runs before PHPUnit starts recording, and the rest of the suite depends on
 * the container that boot produced. So the tests here drive it a second time
 * through MockedApplication and put the bootstrap's statics back afterwards.
 *
 * The container is complete on its own: the router takes its routes from
 * getRoutes(), which scans getRoutesDirs(), so an application only has to
 * override the latter. MockedApplication::$routesDirs stands in for that.
 */
class TestBaseApplication extends TestCase {

	private ?Container $container;
	private BaseConfig $config;
	private array $routesDirs;
	private array $server;
	private string $tempDir = '';

	public function setUp(): void {
		$this->container = BaseApplication::$container;
		$this->config = BaseApplication::config();
		$this->routesDirs = MockedApplication::$routesDirs;
		$this->server = $_SERVER;
	}

	public function tearDown(): void {
		$_SERVER = $this->server;
		MockedApplication::setContainer($this->container);
		MockedApplication::setConfig($this->config);
		MockedApplication::$routesDirs = $this->routesDirs;
		MockedApplication::$configFactoryResult = null;
		MockedApplication::$configAtErrorHandling = null;

		if ($this->tempDir !== '') {
			(new Filesystem())->remove($this->tempDir);
			$this->tempDir = '';
		}
	}

	/** The base container, with the router pointed at the fixture controllers. */
	private function buildContainer(): Container {
		MockedApplication::$routesDirs = [__DIR__ . '/fixtures/Controller'];

		return MockedApplication::callBuildContainer();
	}

	private function makeTempDir(): string {
		$this->tempDir = sys_get_temp_dir() . '/cog-test-container-' . uniqid();

		return $this->tempDir;
	}

	public function testFrameworkVersion() {
		$this->assertMatchesRegularExpression('#^\d+\.\d+\.\d+$#', BaseApplication::FRAMEWORK_VERSION);
	}

	public function testInitializeStoresConfig() {
		MockedApplication::initialize(Environment::TEST, false, false);
		$this->restoreErrorHandlers();

		$config = MockedApplication::config();

		$this->assertSame(Environment::TEST, $config->environment);
		$this->assertFalse($config->debug);
		$this->assertFalse($config->cache);
	}

	/**
	 * Every directory defaults to a path under the framework's own docroot - the
	 * parent of src/ - built from dirname() rather than a '..' segment, so the
	 * strings are already normalized and no directory has to exist for them to
	 * be built. The cache one is not shipped, so an application that keeps the
	 * defaults still has to create it or reassign the property.
	 */
	public function testInitializeDefaultsToDirectoriesUnderTheDocroot() {
		MockedApplication::initialize(Environment::DEV, true, false);
		$this->restoreErrorHandlers();

		$config = MockedApplication::config();
		$docroot = dirname(__DIR__, 2);

		$this->assertSame($docroot, $config->dirDocRoot);
		$this->assertSame($docroot . '/app', $config->dirAppRoot);
		$this->assertSame($docroot . '/public', $config->dirPublic);
		$this->assertSame($docroot . '/cache', $config->dirCache);
		$this->assertSame($docroot . '/templates', $config->dirTemplates);
		$this->assertDirectoryDoesNotExist($config->dirCache);
		$this->assertSame(Runtime::CLI, $config->runtime);
	}

	/**
	 * CLI-ness used to be re-sniffed from $_SERVER on every call. It is now
	 * resolved once, by createConfig(), and carried on the config - so these
	 * drive the factory directly rather than a global.
	 */
	public function testCreateConfigFlagsCliWhenServerProtocolIsAbsent() {
		unset($_SERVER['SERVER_PROTOCOL']);

		$this->assertSame(Runtime::CLI, MockedApplication::callCreateConfig(Environment::TEST)->runtime);
	}

	public function testCreateConfigFlagsWebWhenServerProtocolIsPresent() {
		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

		$this->assertSame(Runtime::WEB, MockedApplication::callCreateConfig(Environment::TEST)->runtime);
	}

	/** The runtime is a snapshot: a later $_SERVER change does not reach an existing config. */
	public function testRuntimeIsNotReSniffedAfterTheConfigIsBuilt() {
		unset($_SERVER['SERVER_PROTOCOL']);
		$config = MockedApplication::callCreateConfig(Environment::TEST);

		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

		$this->assertSame(Runtime::CLI, $config->runtime);
	}

	/** No default carries a '..' segment, so the paths need no further normalizing. */
	public function testInitializeDefaultsAreAlreadyNormalized() {
		MockedApplication::initialize(Environment::DEV, true, false);
		$this->restoreErrorHandlers();

		$config = MockedApplication::config();

		foreach (['dirDocRoot', 'dirAppRoot', 'dirPublic', 'dirCache', 'dirTemplates'] as $property) {
			$this->assertStringNotContainsString('/../', $config->$property, $property . ' still holds a relative segment');
		}
	}

	/**
	 * initialize() takes its config from the createConfig() hook, so a subclass can
	 * install its own BaseConfig subclass rather than having the base one forced on it.
	 */
	public function testInitializeUsesCreateConfigOverride() {
		$config = new BaseConfig(Environment::TEST, false, false, dirCache: $this->makeTempDir());
		MockedApplication::$configFactoryResult = $config;

		MockedApplication::initialize(Environment::DEV, true, false);
		$this->restoreErrorHandlers();

		$this->assertSame($config, MockedApplication::config());
	}

	/**
	 * The config has to be in place before the first lifecycle step, not after
	 * initialize() returns - initializeErrorHandling() and initializeContainer()
	 * both read directories off it.
	 */
	public function testInitializeInstallsConfigBeforeErrorHandling() {
		$config = new BaseConfig(Environment::TEST, false, false, dirCache: $this->makeTempDir());
		MockedApplication::$configFactoryResult = $config;

		MockedApplication::initialize(Environment::DEV, true, false);
		$this->restoreErrorHandlers();

		$this->assertSame($config, MockedApplication::$configAtErrorHandling);
	}

	public function testInitializeBuildsTheContainer() {
		MockedApplication::setContainer(null);
		MockedApplication::initialize(Environment::TEST, false, false);
		$this->restoreErrorHandlers();

		$this->assertInstanceOf(Container::class, BaseApplication::$container);
	}

	public function testInitializeErrorHandlingReturnsHandler() {
		MockedApplication::setConfig(new BaseConfig(Environment::TEST, true, false));

		$handler = MockedApplication::callInitializeErrorHandling();
		$this->restoreErrorHandlers();

		$this->assertInstanceOf(\Symfony\Component\ErrorHandler\ErrorHandler::class, $handler);
	}

	/** ErrorHandler::register() installs itself globally; PHPUnit needs its own back. */
	private function restoreErrorHandlers(): void {
		restore_error_handler();
		restore_exception_handler();
	}

	public function testBuildContainerResolvesTheFrameworkServices() {
		$container = $this->buildContainer();

		$expected = [
			'kernel' => Kernel::class,
			'request_stack' => RequestStack::class,
			'router' => Router::class,
			'inflector' => EnglishInflector::class,
			'mime' => MimeTypes::class,
			'slugger' => AsciiSlugger::class,
			'filesystem' => Filesystem::class,
		];

		foreach ($expected as $id => $class) {
			$this->assertTrue($container->has($id), $id . ' should be registered');
			$this->assertInstanceOf($class, $container->get($id));
		}
	}

	/**
	 * The kernel, the router listener and getCurrentRequest() all have to see the
	 * same request stack, so every service is shared: one instance per container.
	 */
	public function testBuildContainerSharesServices() {
		$container = $this->buildContainer();

		$this->assertSame($container->get('request_stack'), $container->get('request_stack'));
		$this->assertSame($container->get('router'), $container->get('router'));
	}

	/**
	 * Symfony's DI sorted the resolvers by a priority tag; here registration order
	 * is the priority, and the tag hands them back in that order.
	 */
	public function testBuildContainerTagsArgumentValueResolversInPriorityOrder() {
		$resolvers = $this->buildContainer()->get('controller.argument_value_resolver');

		$this->assertSame([
			BackedEnumValueResolver::class,
			RequestAttributeValueResolver::class,
			RequestValueResolver::class,
			SessionValueResolver::class,
			ServiceValueResolver::class,
			DefaultValueResolver::class,
			VariadicValueResolver::class,
		], array_map(static fn(object $resolver) => $resolver::class, $resolvers));
	}

	public function testArgumentResolverFillsControllerArgumentsFromTheRequest() {
		$request = Request::create('/dev/dump');
		$request->attributes->set('id', 3);
		$controller = static fn(Request $request, int $id, string $name = 'anonymous') => null;

		$arguments = $this->buildContainer()->get('argument_resolver')->getArguments($request, $controller);

		$this->assertSame([$request, 3, 'anonymous'], $arguments);
	}

	/**
	 * The query parameter resolver is not in the regular chain: it only runs for
	 * an argument that names it through #[MapQueryParameter], which the argument
	 * resolver looks up by class name.
	 */
	public function testArgumentResolverMapsQueryParametersOnRequest() {
		$request = Request::create('/dev/dump', 'GET', ['page' => '5']);
		$controller = static fn(#[MapQueryParameter] int $page) => null;

		$arguments = $this->buildContainer()->get('argument_resolver')->getArguments($request, $controller);

		$this->assertSame([5], $arguments);
	}

	public function testBuildContainerSubscribesTheKernelListeners() {
		$dispatcher = $this->buildContainer()->get('dispatcher');

		$listenerClasses = static fn(array $listeners) => array_map(static fn(array $listener) => $listener[0]::class, $listeners);

		$this->assertContains(RouterListener::class, $listenerClasses($dispatcher->getListeners(KernelEvents::REQUEST)));
		$this->assertContains(ResponseListener::class, $listenerClasses($dispatcher->getListeners(KernelEvents::RESPONSE)));
	}

	/** The response listener is built with the application's encoding type as its charset. */
	public function testResponseListenerAppliesTheEncodingType() {
		$container = $this->buildContainer();
		$response = new Response('body');
		$event = new ResponseEvent($container->get('kernel'), Request::create('/dev/dump'), HttpKernelInterface::MAIN_REQUEST, $response);

		$container->get('dispatcher')->dispatch($event, KernelEvents::RESPONSE);

		$this->assertSame(BaseApplication::$encodingType, $response->getCharset());
	}

	/**
	 * The whole request path through the container's wiring: the router listener
	 * matches, the controller resolver loads the fixture action, the argument
	 * resolver fills the route parameter and the response listener sets the charset.
	 */
	public function testKernelServesARequestThroughTheContainerWiring() {
		$response = $this->buildContainer()->get('kernel')->handle(Request::create('/fixture/7'));

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('7', $response->getContent());
		$this->assertSame(BaseApplication::$encodingType, $response->getCharset());
	}

	/**
	 * The router listener turns a routing miss into NotFoundHttpException before the
	 * kernel reaches its own 404 fallback, and with no exception listener it escapes
	 * to the error handler registered by initialize().
	 */
	public function testKernelThrowsNotFoundForAnUnknownPath() {
		$this->expectException(NotFoundHttpException::class);

		$this->buildContainer()->get('kernel')->handle(Request::create('/no/such/route'));
	}

	/** Interfaces are not registered: services are fetched by their string id only. */
	public function testInterfacesAreNotRegisteredAsServices() {
		$container = $this->buildContainer();

		$this->assertFalse($container->has(MimeTypesInterface::class));
		$this->assertFalse($container->has(SluggerInterface::class));
	}

	/** The router loads its routes through getRoutes(), so getRoutesDirs() is all an application supplies. */
	public function testRouterLoadsRoutesFromTheRoutesDirs() {
		$routes = $this->buildContainer()->get('router')->getRouteCollection();

		$this->assertCount(3, $routes);
		$this->assertSame('/dev/dump', $routes->get('devDump')->getPath());
	}

	public function testRouterWritesItsCacheUnderTheCacheDirWhenCachingIsOn() {
		$dir = $this->makeTempDir();
		MockedApplication::setConfig(new BaseConfig(Environment::TEST, false, true, dirCache: $dir));

		$match = $this->buildContainer()->get('router')->match('/dev/dump');

		$this->assertSame('devDump', $match['_route']);
		$this->assertFileExists($dir . '/routes/url_matching_routes.php');
	}

	public function testRouterWritesNothingWhenCachingIsOff() {
		$dir = $this->makeTempDir();
		MockedApplication::setConfig(new BaseConfig(Environment::TEST, false, false, dirCache: $dir));

		$match = $this->buildContainer()->get('router')->match('/dev/dump');

		$this->assertSame('devDump', $match['_route']);
		$this->assertDirectoryDoesNotExist($dir);
	}

	/**
	 * How an application customises the container: after parent::buildContainer()
	 * a definition can be replaced outright. The base wiring refers to the router
	 * by id only, so the router listener picks up the replacement.
	 */
	public function testAnApplicationCanReplaceTheRouterAfterTheBaseBuiltIt() {
		$dir = $this->makeTempDir();
		$container = $this->buildContainer();

		$container->addShared('router', Router::class, overwrite: true)
			->addArguments([
				'routes_loader_closure',
				new CallableArgument(MockedApplication::getRoutes(...)),
				new ArrayArgument(['cache_dir' => $dir . '/custom']),
				'context',
			]);

		$response = $container->get('kernel')->handle(Request::create('/dev/dump'));

		$this->assertSame('dump', $response->getContent());
		$this->assertFileExists($dir . '/custom/url_matching_routes.php');
	}

	public function testGetCommandDirs() {
		$dirs = MockedApplication::getCommandDirs();

		$this->assertSame(['Cog\\Command' => dirname(__DIR__) . '/Command'], $dirs);
		$this->assertDirectoryExists($dirs['Cog\\Command']);
	}

	public function testGetRoutesDirsIsEmptyOnTheFramework() {
		$this->assertSame([], BaseApplication::getRoutesDirs());
	}

	public function testGetRoutesIsEmptyWithoutRoutesDirs() {
		MockedApplication::$routesDirs = [];

		$this->assertCount(0, MockedApplication::getRoutes());
	}

	public function testGetRoutesLoadsAttributeRoutesFromTheGivenDirectories() {
		MockedApplication::$routesDirs = [__DIR__ . '/fixtures/Controller'];

		$routes = MockedApplication::getRoutes();

		$this->assertCount(3, $routes);
		$this->assertSame(['devDump', 'devPhpInfo', 'fixtureWithParameter'], array_keys($routes->all()));
		$this->assertSame('/dev/dump', $routes->get('devDump')->getPath());
		$this->assertSame(
			'Cog\Test\fixtures\Controller\FixtureController::dumpAction',
			$routes->get('devDump')->getDefault('_controller')
		);
		$this->assertSame(['id' => '\d+'], $routes->get('fixtureWithParameter')->getRequirements());
	}

	public function testGetCurrentRequestIsNullWithAnEmptyStack() {
		MockedApplication::setContainer($this->buildContainer());

		$this->assertNull(MockedApplication::getCurrentRequest());
	}

	public function testGetCurrentRequestReturnsWhatWasPushedOntoTheStack() {
		$container = $this->buildContainer();
		MockedApplication::setContainer($container);

		$container->get('request_stack')->push(Request::create('/dev/dump'));

		$this->assertSame('/dev/dump', MockedApplication::getCurrentRequest()->getPathInfo());
	}

	/** A container without a request_stack yields null rather than an exception. */
	public function testGetCurrentRequestIsNullWhenTheContainerHasNoRequestStack() {
		MockedApplication::setContainer(new Container());

		$this->assertNull(MockedApplication::getCurrentRequest());
	}

	/**
	 * There is no compiled container to dump any more: the definitions are cheap
	 * enough to rebuild on every request, so the cache switch leaves the container
	 * alone and only the router still writes under the cache directory.
	 */
	public function testInitializeContainerWritesNothingEvenWhenCachingIsOn() {
		$dir = $this->makeTempDir();
		MockedApplication::setConfig(new BaseConfig(Environment::TEST, false, true, dirCache: $dir));
		MockedApplication::setContainer(null);

		MockedApplication::callInitializeContainer();

		$this->assertInstanceOf(Container::class, BaseApplication::$container);
		$this->assertDirectoryDoesNotExist($dir);
	}

	public function testInitializeContainerKeepsAnAlreadyBuiltContainer() {
		$container = $this->buildContainer();
		MockedApplication::setContainer($container);
		MockedApplication::setConfig(new BaseConfig(Environment::TEST, false, false, dirCache: $this->makeTempDir()));

		MockedApplication::callInitializeContainer();

		$this->assertSame($container, BaseApplication::$container);
	}

	public function testDisplayProfilingStaysSilentWhenDebugIsOff() {
		MockedApplication::setConfig(new BaseConfig(Environment::PROD, false, false));

		$this->expectOutputString('');
		MockedApplication::displayProfiling();
	}

	public function testDisplayProfilingRendersTheBarWhenForced() {
		MockedApplication::setContainer($this->buildContainer());
		MockedApplication::setConfig(new BaseConfig(Environment::TEST, false, false));

		ob_start();
		MockedApplication::displayProfiling(true);
		$output = ob_get_clean();

		$this->assertStringContainsString(Environment::TEST->value, $output);
		$this->assertStringContainsString(BaseApplication::FRAMEWORK_VERSION, $output);
		$this->assertStringContainsString('php ' . phpversion(), $output);
		$this->assertStringContainsString('href="' . Url::getForRoute('devDump') . '"', $output);
		$this->assertStringContainsString('href="' . Url::getForRoute('devPhpInfo') . '"', $output);
	}

	/** The bar reports the route and controller of the request being served. */
	public function testDisplayProfilingReportsTheCurrentRoute() {
		$container = $this->buildContainer();
		MockedApplication::setContainer($container);
		MockedApplication::setConfig(new BaseConfig(Environment::DEV, true, false));

		$request = Request::create('/dev/dump');
		$request->attributes->set('_route', 'devDump');
		$request->attributes->set('_controller', ['FixtureController', 'dumpAction']);
		$container->get('request_stack')->push($request);

		ob_start();
		MockedApplication::displayProfiling();
		$output = ob_get_clean();

		$this->assertStringContainsString('>devDump<', $output);
		$this->assertStringContainsString('title="FixtureController::dumpAction"', $output);
	}
}
