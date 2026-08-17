<?php

namespace Cog\Database\Adapters;

use Cog;
use Cog\Codegen\ForeignKey;
use Cog\Codegen\Index;
use Cog\Exceptions\CogException;
use Cog\Type;
use mysqli;

/**
 * @property-read integer $affectedRows
 */
class MySqliAdapter extends Cog\Database\Base {
	public const ADAPTER = 'MySql Improved Database Adapter';

	protected mysqli $mySqli;

	protected int $lastInsertId = 0;

	/** @inheritdoc */
	public function __construct($databaseIndex, $configArray) {
		parent::__construct($databaseIndex, $configArray);

		$this->escapeIdentifierBegin = '`';
		$this->escapeIdentifierEnd = '`';
	}

	public function sqlLimitVariablePrefix($limitInfo): ?string {
		// MySQL uses Limit by Suffixes (via a LIMIT clause)

		// If requested, use SQL_CALC_FOUND_ROWS directive to utilize GetFoundRows() method
		if (array_key_exists('usefoundrows', $this->configArray) && $this->configArray['usefoundrows']) {
			return 'SQL_CALC_FOUND_ROWS';
		}
		return null;
	}

	public function sqlLimitVariableSuffix($limitInfo): ?string {
		// Setup limit suffix (if applicable) via a LIMIT clause
		if ($limitInfo !== '' && $limitInfo !== null) {
			if (str_contains($limitInfo, ';')) {
				throw new \Exception('Invalid Semicolon in LIMIT Info');
			}
			if (str_contains($limitInfo, '`')) {
				throw new \Exception('Invalid Backtick in LIMIT Info');
			}
			return 'LIMIT ' . $limitInfo;
		}

		return null;
	}

	public function sqlSortByVariable(string $sortByInfo): ?string {
		// Setup sorting information (if applicable) via a ORDER BY clause
		if ($sortByInfo !== '') {
			if (str_contains($sortByInfo, ';')) {
				throw new \Exception('Invalid Semicolon in ORDER BY Info');
			}
			if (str_contains($sortByInfo, '`')) {
				throw new \Exception('Invalid Backtick in ORDER BY Info');
			}

			return 'ORDER BY ' . $sortByInfo;
		}

		return null;
	}

	public function connect(): void {
		if ($this->connectedFlag) {
			return;
		}

		// Connect to the Database Server
		// A failed connection throws from the constructor since PHP 8.1, so the
		// driver exception is translated here for the same reason it is in query().
		try {
			$this->mySqli = new MySqli($this->server, $this->username, $this->password, $this->database, $this->port);
		} catch (\mysqli_sql_exception $exception) {
			throw new MySqliException($exception->getMessage(), $exception->getCode(), '');
		}

		if (!$this->mySqli) {
			throw new MySqliException('Unable to connect to Database', -1, null);
		}

		if ($this->mySqli->error) {
			throw new MySqliException($this->mySqli->error, $this->mySqli->errno, null);
		}

		$this->connectedFlag = true; // Update "Connected" Flag
		$this->nonQuery('SET AUTOCOMMIT=1;'); // Set to AutoCommit

		if (array_key_exists('encoding', $this->configArray)) {
			// set_charset() rather than SET NAMES: real_escape_string() only
			// follows the connection charset when it is set through the API.
			try {
				$this->mySqli->set_charset($this->configArray['encoding']);
			} catch (\mysqli_sql_exception $exception) {
				throw new MySqliException($exception->getMessage(), $exception->getCode(), '');
			}
		}

		// Detect ONLY_FULL_GROUP_BY so the query layer knows not to add
		// ungrouped columns to the select list of aggregate queries.
		$row = $this->query('SELECT @@SESSION.sql_mode;', false)->fetchRow();
		$this->onlyFullGroupBy = str_contains($row[0], 'ONLY_FULL_GROUP_BY');

		if (array_key_exists('timezone', $this->configArray)) { // Set time zone (if applicable)
			$this->nonQuery('SET time_zone = "' . $this->configArray['timezone'] . '";');
		}

		if ($this->profiling && $this->isTimeProfilingSupported()) {
			$this->nonQuery('SET PROFILING = 1;', false);
		}
	}

