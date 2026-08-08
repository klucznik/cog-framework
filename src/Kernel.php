<?php declare(strict_types=1);

namespace Cog;

use Cog\Exceptions\RedirectException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\ControllerDoesNotReturnResponseException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception;

class Kernel implements HttpKernelInterface {

	private EventDispatcherInterface $dispatcher;
	protected ControllerResolverInterface $resolver;
	protected ArgumentResolverInterface $argumentResolver;
	protected RequestStack $requestStack;

	/**
	 * Resolver constructor.
	 */
	public function __construct(EventDispatcherInterface $dispatcher, ControllerResolverInterface $resolver, ArgumentResolver $argumentResolver, RequestStack $requestStack) {
		$this->resolver = $resolver;
		$this->dispatcher = $dispatcher;
		$this->requestStack = $requestStack;
		$this->argumentResolver = $argumentResolver;
	}

	public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response|RedirectResponse {
		try {
			return $this->handleRaw($request, $type);
		} catch (\Throwable $e) {
			if ($catch === false) {
				$this->finishRequest($request, $type);

				throw $e;
			}

			return $this->handleThrowable($e, $request, $type);
		}
	}

	/**
	 * Handles a request, letting every exception escape to the caller.
	 */
	private function handleRaw(Request $request, int $type): Response|RedirectResponse {
		$this->requestStack->push($request);

		// dispatch Request event, so the RouterListener can fill request attributes (controller, arguments, matching route etc)
		$event = new RequestEvent($this, $request, $type);
		$this->dispatcher->dispatch($event, KernelEvents::REQUEST);

		if ($event->hasResponse()) {
			return $this->filterResponse($event->getResponse(), $request, $type);
		}

		try {
			// load controller
			if (false === $controller = $this->resolver->getController($request)) {
				throw new NotFoundHttpException(sprintf('Unable to find the controller for path "%s". The route is wrongly configured.', $request->getPathInfo()));
			}

			$event = new ControllerEvent($this, $controller, $request, $type);
//			dump($event);
			$this->dispatcher->dispatch($event, KernelEvents::CONTROLLER);
			$controller = $event->getController();

			// controller arguments
			$arguments = $this->argumentResolver->getArguments($request, $controller, $event->getControllerReflector());

			$event = new ControllerArgumentsEvent($this, $event, $arguments, $request, $type);
			$this->dispatcher->dispatch($event, KernelEvents::CONTROLLER_ARGUMENTS);

			$controller = $event->getController();
			$arguments = $event->getArguments();

//			dump($controller);
//			dump($arguments);

			// call controller
			$response = call_user_func_array($controller, $arguments);

			// view
			if (!$response instanceof Response) {
				$event = new ViewEvent($this, $request, $type, $response, $event);
				$this->dispatcher->dispatch($event, KernelEvents::VIEW);

				if ($event->hasResponse()) {
					$response = $event->getResponse();
				} else {
					$msg = sprintf('The controller must return a "Symfony\Component\HttpFoundation\Response" object but it returned %s.', $this->varToString($response));

					// the user may have forgotten to return something
					if (null === $response) {
						$msg .= ' Did you forget to add a return statement somewhere in your controller?';
					}

					throw new ControllerDoesNotReturnResponseException($msg, $controller, __FILE__, __LINE__ - 17);
				}
			}
		} catch (Exception\ResourceNotFoundException|Exception\MethodNotAllowedException) {
			$response = self::getNotFoundPage($request);
		} catch (RedirectException $e) {
			$response = new RedirectResponse($e->location, $e->status);
		}

		return $this->filterResponse($response, $request, $type);
	}

	/**
	 * Turns a throwable into a response by giving the KernelEvents::EXCEPTION
	 * listeners a chance to build one. If none of them does, the throwable is
	 * rethrown and ends up at the error handler registered by BaseApplication.
	 * @throws \Throwable
	 */
	private function handleThrowable(\Throwable $e, Request $request, int $type): Response {
		$event = new ExceptionEvent($this, $request, $type, $e);
		$this->dispatcher->dispatch($event, KernelEvents::EXCEPTION);

		// a listener might have replaced the throwable
		$e = $event->getThrowable();

		if (!$event->hasResponse()) {
			$this->finishRequest($request, $type);

			throw $e;
		}

		$response = $event->getResponse();

		// a listener that did not ask for a specific status code gets the one
		// carried by the throwable, falling back to a plain 500
		if (!$event->isAllowingCustomResponseCode() && !$response->isClientError() && !$response->isServerError() && !$response->isRedirect()) {
			if ($e instanceof HttpExceptionInterface) {
				$response->setStatusCode($e->getStatusCode());
				$response->headers->add($e->getHeaders());
			} else {
				$response->setStatusCode(500);
			}
		}

		try {
			return $this->filterResponse($response, $request, $type);
		} catch (\Throwable) {
			// the response listeners failed too - return what we have rather than
			// starting the whole dance again
			return $response;
		}
	}

	/**
	 * Describes what a controller returned, for the message on
	 * ControllerDoesNotReturnResponseException.
	 */
	private function varToString(mixed $var): string {
		if (is_object($var)) {
			return sprintf('an object of type %s', $var::class);
		}

		if (is_array($var)) {
			return 'an array';
		}

		if (is_resource($var)) {
			return sprintf('a resource (%s)', get_resource_type($var));
		}

		if ($var === null) {
			return 'null';
		}

		if (is_bool($var)) {
			return sprintf('a boolean value (%s)', $var ? 'true' : 'false');
		}

		if (is_string($var)) {
			return sprintf('a string ("%s")', $var);
		}

		return sprintf('a value of type %s (%s)', get_debug_type($var), (string)$var);
	}

	/**
	 * Filters a response object.
	 */
	private function filterResponse(Response $response, Request $request, int $type): Response
	{
		$event = new ResponseEvent($this, $request, $type, $response);

		$this->dispatcher->dispatch($event, KernelEvents::RESPONSE);

		$this->finishRequest($request, $type);

		return $event->getResponse();
	}

	/**
	 * Publishes the finish request event, then pop the request from the stack.
	 *
	 * Note that the order of the operations is important here, otherwise
	 * operations such as {@link RequestStack::getParentRequest()} can lead to
	 * weird results.
	 */
	private function finishRequest(Request $request, int $type): void {
		$this->dispatcher->dispatch(new FinishRequestEvent($this, $request, $type), KernelEvents::FINISH_REQUEST);
		$this->requestStack->pop();
	}

	/**
	 * Responds with 404 response
	 * @param Request $request
	 * @return Response
	 */
	protected static function getNotFoundPage(Request $request): Response {
		return new Response('404', 404);
	}
}
