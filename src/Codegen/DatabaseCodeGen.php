<?php

namespace Cog\Codegen;

use Cog;
use Cog\Database\Database;
use Cog\Database\FieldBase;
use Cog\Database\FieldType;
use Cog\Exceptions\CogException;
use Cog\Exceptions\InvalidCastException;
use Cog\Type;
use Cog\Util\ConvertNotation;
use Cog\Util\NamespaceUtil;
use SimpleXMLElement;
use Symfony\Component\String\ByteString;

/**
 * @package Codegen
 *
 * @property-read string $namespaceData namespace the hand-editable data subclasses are generated into
 * @property-read string $namespaceType namespace the hand-editable type subclasses are generated into
 */
class DatabaseCodeGen extends DatabaseCodeGenBase {
	/** @var Table[] */
	protected array $tableArray = [];
	/** @var Table[] */
	protected array $excludedTableArray = [];

	/** @var TypeTable[] */
	protected array $typeTableArray = [];
	protected array $associationTableNameArray = [];

	protected Cog\Database\Base $database;

	protected int $databaseIndex;
	/** @var string The delimiter to be used for parsing comments on the DB tables for being used as the name of Meta control's Label */
	protected string $commentMetaControlLabelDelimiter;

	// Namespaces the generated subclasses live in. Templates read these rather
	// than writing the namespace out, so an application is not tied to App\Data.
	protected string $namespaceData;
	protected string $namespaceType;

	// Table Suffixes
	/** @var string[] */
	protected array $typeTableSuffixArray;
	/** @var int[] */
	protected array $typeTableSuffixLengthArray;
	protected string $associationTableSuffix;
	protected int $associationTableSuffixLength;

	// Exclude Patterns & Lists
	protected string $excludePattern;
	/** @var string[] */
	protected array $excludeListArray;

	// Include Patterns & Lists
	protected string $includePattern;
	/** @var string[] */
	protected array $includeListArray;

	// Uniquely Associated Objects
	protected string $associatedObjectPrefix;
	protected string $associatedObjectSuffix;

	// Type Table Items, Table Name and Column Name RegExp Patterns
	protected string $patternTableName = '[[:alpha:]_][[:alnum:]_]*';
	protected string $patternColumnName = '[[:alpha:]_][[:alnum:]_]*';

	/**
	 * @param string $tableName
	 * @return Table
	 */
	public function getTable(string $tableName): TableBase {
		$tableName = strtolower($tableName);

		if (array_key_exists($tableName, $this->tableArray)) {
			return $this->tableArray[$tableName];
		}

		if (array_key_exists($tableName, $this->typeTableArray)) {
			return $this->typeTableArray[$tableName];
		}

		throw new CogException(sprintf('Table does not exist or does not have a defined Primary Key: %s', $tableName));
	}

