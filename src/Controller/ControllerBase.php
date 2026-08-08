<?php

namespace Cog\Controller;

use Cog\Base;
use Cog\Exceptions\CogException;
use Cog\Exceptions\RedirectException;
use Cog\Util\Template;
use Cog\Util\Url;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;

abstract class ControllerBase extends Base {

	protected Request $request;

	/**
	 * @param string|null $templateFile
	 * @param array $tokens
	 * @return Response
	 * @throws CogException
	 */
	protected function render(?string $templateFile = null, array $tokens = []): Response {
		if ($templateFile === '' || $templateFile === null) {
			$templateFile = $this->findTemplate();

			if ($templateFile === null) {
				throw new CogException('Template not found ' . $templateFile);
			}
		}

		$tokens['controller'] = $this;
		$tokens['request'] = $this->request;

		$toReturn = Template::render($templateFile, $tokens);

		return new Response($toReturn);
	}

	protected function findTemplate(): ?string {
		$reflector = new ReflectionClass(static::class);
		$path = pathinfo($reflector->getFileName());

		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$i = 0;
		while ($backtrace[$i]['function'] === 'render' || $backtrace[$i]['function'] === 'findTemplate') {
			$i++;
		}

		$function = ucwords($backtrace[$i]['function']);
		if (str_ends_with($function, 'Action')) {
			$function = substr($function, 0, -strlen('Action'));
		}

		$filename = $path['dirname'] . '/' . basename($path['filename'], '.class') . $function . '.tpl.php';
		if (file_exists($filename)) {
			return $filename;
		}

		return null;
	}

	/**
	 * This will redirect the user to a new web location. This can be a
	 * relative or absolute web path, or it can be an entire URL.
	 *
	 * @param string $location
	 * @param int $status The status code to use for the Response
	 *
	 * @throws RedirectException
	 */
	protected function redirect(string $location, int $status = 302): void {
		throw new RedirectException($location, $status);
	}

	/**
	 * Throws RedirectResponse to the given route with the given parameters.
	 *
	 * @param string $route      The name of the route
	 * @param array  $parameters An array of parameters
	 * @param integer $referenceType The type of reference to be generated (one of the constants)
	 * @param int $status     The status code to use for the Response
	 *
	 * @throws RedirectException
	 */
	protected function redirectToRoute(string $route, array $parameters = [], int $referenceType = UrlGenerator::ABSOLUTE_PATH, int $status = 302): void {
		$this->redirect(Url::getForRoute($route, $parameters, $referenceType), $status);
	}
}
