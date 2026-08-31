<?php

namespace Cog\Codegen;

use Cog\Type;
use Cog\Util\ConvertNotation;

/**
 * Stateless helpers for the Code Generator: everything that has to do with naming variables and is a pure function
 *
 * @package Codegen
 */
abstract class VariableNameCreator {

	/**
	 * @param Column $column
	 * @return string
	 */
	public static function variableNameFromColumn(Column $column): string {
		return ConvertNotation::prefixFromType($column->variableType) . ConvertNotation::pascalCase($column->name);
	}

	/**
	 * @param Column $column
	 * @return string
	 */
	public static function variableNameFromColumnWithType(Column $column): string {
		return ConvertNotation::camelCase($column->name);
	}

	/**
	 * The function determines whether there is a comment on the column or not.
	 * If yes, and the settings for the database has the option for using comments for Meta Control label names turned on
	 * along with a preferred delimiter supplied, then the function will return the computed meta control label name. Otherwise it
	 * just returns the propertyName of the column.
	 * @param Column $column
	 * @internal param string $delimiter
	 * @return string
	 */
	public static function metaControlLabelNameFromColumn(Column $column): string {
		$delimiter = null;
		$table = $column->ownerTable;
		$dbIndex = $table->ownerDbIndex;

		foreach (CodeGenRunner::$codegenArray as $databaseCodeGen) {
			if ($databaseCodeGen instanceof DatabaseCodeGen && $databaseCodeGen->databaseIndex === $dbIndex) {
				$delimiter = $databaseCodeGen->commentMetaControlLabelDelimiter;
				break;
			}
		}

		// No configured delimiter, and no matching codegen at all, both mean "fall
		// back to the property name". $delimiter is still null in the latter case,
		// which trim() has rejected since PHP 8.1.
		if ($delimiter === null || trim($delimiter) === '') {
			$delimiter = null;
		}

		if ($delimiter && $column->comment && ($labelText = strstr($column->comment, $delimiter, true))) {
			return str_replace("'", "\\'", $labelText);
		}

		return ConvertNotation::wordsFromCamelCase($column->propertyName);
	}

	/**
	 * @param Column $column
	 * @return string
	 */
	public static function propertyNameFromColumn(Column $column): string {
		return ConvertNotation::camelCase($column->name);
	}

	/**
	 * @param Column $column
	 * @return string
	 */
	public static function referenceColumnNameFromColumn(Column $column): string {
		$columnName = $column->name;
		$length = strlen($columnName);

		// Does the column name for this reference column end in "_id"?
		if (($length > 3) && (substr($columnName, $length - 3) === '_id')) {
			// It ends in "_id" but we don't want to include the "Id" suffix
			// in the Variable Name.  So remove it.
			$columnName = substr($columnName, 0, $length - 3);
		} else {
			// Otherwise, let's add "_object" so that we don't confuse this variable name
			// from the variable that was mapped from the physical database
			// E.g., if it's a numeric FK, and the column is defined as "person INT",
			// there will end up being two variables, one for the Person id integer, and
			// one for the Person object itself.  We'll add Object t o the name of the Person object
			// to make this declination.
			$columnName = sprintf('%s_object', $columnName);
		}

		return $columnName;
	}

	/**
	 * @param Column $column
	 * @return string
	 */
	public static function referenceVariableNameFromColumn(Column $column): string {
		$name = self::referenceColumnNameFromColumn($column);
		return ConvertNotation::prefixFromType(Type::OBJECT) . ConvertNotation::pascalCase($name);
	}

	/**
	 * @param Column $column
	 * @return string
	 */
	public static function referencePropertyNameFromColumn(Column $column): string {
		return ConvertNotation::camelCase(self::referenceColumnNameFromColumn($column));
	}

	/**
	 * @param Column $column
	 * @return string
	 */
	public static function formControlVariableNameForColumn(Column $column): string {
		if ($column->identity || $column->timestamp) {
			return sprintf('lbl%s', $column->propertyNameUppercase);
		}

		if ($column->reference) {
			return sprintf('lst%s', $column->reference->propertyNameUppercase);
		}

		return match ($column->variableType) {
			Type::BOOLEAN => sprintf('chk%s', $column->propertyNameUppercase),
			Type::DATETIME => sprintf('cal%s', $column->propertyNameUppercase),
			default => sprintf('txt%s', $column->propertyNameUppercase),
		};
	}

	/**
	 * @param Column $column
	 * @return string
	 */
	public static function translationNameForColumn(Column $column): string {
		return ConvertNotation::translationNameFromString(self::formControlVariableNameForColumn($column));
	}
}
