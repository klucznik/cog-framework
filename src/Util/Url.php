<?php

namespace Cog\Util;

use Cog\BaseApplication;
use Cog\Exceptions\RedirectException;
use Symfony;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class Url {

	/**
	 * Generates a URL or path for a specific route based on the given parameters.
	 * Parameters that reference placeholders in the route pattern will substitute
	 * them in the path or host. Extra params are added as query string to the URL.
	 *
	 * When the passed reference type cannot be generated for the route because it
	 * requires a different host or scheme than the current one, the method will
	 * return a more comprehensive reference that includes the required params. For
	 * example, when you call this method with $referenceType = ABSOLUTE_PATH but
	 * the route requires the https scheme whereas the current scheme is http, it
	 * will instead return an ABSOLUTE_URL with the https scheme and the current host.
	 * This makes sure the generated URL matches the route in any case.
	 *
	 * If there is no route with the given name, the generator must throw the RouteNotFoundException.
	 *
	 * @param string $routeName The name of the route
	 * @param array $parameters An array of parameters
	 * @param int $referenceType The type of reference to be generated (one of the constants) defaults to UrlGenerator::ABSOLUTE_PATH
	 *
	 * @return string The generated URL
	 */
	public static function getForRoute(string $routeName, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string {
		/** @var UrlGeneratorInterface $generator */
		$generator = BaseApplication::$container->get('router');
		return $generator->generate($routeName, $parameters, $referenceType);
	}

	/**
	 * @param string $url
	 * @throws RedirectException
	 */
	public static function redirect(string $url): void {
		throw new RedirectException($url);
	}
}
