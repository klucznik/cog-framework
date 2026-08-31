<?php declare(strict_types=1);

namespace Cog\Test;

use Cog\Database\Database;
use Cog\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\EventListener\ResponseListener;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Router;

/**
 * The database profiling report at Cog\ExampleApp\Dev\DevController::profileAction(), the one page
 * the framework ships rather than just supports. Nothing else reaches it: the only caller is the
 * form Database::displayProfilingHelper() prints into the dev toolbar, posting base64 json to
 * whatever url Database::$urlProfilePage holds. That indirection is the thing worth pinning - the
 * page and the link to it are declared in two files, and a broken link looks exactly like a working
 * one until someone clicks it.
 *
 * Every request here is made against $urlProfilePage rather than a literal path, so a route that
 * moves without the toolbar url moving fails the whole class.
 *
 * Driven through a real Router and Kernel, the way TestKernel does it, because the route attribute
 * and the method restriction on it are half of what is under test.
 */
class TestDevProfile extends TestCase {

	/** The example app's dev directory, holding the controller and its template. */
	private const string DEV_DIR = __DIR__ . '/../../app/Dev';

	private array $routesDirs;
	private RequestStack $requestStack;
	private EventDispatcher $dispatcher;

	public function setUp(): void {
		$this->routesDirs = MockedApplication::$routesDirs;
		MockedApplication::$routesDirs = [self::DEV_DIR];

		$this->requestStack = new RequestStack();

		$this->dispatcher = new EventDispatcher();
		$this->dispatcher->addSubscriber(new RouterListener($this->router(), $this->requestStack));
		$this->dispatcher->addSubscriber(new ResponseListener('UTF-8'));

		// the page prints the connection's own settings, so it needs a real one. Index 0, since the
		// suite closes every connection between tests.
		Database::initializeConnection([
			'adapter' => 'MySqli',
			'server' => getenv('COG_TEST_DB_SERVER') ?: 'localhost',
			'encoding' => 'UTF8',
			'database' => getenv('COG_TEST_DB_NAME') ?: 'cog_framework_test',
			'username' => getenv('COG_TEST_DB_USER') ?: 'root',
			'password' => getenv('COG_TEST_DB_PASSWORD') ?: '',
			'profiling' => true,
		], 0);
	}

	public function tearDown(): void {
		foreach (array_keys(Database::$databases) as $index) {
			Database::$databases[$index]->close();
			unset(Database::$databases[$index]);
		}

		MockedApplication::$routesDirs = $this->routesDirs;
	}

	private function router(): Router {
		return new Router(
			new ClosureLoader(),
			[MockedApplication::class, 'getRoutes'],
			[],
			new RequestContext()
		);
	}

	/** One profiled query in the shape Database::$profileArray holds them. */
	private function query(string $sql, ?float $seconds = null, string $file = '/app/Data/Page.php', int $line = 42): array {
		$profile = [
			'backtrace' => [
				'class' => 'App\\Data\\Page',
				'type' => '::',
				'function' => 'queryArray',
				'args' => ['[QQ::all()]'],
				'file' => $file,
				'line' => $line,
			],
			'query' => $sql,
		];

		if ($seconds !== null) {
			$profile['timeInfo'] = ['total time' => $seconds];
		}

		return $profile;
	}

	/** @param array[] $queries */
	private function report(array $queries, int $databaseIndex = 0, string $referrer = '/somewhere'): Response {
		return $this->post([
			'profileData' => base64_encode(json_encode($queries)),
			'databaseIndex' => (string) $databaseIndex,
			'referrer' => $referrer,
		]);
	}

	private function kernel(): Kernel {
		return new Kernel(
			$this->dispatcher,
			new ControllerResolver(),
			new ArgumentResolver(),
			$this->requestStack
		);
	}

	private function post(array $parameters): Response {
		return $this->kernel()->handle(Request::create(Database::$urlProfilePage, 'POST', $parameters));
	}

	/**
	 * The toolbar posts a form, so the route has to accept POST and only POST, at exactly the url
	 * the toolbar was given.
	 */
	public function testTheToolbarUrlIsTheRouteThePageIsOn(): void {
		$route = $this->router()->getRouteCollection()->get('devProfile');

		$this->assertNotNull($route, 'the devProfile route is not registered');
		$this->assertSame(Database::$urlProfilePage, $route->getPath());
		$this->assertSame(['POST'], $route->getMethods());
	}

	/**
	 * Anything but POST is not this page - the toolbar has no link to follow. The router refuses it
	 * before the controller is reached; turning that into a response is the application's job, not
	 * the kernel's, so what is asserted here is the refusal itself.
	 */
	public function testTheReportIsNotServedOverGet(): void {
		$this->expectException(MethodNotAllowedHttpException::class);

		$this->kernel()->handle(Request::create(Database::$urlProfilePage, 'GET'));
	}

