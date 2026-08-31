<?php declare(strict_types=1);

namespace Cog\ExampleApp\Dev;

use Cog\Controller\ControllerBase;
use Cog\Database\Database;
use Cog\Type;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Debug-only utilities. The directory is only added to the route dirs when debug is on - see
 * Cog\ExampleApp\CogApplication::getRoutesDirs().
 */
#[Route('/dev', name: 'dev')]
class DevController extends ControllerBase {

	/**
	 * The database profiling report the toolbar posts to - see
	 * Cog\Database\Database::displayProfilingHelper(), and the url it uses in
	 * CogApplication::initializeDatabaseConnection(). It takes no session of any kind: the report is
	 * read while browsing the site signed out, and the debug-only route is the access control.
	 */
	#[Route(
		path: ['/profile'],
		name: 'Profile',
		methods: ['POST']
	)]
	public function profileAction(Request $request): Response {
		$this->request = $request;

		if (
			$request->request->has('databaseIndex') === false
			|| $request->request->has('profileData') === false
			|| $request->request->has('referrer') === false
		) {
			return $this->profileMessage('Nothing to profile. No Database Profiling data received.');
		}

		// the index reaches the template, which reads the connection's settings out of it. It comes
		// from a form field, so an index no connection was ever opened on has to end here rather than
		// halfway down a half-rendered page.
		$databaseIndex = $request->request->getInt('databaseIndex');
		if (array_key_exists($databaseIndex, Database::$databases) === false) {
			return $this->profileMessage('Nothing to profile. Database index ' . $databaseIndex . ' is not configured.');
		}

		$profileArray = Type::cast(
			json_decode(base64_decode($request->request->getString('profileData')), true),
			Type::ARRAY
		);

		$totalTime = 0.0;
		foreach ($profileArray as $profile) {
			if (isset($profile['timeInfo']['total time'])) {
				$totalTime += floatval($profile['timeInfo']['total time']);
			}
		}

		return $this->render(null, [
			'databaseIndex' => $databaseIndex,
			'referrer' => $request->request->getString('referrer'),
			'profileArray' => $profileArray,
			'count' => count($profileArray),
			'totalTime' => $totalTime,
		]);
	}

	/** The bail-out the profiling page shows instead of a report, as plain text. */
	private function profileMessage(string $message): Response {
		return new Response($message, 200, ['Content-Type' => 'text/plain']);
	}

}
