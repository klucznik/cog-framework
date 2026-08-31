<?php declare(strict_types=1);

namespace Cog\Test;

use Cog\Codegen\CodeGen;
use Cog\Codegen\CodeGenRunner;
use Cog\Exceptions\CogException;
use Cog\Util\FileSystem;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * Tests for the template machinery in Cog\Codegen\CodeGen and the settings
 * parsing in Cog\Codegen\CodeGenRunner.
 *
 * Neither needs a database. TestCodegen covers a real generation end to end, but
 * only ever the happy path of a clean run against a valid config: it wipes its
 * build directory first, so the branch that refuses to overwrite an existing
 * hand-editable subclass never runs, and it never feeds the runner a config that
 * is missing, malformed or wrongly shaped.
 *
 * Everything here drives CodeGen against a scratch template directory built per
 * test, which makes the write rules and the error paths reachable.
 *
 * The rule that matters most is OverwriteFlag="false": it is the promise that
 * generated App\Data subclasses are written once and never clobbered. Losing it
 * would silently destroy hand-written code on the next db:codegen.
 */
class TestCodegenTemplates extends TestCase {

	/** @var string scratch docroot, created per test and removed in tearDown */
	private string $docroot;

	/** @var array<string, mixed> CodeGenRunner statics to put back */
	private array $runnerState = [];

	public function setUp(): void {
		$this->docroot = sys_get_temp_dir() . '/cog-template-test-' . bin2hex(random_bytes(8));
		mkdir($this->docroot);

		// CodeGenRunner keeps its results in statics that the fixture also uses.
		$this->runnerState = [
			'codegenArray' => isset(CodeGenRunner::$codegenArray) ? CodeGenRunner::$codegenArray : null,
			'rootErrors' => CodeGenRunner::$rootErrors,
			'applicationName' => isset(CodeGenRunner::$applicationName) ? CodeGenRunner::$applicationName : null,
			'settingsFilePath' => isset(CodeGenRunner::$settingsFilePath) ? CodeGenRunner::$settingsFilePath : null,
		];
		CodeGenRunner::$rootErrors = '';
	}

	public function tearDown(): void {
		if ($this->runnerState['codegenArray'] !== null) {
			CodeGenRunner::$codegenArray = $this->runnerState['codegenArray'];
		}
		CodeGenRunner::$rootErrors = $this->runnerState['rootErrors'];
		if ($this->runnerState['applicationName'] !== null) {
			CodeGenRunner::$applicationName = $this->runnerState['applicationName'];
		}
		if ($this->runnerState['settingsFilePath'] !== null) {
			CodeGenRunner::$settingsFilePath = $this->runnerState['settingsFilePath'];
		}

		if (is_dir($this->docroot)) {
			FileSystem::removeDirectory($this->docroot);
		}
	}

	/**
	 * A generator rooted at the scratch docroot.
	 *
	 * @param string[] $templatesPaths docroot-relative, in override order
	 */
	private function codegen(array $templatesPaths = ['/templates']): TemplateCodeGenHarness {
		return new TemplateCodeGenHarness(
			$this->docroot,
			$templatesPaths,
			new SimpleXMLElement('<database index="1"/>')
		);
	}

	/**
	 * Writes a template into <docroot>/<templatesPath>/<prefix>/<module>/<filename>,
	 * creating the directories on the way.
	 */
	private function writeTemplate(string $module, string $filename, string $contents, string $templatesPath = '/templates'): string {
		$directory = $this->docroot . $templatesPath . '/' . $module;
		if (!is_dir($directory)) {
			mkdir($directory, 0777, true);
		}

		file_put_contents($directory . '/' . $filename, $contents);

		return $directory . '/' . $filename;
	}

	/** The <template/> header line the generator parses off the top of every entry point. */
	private function header(array $attributes = []): string {
		$attributes = array_merge([
			'OverwriteFlag' => 'true',
			'DocrootFlag' => 'true',
			'DirectorySuffix' => '',
			'TargetDirectory' => '/generated',
			'TargetFileName' => 'Output.php',
		], $attributes);

		$pairs = '';
		foreach ($attributes as $name => $value) {
			$pairs .= sprintf(' %s="%s"', $name, $value);
		}

		return '<template' . $pairs . '/>';
	}

