<?php

namespace Cog\Test;

use Cog\Kernel;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ControllerDoesNotReturnResponseException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\EventListener\ResponseListener;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Router;

/**
 * Drives Cog\Kernel with the collaborators it is wired to in
 * BaseApplication::buildContainer() - a RouterListener, a ResponseListener, the
 * stock controller and argument resolvers - rather than with test doubles, so
 * what is asserted here is the request path an application actually gets.
 *
 * Routes come from the fixture controller in fixtures/Kernel, loaded through
 * BaseApplication::getRoutes() by way of MockedApplication.
 */
class TestKernel extends TestCase {

	private RequestStack $requestStack;
	private EventDispatcher $dispatcher;
	private array $routesDirs;

	public function setUp(): void {
		$this->routesDirs = MockedApplication::$routesDirs;
		MockedApplication::$routesDirs = [__DIR__ . '/fixtures/Kernel'];

		$this->requestStack = new RequestStack();

		$router = new Router(
			new ClosureLoader(),
			[MockedApplication::class, 'getRoutes'],
			[],
			new RequestContext()
		);

		$this->dispatcher = new EventDispatcher();
		$this->dispatcher->addSubscriber(new RouterListener($router, $this->requestStack));
		$this->dispatcher->addSubscriber(new ResponseListener('UTF-8'));
	}

	public function tearDown(): void {
		MockedApplication::$routesDirs = $this->routesDirs;
	}

	private function kernel(): Kernel {
		return new Kernel(
			$this->dispatcher,
			new ControllerResolver(),
			new ArgumentResolver(),
			$this->requestStack
		);
	}