	public function testTheReportListsEveryQuery(): void {
		$response = $this->report([
			$this->query('SELECT * FROM person WHERE id = 1', 0.0012),
			$this->query('SELECT * FROM blog_post WHERE obj_id = 1', 0.0071),
			$this->query('SELECT * FROM asset', 0.0184),
		]);

		$this->assertSame(200, $response->getStatusCode());
		$content = $response->getContent();

		$this->assertStringContainsString('There were 3 queries that were performed.', $content);
		$this->assertStringContainsString('SELECT * FROM person WHERE id = 1', $content);
		$this->assertStringContainsString('SELECT * FROM blog_post WHERE obj_id = 1', $content);
		$this->assertStringContainsString('SELECT * FROM asset', $content);
	}

	/** The two count branches that are not the plural one. */
	public function testTheQueryCountIsWordedForOneAndForNone(): void {
		$this->assertStringContainsString(
			'There was 1 query that was performed.',
			$this->report([$this->query('SELECT 1', 0.0001)])->getContent()
		);

		$this->assertStringContainsString(
			'There were no queries that were performed.',
			$this->report([])->getContent()
		);
	}

	/** Summed over the queries, in milliseconds - the number the toolbar link also shows. */
	public function testTheHeadlineTimeIsTheSumOfTheQueries(): void {
		$content = $this->report([
			$this->query('SELECT 1', 0.0012),
			$this->query('SELECT 2', 0.0071),
			$this->query('SELECT 3', 0.0184),
		])->getContent();

		$this->assertStringContainsString('26.7ms', $content);
	}

	/**
	 * The colouring is the only thing on the page that reads the timings, and the thresholds are the
	 * reason to open the page at all: 10ms and up is slow, 5ms and up is sluggish.
	 */
	public function testSlowQueriesAreMarked(): void {
		$content = $this->report([
			$this->query('SELECT fast', 0.0012),
			$this->query('SELECT sluggish', 0.0071),
			$this->query('SELECT slow', 0.0184),
		])->getContent();

		$this->assertSame(1, substr_count($content, 'class="function_details slow"'));
		$this->assertSame(1, substr_count($content, 'class="function_details sluggish"'));
	}

	/** A query with no timeInfo is still listed, it just has no Time line. */
	public function testAnUntimedQueryIsStillListed(): void {
		$content = $this->report([$this->query('SELECT untimed')])->getContent();

		$this->assertStringContainsString('SELECT untimed', $content);
		$this->assertStringNotContainsString('<b>Time:</b>', $content);
	}

	/** Both the caller and the file it came from, which is what makes a query findable. */
	public function testEachQueryNamesItsCaller(): void {
		$content = $this->report([
			$this->query('SELECT 1', 0.0012, '/app/Controller/BlogController.php', 118),
		])->getContent();

		$this->assertStringContainsString('App\Data\Page::queryArray([QQ::all()])', $content);
		$this->assertStringContainsString('/app/Controller/BlogController.php', $content);
		$this->assertStringContainsString('118', $content);
	}

	/**
	 * Everything on this page arrives in a form field. The queries are the application's own, but the
	 * referrer is whatever was posted, and neither is trusted enough to be written raw.
	 */
	public function testPostedTextIsEscaped(): void {
		$content = $this->report(
			[$this->query('SELECT "<script>alert(1)</script>"', 0.0012)],
			referrer: '/"><script>alert(2)</script>'
		)->getContent();

		$this->assertStringNotContainsString('<script>alert(1)</script>', $content);
		$this->assertStringNotContainsString('<script>alert(2)</script>', $content);
		$this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $content);
		$this->assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $content);
	}

	/** Reached directly rather than through the toolbar - there is nothing to report. */
	public function testAPostWithNoProfilingDataIsRefused(): void {
		$response = $this->post([]);

		$this->assertSame('Nothing to profile. No Database Profiling data received.', $response->getContent());
		$this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
	}

	/**
	 * The index picks the connection whose settings the page prints, so an index no connection was
	 * opened on has to be refused before the template reads it - otherwise the page dies half
	 * rendered, on a value that came out of a form field.
	 */
	public function testAnUnknownDatabaseIndexIsRefused(): void {
		$response = $this->report([$this->query('SELECT 1', 0.0012)], databaseIndex: 99);

		$this->assertSame('Nothing to profile. Database index 99 is not configured.', $response->getContent());
		$this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
	}

	/** The connection the profiled queries actually ran on is named in the header. */
	public function testTheHeaderNamesTheConnection(): void {
		$content = $this->report([$this->query('SELECT 1', 0.0012)])->getContent();

		$this->assertStringContainsString(Database::$databases[0]->database, $content);
		$this->assertStringContainsString(Database::$databases[0]->adapter, $content);
	}
}
