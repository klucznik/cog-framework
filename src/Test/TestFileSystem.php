<?php

namespace Cog\Test;

use Cog\Exceptions\CogException;
use Cog\Util\FileSystem;
use PHPUnit\Framework\TestCase;

class TestFileSystem extends TestCase {

	/** @var string a scratch directory created per test and removed in tearDown */
	private string $workDirectory;

	public function setUp(): void {
		$this->workDirectory = sys_get_temp_dir() . '/cog-test-' . bin2hex(random_bytes(8));
		mkdir($this->workDirectory);
	}

	public function tearDown(): void {
		if (is_dir($this->workDirectory)) {
			FileSystem::removeDirectory($this->workDirectory);
		}
	}

	public function testSanitizeFilename() {
		$this->assertEquals('report.pdf', FileSystem::sanitizeFilename('report.pdf'));
		$this->assertEquals('my report (final) [v2].pdf', FileSystem::sanitizeFilename('my report (final) [v2].pdf'));
		$this->assertEquals('a-b_c~d,e;f.g', FileSystem::sanitizeFilename('a-b_c~d,e;f.g'));
	}

	public function testSanitizeFilenameStripsPathSeparators() {
		$this->assertEquals('etcpasswd', FileSystem::sanitizeFilename('../../etc/passwd'));
		$this->assertEquals('etcpasswd', FileSystem::sanitizeFilename('/etc/passwd'));
		$this->assertEquals('C:Windowssystem32', FileSystem::sanitizeFilename('C:\Windows\system32'));
	}

	public function testSanitizeFilenameStripsPeriodRuns() {
		$this->assertEquals('file.txt', FileSystem::sanitizeFilename('file.txt'));
		$this->assertEquals('filetxt', FileSystem::sanitizeFilename('file..txt'));
		$this->assertEquals('', FileSystem::sanitizeFilename('...'));
	}

	public function testSanitizeFilenameStripsMarkup() {
		$this->assertEquals('name.txt', FileSystem::sanitizeFilename('na<me>.txt'));
		$this->assertEquals('scriptalert(1)script.txt', FileSystem::sanitizeFilename('<script>alert(1)</script>.txt'));
	}

	public function testSanitizeFilenameKeepsLettersFromAnyScript() {
		$this->assertEquals('żółć.txt', FileSystem::sanitizeFilename('żółć.txt'));
		$this->assertEquals('gęślą jaźń.pdf', FileSystem::sanitizeFilename('gęślą jaźń.pdf'));
		$this->assertEquals('文書.txt', FileSystem::sanitizeFilename('文書.txt'));
		$this->assertEquals('résumé (v2).doc', FileSystem::sanitizeFilename('résumé (v2).doc'));

		// Non-ASCII punctuation is still dropped
		$this->assertEquals('quoted.txt', FileSystem::sanitizeFilename('«quoted».txt'));
	}

	/** Invalid UTF-8 cannot be sanitized meaningfully, so nothing is returned. */
	public function testSanitizeFilenameWithInvalidUtf8() {
		$this->assertEquals('', FileSystem::sanitizeFilename("bad\xC3name.txt"));
	}

	public function testGetMimeType() {
		$textFile = $this->workDirectory . '/notes.txt';
		file_put_contents($textFile, 'plain text content');

		$this->assertEquals('text/plain', FileSystem::getMimeType($textFile));
	}

	public function testRemoveDirectory() {
		$nested = $this->workDirectory . '/one/two';
		mkdir($nested, 0777, true);
		file_put_contents($nested . '/deep.txt', 'deep');
		file_put_contents($this->workDirectory . '/one/shallow.txt', 'shallow');

		$this->assertTrue(FileSystem::removeDirectory($this->workDirectory . '/one'));
		$this->assertDirectoryDoesNotExist($this->workDirectory . '/one');
		$this->assertDirectoryExists($this->workDirectory);
	}

	public function testRemoveDirectoryOnNonDirectory() {
		$file = $this->workDirectory . '/not-a-directory.txt';
		file_put_contents($file, 'content');

		$this->expectException(CogException::class);
		FileSystem::removeDirectory($file);
	}

	public function testCleanDirectory() {
		file_put_contents($this->workDirectory . '/first.txt', 'first');
		file_put_contents($this->workDirectory . '/second.txt', 'second');
		mkdir($this->workDirectory . '/subdirectory');
		file_put_contents($this->workDirectory . '/subdirectory/kept.txt', 'kept');

		$this->assertEquals(2, FileSystem::cleanDirectory($this->workDirectory));

		$this->assertFileDoesNotExist($this->workDirectory . '/first.txt');
		$this->assertFileDoesNotExist($this->workDirectory . '/second.txt');

		// Only files directly in the directory are removed
		$this->assertDirectoryExists($this->workDirectory . '/subdirectory');
		$this->assertFileExists($this->workDirectory . '/subdirectory/kept.txt');
	}

	public function testCleanDirectoryWithOmittedFiles() {
		file_put_contents($this->workDirectory . '/.gitignore', '*');
		file_put_contents($this->workDirectory . '/cache.tmp', 'tmp');

		$this->assertEquals(1, FileSystem::cleanDirectory($this->workDirectory, ['.gitignore']));

		$this->assertFileExists($this->workDirectory . '/.gitignore');
		$this->assertFileDoesNotExist($this->workDirectory . '/cache.tmp');
	}

	public function testCleanEmptyDirectory() {
		$this->assertEquals(0, FileSystem::cleanDirectory($this->workDirectory));
	}
}
