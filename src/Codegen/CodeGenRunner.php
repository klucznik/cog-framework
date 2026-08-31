<?php

namespace Cog\Codegen;

use Cog\Base;
use Cog\Exceptions\CogException;
use SimpleXMLElement;

/**
 * This is the CodeGen class which performs the code generation
 * for both the Object-Relational Model (e.g. Data Objects) and
 * the draft Form, which make up simple HTML/PHP scripts to perform
 * basic CRUD functionality on each object.
 *
 * @package Codegen
 * @param string $errors
 * @param string $warnings
 */
abstract class CodeGenRunner extends Base {
	// DebugMode -- for Template Developers
	// This will output the current evaluated template/statement to the screen
	// On "eval" errors, you can click on the "View Rendered Page" to see what currently
	// is being evaluated, which should hopefully aid in template debugging.
	public const DEBUG_MODE = false;

	/**
	 * This static array contains an array of active and executed codegen objects, based
	 * on the XML Configuration passed in to Run()
	 * @var CodeGen[] array of active/executed codegen objects
	 */
	public static array $codegenArray;

	/** @var SimpleXMLElement This is the SimpleXML representation of the Settings XML file */
	protected static SimpleXMLElement $settingsXml;

	public static string $settingsFilePath;

	/** @var string Application Name (from CodeGen Settings) */
	public static string $applicationName;

	public static string $rootErrors = '';

	/**
	 * @return string
	 */
	public static function getSettingsXml(): string {
		$crLf = "\r\n";

		$toReturn = sprintf('<codegen>%s', $crLf);
		$toReturn .= sprintf('	<name application="%s"/>%s', self::$applicationName, $crLf);
		$toReturn .= sprintf('	<dataSources>%s', $crLf);
		foreach (self::$codegenArray as $codegen) {
			$toReturn .= $crLf . $codegen->getConfigXml();
		}
		$toReturn .= sprintf('%s	</dataSources>%s', $crLf, $crLf);
		$toReturn .= '</codegen>';

		return $toReturn;
	}

	/**
	 * @param string $docroot
	 * @param string $settingsXmlFilePath
	 * @throws CogException
	 */
	public static function run(string $docroot, string $settingsXmlFilePath): void {
		self::$codegenArray = [];
		self::$settingsFilePath = $settingsXmlFilePath;

		if (file_exists($settingsXmlFilePath) === false || is_file($settingsXmlFilePath) === false) {
			self::$rootErrors = 'FATAL ERROR: Cog\Codegen\CodeGen Settings XML File (' . $settingsXmlFilePath . ') was not found.';
			return;
		}

		// Try Parsing the Xml Settings File
		try {
			libxml_use_internal_errors(true);
			self::$settingsXml = new SimpleXMLElement(file_get_contents($settingsXmlFilePath));
		} catch (\Exception $exception) {
			self::$rootErrors .= 'FATAL ERROR: Unable to parse CodeGen Settings XML File: ' . $settingsXmlFilePath;
			self::$rootErrors .= "\r\n";
			foreach(libxml_get_errors() as $error) {
				self::$rootErrors .= ' - ' . $error->message;
			}
			self::$rootErrors .= $exception->getMessage();
			return;
		}

		// Application Name
		self::$applicationName = Utils::lookupSetting(self::$settingsXml, 'name', 'application');

		// Iterate Through DataSources
		if (self::$settingsXml->dataSources->asXML()) {
			foreach (self::$settingsXml->dataSources->children() as $childNode) {
				// <templates/> may be repeated - later entries override earlier ones,
				// a whole module directory at a time.
				$templatesPaths = Utils::lookupSettings($childNode, 'templates', 'path');

				if ($templatesPaths === []) {
					self::$rootErrors .= sprintf("No <templates path=\"...\"/> configured for a data source in CodeGen Settings XML File (%s)\r\n",
						$settingsXmlFilePath);
					continue;
				}

				switch (dom_import_simplexml($childNode)->nodeName) {
					case 'database':
						self::$codegenArray[] = new DatabaseCodeGen($docroot, $templatesPaths, $childNode);
						break;
					default:
						self::$rootErrors .= sprintf("Invalid Data Source Type in CodeGen Settings XML File (%s): %s\r\n",
							$settingsXmlFilePath, dom_import_simplexml($childNode)->nodeName);
						break;
				}
			}
		}
	}

	/**
	 *
	 * @return string[]
	 * @throws \Exception
	 */
	public static function generateAggregate(): array {
		$codeGenDb = [];

		foreach (self::$codegenArray as $codegen) {
			if ($codegen instanceof DatabaseCodeGen) {
				$codeGenDb[] = $codegen;
			}
		}

		return DatabaseCodeGen::generateAggregateHelper($codeGenDb);
	}
}
