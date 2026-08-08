<?php

namespace Cog\Console;

use Cog\Util\NamespaceUtil;
use DirectoryIterator;
use ReflectionClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

/**
 * Class CommandApplication
 * @package Cog\Console
 *
 * Console application that discovers its commands from a list of directories,
 * so the framework does not need to know which directories the app provides.
 */
class CommandApplication extends Application {

	/**
	 * Registers every command found in the given directories.
	 *
	 * @param array<string, string> $dirs map of PSR-4 namespace prefix => absolute directory path
	 */
	public function addCommandDirs(array $dirs): static {
		foreach ($dirs as $namespace => $dir) {
			$this->addCommandDir($dir, $namespace);
		}

		return $this;
	}

	/**
	 * Registers every instantiable command class found directly in $dir.
	 *
	 * Abstract classes, non-command classes and subdirectories are skipped, so base
	 * classes and helper directories need no special casing.
	 */
	public function addCommandDir(string $dir, string $namespace): static {
		if (!is_dir($dir)) {
			return $this;
		}

		$commands = [];

		foreach (new DirectoryIterator($dir) as $file) {
			if ($file->isDot() || $file->isDir() || $file->getExtension() !== 'php') { continue; }

			$class = NamespaceUtil::getClassNameForFile($namespace, $file->getBasename());
			if (!class_exists($class)) { continue; }

			$reflection = new ReflectionClass($class);
			if ($reflection->isAbstract() || !$reflection->isSubclassOf(Command::class)) { continue; }

			$commands[] = new $class;
		}

		$this->addCommands($commands);

		return $this;
	}
}
