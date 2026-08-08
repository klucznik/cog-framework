<?php

namespace Cog\Test;

use Cog\Codegen\CodeGenRunner;
use Cog\Database\Database;
use Throwable;

/**
 * Runs the code generator against the `cog_test` fixture database before the
 * rest of the suite executes.
 *
 * Everything happens inside a throwaway build directory (see BUILD_DIR) so the
 * repository itself never gains a generated/ or app/ tree:
 *
 *     <build>/codegen          symlink (or copy) of the repository's templates
 *     <build>/codegen-overlay  a second templates path layered over the first
 *     <build>/codegen.xml      generated config, pointing at both of the above
 *     <build>/generated/...    Generated\Data, Generated\Node, Generated\Type
 *     <build>/app/...          App\Data, App\Type
 *
 * The build directory doubles as the docroot handed to CodeGenRunner, which is
 * what the DocrootFlag/TargetDirectory pairs in the templates resolve against.
 * The generated namespaces are then registered with an autoloader, so tests
 * that come later can instantiate the generated ORM classes.
 *
 * The whole thing is idempotent: generate() runs at most once per process and
 * wipes the previous build, so a stale file from an earlier run can never make
 * a test pass.
 */
abstract class CodegenFixture {

	/** Build directory, relative to the repository root. Ignored by git. */
	public const string BUILD_DIR = '.phpunit.codegen';

	/** Second template directory, layered over BUILD_DIR/codegen. Relative to the build directory. */
	public const string OVERLAY_DIR = 'codegen-overlay';

	/** Emitted by the overlaid template, asserted on by TestCodegen. */
	public const string OVERLAY_MARKER = '// generated from the overlaid template directory';

	/** Database index the generated config (and therefore the ORM) binds to. */
	public const int DATABASE_INDEX = 1;

	/** @var bool whether generate() has already run in this process */
	private static bool $generated = false;

	/**
	 * The error that stopped generation, or null when it succeeded (or has not
	 * been attempted yet). TestCodegen turns this into a failed assertion; the
	 * remaining tests are left to fail on their own terms rather than taking
	 * the whole bootstrap down with them.
	 */
	private static ?string $error = null;

	/** @var string[] report lines produced by the generator */
	private static array $report = [];

	public static function getError(): ?string {
		return self::$error;
	}

	/** @return string[] */
	public static function getReport(): array {
		return self::$report;
	}

	public static function getBuildPath(string $subPath = ''): string {
		return dirname(__DIR__, 2) . '/' . self::BUILD_DIR . ($subPath ? '/' . ltrim($subPath, '/') : '');
	}

	/**
	 * Generate the ORM layer for the fixture database. Safe to call repeatedly;
	 * only the first call does any work.
	 */
	public static function generate(): void {
		if (self::$generated) {
			return;
		}
		self::$generated = true;

		try {
			self::prepareBuildDirectory();
			self::connect();

			$buildPath = self::getBuildPath();
			CodeGenRunner::run($buildPath, $buildPath . '/codegen.xml');

			if (CodeGenRunner::$rootErrors) {
				self::$error = CodeGenRunner::$rootErrors;
				return;
			}

			$errors = '';
			foreach (CodeGenRunner::$codegenArray as $codegen) {
				self::$report[] = trim($codegen->generateAll());
				$errors .= $codegen->errors;
			}

			foreach (CodeGenRunner::generateAggregate() as $message) {
				self::$report[] = $message;
			}

			if ($errors) {
				self::$error = $errors;
			}
		} catch (Throwable $exception) {
			self::$error = get_class($exception) . ': ' . $exception->getMessage();
		} finally {
			self::disconnect();
		}
	}

	/**
	 * Map the generated namespaces onto the build directory. Registered from the
	 * PHPUnit bootstrap, before generation, so it also covers the subclasses that
	 * are only written on the first run.
	 *
	 * Prepended, so the build directory beats composer's own `Generated\ =>
	 * generated` mapping. Without that the suite loads whatever was last written
	 * to the repository's generated/ directory and never sees what the bootstrap
	 * just generated, which makes every assertion about generated code vacuous.
	 */
	public static function registerAutoloader(): void {
		spl_autoload_register(static function (string $class): void {
			$prefixes = [
				'Generated\\' => self::getBuildPath('generated'),
				'App\\' => self::getBuildPath('app'),
			];

			foreach ($prefixes as $prefix => $baseDir) {
				if (!str_starts_with($class, $prefix)) {
					continue;
				}

				$relative = str_replace('\\', '/', substr($class, strlen($prefix)));
				$file = $baseDir . '/' . $relative . '.php';

				if (is_file($file)) {
					require_once $file;
				}

				return;
			}
		}, true, true);
	}