	//
	// generateFile: writing
	//

	public function testGenerateFileWritesTheEvaluatedTemplate() {
		$this->writeTemplate('db_orm/class_gen', '_main.tpl.php', $this->header() . "\nhello from the template");

		$this->assertTrue($this->codegen()->generateFile('db_orm/class_gen', '_main.tpl.php', []));
		$this->assertSame('hello from the template', file_get_contents($this->docroot . '/generated/Output.php'));
	}

	/** The header line is stripped; only what follows it is written. */
	public function testGenerateFileStripsTheHeaderLine() {
		$this->writeTemplate('db_orm/class_gen', '_main.tpl.php', $this->header() . "\n<?php echo 'body'; ?>");

		$this->codegen()->generateFile('db_orm/class_gen', '_main.tpl.php', []);

		$written = file_get_contents($this->docroot . '/generated/Output.php');
		$this->assertStringNotContainsString('<template', $written);
		$this->assertSame('body', $written);
	}

	/** Templates get the generator and the caller's arguments in scope. */
	public function testGenerateFileEvaluatesPhpWithArguments() {
		$this->writeTemplate('db_orm/class_gen', '_main.tpl.php', $this->header() . "\n<?= \$greeting ?> from <?= \$moduleName ?>");

		$this->codegen()->generateFile('db_orm/class_gen', '_main.tpl.php', ['greeting' => 'hi']);

		$this->assertSame('hi from db_orm/class_gen', file_get_contents($this->docroot . '/generated/Output.php'));
	}

	/** The target directory is created recursively - it is normally nested. */
	public function testGenerateFileCreatesNestedTargetDirectory() {
		$this->writeTemplate('db_orm/class_gen', '_main.tpl.php', $this->header(['TargetDirectory' => '/generated/Data/Deep']) . "\nx");

		$this->codegen()->generateFile('db_orm/class_gen', '_main.tpl.php', []);

		$this->assertFileExists($this->docroot . '/generated/Data/Deep/Output.php');
	}

	public function testGenerateFileAppendsDirectorySuffix() {
		$this->writeTemplate('db_orm/class_gen', '_main.tpl.php', $this->header(['DirectorySuffix' => '/Extra']) . "\nx");

		$this->codegen()->generateFile('db_orm/class_gen', '_main.tpl.php', []);

		$this->assertFileExists($this->docroot . '/generated/Extra/Output.php');
	}

	/**
	 * With DocrootFlag="false" the target directory is used as given, so a template
	 * can write outside the docroot entirely.
	 */
	public function testGenerateFileWithoutDocrootFlagUsesTargetDirectoryAsGiven() {
		$absolute = $this->docroot . '/outside';
		$this->writeTemplate('db_orm/class_gen', '_main.tpl.php', $this->header([
			'DocrootFlag' => 'false',
			'TargetDirectory' => $absolute,
		]) . "\nx");

		$this->codegen()->generateFile('db_orm/class_gen', '_main.tpl.php', []);

		$this->assertFileExists($absolute . '/Output.php');
	}

	//
	// generateFile: the overwrite rule
	//

	/**
	 * OverwriteFlag="false" means "hand-editable subclass, write once". This is the
	 * guarantee that db:codegen never destroys code someone wrote in App\Data.
	 */
	public function testGenerateFileDoesNotOverwriteWhenOverwriteFlagIsFalse() {
		$this->writeTemplate('db_orm/class_subclass', '_main.tpl.php', $this->header(['OverwriteFlag' => 'false']) . "\ngenerated body");

		$codegen = $this->codegen();

		$this->assertTrue($codegen->generateFile('db_orm/class_subclass', '_main.tpl.php', []));
		$this->assertSame('generated body', file_get_contents($this->docroot . '/generated/Output.php'));

		// Someone edits the generated subclass by hand...
		file_put_contents($this->docroot . '/generated/Output.php', 'hand written body');

		// ...and the next run must leave it exactly as it is.
		$this->assertTrue($codegen->generateFile('db_orm/class_subclass', '_main.tpl.php', []));
		$this->assertSame('hand written body', file_get_contents($this->docroot . '/generated/Output.php'));
	}

