<?php declare(strict_types=1);

namespace Cog\EventListener;

use Cog\Exceptions\RedirectException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Turns a RedirectException thrown anywhere below the kernel - a controller
 * action, a guard it calls, a template - into the RedirectResponse it asks for.
 */
class RedirectExceptionListener implements EventSubscriberInterface {

	public function onKernelException(ExceptionEvent $event): void {
		$throwable = $event->getThrowable();

		if (!$throwable instanceof RedirectException) {
			return;
		}

		$event->setResponse(new RedirectResponse($throwable->location, $throwable->status));
	}

	public static function getSubscribedEvents(): array {
		return [
			KernelEvents::EXCEPTION => 'onKernelException',
		];
	}
}
