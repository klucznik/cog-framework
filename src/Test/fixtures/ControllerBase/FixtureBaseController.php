<?php

namespace Cog\Test\fixtures\ControllerBase;

use Cog\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Exercises ControllerBase from the outside: render() is protected and
 * findTemplate() reads the call stack to work out which template belongs to the
 * action that called it, so both have to be reached through a real action.
 *
 * The template beside this file is named for showAction() - see findTemplate().
 */
class FixtureBaseController extends ControllerBase {

	public function setRequest(Request $request): void {
		$this->request = $request;
	}

	/** Renders FixtureBaseControllerShow.tpl.php, found by convention. */
	#[Route('/base/show', name: 'fixtureBaseShow')]
	public function showAction(): Response {
		return $this->render();
	}

	/** Same, but naming the template explicitly. */
	#[Route('/base/explicit', name: 'fixtureBaseExplicit')]
	public function explicitAction(): Response {
		return $this->render(__DIR__ . '/explicit.tpl.php', ['value' => 'given']);
	}

	/** No template is named for this one, by convention or otherwise. */
	#[Route('/base/missing', name: 'fixtureBaseMissing')]
	public function missingAction(): Response {
		return $this->render();
	}

	#[Route('/base/leave', name: 'fixtureBaseLeave')]
	public function leaveAction(): Response {
		$this->redirect('/gone', 301);
	}

	#[Route('/base/leave-to-route', name: 'fixtureBaseLeaveToRoute')]
	public function leaveToRouteAction(): Response {
		$this->redirectToRoute('fixtureBaseShow');
	}

	/** No name given, so AttributeRouteControllerLoader has to invent one. */
	#[Route('/base/unnamed')]
	public function unnamedAction(): Response {
		return new Response('unnamed');
	}
}