	public function __get($name) {
		switch ($name) {
			case 'affectedRows':
				return $this->mySqli->affected_rows;

			default:
				try {
					return parent::__get($name);
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}

	/**
	 * @param string $query
	 * @param bool $saveProfilingInfo
	 * @return MySqliResult
	 * @throws MySqliException
	 */
	public function query(string $query, bool $saveProfilingInfo = true): MySqliResult {
		// perform the Query
		// mysqli reports errors by throwing since PHP 8.1, so the driver exception
		// has to be translated here as well as checked for - otherwise a failing
		// query escapes as a mysqli_sql_exception without the query attached.
		try {
			$result = $this->mySqli->query($query);
		} catch (\mysqli_sql_exception $exception) {
			throw new MySqliException($this->mySqli->error, $this->mySqli->errno, $query);
		}

		if ($this->mySqli->error || $result === false) { // check uf the query errored
			throw new MySqliException($this->mySqli->error, $this->mySqli->errno, $query);
		}

		if ($query !== 'SHOW PROFILE;') {
			$this->lastInsertId = 0;
		}

		// Log Query (for Profiling, if applicable)
		if ($saveProfilingInfo) {
			$this->logQuery($query, $this->getTimeProfilingInfo());
		}

		// return the Result
		return new MySqliResult($result, $this);
	}

	/**
	 * Performs a Multi Result-Set Query, which is available with Stored Procedures in MySQL 5
	 *
	 * @param string $query
	 * @return MySqliResult[] array of results
	 * @throws MySqliException
	 */
	public function multiQuery(string $query): array {
		// Log Query (for Profiling, if applicable)
		$this->logQuery($query);

		// Perform the Query
		$this->mySqli->multi_query($query);
		if ($this->mySqli->error) {
			throw new MySqliException($this->mySqli->error, $this->mySqli->errno, $query);
		}

		$this->lastInsertId = 0;

		$resultSets = [];
		do {
			if ($result = $this->mySqli->store_result()) {
				$resultSets[] = new MySqliResult($result, $this);
			}
		} while ($this->mySqli->next_result());

		return $resultSets;
	}

	/** @inheritdoc */
	public function nonQuery(string $sql, bool $saveProfilingInfo = true): void {
		// Perform the Query
		try {
			$this->mySqli->query($sql);
		} catch (\mysqli_sql_exception $e) {
			throw new MySqliException($this->mySqli->error, $this->mySqli->errno, $sql);
		}

		if ($this->mySqli->error) {
			throw new MySqliException($this->mySqli->error, $this->mySqli->errno, $sql);
		}

		$this->lastInsertId = $this->mySqli->insert_id;

		// Log Query (for Profiling, if applicable)
		if ($this->profiling && $saveProfilingInfo) {
			$this->logQuery($sql, $this->getTimeProfilingInfo());
		}
	}

	public function getTables(): array {
		// Use the MySQL5 Information Schema to get a list of all the tables in this database (excluding views, etc.)
		$databaseName = $this->database;

		$result = $this->query("
			SELECT
				`table_name`
			FROM
				`information_schema`.`tables`
			WHERE
				`table_type` <> 'VIEW' AND
				`table_schema` = '$databaseName';
		");

		$toReturn = [];
		while ($rowArray = $result->fetchRow()) {
			$toReturn[] = $rowArray[0];
		}
		return $toReturn;
	}

	public function getFieldsForTable(string $tableName): array {
		$result = $this->query(sprintf('SELECT * FROM %s%s%s LIMIT 1', $this->escapeIdentifierBegin, $tableName, $this->escapeIdentifierEnd));
		return $result->fetchFields();
	}

	public function insertId(?string $tableName = null, ?string $columnName = null): int {
		return $this->lastInsertId;
	}

	public function close(): void {
		if ($this->connectedFlag) {
			$this->mySqli->close();
			$this->connectedFlag = false;
		}
	}

	public function escapeString(string $text): string {
		return $this->mySqli->real_escape_string($text);
	}

	public function transactionBegin(): void {
		// Set to AutoCommit
		$this->nonQuery('SET AUTOCOMMIT=0;');
	}

	public function transactionCommit(): void {
		$this->nonQuery('COMMIT;');
		// Set to AutoCommit
		$this->nonQuery('SET AUTOCOMMIT=1;');
	}

	public function transactionRollback(): void {
		$this->nonQuery('ROLLBACK;');
		// Set to AutoCommit
		$this->nonQuery('SET AUTOCOMMIT=1;');
	}

	public function getFoundRows() {
		if (array_key_exists('usefoundrows', $this->configArray) && $this->configArray['usefoundrows']) {
			$result = $this->query('SELECT FOUND_ROWS();');
			$row = $result->fetchArray();
			return $row[0];
		}
		throw new CogException('Cannot call GetFoundRows() on the database when "usefoundrows" configuration was not set to true.');
	}

	/**
	 * @param string $tableName
	 * @return Index[]
	 * @throws \Exception
	 */
	public function getIndexesForTable(string $tableName): array {
		// Figure out the Table Type (InnoDB, MyISAM, etc.) by parsing the Create Table description
		$createStatement = $this->getCreateStatementForTable($tableName);
		$tableType = $this->getTableTypeForCreateStatement($createStatement);

		switch (true) {
			case str_starts_with($tableType, 'INNODB'):
			case str_starts_with($tableType, 'MYISAM'):
			case str_starts_with($tableType, 'MEMORY'):
			case str_starts_with($tableType, 'HEAP'):
				return $this->parseForIndexes($createStatement);

			default:
				throw new \Exception('Table Type is not supported: '. $tableType);
		}
	}

	/**
	 * @param $tableName
	 * @return ForeignKey[]
	 * @throws \Exception
	 */
	public function getForeignKeysForTable($tableName): array {
		$foreignKeyArray = [];

		// Figure out the Table Type (InnoDB, MyISAM, etc.) by parsing the Create Table description
		$createStatement = $this->getCreateStatementForTable($tableName);
		$tableType = $this->getTableTypeForCreateStatement($createStatement);

		switch (true) {
			case str_starts_with($tableType, 'INNODB'):
				$foreignKeyArray = $this->parseForInnoDbForeignKeys($createStatement);
				break;

			case str_starts_with($tableType, 'MYISAM'):
			case str_starts_with($tableType, 'MEMORY'):
			case str_starts_with($tableType, 'HEAP'):
				break;

			default:
				throw new \Exception('Table Type is not supported: ' . $tableType);
		}

		return $foreignKeyArray;
	}

	/**
	 * MySql defines KeyDefinition to be [OPTIONAL_NAME] ([COL], ...)
	 * If the key name exists, this will parse it out and return it
	 * @param string $keyDefinition
	 * @return string|null
	 * @throws \Exception
	 */
	private function parseNameFromKeyDefinition(string $keyDefinition): ?string {
		$keyDefinition = trim($keyDefinition);

		$position = strpos($keyDefinition, '(');

		if ($position === false) {
			throw new \Exception('Invalid Key Definition: ' . $keyDefinition);
		}

		if ($position === 0) {
			return null; // No Key Name Defined
		}

		// If we're here, then we have a key name defined
		$name = trim(substr($keyDefinition, 0, $position));

		// Rip Out leading and trailing "`" character (if applicable)
		if (str_starts_with($name, '`')) {
			$name = substr($name, 1);
		}

		if (str_ends_with($name, '`')) {
			$name = substr($name, 0, -1);
		}

		return $name;
	}


	/**
	 * MySql defines KeyDefinition to be [OPTIONAL_NAME] ([COL], ...)
	 * This will return an array of strings that are the names [COL], etc.
	 * @param string $keyDefinition
	 * @return array
	 * @throws \Exception
	 */
	private function parseColumnNameArrayFromKeyDefinition(string $keyDefinition): array {
		$keyDefinition = trim($keyDefinition);

		// Get rid of the opening "(" and the closing ")"
		$position = strpos($keyDefinition, '(');
		if ($position === false) {
			throw new \Exception('Invalid Key Definition: ' . $keyDefinition);
		}
		$keyDefinition = trim(substr($keyDefinition, $position + 1));

		$position = strpos($keyDefinition, ')');
		if ($position === false) {
			throw new \Exception('Invalid Key Definition: ' . $keyDefinition);
		}
		$keyDefinition = trim(substr($keyDefinition, 0, $position));

		// Create the Array
		// TODO: Current method doesn't support key names with commas or parenthesis in them!
		$toReturn = explode(',', $keyDefinition);

		// Take out trailing and leading "`" character in each name (if applicable)
		foreach ($toReturn as $i => $value) {
			$column = $value;

			if (str_starts_with($column, '`')) {
				$column = substr($column, 1, strpos($column, '`', 1) - 1);
			}

			$toReturn[$i] = $column;
		}

		return $toReturn;
	}

	/**
	 * @param string $createStatement
	 * @return Index[]
	 * @throws \Exception
	 */
	private function parseForIndexes(string $createStatement): array {
		$indexArray = [];

		// MySql nicely splits each object in a table into its own line
		// Split create statement into lines, and then pull out anything
		// that says "PRIMARY KEY", "UNIQUE KEY", or just plain ol' "KEY"
		$lineArray = explode("\n", $createStatement);

		// We don't care about the first line or the last line
		$count = count($lineArray);
		for ($i = 1; $i < ($count - 1); $i++) {
			$line = $lineArray[$i];

			// Each object has a two-space indent
			// So this is a key object if any of those key-related words exist at position 2
			switch (2) {
				case (strpos($line, 'PRIMARY KEY')):
					$keyDefinition = substr($line, strlen('  PRIMARY KEY '));

					$keyName = $this->parseNameFromKeyDefinition($keyDefinition);
					$columnNameArray = $this->parseColumnNameArrayFromKeyDefinition($keyDefinition);

					$index = new Index($keyName);
					$index->primaryKey = true;
					$index->unique = true;
					$index->columnNameArray = $columnNameArray;

					$indexArray[] = $index;
					break;

				case (strpos($line, 'UNIQUE KEY')):
					$keyDefinition = substr($line, strlen('  UNIQUE KEY '));

					$keyName = $this->parseNameFromKeyDefinition($keyDefinition);
					$columnNameArray = $this->parseColumnNameArrayFromKeyDefinition($keyDefinition);

					$index = new Index($keyName);
					$index->primaryKey = false;
					$index->unique = true;
					$index->columnNameArray = $columnNameArray;

					$indexArray[] = $index;
					break;

				case (strpos($line, 'KEY')):
					$keyDefinition = substr($line, strlen('  KEY '));

					$keyName = $this->parseNameFromKeyDefinition($keyDefinition);
					$columnNameArray = $this->parseColumnNameArrayFromKeyDefinition($keyDefinition);

					$index = new Index($keyName);
					$index->primaryKey = false;
					$index->unique = false;
					$index->columnNameArray = $columnNameArray;

					$indexArray[] = $index;
					break;
			}
		}

		return $indexArray;
	}

	/**
	 * @param string $createStatement
	 * @return ForeignKey[]
	 * @throws \Exception
	 */
	private function parseForInnoDbForeignKeys(string $createStatement): array {
		// MySql nicely splits each object in a table into its own line
		// Split create statement into lines, and then pull out anything
		// that starts with "CONSTRAINT" and contains "FOREIGN KEY"
		$lineArray = explode("\n", $createStatement);

		$foreignKeyArray = [];

		// We don't care about the first line or the last line
		for ($i = 1; $i < (count($lineArray) - 1); $i++) {
			$line = $lineArray[$i];

			// Check to see if the line:
			// * Starts with "CONSTRAINT" at position 2 AND
			// * contains "FOREIGN KEY"

			if (strpos($line, 'CONSTRAINT') === 2 && str_contains($line, 'FOREIGN KEY')) {
				$line = substr($line, strlen('  CONSTRAINT '));

				// By the end of the following lines, we will end up with a strTokenArray
				// Index 0: the FK name
				// Index 1: the list of columns that are the foreign key
				// Index 2: the table which this FK references
				// Index 3: the list of columns which this FK references
				$tokenArray = explode(' FOREIGN KEY ', $line);
				$tokenArray[1] = explode(' REFERENCES ', $tokenArray[1]);
				$tokenArray[2] = $tokenArray[1][1];
				$tokenArray[1] = $tokenArray[1][0];
				$tokenArray[2] = explode(' ', $tokenArray[2]);
				$tokenArray[3] = $tokenArray[2][1];
				$tokenArray[2] = $tokenArray[2][0];

				// Cleanup, and change Index 1 and Index 3 to be an array based on the
				// parsed column name list
				if (str_starts_with($tokenArray[0], '`')) {
					$tokenArray[0] = substr($tokenArray[0], 1, -1);
				}
				$tokenArray[1] = $this->parseColumnNameArrayFromKeyDefinition($tokenArray[1]);
				if (str_starts_with($tokenArray[2], '`')) {
					$tokenArray[2] = substr($tokenArray[2], 1, -1);
				}
				$tokenArray[3] = $this->parseColumnNameArrayFromKeyDefinition($tokenArray[3]);

				// Create the FK object and add it to the return array
				$foreignKey = new ForeignKey($tokenArray[0], $tokenArray[1], $tokenArray[2], $tokenArray[3]);
				$foreignKeyArray[] = $foreignKey;

				// Ensure the FK object has matching column numbers (or else, throw)

				if (count($foreignKey->columnNameArray) === 0 || count($foreignKey->columnNameArray) !== count($foreignKey->referenceColumnNameArray)) {
					throw new \Exception('Invalid Foreign Key definition: ' . $line);
				}
			}
		}
		return $foreignKeyArray;
	}

	private function getCreateStatementForTable(string $tableName): array|string {
		// Use the MySQL "SHOW CREATE TABLE" functionality to get the table's Create statement
		$result = $this->query(sprintf('SHOW CREATE TABLE `%s`', $tableName));
		$row = $result->fetchRow();
		$createTable = $row[1];
		$createTable = str_replace("\r", '', $createTable);
		return $createTable;
	}

	private function getTableTypeForCreateStatement(string $createStatement): string {
		// Table Type is in the last line of the Create Statement, "TYPE=DbTableType"
		$lineArray = explode("\n", $createStatement);
		$finalLine = strtoupper($lineArray[count($lineArray) - 1]);

		switch(true) {
			case str_starts_with($finalLine, ') TYPE='):
				return trim(substr($finalLine, 7));

			case str_starts_with($finalLine, ') ENGINE='):
				return trim(substr($finalLine, 9));

			default:
				throw new \Exception('Invalid Table Description');
		}
	}

	/**
	 * As of MySQL 5.6.3, EXPLAIN provides information about
	 * SELECT, DELETE, INSERT, REPLACE, and UPDATE statements.
	 * Before MySQL 5.6.3, EXPLAIN provides information only about SELECT statements.
	 * @param string $sql
	 * @return MySqliResult | null
	 * @throws CogException
	 */
	public function explainStatement(string $sql): ?MySqliResult {
		if ($this->mySqli->server_version >= 50603) {
			return $this->query('EXPLAIN ' . $sql);
		}

		// We have the version before 5.6.3
		// let's check if it is SELECT-only request
		if (substr_count($sql, 'DELETE') === 0 && substr_count($sql, 'INSERT') === 0 &&
			substr_count($sql, 'REPLACE') === 0 && substr_count($sql, 'UPDATE') === 0
		) {
			return $this->query('EXPLAIN ' . $sql);
		}

		return null; // Return null by default
	}

	/**
	 * Check if profiling of queries is supported by the server
	 * @return boolean
	 * @throws MySqliException
	 */
	protected function isTimeProfilingSupported(): bool {
		return $this->mySqli->server_version >= 50037;
	}

	/**
	 * Allows for the enabling of DB profiling while in middle of the script
	 * @return void
	 * @throws CogException
	 */
	public function enableProfiling(): void {
		parent::enableProfiling();
		if ($this->connectedFlag && $this->profiling && $this->isTimeProfilingSupported() ) {
			$this->nonQuery('SET PROFILING = 1;', false);
		}
	}

	/**
	 * Allows for the disabling of DB profiling while in middle of the script
	 * @return void
	 * @throws CogException
	 */
	public function disableProfiling(): void {
		if ($this->connectedFlag && $this->profiling && $this->isTimeProfilingSupported() ) {
			$this->nonQuery('SET PROFILING = 0;', false);
		}
		parent::disableProfiling();
	}

	protected function getTimeProfilingInfo(): ?array {
		if ($this->profiling && $this->isTimeProfilingSupported()) {
			$profilingResult = $this->query('SHOW PROFILE;', false);

			$timeInfo = [];
			$queryTime = 0;
			while ($mixRow = $profilingResult->fetchRow()) {
				$timeInfo[$mixRow[0]] = $mixRow[1];
				$queryTime += Type::cast($mixRow[1], Type::FLOAT);
			}
			$timeInfo['total time'] = $queryTime;

			return $timeInfo;
		}

		return null;
	}
}
