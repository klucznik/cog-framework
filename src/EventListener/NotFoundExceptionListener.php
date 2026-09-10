<?php declare(strict_types=1);

namespace Cog\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Answers a NotFoundHttpException - a routing miss, or a controller that could
 * not find what was asked for - with the application's 404 page.
 *
 * Runs late, so an application listener registered at the default priority
 * (one that renders errors as JSON for api callers, say) gets the first word.
 * An application with its own page extends this and overrides notFoundResponse().
 */
class NotFoundExceptionListener implements EventSubscriberInterface {

	public function onKernelException(ExceptionEvent $event): void {
		$throwable = $event->getThrowable();

		if (!$throwable instanceof NotFoundHttpException) {
			return;
		}

		$event->setResponse($this->notFoundResponse($event->getRequest(), $throwable));
	}

	protected function notFoundResponse(Request $request, NotFoundHttpException $exception): Response {
		return new Response('404', 404);
	}

	public static function getSubscribedEvents(): array {
		return [
			KernelEvents::EXCEPTION => ['onKernelException', -128],
		];
	}
}
