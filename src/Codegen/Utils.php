<?php

namespace Cog\Codegen;

use Cog\Exceptions\CogException;
use Cog\Type;
use Cog\Util\StringUtils;
use Cog\Util\Template;
use Exception;
use SimpleXMLElement;
use Symfony\Component\String\Inflector\EnglishInflector;
use Symfony\Component\String\Inflector\InflectorInterface;

/**
 * Stateless helpers for the Code Generator: settings lookup, template directory
 * resolution, template evaluation and the naming helpers the templates call.
 *
 * @package Codegen
 */
abstract class Utils {

	/** @var string[] array of directories to be excluded in codegen (lower cased) */
	protected static array $directoriesToExcludeArray = ['.', '..', '.svn', 'svn', 'cvs', '.git'];

	protected static ?InflectorInterface $inflector = null;

	/**
	 * This will look up either the node value (if no attribute name is passed in) or the attribute value
	 * for a given Tag.  Node Searches only apply from the root level of the configuration XML being passed in
	 * (e.g. it will not be able to look up the tag name of a grandchild of the root node)
	 *
	 * If No Tag Name is passed in, then attribute/value lookup is based on the root node, itself.
	 *
	 * @param SimpleXmlElement $node
	 * @param string|null $tagName
	 * @param string|null $attributeName
	 * @param string $type
	 * @return mixed the return type depends on the Type you pass in to $strType
	 * @throws CogException
	 */
	public static function lookupSetting(\SimpleXmlElement $node, ?string $tagName, ?string $attributeName = null, string $type = Type::STRING): mixed {
		if ($tagName) {
			$node = $node->$tagName;
		}

		if ($attributeName) {
			switch ($type) {
				case Type::INTEGER:
					try {
						return Type::cast($node[$attributeName], Type::INTEGER);
					} catch (\Exception $exception) {
						return null;
					}
				case Type::BOOLEAN:
					try {
						return Type::cast($node[$attributeName], Type::BOOLEAN);
					} catch (\Exception $exception) {
						return null;
					}
				default:
					return StringUtils::trim(Type::cast($node[$attributeName], Type::STRING));
			}
		} else {
			return StringUtils::trim(Type::cast($node, Type::STRING));
		}
	}

	/**
	 * The repeatable counterpart to lookupSetting(): collects the attribute value of
	 * every occurrence of a tag, in document order, skipping empty ones.
	 *
	 * @param SimpleXmlElement $node
	 * @param string $tagName
	 * @param string $attributeName
	 * @return string[]
	 * @throws CogException
	 */
	public static function lookupSettings(\SimpleXmlElement $node, string $tagName, string $attributeName): array {
		$values = [];

		foreach ($node->$tagName as $child) {
			$value = StringUtils::trim(Type::cast($child[$attributeName], Type::STRING));

			if ($value !== '' && $value !== null) {
				$values[] = $value;
			}
		}

		return $values;
	}

	/**
	 * Every configured directory that actually holds the given relative path, in
	 * configuration order.
	 *
	 * @param string $docroot
	 * @param string[] $templatesPaths docroot-relative template directories, in override order
	 * @param string $relative a prefix (e.g. db_orm) or a prefix/module pair (e.g. db_orm/class_gen)
	 * @return string[] absolute paths
	 */
	public static function templateDirs(string $docroot, array $templatesPaths, string $relative): array {
		$dirs = [];

		foreach ($templatesPaths as $templatesPath) {
			$dir = $docroot . $templatesPath . '/' . $relative;
			if (is_dir($dir)) {
				$dirs[] = $dir;
			}
		}

		return $dirs;
	}