	/** The generated half is the opposite: rewritten on every run. */
	public function testGenerateFileOverwritesWhenOverwriteFlagIsTrue() {
		$this->writeTemplate('db_orm/class_gen', '_main.tpl.php', $this->header(['OverwriteFlag' => 'true']) . "\ngenerated body");

		$codegen = $this->codegen();
		$codegen->generateFile('db_orm/class_gen', '_main.tpl.php', []);

		file_put_contents($this->docroot . '/generated/Output.php', 'stale body');

		$codegen->generateFile('db_orm/class_gen', '_main.tpl.php', []);

		$this->assertSame('generated body', file_get_contents($this->docroot . '/generated/Output.php'));
	}

	//
	// generateFile: not saving
	//

	/** Asked not to save, the evaluated template comes back instead of being written. */
	public function testGenerateFileWithoutSavingReturnsTheTemplate() {
		$this->writeTemplate('db_orm/class_gen', '_main.tpl.php', $this->header() . "\nevaluated body");

		$result = $this->codegen()->generateFile('db_orm/class_gen', '_main.tpl.php', [], false);

		$this->assertSame('evaluated body', $result);
		$this->assertFileDoesNotExist($this->docroot . '/generated/Output.php');
	}

	/**
	 * A template that declares no target directory is one whose feature is no longer
	 * generated. That is a success, not a failure - nothing is written.
	 */
	public function testGenerateFileWithoutTargetDirectoryIsASuccess() {
		$this->writeTemplate('db_orm/class_gen', '_main.tpl.php', $this->header(['TargetDirectory' => '']) . "\nx");

		$this->assertTrue($this->codegen()->generateFile('db_orm/class_gen', '_main.tpl.php', []));
	}

	//
	// generateFile: the error paths
	//

	public function testGenerateFileWithUnknownModuleThrowsAndListsCandidates() {
		$codegen = $this->codegen(['/templates', '/app-templates']);

		try {
			$codegen->generateFile('db_orm/missing_module', '_main.tpl.php', []);
			$this->fail('expected a CogException');
		} catch (CogException $exception) {
			$this->assertStringContainsString('Template Module Not Found', $exception->getMessage());
			// Every configured layer is named, so a mis-set templates path is obvious
			$this->assertStringContainsString('/templates/db_orm/missing_module', $exception->getMessage());
			$this->assertStringContainsString('/app-templates/db_orm/missing_module', $exception->getMessage());
		}
	}

	public function testGenerateFileWithMissingTemplateThrows() {
		$this->writeTemplate('db_orm/class_gen', 'other.tpl.php', $this->header() . "\nx");

		$this->expectException(CogException::class);
		$this->expectExceptionMessageIsOrContains('Template File Not Found');

		$this->codegen()->generateFile('db_orm/class_gen', '_main.tpl.php', []);
	}

	/** A template with no newline has no header line to parse. */
	public function testGenerateFileWithSingleLineTemplateThrows() {
		$this->writeTemplate('db_orm/class_gen', '_main.tpl.php', 'no newline at all');

		$this->expectException(\Exception::class);
		$this->expectExceptionMessageIsOrContains('Template\'s first line must be');

		$this->codegen()->generateFile('db_orm/class_gen', '_main.tpl.php', []);
	}

	public function testGenerateFileWithNonXmlFirstLineThrows() {
		$this->writeTemplate('db_orm/class_gen', '_main.tpl.php', "just a comment\nbody");

		$this->expectException(\Exception::class);
		$this->expectExceptionMessageIsOrContains('Template\'s first line must be');

		$this->codegen()->generateFile('db_orm/class_gen', '_main.tpl.php', []);
	}

