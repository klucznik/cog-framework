<?php

namespace Cog\Test;

use Cog\Enum\Environment;
use Cog\Util\NamespaceUtil;
use PHPUnit\Framework\TestCase;

class TestNamespaceUtil extends TestCase {

	public function testClassNameForFile() {
		$this->assertEquals('Cog\Enum\Environment', NamespaceUtil::getClassNameForFile('Cog\Enum', 'Environment.php'));
		$this->assertEquals('Cog\Enum\Environment', NamespaceUtil::getClassNameForFile('Cog\Enum', 'Environment'));

		// A trailing separator on the namespace is tolerated rather than doubled
		$this->assertEquals('Cog\Enum\Environment', NamespaceUtil::getClassNameForFile('Cog\Enum\\', 'Environment.php'));

		// Only the basename of a path is used
		$this->assertEquals(
			'Cog\Enum\Environment',
			NamespaceUtil::getClassNameForFile('Cog\Enum', '/some/where/Environment.php')
		);
	}

	public function testClassesInNamespace() {
		$classes = NamespaceUtil::getClassesInNamespace('Cog\Enum');

		$this->assertContains(Environment::class, $classes);
	}

	/** Every entry has to be a real, loadable class - dot entries and non-PHP files are filtered out. */
	public function testClassesInNamespaceAreAllLoadable() {
		$classes = NamespaceUtil::getClassesInNamespace('Cog\Exceptions');

		$this->assertNotEmpty($classes);
		foreach ($classes as $class) {
			$this->assertTrue(class_exists($class), $class . ' should be loadable');
		}
	}

	public function testNormalize() {
		$this->assertEquals('App\Data', NamespaceUtil::normalize('App\Data'));
		$this->assertEquals('App\Data', NamespaceUtil::normalize('\App\Data'));
		$this->assertEquals('App\Data', NamespaceUtil::normalize('App\Data\\'));
		$this->assertEquals('App\Data', NamespaceUtil::normalize('\App\Data\\'));

		$this->assertEquals('Cog\ExampleApp\Data', NamespaceUtil::normalize('\Cog\ExampleApp\Data'));
		$this->assertEquals('App', NamespaceUtil::normalize('\App\\'));
		$this->assertEquals('', NamespaceUtil::normalize(''));
		$this->assertEquals('', NamespaceUtil::normalize('\\'));
	}

	public function testIsValid() {
		$this->assertTrue(NamespaceUtil::isValid('App'));
		$this->assertTrue(NamespaceUtil::isValid('App\Data'));
		$this->assertTrue(NamespaceUtil::isValid('Cog\ExampleApp\Data'));
		$this->assertTrue(NamespaceUtil::isValid('_Under\Score1'));
	}

	public function testIsValidRejectsMalformed() {
		$this->assertFalse(NamespaceUtil::isValid(''));
		$this->assertFalse(NamespaceUtil::isValid('App\Bad-Name'));
		$this->assertFalse(NamespaceUtil::isValid('1App\Data'));
		$this->assertFalse(NamespaceUtil::isValid('App\\\\Data'));
		$this->assertFalse(NamespaceUtil::isValid('App\Data '));

		// A normalized namespace is expected - the separators must already be gone
		$this->assertFalse(NamespaceUtil::isValid('\App\Data'));
		$this->assertFalse(NamespaceUtil::isValid('App\Data\\'));
	}

	public function testUndefinedNamespace() {
		$this->assertEquals([], NamespaceUtil::getClassesInNamespace('Nowhere\AtAll'));
	}
}
