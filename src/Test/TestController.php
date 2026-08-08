<?php

namespace Cog\Test;

use Cog\BaseApplication;
use Cog\Exceptions\CogException;
use Cog\Exceptions\RedirectException;
use Cog\Test\fixtures\ControllerBase\FixtureBaseController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * ControllerBase and the route loader that discovers its actions.
 *
 * render() and findTemplate() are protected and findTemplate() reads the call
 * stack to decide which template belongs to the caller, so everything is driven
 * through the fixture controller's real actions rather than called directly.
 */
class TestController extends TestCase {

	private const string FIXTURE_DIR = __DIR__ . '/fixtures/ControllerBase';

	private ?Container $container;
	private array $routesDirs;

	public function setUp(): void {
		$this->container = BaseApplication::$container;
		$this->routesDirs = MockedApplication::$routesDirs;
		MockedApplication::$routesDirs = [self::FIXTURE_DIR];
	}

	public function tearDown(): void {
		MockedApplication::setContainer($this->container);
		MockedApplication::$routesDirs = $this->routesDirs;
	}

	private function controller(string $path = '/base/show'): FixtureBaseController {
		$controller = new FixtureBaseController();
		$controller->setRequest(Request::create($path));

		return $controller;
	}

	/** Points BaseApplication::$container at a container whose router knows the fixture routes. */
	private function useRoutedContainer(): void {
		$container = MockedApplication::callBuildContainer();
		$container->getDefinition('router')
			->setArgument('$resource', [MockedApplication::class, 'getRoutes'])
			->setArgument('$options', []);
		$container->compile();

		MockedApplication::setContainer($container);
	}

	//////////////////////////////
	// render() and findTemplate()
	//////////////////////////////

	/**
	 * With no template named, findTemplate() builds one from the controller's
	 * file name and the action that called render() - showAction() looks for
	 * FixtureBaseControllerShow.tpl.php beside the class.
	 */
	public function testRenderFindsTheTemplateByConvention() {
		$response = $this->controller('/base/show')->showAction();

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame("shown for /base/show", $response->getContent());
	}

	/** The controller and the request are always available to the template. */
	public function testRenderPassesTheControllerAndRequestAsTokens() {
		$response = $this->controller('/base/show?q=1')->showAction();

		$this->assertStringContainsString('/base/show', $response->getContent());
	}

	public function testRenderAcceptsAnExplicitTemplateAndTokens() {
		$response = $this->controller()->explicitAction();

		$this->assertSame("explicit: given", $response->getContent());
	}

	/** No template by that name and none by convention either. */
	public function testRenderThrowsWhenNoTemplateCanBeFound() {
		$this->expectException(CogException::class);

		$this->controller()->missingAction();
	}

	public function testRenderEmitsNothingOfItsOwn() {
		$this->expectOutputString('');

		$this->controller()->showAction();
	}

	//////////////////////////////
	// Redirects
	//////////////////////////////

	public function testRedirectThrowsARedirectException() {
		try {
			$this->controller()->leaveAction();
			$this->fail('expected a RedirectException');
		} catch (RedirectException $exception) {
			$this->assertSame('/gone', $exception->location);
			$this->assertSame(301, $exception->status);
		}
	}

	public function testRedirectToRouteResolvesTheRouteFirst() {
		$this->useRoutedContainer();

		try {
			$this->controller()->leaveToRouteAction();
			$this->fail('expected a RedirectException');
		} catch (RedirectException $exception) {
			$this->assertSame('/base/show', $exception->location);
			$this->assertSame(302, $exception->status);
		}
	}

	/** Url::getForRoute() is the piece redirectToRoute() leans on. */
	public function testUrlForRouteGeneratesAnAbsoluteUrlWhenAsked() {
		$this->useRoutedContainer();

		$this->assertSame(
			'/base/show',
			\Cog\Util\Url::getForRoute('fixtureBaseShow')
		);
		$this->assertStringEndsWith(
			'/base/show',
			\Cog\Util\Url::getForRoute('fixtureBaseShow', [], UrlGeneratorInterface::ABSOLUTE_URL)
		);
	}

	//////////////////////////////
	// AttributeRouteControllerLoader
	//////////////////////////////

	/** The loader sets _controller to Class::method for a named action. */
	public function testLoaderSetsTheControllerDefault() {
		$routes = MockedApplication::getRoutes();

		$this->assertSame(
			FixtureBaseController::class . '::showAction',
			$routes->get('fixtureBaseShow')->getDefault('_controller')
		);
	}

	/**
	 * An action with no name given gets one derived from the class and method,
	 * with the "controller" keyword and the "Action" suffix taken back out.
	 */
	public function testLoaderInventsANameForAnUnnamedRoute() {
		$names = array_keys(MockedApplication::getRoutes()->all());

		$generated = array_values(array_filter(
			$names,
			static fn(string $name) => !str_starts_with($name, 'fixtureBase')
		));

		$this->assertCount(1, $generated);
		$this->assertStringNotContainsString('controller_', $generated[0]);
		$this->assertStringNotContainsString('action', $generated[0]);
		$this->assertStringEndsWith('unnamed', $generated[0]);
	}

	public function testLoaderFindsEveryActionInTheDirectory() {
		$routes = MockedApplication::getRoutes();

		$this->assertCount(6, $routes);
		$this->assertSame('/base/explicit', $routes->get('fixtureBaseExplicit')->getPath());
	}
}
