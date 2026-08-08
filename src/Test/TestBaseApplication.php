<?php

namespace Cog\Test;

use Cog\BaseApplication;
use Cog\BaseConfig;
use Cog\Enum\Environment;
use Cog\Kernel;
use Cog\Util\Url;
use ArgumentCountError;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
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
 * The base container is deliberately incomplete: it registers 'router' without a
 * $resource, so the application layer has to supply one. Anything that needs a
 * working router goes through buildRoutedContainer().
 */
class TestBaseApplication extends TestCase {

	private ?Container $container;
	private BaseConfig $config;
	private array $routesDirs;
	private string $tempDir = '';

	public function setUp(): void {
		$this->container = BaseApplication::$container;
		$this->config = BaseApplication::config();
		$this->routesDirs = MockedApplication::$routesDirs;
	}

	public function tearDown(): void {
		MockedApplication::setContainer($this->container);
		MockedApplication::setConfig($this->config);
		MockedApplication::$routesDirs = $this->routesDirs;

		if ($this->tempDir !== '') {
			(new Filesystem())->remove($this->tempDir);
			$this->tempDir = '';
		}
	}

	/** A container the application layer has finished wiring: router given its routes. */
	private function buildRoutedContainer(): ContainerBuilder {
		MockedApplication::$routesDirs = [__DIR__ . '/fixtures/Controller'];

		$container = MockedApplication::callBuildContainer();
		$container->getDefinition('router')
			->setArgument('$resource', [MockedApplication::class, 'getRoutes'])
			->setArgument('$options', []);
		$container->compile();

		return $container;
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
	 * The cache and template directories default to paths inside src/ - the
	 * template one is not even shipped, so applications are expected to
	 * reassign both rather than rely on the defaults.
	 */
	public function testInitializeDefaultsToDirectoriesInsideSrc() {
		MockedApplication::initialize(Environment::DEV, true, false);
		$this->restoreErrorHandlers();

		$config = MockedApplication::config();

		$this->assertSame(dirname(__DIR__) . '/cache', $config->dirCache);
		$this->assertSame(dirname(__DIR__) . '/templates', $config->dirTemplates);
		$this->assertDirectoryDoesNotExist($config->dirTemplates);
	}

	public function testInitializeBuildsCompiledContainer() {
		MockedApplication::setContainer(null);
		MockedApplication::initialize(Environment::TEST, false, false);
		$this->restoreErrorHandlers();

		$this->assertInstanceOf(ContainerBuilder::class, BaseApplication::$container);
		$this->assertTrue(BaseApplication::$container->isCompiled());
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

	public function testBuildContainerMarksTheIntendedServicesPublic() {
		$container = MockedApplication::callBuildContainer();

		foreach (['kernel', 'request_stack', 'router', 'inflector', 'mime', 'slugger', 'filesystem'] as $id) {
			$this->assertTrue($container->getDefinition($id)->isPublic(), $id . ' should be public');
		}

		foreach (['context', 'dispatcher', 'controller_resolver', 'session_factory'] as $id) {
			$this->assertFalse($container->getDefinition($id)->isPublic(), $id . ' should be private');
		}
	}

	public function testBuildContainerTagsArgumentValueResolversWithPriorities() {
		$container = MockedApplication::callBuildContainer();

		$priorities = [];
		foreach ($container->findTaggedServiceIds('controller.argument_value_resolver') as $id => $tags) {
			$priorities[$id] = $tags[0]['priority'];
		}

		$this->assertSame([
			'argument_resolver.backed_enum_resolver' => 100,
			'argument_resolver.uid' => 100,
			'argument_resolver.request_attribute' => 100,
			'argument_resolver.request' => 50,
			'argument_resolver.session' => 50,
			'argument_resolver.service' => -50,
			'argument_resolver.variadic' => -150,
			'argument_resolver.default' => -100,
		], $priorities);
	}

	public function testBuildContainerTagsTheTargetedResolverSeparately() {
		$container = MockedApplication::callBuildContainer();

		$this->assertSame(
			['argument_resolver.query_parameter_value_resolver'],
			array_keys($container->findTaggedServiceIds('controller.targeted_value_resolver'))
		);
	}

	public function testBuildContainerSetsKernelDebugOff() {
		$this->assertFalse(MockedApplication::callBuildContainer()->getParameter('kernel.debug'));
	}

	public function testBuildContainerPassesEncodingTypeToTheResponseListener() {
		$container = MockedApplication::callBuildContainer();

		$this->assertSame(
			BaseApplication::$encodingType,
			$container->getDefinition('listener.response')->getArgument('$charset')
		);
	}

	public function testBuildContainerAliasesInterfacesToImplementations() {
		$container = MockedApplication::callBuildContainer();

		$this->assertSame('mime', (string)$container->getAlias(MimeTypesInterface::class));
		$this->assertSame('slugger', (string)$container->getAlias(SluggerInterface::class));
	}

	public function testCompiledContainerResolvesPublicServices() {
		$container = $this->buildRoutedContainer();

		$this->assertInstanceOf(RequestStack::class, $container->get('request_stack'));
		$this->assertInstanceOf(EnglishInflector::class, $container->get('inflector'));
		$this->assertInstanceOf(MimeTypes::class, $container->get('mime'));
		$this->assertInstanceOf(AsciiSlugger::class, $container->get('slugger'));
		$this->assertInstanceOf(Filesystem::class, $container->get('filesystem'));
	}

	/**
	 * The interface aliases are registered without being made public, so they
	 * serve autowiring by type and are not fetchable from the container the way
	 * the services they point at are.
	 */
	public function testInterfaceAliasesAreNotPubliclyFetchable() {
		$container = $this->buildRoutedContainer();

		$this->assertFalse($container->has(MimeTypesInterface::class));
		$this->assertFalse($container->has(SluggerInterface::class));
	}

	/** Private services are inlined away by compilation and cannot be fetched. */
	public function testCompiledContainerInlinesPrivateServices() {
		$container = $this->buildRoutedContainer();

		$this->assertFalse($container->has('dispatcher'));
		$this->assertFalse($container->has('context'));

		$this->expectException(ServiceNotFoundException::class);
		$container->get('dispatcher');
	}

	/**
	 * The base container registers 'router' without a $resource: the framework
	 * has no notion of where an app's controllers live, so the application
	 * subclass has to supply it. Until it does, the kernel cannot be built.
	 */
	public function testRouterIsIncompleteWithoutTheApplicationLayer() {
		$container = MockedApplication::callBuildContainer();
		$container->compile();

		$this->expectException(ArgumentCountError::class);
		$container->get('router');
	}

	public function testKernelResolvesOnceTheRouterHasItsResource() {
		$container = $this->buildRoutedContainer();

		$this->assertInstanceOf(Router::class, $container->get('router'));
		$this->assertInstanceOf(Kernel::class, $container->get('kernel'));
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
		MockedApplication::setContainer($this->buildRoutedContainer());

		$this->assertNull(MockedApplication::getCurrentRequest());
	}

	public function testGetCurrentRequestReturnsWhatWasPushedOntoTheStack() {
		$container = $this->buildRoutedContainer();
		MockedApplication::setContainer($container);

		$container->get('request_stack')->push(Request::create('/dev/dump'));

		$this->assertSame('/dev/dump', MockedApplication::getCurrentRequest()->getPathInfo());
	}

	/** A container without a request_stack yields null rather than an exception. */
	public function testGetCurrentRequestIsNullWhenTheContainerHasNoRequestStack() {
		$empty = new ContainerBuilder();
		$empty->compile();
		MockedApplication::setContainer($empty);

		$this->assertNull(MockedApplication::getCurrentRequest());
	}

	public function testInitializeContainerDumpsTheContainerWhenCachingIsOn() {
		$dir = $this->makeTempDir();
		MockedApplication::setConfig(new BaseConfig(Environment::TEST, false, true, $dir, ''));
		MockedApplication::setContainer(null);

		MockedApplication::callInitializeContainer();

		$this->assertFileExists($dir . '/container/ProjectServiceContainer.php');
		$this->assertFileExists($dir . '/container/ProjectServiceContainer.preload.php');
	}

	/**
	 * The cached branch is only reachable once per file per process, because
	 * initializeContainer() pulls the dump in with require_once.
	 */
	public function testInitializeContainerLoadsTheDumpedContainer() {
		$dir = $this->makeTempDir();
		MockedApplication::setConfig(new BaseConfig(Environment::TEST, false, true, $dir, ''));

		MockedApplication::setContainer(null);
		MockedApplication::callInitializeContainer();

		MockedApplication::setContainer(null);
		MockedApplication::callInitializeContainer();

		$this->assertInstanceOf(Container::class, BaseApplication::$container);
		$this->assertNotInstanceOf(ContainerBuilder::class, BaseApplication::$container);
	}

	public function testInitializeContainerWritesNothingWhenCachingIsOff() {
		$dir = $this->makeTempDir();
		MockedApplication::setConfig(new BaseConfig(Environment::TEST, false, false, $dir, ''));
		MockedApplication::setContainer(null);

		MockedApplication::callInitializeContainer();

		$this->assertInstanceOf(ContainerBuilder::class, BaseApplication::$container);
		$this->assertDirectoryDoesNotExist($dir);
	}

	public function testInitializeContainerKeepsAnAlreadyBuiltContainer() {
		$container = $this->buildRoutedContainer();
		MockedApplication::setContainer($container);
		MockedApplication::setConfig(new BaseConfig(Environment::TEST, false, false, $this->makeTempDir(), ''));

		MockedApplication::callInitializeContainer();

		$this->assertSame($container, BaseApplication::$container);
	}

	public function testDisplayProfilingStaysSilentWhenDebugIsOff() {
		MockedApplication::setConfig(new BaseConfig(Environment::PROD, false, false));

		$this->expectOutputString('');
		MockedApplication::displayProfiling();
	}

	public function testDisplayProfilingRendersTheBarWhenForced() {
		MockedApplication::setContainer($this->buildRoutedContainer());
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
		$container = $this->buildRoutedContainer();
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
