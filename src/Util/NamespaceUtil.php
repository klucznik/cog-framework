<?php

namespace Cog\Util;

use Cog\Path;
use JsonException;

abstract class NamespaceUtil {

	public static function getClassesInNamespace($namespace): array {
		$dir = self::getNamespaceDirectory($namespace);
		if ($dir === false) {
			return [];
		}

		$files = scandir($dir, true);

		$classes = array_map(static function ($file) use ($namespace) {
			return self::getClassNameForFile($namespace, $file);
		}, $files);

		sort($classes);
		return array_filter($classes, 'class_exists');
	}

	/**
	 * Builds the fully qualified class name for a PHP file living directly in $namespace.
	 *
	 * @param string $namespace namespace prefix, with or without a trailing separator
	 * @param string $file file name, with or without the .php extension
	 */
	public static function getClassNameForFile(string $namespace, string $file): string {
		return self::normalize($namespace) . '\\' . basename($file, '.php');
	}

	/**
	 * Trims the leading and trailing separators off a namespace, so callers can
	 * compose '\' . $namespace . '\' . $className without having to know how the
	 * namespace was written.
	 */
	public static function normalize(string $namespace): string {
		return trim($namespace, '\\');
	}

	/**
	 * Whether the given string is a syntactically valid namespace. An empty
	 * string is not: the global namespace is not something you can write.
	 */
	public static function isValid(string $namespace): bool {
		return (bool)preg_match('/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*$/', $namespace);
	}

	private static function getDefinedNamespaces(): array {
		$composerJsonPath = Path::$appRoot . '/composer.json';
		try {
			$composerConfig = json_decode(file_get_contents($composerJsonPath), false, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			return [];
		}

		//Apparently PHP doesn't like hyphens, so we use variable variables instead.
		$psr4 = 'psr-4';
		return (array)$composerConfig->autoload->$psr4;
	}

	private static function getNamespaceDirectory($namespace): false|string {
		$composerNamespaces = self::getDefinedNamespaces();

		$namespaceFragments = explode('\\', $namespace);
		$undefinedNamespaceFragments = [];

		while ($namespaceFragments) {
			$possibleNamespace = implode('\\', $namespaceFragments) . '\\';

			if (array_key_exists($possibleNamespace, $composerNamespaces)) {
				return realpath(Path::$appRoot . '/' . $composerNamespaces[$possibleNamespace] . '/' . implode('/', $undefinedNamespaceFragments));
			}

			$undefinedNamespaceFragments[] = array_pop($namespaceFragments);
		}

		return false;
	}
}