	/**
	 * The directory that wins for the given relative path. Later configured
	 * template directories override earlier ones, so the last match is used.
	 *
	 * The override is deliberately whole-directory: templates pull their partials
	 * in with include __DIR__ . '/partial.tpl.php', so a per-file merge would let
	 * an override load siblings from the layer underneath it.
	 *
	 * @param string $docroot
	 * @param string[] $templatesPaths
	 * @param string $relative a prefix (e.g. db_orm) or a prefix/module pair (e.g. db_orm/class_gen)
	 * @return string|null absolute path, or null when no layer provides it
	 */
	public static function resolveModuleDir(string $docroot, array $templatesPaths, string $relative): ?string {
		$dirs = self::templateDirs($docroot, $templatesPaths, $relative);

		return $dirs ? end($dirs) : null;
	}

	/**
	 * All the paths that were searched for the given relative path, for error messages.
	 *
	 * @param string $docroot
	 * @param string[] $templatesPaths
	 * @param string $relative
	 * @return string
	 */
	public static function templateDirCandidates(string $docroot, array $templatesPaths, string $relative): string {
		return implode("\r\n", array_map(
			static fn(string $templatesPath): string => $docroot . $templatesPath . '/' . $relative,
			$templatesPaths
		));
	}

	/**
	 * The module names (e.g. "class_gen", "class_nodes") in a single template prefix
	 * directory - its subdirectories, minus the housekeeping ones.
	 *
	 * @param string $prefixDir absolute path
	 * @return string[]
	 */
	public static function moduleNames(string $prefixDir): array {
		$moduleNames = [];

		$directory = opendir($prefixDir);

		while (($moduleName = readdir($directory)) !== false) {
			if (is_dir($prefixDir . '/' . $moduleName) && !in_array(strtolower($moduleName), self::$directoriesToExcludeArray, true)) {
				$moduleNames[] = $moduleName;
			}
		}

		closedir($directory);

		return $moduleNames;
	}

	/**
	 * @param string $filePath
	 * @throws Exception
	 */
	public static function setGeneratedFilePermissions(string $filePath): void {
		$chmodResult = chmod($filePath, 0666);
		if ($chmodResult === false) {
			throw new Exception('Unable to chmod ' . $filePath);
		}
	}

	/**
	 * @param CodeGen $codegen the generator the template reaches back into as $codegen
	 * @param string $filename
	 * @param string $moduleName
	 * @param array $argumentArray
	 * @return string|string[]
	 * @throws CogException
	 */
	public static function evaluatePHP(CodeGen $codegen, string $filename, string $moduleName, array $argumentArray): array|string {
		// Of course, we also need to locally allow "$codegen"
		$argumentArray['codegen'] = $codegen;
		$argumentArray['moduleName'] = $moduleName;

		$toReturn = Template::render($filename, $argumentArray);

		// Remove all \r from the template (for Win/*nix compatibility)
		return str_replace("\r", '', $toReturn);
	}

	/**
	 * Rewinds the template output buffer by a number of characters.
	 *
	 * Templates emit lists by writing a separator on every pass of a loop, which
	 * leaves a trailing one behind. Calling this straight after the loop removes
	 * it, so the list ends cleanly:
	 *
	 *     <?php foreach ($columns as $column) { ?>
	 *         '<?= $column->name ?>',
	 *     <?php } ?><?php Utils::goBack(2); ?>];
	 *
	 * It acts on whatever output buffer is innermost, which is the one opened by
	 * Template::render() while the template is being evaluated.
	 *
	 * @param int $characters how many characters to drop from the end
	 * @return void
	 */
	public static function goBack(int $characters): void {
		$contentSoFar = ob_get_contents();
		ob_end_clean();

		$contentSoFar = substr($contentSoFar, 0, -$characters);

		ob_start();
		print $contentSoFar;
	}

	/**
	 * Pluralizing function.
	 *
	 * Cog\Util\StringUtils::pluralize() does the same thing, but resolves its
	 * inflector out of the DI container - which codegen cannot rely on having been
	 * booted. This one builds its own.
	 *
	 * @param string $name
	 * @return string
	 */
	public static function pluralize(string $name): string {
		if (self::$inflector === null) {
			self::$inflector = new EnglishInflector();
		}

		$pluralization = self::$inflector->pluralize($name);
		return array_shift($pluralization);
	}
}
