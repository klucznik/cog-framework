<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>
<?php
$blnTimestamp = false;
foreach ($table->columnArray as $column) {
	if ($column->timestamp) {
		$blnTimestamp = true;
	}
}
?>
/**
	 * Save this <?= $table->className, "\n" ?>
<?php foreach ($table->primaryKeyColumnArray as $column) { ?>
	 * @param ?<?= $column->variableTyped ?> $<?= $column->propertyName ?><?= "\n"?>
<?php } ?>
<?php if ($blnTimestamp) { ?>
	 * @param bool $forceUpdate
	 * @throws OptimisticLockingException
<?php } ?>
	 * @throws CogException

<?php
$returnType = 'void';
foreach ($table->columnArray as $column) {
	if ($column->identity) {
		$returnType = '?int';
		break;
	}
}
print '     * @return ' . $returnType;

$strIdentityCols = '';
$strIdentityValues = '';
$strCols = '';
$strValues = '';
$strColUpdates = '';
foreach ($table->columnArray as $column) {
	if ($column->timestamp === false) {
		if ($column->identity === false) {
			if ($strCols) {
				$strCols .= ",\n";
			}
			if ($strValues) {
				$strValues .= ",\n";
			}
			if ($strColUpdates) {
				$strColUpdates .= ",\n";
			}
			$strCol = '			    			' . $escapeIdentifierBegin . $column->name . $escapeIdentifierEnd;
			$strCols .= $strCol;
			switch ($column->dbType) {
				case 'Date':
					$strValue = '\' . $database->sqlVariable($this->' . $column->variableName . ' instanceof Carbon ? $this->' . $column->variableName . '->toDateString() : $this->' . $column->variableName . ') . \'';
					break;
				case 'Time':
					$strValue = '\' . $database->sqlVariable($this->' . $column->variableName . ' instanceof Carbon ? $this->' . $column->variableName . '->toTimeString() : $this->' . $column->variableName . ') . \'';
					break;
				default:
				$strValue = '\' . $database->sqlVariable($this->' . $column->variableName . ') . \'';
			}
			$strValues .= '		    				' . $strValue;
			$strColUpdates .= $strCol . ' = ' . $strValue;
		} else {
			if ($strIdentityCols) {
				$strIdentityCols .= ",\n";
			}
			if ($strIdentityValues) {
				$strIdentityValues .= ",\n";
			}

			$strIdentityCols .= '			    			' . $escapeIdentifierBegin . $column->name . $escapeIdentifierEnd;
			$strIdentityValues .= '		    				\' . $database->sqlVariable($' . $column->propertyName . ') . \'';
		}
	}
}

if ($strIdentityValues) {
	if ($strValues) {
		$strIdentityCols = " (\n" . $strIdentityCols . ",\n" . $strCols . "\n		    			)";
		$strIdentityValues = " VALUES (\n" . $strIdentityValues . ",\n" . $strValues . "\n	    				)\n";
	} else {
		$strIdentityCols = " (\n" . $strIdentityCols . "\n		    			)";
		$strIdentityValues = " VALUES (\n" . $strIdentityValues . "\n	    				)\n";
	}
}

if ($strValues) {
	$strCols = " (\n" . $strCols . "\n		    			)";
	$strValues = " VALUES (\n" . $strValues . "\n	    				)\n";
} else {
	$strValues = ' DEFAULT VALUES';
}

if (!$strIdentityValues) {
	$strIdentityCols = $strCols;
	$strIdentityValues = $strValues;
}

$strIds = '';
foreach ($table->primaryKeyColumnArray as $objPkColumn) {
	if ($strIds) {
		$strIds .= " AND \n";
	}
	$strIds .= '						' . $escapeIdentifierBegin.$objPkColumn->name.$escapeIdentifierEnd .
		' = \' . $database->sqlVariable($this->' . ($objPkColumn->identity ? '' : '__')  . $objPkColumn->variableName . ') . \'';
}
?>

	 */
	public function save(<?= $codegen->parameterListNulledFromColumnArray($table->primaryKeyColumnArray) ?><?php if ($blnTimestamp) { ?>, bool $forceUpdate = false<?php } ?>): ?int {
		// Get the Database Object for this Class
		$database = <?= $table->className ?>::getDatabase();

		$mixToReturn = null;

		try {
			if (!$this->__blnRestored || (<?= $codegen->implodeObjectArray(' && ', '$', ' != null', 'propertyName', $table->primaryKeyColumnArray); ?>)) {
				// Perform an INSERT query

				if (<?= $codegen->implodeObjectArray(' && ', '$', ' == null', 'propertyName', $table->primaryKeyColumnArray); ?>) {
					$database->nonQuery('
						INSERT INTO <?= $escapeIdentifierBegin ?><?= $table->name ?><?= $escapeIdentifierEnd ?><?= $strCols ?><?= $strValues ?>
					');
				} else {
					$database->nonQuery('
						INSERT INTO <?= $escapeIdentifierBegin ?><?= $table->name ?><?= $escapeIdentifierEnd ?><?= $strIdentityCols ?><?= $strIdentityValues ?>
					');
				}

<?php
foreach ($table->primaryKeyColumnArray as $column) {
	if ($column->identity) {
		print sprintf('		   		// Update Identity column and return its value
				$mixToReturn = $this->%s = $database->insertId(\'%s\', \'%s\');',
			$column->variableName, $table->name, $column->name);
	}
}
?>

			} else {
				// Perform an UPDATE query

				// First checking for Optimistic Locking constraints (if applicable)
<?php foreach ($table->columnArray as $column) { ?>
<?php if ($column->timestamp) { ?>
				if (!$forceUpdate) {
					// Perform the Optimistic Locking check
					$result = $database->query('
						SELECT
							<?= $escapeIdentifierBegin ?><?= $column->name ?><?= $escapeIdentifierEnd ?>

						FROM
							<?= $escapeIdentifierBegin ?><?= $table->name ?><?= $escapeIdentifierEnd ?>

						WHERE
<?= $strIds ?>

					');

					$objRow = $result->fetchArray();
					if ($objRow[0] != (string) $this-><?= $column->variableName ?>) {
						throw new OptimisticLockingException('<?= $table->className ?>');
					}
				}
<?php } ?>
<?php } ?>

				// Perform the UPDATE query
<?php if ($strColUpdates) { ?>
				$database->nonQuery('
					UPDATE
						<?= $escapeIdentifierBegin ?><?= $table->name ?><?= $escapeIdentifierEnd ?>

					SET
<?= $strColUpdates ?>

					WHERE
<?= $strIds ?>

				');
<?php } else { ?>
				// Nothing to update
<?php }?>
			}

<?php foreach ($table->reverseReferenceArray as $reverseReference) { ?>
<?php if ($reverseReference->unique) { ?>
<?php $reverseReferenceTable = $codegen->tableArray[strtolower($reverseReference->table)]; ?>
<?php $reverseReferenceColumn = $reverseReferenceTable->columnArray[strtolower($reverseReference->column)]; ?>


			// Update the adjoined <?= $reverseReference->objectDescription ?> object (if applicable)
			// TODO: Make this into hard-coded SQL queries
			if ($this->blnDirty<?= $reverseReference->objectPropertyName ?>) {
				// Unassociate the old one (if applicable)
				if ($associated = <?= $reverseReference->variableType ?>::LoadBy<?= $reverseReferenceColumn->propertyName ?>(<?= $codegen->implodeObjectArray(', ', '$this->', '', 'variableName', $table->primaryKeyColumnArray) ?>)) {
					$associated-><?= $reverseReferenceColumn->propertyName ?> = null;
					$associated->save();
				}

				// Associate the new one (if applicable)
				if ($this-><?= $reverseReference->objectMemberVariable ?>) {
					$this-><?= $reverseReference->objectMemberVariable ?>-><?= $reverseReferenceColumn->propertyName ?> = $this-><?= $table->primaryKeyColumnArray[0]->variableName ?>;
					$this-><?= $reverseReference->objectMemberVariable ?>->save();
				}

				// Reset the "Dirty" flag
				$this->blnDirty<?= $reverseReference->objectPropertyName ?> = false;
			}
<?php } ?>
<?php } ?>
		} catch (CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}

		// Update __blnRestored and any Non-Identity PK Columns (if applicable)
		$this->__blnRestored = true;
<?php foreach ($table->primaryKeyColumnArray as $column) { ?>
<?php if ((!$column->identity) && $column->primaryKey) { ?>
		$this->__<?= $column->variableName ?> = $this-><?= $column->variableName ?>;
<?php } ?>
<?php } ?>

<?php foreach ($table->columnArray as $column) { ?>
<?php if ($column->timestamp) { ?>
		// Update Local Timestamp
		$result = $database->query('
			SELECT
				<?= $escapeIdentifierBegin ?><?= $column->name ?><?= $escapeIdentifierEnd ?>

			FROM
				<?= $escapeIdentifierBegin ?><?= $table->name ?><?= $escapeIdentifierEnd ?>

			WHERE
<?= $strIds ?>

		');

		$objRow = $result->fetchArray();
		$this-><?= $column->variableName ?> = new Carbon($objRow[0]);
<?php } ?>
<?php } ?>
		return $mixToReturn;
	}