	/**
	 * @param string $tableName
	 * @param string $columnName
	 * @return Column
	 * @throws CogException
	 */
	public function getColumn(string $tableName, string $columnName): Column {
		try {
			$table = $this->getTable($tableName);
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
		$columnName = strtolower($columnName);
		if (array_key_exists($columnName, $table->columnArray)) {
			return $table->columnArray[$columnName];
		}
		throw new CogException(sprintf('Column does not exist in %s: %s', $tableName, $columnName));
	}

	public function getTitle(): string {
		if (array_key_exists($this->databaseIndex, Database::$databases)) {
			$database = Database::$databases[$this->databaseIndex];
			return sprintf('Database Index #%s (%s / %s / %s)', $this->databaseIndex, $database->adapter, $database->server, $database->database);
		}
		return sprintf('Database Index #%s (N/A)', $this->databaseIndex);
	}

	public function getConfigXml(): string {
		$crLf = "\r\n";
		$toReturn = sprintf('		<database index="%s">%s', $this->databaseIndex, $crLf);
		foreach ($this->templatesPaths as $templatesPath) {
			$toReturn .= sprintf('			<templates path="%s"/>%s', $templatesPath, $crLf);
		}
		$toReturn .= sprintf('			<className prefix="%s" suffix="%s"/>%s', $this->classPrefix, $this->classSuffix, $crLf);
		$toReturn .= sprintf('			<associatedObjectName prefix="%s" suffix="%s"/>%s', $this->associatedObjectPrefix, $this->associatedObjectSuffix, $crLf);
		$toReturn .= sprintf('			<namespace data="%s" type="%s"/>%s', $this->namespaceData, $this->namespaceType, $crLf);
		$toReturn .= sprintf('			<typeTableIdentifier suffix="%s"/>%s', implode(',', $this->typeTableSuffixArray), $crLf);
		$toReturn .= sprintf('			<associationTableIdentifier suffix="%s"/>%s', $this->associationTableSuffix, $crLf);
		$toReturn .= sprintf('			<stripFromTableName prefix="%s"/>%s', $this->stripTablePrefix, $crLf);
		$toReturn .= sprintf('			<excludeTables pattern="%s" list="%s"/>%s', $this->excludePattern, implode(',', $this->excludeListArray), $crLf);
		$toReturn .= sprintf('			<includeTables pattern="%s" list="%s"/>%s', $this->includePattern, implode(',', $this->includeListArray), $crLf);
		$toReturn .= sprintf('		</database>%s', $crLf);
		return $toReturn;
	}

	public function getReportLabel(): string {
		// Setup Report Label
		$totalTableCount = count($this->tableArray) + count($this->typeTableArray);
		if ($totalTableCount === 0) {
			$reportLabel = 'There were no tables available to attempt code generation.';
		} elseif ($totalTableCount === 1) {
			$reportLabel = 'There was 1 table available to attempt code generation:';
		} else {
			$reportLabel = 'There were ' . $totalTableCount . ' tables available to attempt code generation:';
		}

		return $reportLabel;
	}

	public function generateAll(): string {
		$report = '';

		// Iterate through all the tables, generating one class at a time
		if ($this->tableArray) {
			foreach ($this->tableArray as $table) {
				if ($this->generateTable($table)) {
					$c = $table->referenceCount;
					if ($c === 0) {
						$count = '(with no relationships)';
					} elseif ($c === 1) {
						$count = '(with 1 relationship)';
					} else {
						$count = sprintf('(with %s relationships)', $c);
					}
					$report .= sprintf("Successfully generated DB ORM Class:   %s %s\r\n", $table->className, $count);
				} else {
					$report .= sprintf("FAILED to generate DB ORM Class:       %s\r\n", $table->className);
				}
			}
		}

		// Iterate through all the TYPE tables, generating one TYPE class at a time
		if ($this->typeTableArray) {
			foreach ($this->typeTableArray as $typeTable) {
				if ($this->generateTypeTable($typeTable)) {
					$report .= sprintf("Successfully generated DB Type Class:  %s\n", $typeTable->className);
				} else {
					$report .= sprintf("FAILED to generate DB Type class:      %s\n", $typeTable->className);
				}
			}
		}

		return $report;
	}

	/**
	 * @param DatabaseCodeGen[] $codeGenArray
	 * @return string[]
	 * @throws \Exception
	 */
	public static function generateAggregateHelper(array $codeGenArray): array {
		$toReturn = [];

		foreach ($codeGenArray as $codegen) {
			// The aggregate templates are optional - skip them when the installed
			// template set does not provide any.
			if (Utils::resolveModuleDir($codegen->docroot, $codegen->templatesPaths, 'aggregate_db_orm') === null) {
				continue;
			}

			$tableArray = []; // Standard ORM Tables

			foreach ($codegen->tableArray as $table) {
				$tableArray[$table->className] = $table;
			}

			if ($codegen->generateFiles('aggregate_db_orm', ['tableArray' => $tableArray])) {
				$toReturn[] = 'Successfully generated Aggregate DB ORM file(s)';
			} else {
				$toReturn[] = 'FAILED to generate Aggregate DB ORM file(s)';
			}
		}

		return $toReturn;
	}


	/** @inheritdoc */
	public function __construct(string $docroot, array $templatePaths, SimpleXMLElement $settingsXml) {
		parent::__construct($docroot, $templatePaths, $settingsXml);

		// Set the databaseIndex
		$this->databaseIndex = Utils::lookupSetting($settingsXml, null, 'index', Type::INTEGER);

		// Append Suffix/Prefixes
		$this->classPrefix = Utils::lookupSetting($settingsXml, 'className', 'prefix');
		$this->classSuffix = Utils::lookupSetting($settingsXml, 'className', 'suffix');
		$this->associatedObjectPrefix = Utils::lookupSetting($settingsXml, 'associatedObjectName', 'prefix');
		$this->associatedObjectSuffix = Utils::lookupSetting($settingsXml, 'associatedObjectName', 'suffix');

		// Namespaces for the generated subclasses. A missing tag or attribute reads
		// as an empty string, which falls back to the defaults so settings files
		// written before this setting existed keep generating what they always did.
		$this->namespaceData = NamespaceUtil::normalize(
			Utils::lookupSetting($settingsXml, 'namespace', 'data') ?: 'App\Data'
		);
		$this->namespaceType = NamespaceUtil::normalize(
			Utils::lookupSetting($settingsXml, 'namespace', 'type') ?: 'App\Type'
		);

		// Table Type Identifiers
		$typeTableSuffixList = Utils::lookupSetting($settingsXml, 'typeTableIdentifier', 'suffix');
		$typeTableSuffixArray = explode(',', $typeTableSuffixList);
		foreach ($typeTableSuffixArray as $typeTableSuffix) {
			$this->typeTableSuffixArray[] = trim($typeTableSuffix);
			$this->typeTableSuffixLengthArray[] = strlen(trim($typeTableSuffix));
		}
		$this->associationTableSuffix = Utils::lookupSetting($settingsXml, 'associationTableIdentifier', 'suffix');
		$this->associationTableSuffixLength = strlen($this->associationTableSuffix);

		// Stripping TablePrefixes
		$this->stripTablePrefix = Utils::lookupSetting($settingsXml, 'stripFromTableName', 'prefix');
		$this->stripTablePrefixLength = strlen($this->stripTablePrefix);

		// Exclude/Include Tables
		$this->excludePattern = Utils::lookupSetting($settingsXml, 'excludeTables', 'pattern');
		$excludeList = Utils::lookupSetting($settingsXml, 'excludeTables', 'list');
		$this->excludeListArray = array_map('trim', explode(',', $excludeList));

		// Include Patterns
		$this->includePattern = Utils::lookupSetting($settingsXml, 'includeTables', 'pattern');
		$includeList = Utils::lookupSetting($settingsXml, 'includeTables', 'list');
		$this->includeListArray = array_map('trim', explode(',', $includeList));

		// Column Comment for MetaControlLabel setting.
		$this->commentMetaControlLabelDelimiter = Utils::lookupSetting($settingsXml, 'columnCommentForMetaControl', 'delimiter');

		// Check to make sure things that are required are there
		if (!$this->databaseIndex) {
			$this->errors .= "CodeGen Settings XML Fatal Error: databaseIndex was invalid or not set\r\n";
		}

		// A malformed namespace generates code that parses fine and never loads,
		// so it is worth catching here rather than at run time.
		foreach (['data' => $this->namespaceData, 'type' => $this->namespaceType] as $attribute => $namespace) {
			if (!NamespaceUtil::isValid($namespace)) {
				$this->errors .= sprintf(
					"CodeGen Settings XML Fatal Error: namespace %s=\"%s\" is not a valid namespace\r\n",
					$attribute,
					$namespace
				);
			}
		}

		if ($this->errors) {
			return;
		}

		$this->analyzeDatabase();
	}

	protected function analyzeDatabase(): void {
		// Set aside the Cog\Database\Database object
		if (array_key_exists($this->databaseIndex, Database::$databases)) {
			$this->database = Database::$databases[$this->databaseIndex];
		}

		// Ensure DB Profiling is DISABLED on this DB
		if ($this->database->profiling) {
			$this->database->disableProfiling();
		}

		$tableArray = $this->database->getTables();

		// ITERATION 1: Simply create the Table and TypeTable Arrays
		if ($tableArray) {
			foreach ($tableArray as $tableName) {

				// Do we Exclude this Table Name? (given includeTables and excludeTables)
				// First check the lists of Excludes and the Exclude Patterns
				if (
					in_array($tableName, $this->excludeListArray, false) ||
					(strlen($this->excludePattern) > 0 && preg_match(':' . $this->excludePattern . ':i', $tableName))
				) {
					// So we THINK we may be excluding this table
					// But check against the explicit INCLUDE list and patterns
					if (!(in_array($tableName, $this->includeListArray, false) || (strlen($this->includePattern) > 0 && preg_match(':' . $this->includePattern . ':i', $tableName)))) {
						// If we're here, then we want to exclude this table
						$this->excludedTableArray[strtolower($tableName)] = true;
						continue; // Exit this iteration of the foreach loop
					}
				}

				// Check to see if this table name exists anywhere else yet, and warn if it is
				foreach (CodeGenRunner::$codegenArray as $codegen) {
					if ($codegen instanceof self) {
						foreach ($codegen->tableArray as $possibleDuplicate) {
							if (strtolower($possibleDuplicate->name) === strtolower($tableName)) {
								$this->errors .= 'Duplicate Table Name Used: ' . $tableName . "\r\n";
							}
						}
					}
				}

				// Perform different tasks based on whether it's an Association table,
				// a Type table, or just a regular table
				$isTypeTable = false;
				foreach ($this->typeTableSuffixLengthArray as $i => $typeTableSuffixLength) {
					if ($typeTableSuffixLength && strlen($tableName) > $typeTableSuffixLength &&
						substr($tableName, strlen($tableName) - $typeTableSuffixLength) === $this->typeTableSuffixArray[$i]
					) {
						// Let's mark, that we have type table
						$isTypeTable = true;
						// Create a TYPE Table and add it to the array
						$typeTable = new TypeTable($tableName);
						$this->typeTableArray[strtolower($tableName)] = $typeTable;
						// If we found type table, there is no point of iterating for other type table suffixes
						break;
//						_p("TYPE Table: $tableName<br />", false);
					}
				}

				if (!$isTypeTable) {
					// If current table wasn't type table, let's look for other table types
					if ($this->associationTableSuffixLength && strlen($tableName) > $this->associationTableSuffixLength &&
						substr($tableName, strlen($tableName) - $this->associationTableSuffixLength) === $this->associationTableSuffix
					) {
						// Add this ASSOCIATION Table Name to the array
						$this->associationTableNameArray[strtolower($tableName)] = $tableName;
//						_p("ASSN Table: $tableName<br />", false);
					} else {
						// Create a Regular Table and add it to the array
						$table = new Table($tableName);
						$this->tableArray[strtolower($tableName)] = $table;
//						_p("Table: $tableName<br />", false);
					}
				}
			}
		}

		// Analyze All the Type Tables
		if ($this->typeTableArray) {
			foreach ($this->typeTableArray as $typeTable) {
				$this->analyzeTypeTable($typeTable);
			}
		}

		// Analyze All the Regular Tables
		if ($this->tableArray) {
			foreach ($this->tableArray as $table) {
				$this->analyzeTable($table);
			}
		}

		// Analyze All the Association Tables
		if ($this->associationTableNameArray) {
			foreach ($this->associationTableNameArray as $associationTableName) {
				$this->analyzeAssociationTable($associationTableName);
			}
		}

		// Finally, for each Relationship in all Tables, warn on non-single Column PK based FK:
		if ($this->tableArray) {
			foreach ($this->tableArray as $table) {
				if ($table->columnArray) {
					foreach ($table->columnArray as $column) {
						if ($column->reference && !$column->reference->isType) {
							$reference = $column->reference;
							$referencedTable = $this->getTable($reference->table);
							$referencedColumn = $referencedTable->columnArray[strtolower($reference->column)];

							if (!$referencedColumn->primaryKey) {
								$this->warnings .= sprintf(
									"Warning: Invalid Relationship created in %s class (for foreign key \"%s\") -- column \"%s\" is not the single-column primary key for the referenced \"%s\" table\r\n",
									$referencedTable->className,
									$reference->keyName,
									$referencedColumn->name,
									$referencedTable->name
								);
							} elseif (count($referencedTable->primaryKeyColumnArray) !== 1) {
								$this->warnings .= sprintf(
									"Warning: Invalid Relationship created in %s class (for foreign key \"%s\") -- column \"%s\" is not the single-column primary key for the referenced \"%s\" table\r\n",
									$referencedTable->className,
									$reference->keyName,
									$referencedColumn->name,
									$referencedTable->name
								);
							}
						}
					}
				}
			}
		}
	}

	/**
	 * @param Table $table
	 * @return string
	 */
	protected function listOfColumnsFromTable(Table $table): string {
		$array = [];
		$columnArray = $table->columnArray;
		if ($columnArray) {
			foreach ($columnArray as $column) {
				$array[] = $column->name;
			}
		}
		return implode(', ', $array);
	}

	/**
	 * @param Table $table
	 * @param string[] $columnNameArray
	 * @return array
	 */
	public function getColumnArray(Table $table, array $columnNameArray): array {
		$toReturn = [];

		if ($columnNameArray) {
			foreach ($columnNameArray as $columnName) {
				$toReturn[] = $table->columnArray[strtolower($columnName)];
			}
		}
		return $toReturn;
	}

	/**
	 * @param Table $table
	 * @return bool
	 * @throws \Exception
	 */
	public function generateTable(Table $table): bool {
		return $this->generateFiles('db_orm', [
			'table' => $table,
			'escapeIdentifierBegin' => Database::$databases[$this->databaseIndex]->escapeIdentifierBegin,
			'escapeIdentifierEnd' => Database::$databases[$this->databaseIndex]->escapeIdentifierEnd
		]);
	}

	/**
	 * @param TypeTable $typeTable
	 * @return bool
	 * @throws \Exception
	 */
	public function generateTypeTable(TypeTable $typeTable): bool {
		return $this->generateFiles('db_type', [
			'typeTable' => $typeTable,
			'escapeIdentifierBegin' => Database::$databases[$this->databaseIndex]->escapeIdentifierBegin,
			'escapeIdentifierEnd' => Database::$databases[$this->databaseIndex]->escapeIdentifierEnd
		]);
	}

	/**
	 * @param $tableName
	 * @throws CogException
	 * @throws InvalidCastException
	 */
	protected function analyzeAssociationTable($tableName): void {
		$fieldArray = $this->database->getFieldsForTable($tableName);

		// Association tables must have 2 fields
		if (count($fieldArray) !== 2) {
			$this->errors .= sprintf("AssociationTable %s does not have exactly 2 columns.\n", $tableName);
			return;
		}

		if (!$fieldArray[0]->notNull || !$fieldArray[1]->notNull) {
			$this->errors .= sprintf("AssociationTable %s's two columns must both be not null or a composite Primary Key", $tableName);
			return;
		}

		if ((!$fieldArray[0]->primaryKey && $fieldArray[1]->primaryKey) || ($fieldArray[0]->primaryKey && !$fieldArray[1]->primaryKey)) {
			$this->errors .= sprintf("AssociationTable %s only support two-column composite Primary Keys.\n", $tableName);
			return;
		}

		$foreignKeyArray = $this->database->getForeignKeysForTable($tableName);

		if (count($foreignKeyArray) !== 2) {
			$this->errors .= sprintf("AssociationTable %s does not have exactly 2 foreign keys. Code Gen analysis found %s.\n", $tableName, count($foreignKeyArray));
			return;
		}

		// Setup two new ManyToManyReference objects
		$manyToManyReferenceArray[0] = new ManyToManyReference();
		$manyToManyReferenceArray[1] = new ManyToManyReference();

		// Ensure that the linked tables are both not excluded
		if (
			array_key_exists($foreignKeyArray[0]->referenceTableName, $this->excludedTableArray) ||
			array_key_exists($foreignKeyArray[1]->referenceTableName, $this->excludedTableArray)
		) {
			return;
		}

		// Setup GraphPrefixArray (if applicable)
		$graphPrefixArray = ['', ''];

		if ($foreignKeyArray[0]->referenceTableName === $foreignKeyArray[1]->referenceTableName) {
			// We are analyzing a graph association
			$graphPrefixArray = $this->calculateGraphPrefixArray($foreignKeyArray);
		}

		// Go through each FK and setup each ManyToManyReference object
		for ($i = 0; $i < 2; $i++) {
			$manyToManyReference = $manyToManyReferenceArray[$i];

			$foreignKey = $foreignKeyArray[$i];
			$oppositeForeignKey = $foreignKeyArray[($i === 0) ? 1 : 0];

			// Make sure the FK is a single-column FK
			if (count($foreignKey->columnNameArray) !== 1) {
				$this->errors .= sprintf("AssociationTable %s has multi-column foreign keys.\n", $tableName);
				return;
			}

			$manyToManyReference->keyName = $foreignKey->keyName;
			$manyToManyReference->table = $tableName;
			$manyToManyReference->column = $foreignKey->columnNameArray[0];
			$manyToManyReference->oppositeColumn = $oppositeForeignKey->columnNameArray[0];
			$manyToManyReference->associatedTable = $oppositeForeignKey->referenceTableName;

			// Calculate OppositeColumnVariableName
			// Do this by first making a fake column which is the PK column of the AssociatedTable,
			// but whose column name is ManyToManyReference->column

			if (
				array_key_exists($manyToManyReference->associatedTable, $this->tableArray)
				|| array_key_exists($manyToManyReference->associatedTable, $this->typeTableArray)
			) {
				$oppositeColumn = clone $this->getTable($manyToManyReference->associatedTable)->primaryKeyColumnArray[0];
			} else {
				throw new \Exception(sprintf("AssociationTable %s has foreign keys that cannot be resolved.\n", $tableName));
			}

			$oppositeColumn->name = $manyToManyReference->oppositeColumn;

			$manyToManyReference->oppositeVariableName = VariableNameCreator::variableNameFromColumnWithType($oppositeColumn);
			$manyToManyReference->oppositePropertyName = VariableNameCreator::propertyNameFromColumn($oppositeColumn);
			$manyToManyReference->oppositeVariableType = $oppositeColumn->variableType;

			$manyToManyReference->variableName = $this->reverseReferenceVariableNameFromTable($oppositeForeignKey->referenceTableName);
			$manyToManyReference->variableType = $this->reverseReferenceVariableTypeFromTable($oppositeForeignKey->referenceTableName);

			$objectDescription = $this->calculateObjectDescriptionForAssociation($tableName, $foreignKey->referenceTableName, $oppositeForeignKey->referenceTableName, false);
			$objectDescriptionPlural = $this->calculateObjectDescriptionForAssociation($tableName, $foreignKey->referenceTableName, $oppositeForeignKey->referenceTableName, true);
			$oppositeObjectDescription = $this->calculateObjectDescriptionForAssociation($tableName, $oppositeForeignKey->referenceTableName, $foreignKey->referenceTableName, false);

			if ($graphPrefixArray[$i] !== '') {
				$objectDescription = $graphPrefixArray[$i] . (new ByteString($objectDescription))->title();
				$objectDescriptionPlural = $graphPrefixArray[$i] . (new ByteString($objectDescriptionPlural))->title();
			}

			if ($graphPrefixArray[($i === 0) ? 1 : 0] !== '') {
				$oppositeObjectDescription = $graphPrefixArray[($i === 0) ? 1 : 0] . (new ByteString($oppositeObjectDescription))->title();
			}

			$manyToManyReference->objectDescription = $objectDescription;
			$manyToManyReference->objectDescriptionPlural = $objectDescriptionPlural;
			$manyToManyReference->oppositeObjectDescription = $oppositeObjectDescription;
		}


		// Iterate through the list of Columns to create objColumnArray
		$columnArray = [];
		foreach ($fieldArray as $field) {
			if (($field->name !== $manyToManyReferenceArray[0]->column) &&
				($field->name !== $manyToManyReferenceArray[1]->column)
			) {
				$column = $this->analyzeTableColumn($field);
				if ($column) {
					$columnArray[strtolower($column->name)] = $column;
				}
			}
		}
		$manyToManyReferenceArray[0]->columnArray = $columnArray;
		$manyToManyReferenceArray[1]->columnArray = $columnArray;

		// Push the ManyToManyReference Objects to the tables
		for ($i = 0; $i < 2; $i++) {
			$manyToManyReference = $manyToManyReferenceArray[$i];
			$tableWithReference = $manyToManyReferenceArray[($i === 0) ? 1 : 0]->associatedTable;

			if (array_key_exists($tableWithReference, $this->tableArray)) {
				$array = $this->getTable($tableWithReference)->manyToManyReferenceArray;
				$array[] = $manyToManyReference;
				$this->getTable($tableWithReference)->manyToManyReferenceArray = $array;
			}
		}
	}

	/**
	 * @param TypeTable $typeTable
	 * @throws InvalidCastException
	 */
	protected function analyzeTypeTable(TypeTable $typeTable): void {
		$typeTable->className = $this->classNameFromTableName($typeTable->name);
		$typeTable->classNamePlural = Utils::pluralize($typeTable->className);

		$typeTable->columnArray = $this->getTableColumns($typeTable);
		$typeTable->indexArray = $this->getTableIndexes($typeTable);

		$this->verifyTableForeignKeys($typeTable);

		// Set up the Array of Reserved Words
		$reservedWords = explode(',', CodeGen::PHP_RESERVED_WORDS);
		for ($i = count($reservedWords) - 1; $i >= 0; $i--) {
			$reservedWords[$i] = strtolower(trim($reservedWords[$i]));
		}

		// Ensure that there are only 2 fields, an integer PK field (can be named anything) and a unique varchar field
		$fieldArray = $this->database->getFieldsForTable($typeTable->name);

		if ($fieldArray[0]->type !== FieldType::INTEGER || !$fieldArray[0]->primaryKey) {
			$this->errors .= sprintf("TypeTable %s's first column is not a PK integer.\n", $typeTable->name);
			return;
		}

		if ($fieldArray[1]->type !== FieldType::VARCHAR || !$fieldArray[1]->unique) {
			$this->errors .= sprintf("TypeTable %s's second column is not a unique VARCHAR.\n", $typeTable->name);
			return;
		}

		// Get the rows
		$result = $this->database->query(sprintf('SELECT * FROM %s', $typeTable->name));
		$nameArray = [];
		$tokenArray = [];
		$extraPropertyArray = [];
		$extraFields = [];

		while ($row = $result->fetchRow()) {
			$nameArray[$row[0]] = str_replace(['\\', "'"], ['\\\\', "\\'"], $row[1]);
			$tokenArray[$row[0]] = $this->typeTokenFromTypeName($row[1]);
			if (count($row) > 2) { // there are extra columns to process
				$extraPropertyArray[$row[0]] = [];
				$size = count($row);
				for ($i = 2; $i < $size; $i++) {
					$fieldName = $this->typeNameFromColumnName($fieldArray[$i]->name);
					$extraFields[$i - 2] = $fieldName;
					$extraPropertyArray[$row[0]][$fieldName] = $row[$i];
				}
			}

			foreach ($reservedWords as $reservedWord) {
				if (strtolower(trim($tokenArray[$row[0]])) === $reservedWord) {
					$this->warnings .= sprintf("Warning: TypeTable %s contains a type name which is a reserved word: %s.  Appended _ to the beginning of it.\r\n",
						$typeTable->name, $reservedWord);
					$tokenArray[$row[0]] = '_' . $tokenArray[$row[0]];
				}
			}

			if (strlen($tokenArray[$row[0]]) === 0) {
				$this->warnings .= sprintf("Warning: TypeTable %s contains an invalid type name: %s\r\n",
					$typeTable->name, stripslashes($nameArray[$row[0]]));
				return;
			}
		}

		ksort($nameArray);
		ksort($tokenArray);

		$typeTable->nameArray = $nameArray;
		$typeTable->tokenArray = $tokenArray;
		$typeTable->extraFieldNamesArray = $extraFields;
		$typeTable->extraPropertyArray = $extraPropertyArray;
	}

	/**
	 * @param Table $table
	 * @throws InvalidCastException
	 */
	protected function analyzeTable(Table $table): void {
		// Set up the Table Object
		$table->className = $this->classNameFromTableName($table->name);
		$table->classNamePlural = Utils::pluralize($table->className);

		$table->columnArray = $this->getTableColumns($table);
		$table->indexArray = $this->getTableIndexes($table);

		$table->ownerDbIndex = $this->databaseIndex;

		$this->verifyTableForeignKeys($table);
		$this->verifyTableName($table);
		$this->verifyColumnNames($table);
		$this->verifyTablePrimaryKey($table);
	}

	/**
	 * @param TableBase $table
	 * @return Column[]
	 * @throws InvalidCastException
	 */
	protected function getTableColumns(TableBase $table): array {
		$columnArray = [];

		// Get the List of Columns
		$fieldArray = $this->database->getFieldsForTable($table->name);

		// Iterate through the list of Columns to create columnArray
		if ($fieldArray) {
			foreach ($fieldArray as $field) {
				$column = $this->analyzeTableColumn($field, $table);
				if ($column) {
					$columnArray[strtolower($column->name)] = $column;
				}
			}
		}

		return $columnArray;
	}

	protected function getTableIndexes(TableBase $table): array {

		// Create an Index array
		$preparedIndexArray = [];
		// Create our Index for Primary Key (if applicable)
		$primaryKeyNamesArray = [];

		foreach ($table->columnArray as $column) {
			if ($column->primaryKey) {
				$primaryKeyNamesArray[] = $column->name;
			}
		}

		if (count($primaryKeyNamesArray)) {
			$index = new Index('pk_' . $table->name);
			$index->primaryKey = true;
			$index->unique = true;
			$index->columnNameArray = $primaryKeyNamesArray;
			$preparedIndexArray[] = $index;
		}

		// Get the List of Indexes
		$indexArray = $this->database->getIndexesForTable($table->name);

		// Iterate though each Index that exists in this table, set any Column's "Index" property
		// to TRUE if they are a single-column index
		if ($indexArray) {
			foreach ($indexArray as $databaseIndex) {
				// Make sure the columns are defined
				if (count($databaseIndex->columnNameArray) === 0) {
					$this->errors .= sprintf("Index %s in table %s indexes on no columns.\n",
						$databaseIndex->keyName, $table->name);
				} else {
					// Ensure every column exist in the DbIndex's columnNameArray
					$failed = false;
					foreach ($databaseIndex->columnNameArray as $columnName) {
						if (!array_key_exists(strtolower($columnName), $table->columnArray) && $table->columnArray[strtolower($columnName)]) {
							// It doesn't exist, add a warning
							$this->errors .= sprintf("Index %s in table %s indexes on the column %s, which does not appear to exist.\n",
								$databaseIndex->keyName, $table->name, $columnName);
							$failed = true;
						}
					}

					if (!$failed) {
						// Let's make sure if this is a single-column index, we haven't already created a single-column index for this column
						$alreadyCreated = false;
						foreach ($preparedIndexArray as $index) {
							if (count($index->columnNameArray) === count($databaseIndex->columnNameArray) &&
								implode(',', $index->columnNameArray) === implode(',', $databaseIndex->columnNameArray)
							) {
								$alreadyCreated = true;
							}
						}

						if (!$alreadyCreated) {
							// Create the Index Object
							$index = new Index($databaseIndex->keyName);
							$index->primaryKey = $databaseIndex->primaryKey;
							$index->unique = $databaseIndex->unique;
							if ($databaseIndex->primaryKey) {
								$index->unique = true;
							}
							$index->columnNameArray = $databaseIndex->columnNameArray;

							// Add the new index object to the index array
							$preparedIndexArray[] = $index;

							// Lastly, if it's a single-column index, update the Column in the table to reflect this
							if (count($databaseIndex->columnNameArray) === 1) {
								$columnName = $databaseIndex->columnNameArray[0];
								$column = $table->columnArray[strtolower($columnName)];
								$column->indexed = true;

								if ($index->unique) {
									$column->unique = true;
								}
							}
						}
					}
				}
			}
		}

		return $preparedIndexArray;
	}

	protected function verifyTableForeignKeys(TableBase $table): void {
		// Get the List of Foreign Keys from the database
		$foreignKeys = $this->database->getForeignKeysForTable($table->name);

		// Iterate through each foreign key that exists in this table
		if ($foreignKeys) {
			foreach ($foreignKeys as $foreignKey) {

				// Make sure it's a single-column FK
				if (count($foreignKey->columnNameArray) !== 1) {
					$this->errors .= sprintf("Foreign Key %s in table %s keys on multiple columns.  Multiple-columned FKs are not supported by the code generator.\n",
						$foreignKey->keyName, $table->name);
				} else {
					// Make sure the column in the FK definition actually exists in this table
					$columnName = $foreignKey->columnNameArray[0];

					if (array_key_exists(strtolower($columnName), $table->columnArray) && ($column = $table->columnArray[strtolower($columnName)])) {

						// Now, we make sure there is a single-column index for this FK that exists
						$found = false;
						if ($indexArray = $table->indexArray) {
							foreach ($indexArray as $index) {
								if ((count($index->columnNameArray) === 1) && (strtolower($index->columnNameArray[0]) === strtolower($columnName))
								) {
									$found = true;
								}
							}
						}

						if (!$found) {
							// Single Column Index for this FK does not exist.  Let's create a virtual one and warn
							$index = new Index(sprintf('virtualix_%s_%s', $table->name, $column->name));
							$index->unique = $column->unique;
							$index->columnNameArray = [$column->name];

							$indexArray = $table->indexArray;
							$indexArray[] = $index;
							$table->indexArray = $indexArray;

							if ($index->unique) {
								$this->warnings .= sprintf("Notice: It is recommended that you add a single-column UNIQUE index on \"%s.%s\" for the Foreign Key %s\r\n",
									$table->name, $columnName, $foreignKey->keyName);
							} else {
								$this->warnings .= sprintf("Notice: It is recommended that you add a single-column index on \"%s.%s\" for the Foreign Key %s\r\n",
									$table->name, $columnName, $foreignKey->keyName);
							}
						}

						// Make sure the table being referenced actually exists
						if (
							array_key_exists(strtolower($foreignKey->referenceTableName), $this->tableArray) ||
							array_key_exists(strtolower($foreignKey->referenceTableName), $this->typeTableArray)
						) {

							// STEP 1: Create the New Reference
							$reference = new Reference();

							// Retrieve the Column object
							$column = $table->columnArray[strtolower($columnName)];

							// Setup Key Name
							$reference->keyName = $foreignKey->keyName;

							$referencedTableName = $foreignKey->referenceTableName;

							// Setup isType flag
							$reference->isType = false;
							if (array_key_exists(strtolower($referencedTableName), $this->typeTableArray)) {
								$reference->isType = true;
							}

							// Setup Table and Column names
							$reference->table = $referencedTableName;
							$reference->column = $foreignKey->referenceColumnNameArray[0];

							// Setup VariableType
							$reference->variableType = $this->classNameFromTableName($referencedTableName);

							// Setup propertyName and variableName
							$reference->propertyName = VariableNameCreator::referencePropertyNameFromColumn($column);
							$reference->variableName = VariableNameCreator::referenceVariableNameFromColumn($column);

							// Add this reference to the column
							$column->reference = $reference;


							// STEP 2: Set up the REVERSE Reference for Non Type-based References
							if (!$reference->isType) {
								// Retrieve the ReferencedTable object
								$referencedTable = $this->getTable($reference->table);
								$reverseReference = new ReverseReference();
								$reverseReference->keyName = $reference->keyName;
								$reverseReference->table = $table->name;
								$reverseReference->column = $columnName;
								$reverseReference->notNull = $column->notNull;
								$reverseReference->unique = $column->unique;
								$reverseReference->propertyName = VariableNameCreator::propertyNameFromColumn($this->getColumn($table->name, $columnName));

								$reverseReference->objectDescription = $this->calculateObjectDescription($table->name, $columnName, $referencedTableName, false);
								$reverseReference->objectDescriptionPlural = $this->calculateObjectDescription($table->name, $columnName, $referencedTableName, true);
								$reverseReference->variableName = $this->reverseReferenceVariableNameFromTable($table->name);
								$reverseReference->variableType = $this->reverseReferenceVariableTypeFromTable($table->name);

								// For Special Case ReverseReferences, calculate Associated MemberVariableName and propertyName...

								// See if ReverseReference is due to an ORM-based Class Inheritance Chain
								if ($column->primaryKey && count($table->primaryKeyColumnArray) === 1) {
									$reverseReference->objectMemberVariable = ConvertNotation::prefixFromType(Type::OBJECT) . $reverseReference->variableType;
									$reverseReference->objectPropertyName = $reverseReference->variableType;
									$reverseReference->objectDescription = $reverseReference->variableType;
									$reverseReference->objectDescriptionPlural = Utils::pluralize($reverseReference->variableType);
									// Otherwise, see if it's just plain ol' unique
								} elseif ($column->unique) {
									$reverseReference->objectMemberVariable = $this->calculateObjectMemberVariable($table->name, $columnName, $referencedTableName);
									$reverseReference->objectPropertyName = $this->calculateObjectPropertyName($table->name, $columnName, $referencedTableName);
								}

								// Add this ReverseReference to the referenced table's ReverseReferenceArray
								$array = $referencedTable->reverseReferenceArray;
								$array[] = $reverseReference;
								$referencedTable->reverseReferenceArray = $array;
							}
						} else {
							$this->errors .= sprintf("Foreign Key %s in table %s references a table %s that does not appear to exist.\n",
								$foreignKey->keyName, $table->name, $foreignKey->referenceTableName);
						}
					} else {
						$this->errors .= sprintf("Foreign Key %s in table %s indexes on a column that does not appear to exist.\n",
							$foreignKey->keyName, $table->name);
					}
				}
			}
		}
	}

	protected function verifyTableName(TableBase $table): void {
		// Verify: Table Name is valid (alphanumeric + "_" characters only, must not start with a number)
		// and NOT a PHP Reserved Word
		$matches = [];
		preg_match('/' . $this->patternTableName . '/', $table->name, $matches);
		if ($table->name !== '_' && count($matches) && $matches[0] === $table->name) {
			// Setup Reserved Words
			$reservedWords = explode(',', CodeGen::PHP_RESERVED_WORDS);
			for ($i = count($reservedWords) - 1; $i >= 0; $i--) {
				$reservedWords[$i] = strtolower(trim($reservedWords[$i]));
			}

			$tableNameToTest = strtolower(trim($table->name));
			foreach ($reservedWords as $reservedWord) {
				if ($tableNameToTest === $reservedWord) {
					$this->errors .= sprintf("Table '%s' has a table name which is a PHP reserved word.\r\n", $table->name);
					unset($this->tableArray[strtolower($table->name)]);
					return;
				}
			}
		} else {
			$this->errors .= sprintf("Table '%s' can only contain characters that are alphanumeric or _, and must not begin with a number.\r\n", $table->name);
			unset($this->tableArray[strtolower($table->name)]);
		}
	}

	protected function verifyColumnNames(TableBase $table): void {
		// Verify: Column Names are all valid names
		foreach ($table->columnArray as $column) {
			$columnName = $column->name;
			$matches = [];
			preg_match('/' . $this->patternColumnName . '/', $columnName, $matches);
			if (!(($columnName !== '_') && count($matches) && ($matches[0] === $columnName))) {
				$this->errors .= sprintf("Table '%s' has an invalid column name: '%s'\r\n", $table->name, $columnName);
				unset($this->tableArray[strtolower($table->name)]);
				return;
			}
		}
	}

	protected function verifyTablePrimaryKey(TableBase $table): void {
		// Verify: Table has at least one PK
		$foundPk = false;
		foreach ($table->columnArray as $column) {
			if ($column->primaryKey) {
				$foundPk = true;
			}
		}
		if (!$foundPk) {
			$this->errors .= sprintf("Table %s does not have any defined primary keys.\n", $table->name);
			unset($this->tableArray[strtolower($table->name)]);
		}
	}

	/**
	 * @param FieldBase $field
	 * @param TableBase|null $table
	 * @return Column|null
	 * @throws InvalidCastException
	 */
	protected function analyzeTableColumn(FieldBase $field, ?TableBase $table = null): ?Column {
		$column = new Column();
		$column->name = $field->name;
		$column->ownerTable = $table;
		if (substr_count($field->name, '-')) {
			$tableName = $table ? ' in table ' . $table->name : '';
			$this->errors .= 'Invalid column name' . $tableName . ': ' . $field->name . '. Dashes are not allowed.';
			return null;
		}

		$column->dbType = $field->type;

		$column->variableType = $this->variableTypeFromDbType($column->dbType);
		$column->variableTypeAsConstant = Type::constant($column->variableType);

		$column->length = $field->maxLength;
		$column->default = $field->default;

		$column->primaryKey = $field->primaryKey;
		$column->notNull = $field->notNull;
		$column->identity = $field->identity;
		$column->unique = $field->unique;
		if ($field->primaryKey && $table && $table->primaryKeyColumnArray !== null && count($table->primaryKeyColumnArray) === 1) {
			$column->unique = true;
		}
		$column->timestamp = $field->timestamp;

		$column->variableName = VariableNameCreator::variableNameFromColumn($column);
		$column->propertyName = VariableNameCreator::propertyNameFromColumn($column);
		$column->comment = $field->comment;

		return $column;
	}


	/**
	 * Override method to perform a property "Get"
	 * This will get the value of $name
	 *
	 * @param string $name Name of the property to get
	 * @return mixed
	 * @throws CogException
	 */
	public function __get($name): mixed {
		switch ($name) {
			case 'tableArray':
				return $this->tableArray;
			case 'typeTableArray':
				return $this->typeTableArray;
			case 'databaseIndex':
				return $this->databaseIndex;
			case 'namespaceData':
				return $this->namespaceData;
			case 'namespaceType':
				return $this->namespaceType;
			case 'commentMetaControlLabelDelimiter':
				return $this->commentMetaControlLabelDelimiter;
			default:
				try {
					return parent::__get($name);
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}
}
