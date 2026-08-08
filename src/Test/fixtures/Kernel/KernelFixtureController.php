<?php

namespace Cog\Test\fixtures\Kernel;

use Cog\Controller\ControllerBase;
use Cog\Exceptions\RedirectException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * One action per branch of Cog\Kernel::handleRaw(). Kept in its own directory so
 * that the route collection TestKernel builds stays independent of the one
 * TestBaseApplication asserts against.
 */
class KernelFixtureController extends ControllerBase {

	#[Route('/kernel/ok', name: 'kernelOk')]
	public function okAction(): Response {
		return new Response('ok');
	}

	/** The argument resolvers have to turn a route placeholder into an int. */
	#[Route('/kernel/param/{id}', name: 'kernelParam', requirements: ['id' => '\d+'])]
	public function parameterAction(int $id): Response {
		return new Response('id:' . $id);
	}

	/** The request itself is injected by RequestValueResolver. */
	#[Route('/kernel/request', name: 'kernelRequest')]
	public function requestAction(Request $request): Response {
		return new Response('path:' . $request->getPathInfo());
	}

	#[Route('/kernel/redirect', name: 'kernelRedirect')]
	public function redirectingAction(): Response {
		throw new RedirectException('/somewhere-else', 301);
	}

	#[Route('/kernel/string', name: 'kernelString')]
	public function stringAction(): string {
		return 'not a response';
	}

	#[Route('/kernel/void', name: 'kernelVoid')]
	public function voidAction(): mixed {
		return null;
	}

	#[Route('/kernel/array', name: 'kernelArray')]
	public function arrayAction(): mixed {
		return ['not', 'a', 'response'];
	}

	#[Route('/kernel/bool', name: 'kernelBool')]
	public function boolAction(): mixed {
		return true;
	}

	#[Route('/kernel/object', name: 'kernelObject')]
	public function objectAction(): mixed {
		return new \stdClass();
	}

	#[Route('/kernel/chatty', name: 'kernelChatty')]
	public function chattyAction(): Response {
		echo 'stray output from a successful controller';

		return new Response('quiet');
	}

	/** Throws without echoing - the plain failure case. */
	#[Route('/kernel/boom', name: 'kernelBoom')]
	public function boomAction(): Response {
		throw new RuntimeException('boom');
	}

	/** Echoes and then throws, so the two can be observed together. */
	#[Route('/kernel/leak', name: 'kernelLeak')]
	public function leakAction(): Response {
		echo 'output from a controller that then threw';

		throw new RuntimeException('boom');
	}

	#[Route('/kernel/conflict', name: 'kernelConflict')]
	public function conflictAction(): Response {
		throw new ConflictHttpException('nope', headers: ['X-Conflict' => 'yes']);
	}
}
