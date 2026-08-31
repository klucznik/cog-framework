<?php

namespace Cog\Database;

use Carbon\Carbon;
use Cog;
use Cog\Codegen\Index;
use Cog\Exceptions\CogException;
use Cog\Query\QQClause;
use Cog\Query\QQCondition;
use Cog\Query\QQNamedValue;
use Cog\Type;

/**
 * Every database adapter must implement the following 5 classes (all which are abstract):
 * * Cog\Database\DatabaseExceptionBase
 * * Cog\Database\FieldBase
 * * Cog\Database\ResultBase
 * * Cog\Database\RowBase
 * * Cog\Database\Exceptions\DatabaseExceptionBase
 *
 * This Database library also has the following classes already defined, and
 * Database adapters are assumed to use them internally:
 * * Cog\Codegen\Index
 * * Cog\Database\ForeignKey
 * * Cog\Database\FieldType (which is an abstract class that solely contains constants)
 *
 * @property-read string $escapeIdentifierBegin
 * @property-read string $escapeIdentifierEnd
 * @property-read boolean $profiling
 * @property-read int $affectedRows
 * @property-read string $profile
 * @property-read int $databaseIndex
 *
 * @property-read int $adapter
 * @property-read string $server
 * @property-read string $port
 * @property-read string $database
 * @property-read string $host
 * @property-read string $username
 * @property-read string $password
 *
 * @property-read boolean $onlyFullGroupBy set by database adapter sub-classes at connect time when the server enforces
 *      ONLY_FULL_GROUP_BY, to prevent the behavior of automatically adding all the columns to the select clause when the query has an aggregation clause.
 */
abstract class Base extends Cog\Base {


	/** Adapter name, must be updated for all Adapters */
	public const string ADAPTER = 'Generic Database Adapter (Abstract)';

	/** @var int Database Index according to the configuration file */
	protected int $databaseIndex;

	/** @var bool Has the profiling been enabled? */
	protected bool $profiling;

	protected array $profileArray = [];
	protected array $configArray;

	/** @var bool did we connect with the server */
	protected bool $connectedFlag = false;

	protected string $escapeIdentifierBegin = '"';
	protected string $escapeIdentifierEnd = '"';

	/** @var bool should be set in sub-classes as appropriate */
	protected bool $onlyFullGroupBy = false;

	// Abstract Methods that ALL Database Adapters MUST implement
	abstract public function connect(): void;
	abstract public function close(): void;

	/**
	 * @param string $query
	 * @param bool $saveProfilingInfo
	 * @return Cog\Database\ResultBase
	 */
	abstract public function query(string $query, bool $saveProfilingInfo = true): ResultBase;

	/**
	 * @param string $sql
	 * @param bool $saveProfilingInfo
	 * @return void
	 * @throws CogException
	 */
	abstract public function nonQuery(string $sql, bool $saveProfilingInfo = true): void;

	/**
	 * get the list of Tables as an array of strings
	 * @return string[]
	 */
	abstract public function getTables(): array;

	/**
	 * @param string|null $tableName
	 * @param string|null $columnName
	 * @return mixed
	 */
	abstract public function insertId(?string $tableName = null, ?string $columnName = null): mixed;

	/**
	 * @param string $tableName
	 * @return FieldBase[]
	 */
	abstract public function getFieldsForTable(string $tableName): array;

	/**
	 * @param string $tableName
	 * @return Index[]
    */
	abstract public function getIndexesForTable(string $tableName): array;

	abstract public function getForeignKeysForTable(string $tableName): array;

	abstract public function transactionBegin(): void;

	abstract public function transactionCommit(): void;

	abstract public function transactionRollback(): void;

	abstract public function sqlLimitVariablePrefix(string $limitInfo): ?string;

	abstract public function sqlLimitVariableSuffix(string $limitInfo): ?string;

	abstract public function sqlSortByVariable(string $sortByInfo): ?string;

	/**
	 * Escapes a string for use inside a quoted SQL literal, using the driver's
	 * connection-charset-aware escaping.
	 * @param string $text
	 * @return string
	 */
	abstract public function escapeString(string $text): string;