	/** Every one of the five attributes is required; a missing one is not defaulted. */
	public function testGenerateFileWithIncompleteHeaderThrows() {
		$this->writeTemplate('db_orm/class_gen', '_main.tpl.php', '<template OverwriteFlag="true"/>' . "\nbody");

		$this->expectException(\Exception::class);
		$this->expectExceptionMessageIsOrContains('Template\'s first line must be');

		$this->codegen()->generateFile('db_orm/class_gen', '_main.tpl.php', []);
	}

	//
	// generateFiles and template layering
	//

	public function testGenerateFilesRunsEveryEntryPointInEveryModule() {
		$this->writeTemplate('db_orm/class_gen', '_main.tpl.php', $this->header(['TargetFileName' => 'Gen.php']) . "\ngen");
		$this->writeTemplate('db_orm/class_nodes', '_main.tpl.php', $this->header(['TargetFileName' => 'Node.php']) . "\nnode");

		$this->assertTrue($this->codegen()->generateFiles('db_orm', []));

		$this->assertFileExists($this->docroot . '/generated/Gen.php');
		$this->assertFileExists($this->docroot . '/generated/Node.php');
	}

	/** Only _*.tpl.php files are entry points; partials are included by them, not run. */
	public function testGenerateFilesIgnoresPartials() {
		$this->writeTemplate('db_orm/class_gen', '_main.tpl.php', $this->header(['TargetFileName' => 'Gen.php']) . "\ngen");
		$this->writeTemplate('db_orm/class_gen', 'partial.tpl.php', 'this is not an entry point');

		$this->assertTrue($this->codegen()->generateFiles('db_orm', []));
		$this->assertFileExists($this->docroot . '/generated/Gen.php');
	}

	public function testGenerateFilesWithUnknownPrefixThrowsAndListsCandidates() {
		$this->writeTemplate('db_orm/class_gen', '_main.tpl.php', $this->header() . "\nx");

		try {
			$this->codegen()->generateFiles('db_type', []);
			$this->fail('expected an Exception');
		} catch (\Exception $exception) {
			$this->assertStringContainsString('found no template directory', $exception->getMessage());
			$this->assertStringContainsString('/templates/db_type', $exception->getMessage());
		}
	}

	/**
	 * A later templates path wins outright for a module it provides - the unit of
	 * override is the whole module directory, so a partial can never be loaded from
	 * the layer underneath the entry point that includes it.
	 */
	public function testLaterTemplateDirectoryOverridesEarlierOne() {
		$this->writeTemplate('db_orm/class_gen', '_main.tpl.php', $this->header() . "\nframework version", '/templates');
		$this->writeTemplate('db_orm/class_gen', '_main.tpl.php', $this->header() . "\napplication version", '/app-templates');

		$this->codegen(['/templates', '/app-templates'])->generateFile('db_orm/class_gen', '_main.tpl.php', []);

		$this->assertSame('application version', file_get_contents($this->docroot . '/generated/Output.php'));
	}

	/** A module only one layer provides is generated as well, not just overridden. */
	public function testLayersAddModulesAsWellAsOverrideThem() {
		$this->writeTemplate('db_orm/class_gen', '_main.tpl.php', $this->header(['TargetFileName' => 'Gen.php']) . "\ngen", '/templates');
		$this->writeTemplate('db_orm/class_extra', '_main.tpl.php', $this->header(['TargetFileName' => 'Extra.php']) . "\nextra", '/app-templates');

		$this->assertTrue($this->codegen(['/templates', '/app-templates'])->generateFiles('db_orm', []));

		$this->assertFileExists($this->docroot . '/generated/Gen.php');
		$this->assertFileExists($this->docroot . '/generated/Extra.php');
	}

	//
	// CodeGenRunner settings parsing
	//

	private function writeSettings(string $contents): string {
		$path = $this->docroot . '/codegen.xml';
		file_put_contents($path, $contents);

		return $path;
	}

	public function testRunnerReportsMissingSettingsFile() {
		CodeGenRunner::run($this->docroot, $this->docroot . '/nope.xml');

		$this->assertStringContainsString('was not found', CodeGenRunner::$rootErrors);
		$this->assertStringContainsString('nope.xml', CodeGenRunner::$rootErrors);
	}

