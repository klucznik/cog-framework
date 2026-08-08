<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>
///////////////////////////////
	// QUERY-RELATED METHODS
	///////////////////////////////

	/**
	 * Internally called method to assist with calling Query for this class on load methods.
	 * @param QueryBuilder|null &$queryBuilder the QueryBuilder object that will be created
	 * @param QQCondition|null $conditions any conditions on the query, itself
	 * @param QQClause[]|QQClause|null $optionalClauses additional optional QQClause object or array of QQClause objects for this query
	 * @param array|null $parameterArray array of name-value pairs to perform PrepareStatement with (sending in null will skip the PrepareStatement step)
	 * @param boolean $countOnly only select a row count
	 * @return string the query statement
	 * @throws CogException
	 */
	protected static function buildQueryStatement(?QueryBuilder &$queryBuilder = null, ?QQCondition $conditions = null, QQClause|array|null $optionalClauses = null, ?array $parameterArray = null, bool $countOnly = false): string {
		// Get the Database Object for this Class
		$database = <?= $table->className ?>::getDatabase();

		// Create/Build out the QueryBuilder object with <?= $table->className ?>-specific SELECT and FROM fields
		$queryBuilder = new QueryBuilder($database, '<?= $table->name ?>');

		$addAllFieldsToSelect = true;
		if ($database->onlyFullGroupBy) {
			// see if we have any group by or aggregation clauses, if yes, don't add the fields to select clause
			if ($optionalClauses instanceof QQAggregationClause || $optionalClauses instanceof QQGroupBy) {
				$addAllFieldsToSelect = false;
			} elseif (is_array($optionalClauses)) {
				foreach ($optionalClauses as $clause) {
					if ($clause instanceof QQAggregationClause || $clause instanceof QQGroupBy) {
						$addAllFieldsToSelect = false;
						break;
					}
				}
			}
		}

		if ($addAllFieldsToSelect) {
			<?= $table->className ?>::getSelectFields($queryBuilder, null, QQ::extractSelectClause($optionalClauses));
		}
		$queryBuilder->addFromItem('<?= $table->name ?>');

		// Set "CountOnly" option (if applicable)
		if ($countOnly) {
			$queryBuilder->setCountOnlyFlag();
		}

		// Apply any conditions
		if ($conditions) {
			try {
				$conditions->updateQueryBuilder($queryBuilder);
			} catch (CogException $exception) {
				$exception->incrementOffset();
				throw $exception;
			}
		}

		// Iterate through all the Optional Clauses (if any) and perform accordingly
		if ($optionalClauses) {
			if ($optionalClauses instanceof QQClause) {
				$optionalClauses->updateQueryBuilder($queryBuilder);
			} elseif (is_array($optionalClauses)) {
				foreach ($optionalClauses as $clause) {
					/** @var QQClause $clause */
					$clause->updateQueryBuilder($queryBuilder);
				}
			} else {
				throw new CogException('Optional Clauses must be a QQClause object or an array of QQClause objects');
			}
		}

		// Get the SQL Statement
		$query = $queryBuilder->getStatement();

		// Prepare the Statement with the Query Parameters (if applicable)
		if (is_array($parameterArray)) {
			if (count($parameterArray)) {
				$query = $database->prepareStatement($query, $parameterArray);
			}

			// Ensure that there are no other Unresolved Named Parameters
			if (str_contains($query, chr(QQNamedValue::DELIMITER_CODE) . '{')) {
				throw new CogException('Unresolved named parameters in the query');
			}
		} elseif ($parameterArray !== null) {
			throw new CogException('Parameter Array must be an array of name-value parameter pairs');
		}

		return $query; //return the Objects
	}

	/**
	 * Static Query method to query for a single <?= $table->className ?> object.
	 * Uses buildQueryStatement to perform most of the work.
	 * @param QQCondition $conditions any conditions on the query, itself
	 * @param null|QQClause|QQClause[] $optionalClauses additional optional QQClause objects for this query
	 * @param array|null $parameterArray array of name-value pairs to perform PrepareStatement with
	 * @return <?= $table->className ?>|null the queried object
	 * @throws CogException
	 */
	public static function querySingle(QQCondition $conditions, QQClause|array|null $optionalClauses = null, ?array $parameterArray = null): ?<?= $table->className ?> {
		// Get the Query Statement
		try {
			$query = <?= $table->className ?>::buildQueryStatement($queryBuilder, $conditions, $optionalClauses, $parameterArray);
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}

		// Perform the Query, Get the First Row, and Instantiate a new <?= $table->className ?> object
		$result = $queryBuilder->database->query($query);

		// Do we have to expand anything?
		if ($queryBuilder->expandAsArrayNode) {
			$toReturn = [];
			$prevItemArray = [];
			while ($dbRow = $result->getNextRow()) {
				$item = <?= $table->className ?>::instantiateDbRow($dbRow, '', $queryBuilder->expandAsArrayNode, $prevItemArray, $queryBuilder->columnAliasArray);
				if ($item) {
					$toReturn[] = $item;
<?php if ($table->primaryKeyColumnArray)  {?>
					$prevItemArray[$item-><?= $table->primaryKeyColumnArray[0]->variableName ?>][] = $item;
<?php } else { ?>
					$prevItemArray[] = $item;
<?php } ?>
				}
			}
			if (count($toReturn)) {
				// Since we only want the object to return, lets return the object and not the array.
				return $toReturn[0];
			}
			return null;
		}

		// No expands just return the first row
		$dbRow = $result->getNextRow();
		if ($dbRow === null) {
			return null;
		}
		return <?= $table->className ?>::instantiateDbRow($dbRow, '', null, null, $queryBuilder->columnAliasArray);
	}

	/**
	 * Static Query method to query for an array of <?= $table->className ?> objects.
	 * Uses buildQueryStatement to perform most of the work.
	 * @param QQCondition $conditions any conditions on the query, itself
	 * @param null|QQClause|QQClause[] $optionalClauses additional optional QQClause objects for this query
	 * @param array|null $parameterArray array of name-value pairs to perform PrepareStatement with
	 * @return <?= $table->className ?>[] the queried objects as an array
	 * @throws CogException
	 */
	public static function queryArray(QQCondition $conditions, QQClause|array|null $optionalClauses = null, ?array $parameterArray = null): array {
		// Get the Query Statement
		try {
			$query = <?= $table->className ?>::buildQueryStatement($queryBuilder, $conditions, $optionalClauses, $parameterArray);
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}

		// Perform the Query and Instantiate the Array Result
		$result = $queryBuilder->database->query($query);
		return <?= $table->className ?>::instantiateDbResult($result, $queryBuilder->expandAsArrayNode, $queryBuilder->columnAliasArray);
	}

	/**
	 * Static query method to issue a query and get a cursor to progressively fetch its results.
	 * Uses buildQueryStatement to perform most of the work.
	 * @param QQCondition $conditions any conditions on the query, itself
	 * @param null|QQClause|QQClause[] $optionalClauses additional optional QQClause objects for this query
	 * @param array|null $parameterArray array of name-value pairs to perform PrepareStatement with
	 * @return ResultBase the cursor resource instance
	 * @throws CogException
	 */
	public static function queryCursor(QQCondition $conditions, QQClause|array|null $optionalClauses = null, ?array $parameterArray = null): ResultBase {
		// Get the query statement
		try {
			$query = <?= $table->className ?>::buildQueryStatement($queryBuilder, $conditions, $optionalClauses, $parameterArray);
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}

		// Perform the query
		$result = $queryBuilder->database->query($query);

		// Return the results cursor
		$result->queryBuilder = $queryBuilder;
		return $result;
	}

	/**
	 * Static Query method to query for a count of <?= $table->className ?> objects.
	 * Uses buildQueryStatement to perform most of the work.
	 * @param QQCondition $conditions any conditions on the query, itself
	 * @param null|QQClause|QQClause[] $optionalClauses additional optional QQClause objects for this query
	 * @param array|null $parameterArray array of name-value pairs to perform PrepareStatement with
	 * @return int the count of queried objects as an integer
	 * @throws CogException
	 */
	public static function queryCount(QQCondition $conditions, QQClause|array|null $optionalClauses = null, ?array $parameterArray = null): int {
		// Get the Query Statement
		try {
			$query = <?= $table->className ?>::buildQueryStatement($queryBuilder, $conditions, $optionalClauses, $parameterArray, true);
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}

		// Perform the Query and return the row_count
		$result = $queryBuilder->database->query($query);

		// Figure out if the query is using GroupBy
		$grouped = false;

		if ($optionalClauses) {
			if ($optionalClauses instanceof QQClause) {
				if ($optionalClauses instanceof QQGroupBy) {
					$grouped = true;
				}
			} elseif (is_array($optionalClauses)) {
				foreach ($optionalClauses as $clause) {
					if ($clause instanceof QQGroupBy) {
						$grouped = true;
						break;
					}
				}
			} else {
				throw new CogException('Optional Clauses must be a QQClause object or an array of QQClause objects');
			}
		}

		if ($grouped) {
			// Groups in this query - return the count of Groups (which is the count of all rows)
			return $result->countRows();
		}

		// No Groups - return the sql-calculated count(*) value
		$dbRow = $result->fetchRow();
		return Type::cast($dbRow[0], Type::INTEGER);
	}

	/**
	 * Updates a QueryBuilder with the SELECT fields for this <?= $table->className ?>

	 * @param QueryBuilder $queryBuilder the Query Builder object to update
	 * @param null|string $prefix optional prefix to add to the SELECT fields
	 * @param null|QQSelect $select
	 */
	public static function getSelectFields(QueryBuilder $queryBuilder, ?string $prefix = null, ?QQSelect $select = null): void {
		if ($prefix) {
			$tableName = $prefix;
			$aliasPrefix = $prefix . '__';
		} else {
			$tableName = '<?= $table->name ?>';
			$aliasPrefix = '';
		}

<?php foreach ($table->primaryKeyColumnArray as $column) { ?>
		$queryBuilder->addSelectItem($tableName, '<?= $column->name ?>', $aliasPrefix . '<?= $column->name ?>');
<?php } ?>

        if ($select) {
            $select->addSelectItems($queryBuilder, $tableName, $aliasPrefix);
        } else {
<?php foreach ($table->columnArray as $column) { ?>
<?php if (!$column->primaryKey) { ?>
			$queryBuilder->addSelectItem($tableName, '<?= $column->name ?>', $aliasPrefix . '<?= $column->name ?>');
<?php } ?>
<?php } ?>
        }
	}
