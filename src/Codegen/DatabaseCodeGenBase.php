<?php

namespace Cog\Codegen;

use Cog\Database\FieldType;
use Cog\Exceptions\CogException;
use Cog\Type;
use Cog\Util\ConvertNotation;
use Cog\Util\StringUtils;
use Exception;

/**
 * This is the class holds helper functions for the DatabaseCodeGen
 * @package Codegen
 */
abstract class DatabaseCodeGenBase extends CodeGen {
	/** @var string Table prefix */
	protected string $stripTablePrefix;
	/** @var int Table prefix length */
	protected int $stripTablePrefixLength;

	/**
	 * @param string $tableName
	 * @param bool $firstLetterLowerCase
	 * @return string
	 */
	public function classNameFromTableName(string $tableName, bool $firstLetterLowerCase = false): string {
		$tableName = $this->stripPrefixFromTable($tableName);
		return sprintf('%s%s%s',
			$this->classPrefix,
			$firstLetterLowerCase ? ConvertNotation::camelCase($tableName) : ConvertNotation::pascalCase($tableName),
			$this->classSuffix);
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public function typeNameFromColumnName(string $name): string {
		return ConvertNotation::camelCase($name);
	}

	/**
	 * @param string $tableName
	 * @return string
	 */
	public function variableNameFromTable(string $tableName): string {
		$tableName = $this->stripPrefixFromTable($tableName);
		return ConvertNotation::prefixFromType(Type::OBJECT) . ConvertNotation::pascalCase($tableName);
	}

	/**
	 * @param string $tableName
	 * @return string
	 */
	public function reverseReferenceVariableNameFromTable(string $tableName): string {
		$tableName = $this->stripPrefixFromTable($tableName);
		return $this->variableNameFromTable($tableName);
	}

	/**
	 * @param string $tableName
	 * @return string
	 */
	public function reverseReferenceVariableTypeFromTable(string $tableName): string {
		$tableName = $this->stripPrefixFromTable($tableName);
		return $this->classNameFromTableName($tableName);
	}

	/**
	 * @param Column $column
	 * @param bool $includeEquality
	 * @return string
	 */
	protected function parameterCleanupFromColumn(Column $column, bool $includeEquality = false): string {
		if ($includeEquality) {
			return sprintf('$%s = $database->sqlVariable($%s, true);', $column->variableName, $column->variableName);
		}

		return sprintf('$%s = $database->sqlVariable($%s);', $column->variableName, $column->variableName);
	}

	/**
	 * If applicable, strip any StripTablePrefix from the table name
	 * @param $tableName
	 * @return string
	 */
	protected function stripPrefixFromTable($tableName): string {

		if ($this->stripTablePrefixLength && strlen($tableName) > $this->stripTablePrefixLength && (str_starts_with($tableName, $this->stripTablePrefix))) {
			return substr($tableName, $this->stripTablePrefixLength);
		}

		return $tableName;
	}

	//

	/**
	 * To be used to list the columns as input parameters, or as parameters for sprintf
	 * @param Column[] $columnArray
	 * @return string
	 * @throws CogException
	 */
	public function parameterListFromColumnArray(array $columnArray): string {
		$toReturn = [];

		if ($columnArray) {
			foreach ($columnArray as $object) {
				$toReturn[] = sprintf('?%s $%s', $object->__get('variableTyped'), $object->__get('propertyName'));
			}
		}

		return implode(', ', $toReturn);
	}

	/**
	 * To be used to list the columns as input parameters, or as parameters for sprintf
	 * @param Column[] $columnArray
	 * @return string
	 * @throws CogException
	 */
	public function parameterListNulledFromColumnArray(array $columnArray): string {
		$toReturn = [];

		if ($columnArray) {
			foreach ($columnArray as $object) {
				$toReturn[] = sprintf('?%s $%s = null', $object->__get('variableTyped'), $object->__get('propertyName'));
			}
		}

		return implode(', ', $toReturn);
	}

	/**
	 * @param string $glue
	 * @param string $prefix
	 * @param string $suffix
	 * @param string $property
	 * @param array $arrayToImplode
	 * @return string
	 */
	public function implodeObjectArray(string $glue, string $prefix, string $suffix, string $property, array $arrayToImplode): string {
		$toReturn = [];

		if ($arrayToImplode) {
			foreach ($arrayToImplode as $object) {
				$toReturn[] = sprintf('%s%s%s', $prefix, $object->__get($property), $suffix);
			}
		}

		return implode($glue, $toReturn);
	}

	/**
	 * @param string $name
	 * @return string
	 */
	protected function typeTokenFromTypeName(string $name): string {
		$toReturn = '';
		$length = strlen($name);

		for ($i=0; $i<$length; $i++) {
			if ($name[$i] === ' ') {
				$toReturn .= '_';
			} elseif (
				($name[$i] === '_') ||
				(ord($name[$i]) >= ord('a') && ord($name[$i]) <= ord('z')) ||
				(ord($name[$i]) >= ord('A') && ord($name[$i]) <= ord('Z')) ||
				(ord($name[$i]) >= ord('0') && ord($name[$i]) <= ord('9'))
			) {
				$toReturn .= $name[$i];
			}
		}

		if (is_numeric(StringUtils::firstCharacter($toReturn))) {
			$toReturn = '_' . $toReturn;
		}

		return $toReturn;
	}

	/**
	 * @param Column $column
	 * @return string
	 */
	public function formControlClassForColumn(Column $column): string {
		if ($column->identity || $column->timestamp) {
			return 'Label';
		}

		if ($column->reference) {
			return 'ListControlInterface';
		}

		return match ($column->variableType) {
			Type::BOOLEAN => 'CheckBoxInterface',
			Type::DATETIME => 'DateTimePickerInterface',
			Type::INTEGER => 'IntegerTextBox',
			Type::FLOAT => 'FloatTextBox',
			default => 'TextBox',
		};
	}

	/**
	 * @param ReverseReference $reverseReference
	 * @return string
	 * @throws Exception
	 */
	public function formControlVariableNameForUniqueReverseReference(ReverseReference $reverseReference): string {
		if ($reverseReference->unique) {
			return sprintf('lst%s', $reverseReference->objectDescriptionUppercase);
		}

		throw new Exception('FormControlVariableNameForUniqueReverseReference requires ReverseReference to be unique');
	}

	/**
	 * @param ManyToManyReference $manyToManyReference
	 * @return string
	 */
	public function formControlVariableNameForManyToManyReference(ManyToManyReference $manyToManyReference): string {
		return sprintf('lst%s', $manyToManyReference->objectDescriptionPluralUppercase);
	}

	/**
	 * @param ReverseReference $reverseReference
	 * @return string
	 * @throws Exception
	 */
	public function formLabelVariableNameForUniqueReverseReference(ReverseReference $reverseReference): string {
		if ($reverseReference->unique) {
			return sprintf('lbl%s', $reverseReference->objectDescription);
		}

		throw new Exception('FormControlVariableNameForUniqueReverseReference requires ReverseReference to be unique');
	}

	/**
	 * @param ManyToManyReference $manyToManyReference
	 * @return string
	 */
	public function formLabelVariableNameForManyToManyReference(ManyToManyReference $manyToManyReference): string {
		return sprintf('lbl%s', $manyToManyReference->objectDescriptionPluralUppercase);
	}

	/**
	 * @param Column $column
	 * @return string|null
	 * @throws Exception
	 */
	public function formControlTypeForColumn(Column $column): ?string {
		if ($column->identity || $column->timestamp) {
			return 'Label';
		}

		if ($column->reference) {
			return 'ListBox';
		}

		return match ($column->variableType) {
			Type::BOOLEAN => 'CheckBox',
			Type::DATETIME => 'Calendar',
			Type::FLOAT => 'FloatTextBox',
			Type::INTEGER => 'IntegerTextBox',
			Type::STRING => 'TextBox',
			default => throw new Exception('Unknown type for Column: %s' . $column->variableType),
		};
	}

	/**
	 * @param ReverseReference $reverseReference
	 * @return string
	 * @throws Exception
	 */
	public function translationNameForUniqueReverseReference(ReverseReference $reverseReference): string {
		return ConvertNotation::translationNameFromString($this->formControlVariableNameForUniqueReverseReference($reverseReference));
	}

	/**
	 * @param ManyToManyReference $manyToManyReference
	 * @return string
	 */
	public function translationNameForManyToManyReference(ManyToManyReference $manyToManyReference): string {
		return ConvertNotation::translationNameFromString($this->formControlVariableNameForManyToManyReference($manyToManyReference));
	}

	/**
	 * @param string $tableName
	 * @param string $columnName
	 * @param string $referencedTableName
	 * @return string
	 */
	protected function calculateObjectMemberVariable(string $tableName, string $columnName, string $referencedTableName): string {
		return sprintf('%s%s%s%s',
			ConvertNotation::prefixFromType(Type::OBJECT),
			$this->associatedObjectPrefix,
			$this->calculateObjectDescription($tableName, $columnName, $referencedTableName, false),
			$this->associatedObjectSuffix);
	}

	/**
	 * @param string $tableName
	 * @param string $strColumnName
	 * @param string $referencedTableName
	 * @return string
	 */
	protected function calculateObjectPropertyName(string $tableName, string $strColumnName, string $referencedTableName): string {
		return sprintf('%s%s%s',
			$this->associatedObjectPrefix,
			$this->calculateObjectDescription($tableName, $strColumnName, $referencedTableName, false),
			$this->associatedObjectSuffix);
	}

	// TODO: These functions need to be documented heavily with information from "lexical analysis on fk names.txt"
	/**
	 * @param string $tableName
	 * @param string $columnName
	 * @param string $referencedTableName
	 * @param boolean $pluralize
	 * @return string
	 */
	protected function calculateObjectDescription(string $tableName, string $columnName, string $referencedTableName, bool $pluralize): string {
		// Strip Prefixes (if applicable)
		$tableName = $this->stripPrefixFromTable($tableName);
		$referencedTableName = $this->stripPrefixFromTable($referencedTableName);

		// Starting Point
		$strToReturn = ConvertNotation::camelCase($tableName);

		if ($pluralize) {
			$strToReturn = Utils::pluralize($strToReturn);
		}

		if ($tableName === $referencedTableName) {
			// Self-referencing Reference to Describe

			// If Column Name is only the name of the referenced table, or the name of the referenced table with "_id",
			// then the object description is simply based off the table name.
			if ($columnName === $referencedTableName || $columnName === $referencedTableName . '_id') {
				return sprintf('Child%s', $strToReturn);
			}

			// Rip out trailing "_id" if applicable
			$length = strlen($columnName);
			if ($length > 3 && substr($columnName, $length - 3) === '_id') {
				$columnName = substr($columnName, 0, $length - 3);
			}

			// Rip out the referenced table name from the column name
			$columnName = str_replace($referencedTableName, '', $columnName);

			// Change any double "_" to single "_"
			$columnName = str_replace('__', '_', $columnName);
			$columnName = str_replace('__', '_', $columnName);

			$columnName = ConvertNotation::pascalCase($columnName);

			// Special case for Parent/Child
			if ($columnName === 'Parent') {
				return sprintf('Child%s', $strToReturn);
			}

			return sprintf('%sAs%s', $strToReturn, $columnName);

		}

		// If Column Name is only the name of the referenced table, or the name of the referenced table with "_id",
		// then the object description is simply based off the table name.
		if ($columnName === $referencedTableName || $columnName === $referencedTableName . '_id') {
			return $strToReturn;
		}

		// Rip out trailing "_id" if applicable
		$length = strlen($columnName);
		if (($length > 3) && (substr($columnName, $length - 3) === '_id')) {
			$columnName = substr($columnName, 0, $length - 3);
		}

		// Rip out the referenced table name from the column name
		$columnName = str_replace($referencedTableName, '', $columnName);

		// Change any double "_" to single "_"
		$columnName = str_replace('__', '_', $columnName);
		$columnName = str_replace('__', '_', $columnName);

		return sprintf('%sAs%s', $strToReturn, ConvertNotation::pascalCase($columnName));
	}



	/**
	 * This is called for ReverseReference Object Descriptions for association tables (many-to-many)
	 * @param string $associationTableName
	 * @param string $tableName
	 * @param string $referencedTableName
	 * @param boolean $pluralize
	 * @return string
	 */
	protected function calculateObjectDescriptionForAssociation(string $associationTableName, string $tableName, string $referencedTableName, bool $pluralize): string {
		// Strip Prefixes (if applicable)
		$tableName = $this->stripPrefixFromTable($tableName);
		$associationTableName = $this->stripPrefixFromTable($associationTableName);
		$referencedTableName = $this->stripPrefixFromTable($referencedTableName);

		// Starting Point
		$toReturn = ConvertNotation::camelCase($referencedTableName);

		if ($pluralize) {
			$toReturn = Utils::pluralize($toReturn);
		}

		// Let's start with strAssociationTableName

		// Rip out trailing "_assn" if applicable
		$associationTableName = str_replace($this->associationTableSuffix, '', $associationTableName);

		// Take out strTableName if applicable (both with and without underscores)
		$associationTableName = str_replace($tableName, '', $associationTableName);
		$tableName = str_replace('_', '', $tableName);
		$associationTableName = str_replace($tableName, '', $associationTableName);

		// Take out strReferencedTableName if applicable (both with and without underscores)
		$associationTableName = str_replace($referencedTableName, '', $associationTableName);
		$referencedTableName = str_replace('_', '', $referencedTableName);
		$associationTableName = str_replace($referencedTableName, '', $associationTableName);

		// Change any double "__" to single "_"
		$associationTableName = str_replace('__', '_', $associationTableName);
		$associationTableName = str_replace('__', '_', $associationTableName);
		$associationTableName = str_replace('__', '_', $associationTableName);

		// If we have nothing left or just a single "_" in AssociationTableName, return "Starting Point"
		if (($associationTableName === '_') || ($associationTableName === '')) {
			return sprintf(
				'%s%s%s',
				$this->associatedObjectPrefix,
				$toReturn,
				$this->associatedObjectSuffix
			);
		}

		// Otherwise, add "As" and the predicate
		return sprintf(
			'%s%sAs%s%s',
			$this->associatedObjectPrefix,
			$toReturn,
			ConvertNotation::pascalCase($associationTableName),
			$this->associatedObjectSuffix
		);
	}

	/**
	 * This is called by AnalyzeAssociationTable to calculate the GraphPrefixArray for a self-referencing association table (e.g. directed graph)
	 * @param $foreignKeyArray
	 * @return array
	 */
	protected function calculateGraphPrefixArray($foreignKeyArray): array {
		$graphPrefixArray = [];
		// Analyze Column Names to determine GraphPrefixArray
		if ((stripos($foreignKeyArray[0]->columnNameArray[0], 'parent') !== false) ||
			(stripos($foreignKeyArray[1]->columnNameArray[0], 'child') !== false)
		) {
			$graphPrefixArray[0] = '';
			$graphPrefixArray[1] = 'parent';
		} elseif ((stripos($foreignKeyArray[0]->columnNameArray[0], 'child') !== false) ||
			(stripos($foreignKeyArray[1]->columnNameArray[0], 'parent') !== false)
		) {
			$graphPrefixArray[0] = 'parent';
			$graphPrefixArray[1] = '';
		} else {
			// Use Default Prefixing for Graphs
			$graphPrefixArray[0] = 'parent';
			$graphPrefixArray[1] = '';
		}

		return $graphPrefixArray;
	}

	/**
	 * @param string $dbType
	 * @return string
	 * @throws Exception
	 */
	public function variableTypeFromDbType(string $dbType): string {
		return match ($dbType) {
			FieldType::BIT => Type::BOOLEAN,
			FieldType::BLOB, FieldType::CHAR, FieldType::VARCHAR => Type::STRING,
			FieldType::DATE, FieldType::TIME, FieldType::DATETIME, FieldType::TIMESTAMP => Type::DATETIME,
			FieldType::FLOAT => Type::FLOAT,
			FieldType::INTEGER => Type::INTEGER,
			default => throw new Exception('Invalid Db Type to Convert:' . $dbType),
		};
	}
}
