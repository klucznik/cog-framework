<?php

namespace Cog\Codegen;

use Cog\Base;
use Cog\Exceptions\CogException;
use Cog\Type;
use Exception;
use SimpleXMLElement;

/**
 * This is the CodeGen class which performs the code generation
 * for both the Object-Relational Model (e.g. Data Objects) and
 * the draft Form, which make up simple HTML/PHP scripts to perform
 * basic CRUD functionality on each object.
 *
 * @package Codegen
 * @property string $errors
 * @property string $warnings
 */
abstract class CodeGen extends Base {
	/** @var string Class Name Prefix */
	protected string $classPrefix = '';
	/** @var string Class Name Suffix */
	protected string $classSuffix = '';

	protected string $errors = '';
	protected string $warnings = '';

	// PHP Reserved Words.  They make up:
	// Invalid Type names -- these are reserved words which cannot be Type names in any user type table
	// Invalid Table names -- these are reserved words which cannot be used as any table name
	//please refer to : http://php.net/manual/en/reserved.php
	protected const PHP_RESERVED_WORDS = 'new, null, break, return, switch, self, case, const, clone, continue, declare, default, echo, else, ' .
		'elseif, empty, exit, eval, if, try, throw, catch, public, private, protected, function, extends, foreach, for, while, do, var, ' .
		'class, static, abstract, isset, unset, implements, interface, instanceof, include, include_once, require, require_once, ' .
		'abstract, and, or, xor, array, list, false, true, global, parent, print, exception, namespace, goto, final, endif, endswitch, ' .
		'enddeclare, endwhile, use, as, endfor, endforeach, this, iterable, object, void, bool, int, string, resource, mixed, numeric, ' .
		'trait, insteadof, callable, finally, yield from, yield';

	// Relative Paths to the CORE Template and sub template Directories
	protected string $docroot;

	/**
	 * Docroot-relative template directories, in the order they were configured.
	 * Later entries override earlier ones, a whole module directory at a time.
	 * @var string[]
	 */
	protected array $templatesPaths;

	public function getTitle(): string { return ''; }

	public function getReportLabel(): string { return ''; }

	public function generateAll(): string { return ''; }

	public function getConfigXml(): string { return ''; }

	/**
	 * CodeGen constructor.
	 * @param string $docroot
	 * @param string[] $templatePaths docroot-relative template directories, in override order
	 * @param SimpleXMLElement $settingsXml
	 */
	public function __construct(string $docroot, array $templatePaths, SimpleXMLElement $settingsXml) {
		$this->docroot = $docroot;
		$this->templatesPaths = array_values($templatePaths);
	}

	/**
	 * Given a template prefix (e.g. db_orm_, db_type_, rest_, soap_, etc.), pull
	 * all the _*.tpl templates from any sub folders of the template prefix in Cog\Codegen\self::TemplatesPath,
	 * and call GenerateFile() on each one.
	 *
	 * @param string $templatePrefix the prefix of the templates you want to generate against
	 * @param array $argumentArray array of arguments to send to EvaluateTemplate
	 * @return boolean success/failure on whether all the files generated successfully
	 * @throws Exception
	 */
	public function generateFiles(string $templatePrefix, array $argumentArray): bool {
		// Make sure at least one of the configured template paths provides the prefix
		$prefixDirs = Utils::templateDirs($this->docroot, $this->templatesPaths, $templatePrefix);

		if ($prefixDirs === []) {
			throw new Exception(sprintf(
				"CodeGen found no template directory for '%s'. Tried:\r\n%s",
				$templatePrefix,
				Utils::templateDirCandidates($this->docroot, $this->templatesPaths, $templatePrefix)
			));
		}

		// Collect the module names (e.g. "class_gen", "class_nodes") contributed by any
		// of the template paths, then let the last path providing each one win outright.
		$moduleNames = [];

		foreach ($prefixDirs as $prefixDir) {
			foreach (Utils::moduleNames($prefixDir) as $moduleName) {
				$moduleNames[$moduleName] = true;
			}
		}

		// Index by [module_name][filename] where module name is resolved to the single
		// directory that wins for it, and filename is a _*.tpl.php entry point in there
		$templateArray = [];

		foreach (array_keys($moduleNames) as $moduleName) {
			$moduleDir = Utils::resolveModuleDir($this->docroot, $this->templatesPaths, $templatePrefix . '/' . $moduleName);

			$moduleDirectory = opendir($moduleDir);
			while ($filename = readdir($moduleDirectory)) {
				if (str_starts_with($filename, '_') && str_ends_with($filename, '.tpl.php')) {
					$templateArray[$moduleName][$filename] = true;
				}
			}

			closedir($moduleDirectory);
		}

		// Finally, iterate through all the TemplateFiles and call GenerateFile to Evaluate/Generate/Save them
		$success = true;

		foreach ($templateArray as $moduleName => $fileArray) {
			foreach (array_keys($fileArray) as $filename) {
				if (!$this->generateFile($templatePrefix . '/' . $moduleName, $filename, $argumentArray)) {
					$success = false;
				}
			}
		}

		return $success;
	}

