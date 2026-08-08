<?php

namespace Cog\Test\fixtures\Controller;

use Cog\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A controller with nothing but routes on it, so TestBaseApplication can point
 * BaseApplication::getRoutes() at a directory and assert what comes back. The
 * route names are the ones BaseApplication::displayProfiling() links to, which
 * is what makes that method reachable from a test at all.
 */
class FixtureController extends ControllerBase {

	#[Route('/dev/dump', name: 'devDump')]
	public function dumpAction(): Response {
		return new Response('dump');
	}

	#[Route('/dev/phpinfo', name: 'devPhpInfo')]
	public function phpInfoAction(): Response {
		return new Response('phpinfo');
	}

	#[Route('/fixture/{id}', name: 'fixtureWithParameter', requirements: ['id' => '\d+'])]
	public function parameterAction(int $id): Response {
		return new Response((string)$id);
	}
}