	private function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): Response {
		return $this->kernel()->handle($request, $type, $catch);
	}

	public function testHandleReturnsTheControllerResponse() {
		$response = $this->handle(Request::create('/kernel/ok'));

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('ok', $response->getContent());
	}

	/** The ResponseListener is what puts the application charset on the response. */
	public function testHandleAppliesTheResponseListenerCharset() {
		$response = $this->handle(Request::create('/kernel/ok'));

		$this->assertSame('UTF-8', $response->getCharset());
		$this->assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
	}

	public function testHandleResolvesRouteParametersIntoControllerArguments() {
		$response = $this->handle(Request::create('/kernel/param/42'));

		$this->assertSame('id:42', $response->getContent());
	}

	public function testHandleResolvesTheRequestArgument() {
		$response = $this->handle(Request::create('/kernel/request'));

		$this->assertSame('path:/kernel/request', $response->getContent());
	}

	/** The request is pushed for the duration of the call and popped by finishRequest(). */
	public function testHandleLeavesTheRequestStackEmpty() {
		$this->handle(Request::create('/kernel/ok'));

		$this->assertNull($this->requestStack->getCurrentRequest());
	}

	public function testHandleTurnsARedirectExceptionIntoARedirectResponse() {
		$response = $this->handle(Request::create('/kernel/redirect'));

		$this->assertInstanceOf(RedirectResponse::class, $response);
		$this->assertSame(301, $response->getStatusCode());
		$this->assertSame('/somewhere-else', $response->getTargetUrl());
	}

	/** A listener answering the REQUEST event short-circuits the controller entirely. */
	public function testHandleShortCircuitsWhenARequestListenerAnswers() {
		$this->dispatcher->addListener(KernelEvents::REQUEST, static function (RequestEvent $event) {
			$event->setResponse(new Response('from the listener', 202));
		}, 1000);

		$response = $this->handle(Request::create('/kernel/ok'));

		$this->assertSame(202, $response->getStatusCode());
		$this->assertSame('from the listener', $response->getContent());
		$this->assertNull($this->requestStack->getCurrentRequest());
	}

	/** Without a VIEW listener, a controller that returns something else is an error. */
	public function testControllerReturningANonResponseThrows() {
		$this->expectException(ControllerDoesNotReturnResponseException::class);

		$this->handle(Request::create('/kernel/string'), catch: false);
	}

	public function testControllerReturningNullIsToldItForgotToReturn() {
		try {
			$this->handle(Request::create('/kernel/void'), catch: false);
			$this->fail('expected a ControllerDoesNotReturnResponseException');
		} catch (ControllerDoesNotReturnResponseException $exception) {
			$this->assertStringContainsString('Did you forget to add a return statement', $exception->getMessage());
		}
	}

	/**
	 * The message names what came back instead of a Response, for each shape
	 * varToString() knows about.
	 */
	public function testControllerReturnValueIsDescribedInTheMessage() {
		$cases = [
			'/kernel/string' => 'a string ("not a response")',
			'/kernel/array' => 'an array',
			'/kernel/bool' => 'a boolean value (true)',
			'/kernel/object' => 'an object of type stdClass',
			'/kernel/void' => 'null',
		];

		foreach ($cases as $path => $expected) {
			try {
				$this->handle(Request::create($path), catch: false);
				$this->fail('expected ' . $path . ' to be rejected');
			} catch (ControllerDoesNotReturnResponseException $exception) {
				$this->assertStringContainsString($expected, $exception->getMessage(), $path);
			}
		}
	}

	/** A VIEW listener gets the chance to turn that return value into a response. */
	public function testViewListenerConvertsTheControllerReturnValue() {
		$this->dispatcher->addListener(KernelEvents::VIEW, static function (ViewEvent $event) {
			$event->setResponse(new Response('viewed: ' . $event->getControllerResult()));
		});

		$response = $this->handle(Request::create('/kernel/string'));

		$this->assertSame('viewed: not a response', $response->getContent());
	}

	public function testUnroutableRequestThrowsNotFound() {
		$this->expectException(NotFoundHttpException::class);

		$this->handle(Request::create('/no/such/path'));
	}

	/** With no EXCEPTION listener willing to answer, the throwable is rethrown. */
	public function testUncaughtThrowableIsRethrownAndTheStackUnwound() {
		try {
			$this->handle(Request::create('/kernel/boom'));
			$this->fail('expected the controller exception to be rethrown');
		} catch (RuntimeException $exception) {
			$this->assertSame('boom', $exception->getMessage());
		}

		$this->assertNull($this->requestStack->getCurrentRequest());
	}

	/**
	 * The kernel does no output buffering of its own: a controller communicates
	 * through the Response it returns, and anything it echoes goes straight out,
	 * where it can be seen rather than silently swallowed.
	 */
	public function testKernelOpensNoOutputBufferOfItsOwn() {
		$level = ob_get_level();

		$this->kernel()->handle(Request::create('/kernel/ok'));
		$this->assertSame($level, ob_get_level(), 'after a successful request');

		$this->kernel()->handle(Request::create('/kernel/redirect'));
		$this->assertSame($level, ob_get_level(), 'after a redirect');

		try {
			$this->kernel()->handle(Request::create('/kernel/boom'));
		} catch (RuntimeException) {
			// the buffer level is the point, not the exception
		}
		$this->assertSame($level, ob_get_level(), 'after a controller threw');
	}

	public function testControllerOutputReachesTheClient() {
		$this->expectOutputString('stray output from a successful controller');

		$response = $this->kernel()->handle(Request::create('/kernel/chatty'));

		$this->assertSame('quiet', $response->getContent());
	}

	/** Same for a controller that echoes and then throws. */
	public function testOutputEchoedBeforeAThrowReachesTheClient() {
		$this->expectOutputString('output from a controller that then threw');

		$this->expectException(RuntimeException::class);
		$this->kernel()->handle(Request::create('/kernel/leak'));
	}

	public function testCatchFalseSkipsTheExceptionListenersEntirely() {
		$seen = false;
		$this->dispatcher->addListener(KernelEvents::EXCEPTION, static function (ExceptionEvent $event) use (&$seen) {
			$seen = true;
			$event->setResponse(new Response('handled'));
		});

		try {
			$this->handle(Request::create('/kernel/boom'), catch: false);
			$this->fail('expected the controller exception to escape');
		} catch (RuntimeException) {
			// expected
		}

		$this->assertFalse($seen, 'the exception listener should not have run');
		$this->assertNull($this->requestStack->getCurrentRequest());
	}

	public function testExceptionListenerResponseIsUsed() {
		$this->dispatcher->addListener(KernelEvents::EXCEPTION, static function (ExceptionEvent $event) {
			$event->setResponse(new Response('handled: ' . $event->getThrowable()->getMessage()));
		});

		$response = $this->handle(Request::create('/kernel/boom'));

		$this->assertSame('handled: boom', $response->getContent());
	}

	/** A plain throwable with a 200 response from the listener becomes a 500. */
	public function testExceptionListenerResponseWithoutAStatusBecomesServerError() {
		$this->dispatcher->addListener(KernelEvents::EXCEPTION, static function (ExceptionEvent $event) {
			$event->setResponse(new Response('handled'));
		});

		$response = $this->handle(Request::create('/kernel/boom'));

		$this->assertSame(500, $response->getStatusCode());
	}

	/** An HttpException lends its status code and headers to that response. */
	public function testHttpExceptionStatusAndHeadersAreCopiedOntoTheResponse() {
		$this->dispatcher->addListener(KernelEvents::EXCEPTION, static function (ExceptionEvent $event) {
			$event->setResponse(new Response('conflicted'));
		});

		$response = $this->handle(Request::create('/kernel/conflict'));

		$this->assertSame(409, $response->getStatusCode());
		$this->assertSame('yes', $response->headers->get('X-Conflict'));
	}

	/** A listener that sets its own error status keeps it. */
	public function testExceptionListenerKeepsAStatusItSetItself() {
		$this->dispatcher->addListener(KernelEvents::EXCEPTION, static function (ExceptionEvent $event) {
			$event->setResponse(new Response('teapot', 418));
		});

		$response = $this->handle(Request::create('/kernel/boom'));

		$this->assertSame(418, $response->getStatusCode());
	}

	/** A throwable replaced by a listener is the one that gets rethrown. */
	public function testExceptionListenerCanReplaceTheThrowable() {
		$this->dispatcher->addListener(KernelEvents::EXCEPTION, static function (ExceptionEvent $event) {
			$event->setThrowable(new RuntimeException('replaced'));
		});

		try {
			$this->handle(Request::create('/kernel/boom'));
			$this->fail('expected the replacement to be rethrown');
		} catch (RuntimeException $exception) {
			$this->assertSame('replaced', $exception->getMessage());
		}
	}

	public function testSubRequestsAreHandledAsSuchAndPopped() {
		$types = [];
		$this->dispatcher->addListener(KernelEvents::REQUEST, static function (RequestEvent $event) use (&$types) {
			$types[] = $event->getRequestType();
		}, 1000);

		$response = $this->handle(Request::create('/kernel/ok'), HttpKernelInterface::SUB_REQUEST);

		$this->assertSame([HttpKernelInterface::SUB_REQUEST], $types);
		$this->assertSame('ok', $response->getContent());
		$this->assertNull($this->requestStack->getCurrentRequest());
	}
}