	/**
	 * @param string $moduleName
	 * @param string $filename
	 * @param array $argumentArray
	 * @param boolean $save whenever or not to actually perform the save
	 *
	 * @return string | bool returns the evaluated template or boolean save success.
	 *
	 * @throws Exception
	 * @throws CogException
	 */
	public function generateFile(string $moduleName, string $filename, array $argumentArray, bool $save = true): bool|string {
		// Figure out the actual TemplateFilePath. The module directory is resolved as a
		// whole, so a template never picks up partials from a layer it is overriding.
		$moduleDir = Utils::resolveModuleDir($this->docroot, $this->templatesPaths, $moduleName);

		if ($moduleDir === null) {
			throw new CogException(sprintf(
				"Template Module Not Found: %s. Tried:\r\n%s",
				$moduleName,
				Utils::templateDirCandidates($this->docroot, $this->templatesPaths, $moduleName)
			));
		}

		$templateFilePath = $moduleDir . '/' . $filename;

		// Setup Debug/CogException Message
		if (CodeGenRunner::DEBUG_MODE) {
			echo "Evaluating $templateFilePath<br/>";
		}
		$error = 'Template\'s first line must be <template OverwriteFlag="boolean" DocrootFlag="boolean" TargetDirectory="string" DirectorySuffix="string" TargetFileName="string"/>: ' . $templateFilePath;

		// Check to see if the template file exists, and if it does, Load It
		if (file_exists($templateFilePath) === false) {
			throw new CogException('Template File Not Found: ' . $templateFilePath);
		}

		// Evaluate the Template
		$template = Utils::evaluatePHP($this, $templateFilePath, $moduleName, $argumentArray);

		// Parse out the first line (which contains path and overwriting information)
		$position = strpos($template, "\n");
		if ($position === false) {
			throw new Exception($error);
		}

		$firstLine = trim(substr($template, 0, $position));
		$template = substr($template, $position + 1);

		$templateXml = null;

		// Attempt to Parse the First Line as XML
		try {
			$templateXml = new SimpleXMLElement($firstLine);
		} catch (Exception $exception) {}

		if ($templateXml === null || !$templateXml instanceof SimpleXMLElement) {
			throw new Exception($error);
		}

		$overwriteFlag = Type::cast($templateXml['OverwriteFlag'], Type::BOOLEAN);
		$docrootFlag = Type::cast($templateXml['DocrootFlag'], Type::BOOLEAN);
		$targetDirectory = Type::cast($templateXml['TargetDirectory'], Type::STRING);
		$directorySuffix = Type::cast($templateXml['DirectorySuffix'], Type::STRING);
		$targetFileName = Type::cast($templateXml['TargetFileName'], Type::STRING);

		if ($overwriteFlag === null || $targetFileName === null || $targetDirectory === null || $directorySuffix === null || $docrootFlag === null) {
			throw new Exception($error);
		}

		if ($save && $targetDirectory) {
			// Figure out the REAL target directory
			if ($docrootFlag) {
				$targetDirectory = $this->docroot . $targetDirectory . $directorySuffix;
			} else {
				$targetDirectory .= $directorySuffix;
			}

			// Create Directory (if needed). Recursive, because a template's target
			// directory is usually nested (e.g. /generated/Data) and its parent
			// does not necessarily exist yet.
			if (is_dir($targetDirectory) === false) {
				$mkdirResult = mkdir($targetDirectory, 0777, true);
				if ($mkdirResult === false && is_dir($targetDirectory) === false) {
					throw new Exception('Unable to mkdir ' . $targetDirectory);
				}
			}

			// Save to Disk
			$filePath = sprintf('%s/%s', $targetDirectory, $targetFileName);

			if ($overwriteFlag || file_exists($filePath) === false) {
				$bytesSaved = file_put_contents($filePath, $template);
				Utils::setGeneratedFilePermissions($filePath);

				return ($bytesSaved === strlen($template));
			}

			// Because we are not supposed to overwrite, we should return "true" by default
			return true;
		}

		// Why Did We Not Save?
		if ($save) {
			// We WANT to Save, but configuration says that this functionality/feature should no longer be generated
			// By definition, we should return "true"
			return true;
		}

		// Running GenerateFile() specifically asking it not to save -- so return the evaluated template instead
		return $template;
	}

	/**
	 * @param CodeGen[] $codeGenArray
	 * @return array
	 */
	public static function generateAggregateHelper(array $codeGenArray): array {
		return [];
	}

	/**
	 * Override method to perform a property "Get" This will get the value of $name
	 * @param string $name Name of the property to get
	 * @return mixed
	 * @throws CogException
	 */
	public function __get($name) {
		switch ($name) {
			case 'errors':
				return $this->errors;
			case 'warnings':
				return $this->warnings;

			default:
				try {
					return parent::__get($name);
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}

	public function __set($name, $value) {
		try {
			switch ($name) {
				case 'errors':
					return $this->errors = Type::cast($value, Type::STRING);
				case 'warnings':
					return $this->warnings = Type::cast($value, Type::STRING);

				default:
					return parent::__set($name, $value);
			}
		} catch (CogException $exception) {
			$exception->incrementOffset();
		}

		return null; // @codeCoverageIgnore
	}
}