	/** A directory is not a settings file either. */
	public function testRunnerReportsSettingsPathThatIsNotAFile() {
		CodeGenRunner::run($this->docroot, $this->docroot);

		$this->assertStringContainsString('was not found', CodeGenRunner::$rootErrors);
	}

	/** The libxml errors are included, so a broken config says where it broke. */
	public function testRunnerReportsMalformedXml() {
		$path = $this->writeSettings('<codegen><dataSources></codegen>');

		CodeGenRunner::run($this->docroot, $path);

		$this->assertStringContainsString('Unable to parse CodeGen Settings XML File', CodeGenRunner::$rootErrors);
		$this->assertStringContainsString($path, CodeGenRunner::$rootErrors);
	}

	public function testRunnerReportsDataSourceWithoutTemplates() {
		$path = $this->writeSettings(
			'<codegen><name application="Test"/><dataSources><database index="1"/></dataSources></codegen>'
		);

		CodeGenRunner::run($this->docroot, $path);

		$this->assertStringContainsString('No <templates path="..."/> configured', CodeGenRunner::$rootErrors);
		$this->assertSame([], CodeGenRunner::$codegenArray);
	}

	/** Only <database/> is a known data source type; anything else is reported by name. */
	public function testRunnerReportsUnknownDataSourceType() {
		$path = $this->writeSettings(
			'<codegen><name application="Test"/><dataSources>' .
			'<service index="1"><templates path="/templates"/></service>' .
			'</dataSources></codegen>'
		);

		CodeGenRunner::run($this->docroot, $path);

		$this->assertStringContainsString('Invalid Data Source Type', CodeGenRunner::$rootErrors);
		$this->assertStringContainsString('service', CodeGenRunner::$rootErrors);
	}

	public function testRunnerReadsTheApplicationName() {
		$path = $this->writeSettings(
			'<codegen><name application="My Application"/><dataSources><database index="1"/></dataSources></codegen>'
		);

		CodeGenRunner::run($this->docroot, $path);

		$this->assertSame('My Application', CodeGenRunner::$applicationName);
		$this->assertSame($path, CodeGenRunner::$settingsFilePath);
	}

	/**
	 * getSettingsXml() echoes the configuration back in the run report, composed
	 * from each data source's own getConfigXml(). Only db:codegen calls it, which
	 * is why it had no coverage.
	 */
	public function testRunnerRendersTheSettingsReport() {
		CodeGenRunner::$applicationName = 'Reported Application';
		CodeGenRunner::$codegenArray = [];

		$rendered = CodeGenRunner::getSettingsXml();

		$this->assertStringContainsString('<codegen>', $rendered);
		$this->assertStringContainsString('<name application="Reported Application"/>', $rendered);
		$this->assertStringContainsString('<dataSources>', $rendered);
		$this->assertStringContainsString('</codegen>', $rendered);
	}

	public function testRunnerSettingsReportIncludesEachDataSource() {
		CodeGenRunner::$applicationName = 'Reported Application';
		CodeGenRunner::$codegenArray = [$this->codegen()];

		$this->assertStringContainsString('<!-- harness -->', CodeGenRunner::getSettingsXml());
	}

	/** Each run starts from a clean slate rather than accumulating earlier results. */
	public function testRunnerResetsItsResultsOnEachRun() {
		$path = $this->writeSettings(
			'<codegen><name application="Test"/><dataSources><database index="1"/></dataSources></codegen>'
		);

		CodeGenRunner::run($this->docroot, $path);
		$this->assertSame([], CodeGenRunner::$codegenArray);

		CodeGenRunner::run($this->docroot, $path);
		$this->assertSame([], CodeGenRunner::$codegenArray);
	}
}

/**
 * A concrete CodeGen with no data source behind it, so the template machinery can
 * be driven directly. Declared here rather than in its own file because it exists
 * only for this test - src/Test/ files are listed one by one in phpunit.xml.dist,
 * and this is not a test class.
 */
class TemplateCodeGenHarness extends CodeGen {

	/** Identifies this harness in the settings report. */
	public function getConfigXml(): string {
		return "\t\t<!-- harness -->\r\n";
	}
}
