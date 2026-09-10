<?php declare(strict_types=1);

namespace Cog\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Answers any HttpException nobody else has - a 405 from the router, a 403 or
 * 401 from a guard, an exception carrying #[WithHttpStatus] - with an empty
 * response of that status and its headers.
 *
 * Runs last, after the not-found listener has claimed the 404s and after any
 * application listener that renders a body.
 */
class HttpExceptionListener implements EventSubscriberInterface {

	public function onKernelException(ExceptionEvent $event): void {
		$throwable = $event->getThrowable();

		if (!$throwable instanceof HttpExceptionInterface) {
			return;
		}

		$event->setResponse(new Response(null, $throwable->getStatusCode(), $throwable->getHeaders()));
	}

	public static function getSubscribedEvents(): array {
		return [
			KernelEvents::EXCEPTION => ['onKernelException', -192],
		];
	}
}