	public function __get($name): mixed {
		switch ($name) {
			case 'escapeIdentifierBegin':
				return $this->escapeIdentifierBegin;
			case 'escapeIdentifierEnd':
				return $this->escapeIdentifierEnd;
			case 'profiling':
				return $this->profiling;
			case 'affectedRows':
				return -1;
			case 'profile':
				return $this->profileArray;
			case 'databaseIndex':
				return $this->databaseIndex;
			case 'adapter':
				$constantName = get_class($this) . '::ADAPTER';
				return constant($constantName) . ' (' . $this->configArray['adapter'] . ')';

			case 'server':
			case 'port':
			case 'database':
			case 'username':
			case 'password':
				return $this->configArray[strtolower($name)];

			case 'onlyFullGroupBy':
				return $this->onlyFullGroupBy;

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
	 * Constructs a Database Adapter based on the database index and the configuration array of properties for this particular adapter
	 * Sets up the base-level configuration properties for this database,
	 * namely DB Profiling and Database Index
	 *
	 * @param integer $databaseIndex
	 * @param string[] $configArray configuration array as passed in to the constructor by Database::initializeConnections();
	 * @throws CogException
	 */
	public function __construct(int $databaseIndex, array $configArray) {
		// Setup databaseIndex
		$this->databaseIndex = $databaseIndex;

		// Save the ConfigArray
		$this->configArray = $configArray;

		// Setup Profiling Array (if applicable)
		$this->profiling = Type::cast($configArray['profiling'], Type::BOOLEAN);
		if ($this->profiling) {
			$this->enableProfiling();
		}

		// Connect eagerly: a constructed adapter always holds a live connection,
		// which escapeString() relies on, and a bad configuration fails here
		// rather than inside the first query.
		$this->connect();
	}

	/**
	 * Allows for the enabling of DB profiling while in middle of the script
	 * @return void
	 */
	public function enableProfiling(): void {
		// Only perform profiling initialization if profiling is not yet enabled
		if ($this->profiling === false) {
			$this->profiling = true;
		}
	}

	/**
	 * Allows for the disabling of DB profiling while in middle of the script
	 * @return void
	 */
	public function disableProfiling(): void {
		// Turn off profiling only if profiling is enabled
		if ($this->profiling === true) {
			$this->profiling = false;
		}
	}

	/**
	 * If profiling is on, then log the query to the profile array
	 * @param string $query
	 * @param null|array $timeInfo
	 * @return void
	 */
	protected function logQuery(string $query, ?array $timeInfo = null): void {
		if ($this->profiling) {
			// Dereference-ize Backtrace Information
			$debugBacktrace = debug_backtrace();

			// get rid of unnecessary backtrace info in case of:
			// query
			if (
				count($debugBacktrace) > 3 &&
				array_key_exists('function', $debugBacktrace[2]) &&
				(
					($debugBacktrace[2]['function'] === 'queryArray') ||
					($debugBacktrace[2]['function'] === 'querySingle') ||
					($debugBacktrace[2]['function'] === 'queryCount')
				)
			) {
				$backtrace = $debugBacktrace[3];
			} elseif (array_key_exists(2, $debugBacktrace)) {
				$backtrace = $debugBacktrace[2]; // non query
			} else {
				$backtrace = $debugBacktrace[1]; // ad hoc query
			}

			// get rid of reference to current object in backtrace array
			if (array_key_exists('object', $backtrace)) {
				$backtrace['object'] = null;
			}

			foreach ($backtrace['args'] as $key => $item) {
				$backtrace['args'][$key] = $this->classifyBacktrace($item);
			}

			// Push it onto the profiling information array
			$profile = compact('backtrace', 'query');
			if ($timeInfo) {
				$profile['timeInfo'] = $timeInfo;
			}
			$this->profileArray[] = $profile;
		}
	}

	private function classifyBacktrace(mixed $backtrace): string {
		$toReturn = '';

		if (($backtrace instanceof QQClause) || ($backtrace instanceof QQCondition)) {
			$toReturn = sprintf('[%s]', $backtrace);
		} elseif ($backtrace === null) {
			$toReturn = 'null';
		} elseif (is_int($backtrace)) {
			return $toReturn;
		} elseif (is_object($backtrace)) {
			$toReturn = 'object';
		} elseif (is_string($backtrace)) {
			$toReturn = sprintf("'%s'", $backtrace);
		} elseif (is_array($backtrace)) {
			foreach ($backtrace as $i) {
				$toReturn .= $this->classifyBacktrace($i) . ', ';
			}
		}
		return $toReturn;
	}

	/**
	 * Properly escapes $data to be used as a SQL query parameter.
	 * If IncludeEquality is set (usually not), then include an equality operator.
	 * So for most data, it would just be "=".  But, for example,
	 * if $data is NULL, then most RDBMS's require the use of "IS".
	 *
	 * @param mixed $data
	 * @param boolean $includeEquality whether to include an equality operator
	 * @param boolean $reverseEquality whether the included equality operator should be a "NOT EQUAL", e.g. "!="
	 * @return string the properly formatted SQL variable
	 */
	public function sqlVariable(mixed $data, bool $includeEquality = false, bool $reverseEquality = false): string {
		// Are we processing a BOOLEAN value?
		if (is_bool($data)) {

			// We must include the equality
			if ($includeEquality) {

				// Do a "Reverse Equality"
				if ($reverseEquality) {
					return $data ? '= 0' : '!= 0';
				}

				// Do a "Normal Equality"
				return $data ? '!= 0' : '= 0';
			}

			// Don't include an equality
			return $data ? '1' : '0';
		}

		// Check for Equality Inclusion
		if ($includeEquality) {
			if ($reverseEquality) {
				$toReturn = '!= ';
				if ($data === null) {
					$toReturn = 'IS NOT ';
				}
			} else {
				$toReturn = '= ';
				if ($data === null) {
					$toReturn = 'IS ';
				}
			}
		} else {
			$toReturn = '';
		}

		// Check for NULL Value
		if ($data === null) {
			return $toReturn . 'NULL';
		}

		// Check for NUMERIC Value
		if (is_int($data) || is_float($data)) {
			return $toReturn . sprintf('%s', $data);
		}

		// Check for DATE Value
		if ($data instanceof Carbon) {
			return $toReturn . sprintf("'%s'", $data->toDateTimeString());
		}

		// Assume it's some kind of string value
		return $toReturn . sprintf("'%s'", $this->escapeString($data));
	}

	public function prepareStatement(string $query, array $parameterArray): array|string {
		foreach ($parameterArray as $key => $value) {
			if (is_array($value)) {
				$parameters = [];
				foreach ($value as $parameter) {
					$parameters[] = $this->sqlVariable($parameter);
				}

				$query = str_replace(chr(QQNamedValue::DELIMITER_CODE) . '{' . $key . '}', implode(',', $parameters), $query);
			} else {
				$query = str_replace([
					chr(QQNamedValue::DELIMITER_CODE) . '{=' . $key . '=}',
					chr(QQNamedValue::DELIMITER_CODE) . '{!' . $key . '!}',
					chr(QQNamedValue::DELIMITER_CODE) . '{' . $key . '}'
				], [
					$this->sqlVariable($value, true, false),
					$this->sqlVariable($value, true, true),
					$this->sqlVariable($value)
				], $query);
			}
		}

		return $query;
	}

	/**
	 * Returns database profiling data, null if profiling is not enabled
	 * @return array | null
	 */
	public function outputProfiling(): ?array {
		if ($this->profiling) {
			return $this->profileArray;
		}
		return null;
	}

	/**
	 * Returns database profiling data, null if profiling is not enabled
	 * Strips newlines from sql queries
	 * @return array | null
	 */
	public function outputProfilingWithoutLineBreaks(): ?array {
		if ($this->profiling) {
			$toReturn = [];

			foreach ($this->profileArray as $profile) {
				if (array_key_exists('query', $profile)) {
					$profile['query'] = trim(preg_replace('/\s\s+/', ' ', $profile['query']));
				}
				$toReturn[] = $profile;
			}

			return $toReturn;
		}
		return null;
	}

	/**
	 * Executes the explain statement for a given query and returns the output without any transformation.
	 * If the database adapter does not support EXPLAIN statements, returns null.
	 * @param string $sql
	 * @return ResultBase | null
	 */
	public function explainStatement(string $sql): ?object {
		return null;
	}
}