	/**
	 * Wipe and re-create the build directory, link the templates into it and
	 * write the codegen config that points at them.
	 *
	 * @throws \RuntimeException
	 */
	private static function prepareBuildDirectory(): void {
		$buildPath = self::getBuildPath();
		self::remove($buildPath);

		if (!mkdir($buildPath, 0777, true) && !is_dir($buildPath)) {
			throw new \RuntimeException('unable to create the codegen build directory: ' . $buildPath);
		}

		$templates = dirname(__DIR__, 2) . '/codegen';
		if (!is_dir($templates)) {
			throw new \RuntimeException('codegen templates not found: ' . $templates);
		}

		// The templates have to live under the docroot, because CodeGen resolves
		// them as docroot . templatesPath. A symlink keeps the build directory
		// self-contained; copying is the fallback for filesystems without them.
		if (@symlink($templates, $buildPath . '/codegen') === false) {
			self::copyDirectory($templates, $buildPath . '/codegen');
		}

		self::prepareTemplateOverlay($templates, $buildPath);

		file_put_contents($buildPath . '/codegen.xml', self::settingsXml());
	}

	/**
	 * Second template directory, layered on top of the first by the generated config.
	 *
	 * It contains a single module - a copy of db_orm/class_nodes with a marker appended
	 * to its entry point - so that finding the marker in the generated Node classes
	 * proves the last configured template directory won for that module. Copying the
	 * whole module rather than just the entry point mirrors how overriding actually
	 * works: the unit of override is the module directory.
	 *
	 * @throws \RuntimeException
	 */
	private static function prepareTemplateOverlay(string $templates, string $buildPath): void {
		$module = $buildPath . '/' . self::OVERLAY_DIR . '/db_orm/class_nodes';

		self::copyDirectory($templates . '/db_orm/class_nodes', $module);

		$entryPoint = $module . '/_main.tpl.php';
		$template = file_get_contents($entryPoint);

		if ($template === false) {
			throw new \RuntimeException('unable to read the overlaid template: ' . $entryPoint);
		}

		file_put_contents($entryPoint, $template . "\n" . self::OVERLAY_MARKER . "\n");
	}

	/**
	 * The codegen config used by the suite. Deliberately minimal - it is the
	 * shipped codegen.xml-dist stripped to the settings the fixture database
	 * needs, with the templates path pointing inside the build directory.
	 */
	private static function settingsXml(): string {
		$index = self::DATABASE_INDEX;
		$overlay = self::OVERLAY_DIR;

		return <<<XML
		<?xml version="1.0" encoding="UTF-8" ?>
		<codegen>
			<name application="Cog Test Suite"/>
			<templateEscape begin="&lt;%" end="%&gt;"/>
			<dataSources>
				<database index="{$index}">
					<templates path="/codegen"/>
				<templates path="/{$overlay}"/>
					<className prefix="" suffix=""/>
					<associatedObjectName prefix="" suffix=""/>
					<typeTableIdentifier suffix="_type"/>
					<associationTableIdentifier suffix="_assn"/>
					<stripFromTableName prefix=""/>
					<excludeTables pattern="" list=""/>
					<includeTables pattern="" list=""/>
					<relationships><![CDATA[
					]]></relationships>
					<relationshipsScript filepath="" format="sql"/>
				</database>
			</dataSources>
		</codegen>
		XML;
	}

	/**
	 * Open the connection the generator reads the schema from, on the index the
	 * generated config declares.
	 */
	private static function connect(): void {
		Database::initializeConnection([
			'adapter' => 'MySqli',
			'server' => getenv('COG_TEST_DB_SERVER') ?: 'localhost',
			'encoding' => 'UTF8',
			'database' => getenv('COG_TEST_DB_NAME') ?: 'cog_test',
			'username' => getenv('COG_TEST_DB_USER') ?: 'root',
			'password' => getenv('COG_TEST_DB_PASSWORD') ?: '',
			'profiling' => false,
		], self::DATABASE_INDEX);
	}

	private static function disconnect(): void {
		if (array_key_exists(self::DATABASE_INDEX, Database::$databases)) {
			Database::$databases[self::DATABASE_INDEX]->close();
			unset(Database::$databases[self::DATABASE_INDEX]);
		}
	}

	private static function remove(string $path): void {
		if (is_link($path) || is_file($path)) {
			unlink($path);
			return;
		}

		if (!is_dir($path)) {
			return;
		}

		foreach (scandir($path) as $entry) {
			if ($entry !== '.' && $entry !== '..') {
				self::remove($path . '/' . $entry);
			}
		}

		rmdir($path);
	}

	private static function copyDirectory(string $source, string $destination): void {
		if (!mkdir($destination, 0777, true) && !is_dir($destination)) {
			throw new \RuntimeException('unable to create ' . $destination);
		}

		foreach (scandir($source) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$from = $source . '/' . $entry;
			$to = $destination . '/' . $entry;

			is_dir($from) ? self::copyDirectory($from, $to) : copy($from, $to);
		}
	}
}
