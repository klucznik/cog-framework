<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>
/**
	 * Override method to perform a property "Set"
	 * This will set the property $name to be $value
	 *
	 * @param string $name Name of the property to set
	 * @param string $value New value of the property
	 * @return mixed
	 * @throws CogException
	 */
	public function __set($name, $value) {
		switch ($name) {
			///////////////////
			// Member Variables
			///////////////////
<?php foreach ($table->columnArray as $column) { ?>
<?php if (!$column->identity && !$column->timestamp) { ?>
			case '<?= $column->propertyName ?>':
				/**
				 * Sets the value for <?= $column->variableName ?> <?php if ($column->primaryKey) { print '(PK)'; } elseif ($column->unique) { print '(Unique)'; } elseif ($column->notNull) { print '(Not Null)'; } ?>

				 * @param <?= $column->variableType ?> $value
				 * @return <?= $column->variableType ?>

				 */
				try {
<?php if ($column->reference && !$column->reference->isType) { ?>
					$this-><?= $column->reference->variableName ?> = null;
<?php } ?>
					return ($this-><?= $column->variableName ?> = Type::cast($value, <?= $column->variableTypeAsConstant ?>));
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}

<?php } ?>
<?php } ?>

			///////////////////
			// Member Objects
			///////////////////
<?php foreach ($table->columnArray as $column) { ?>
<?php if ($column->reference && (!$column->reference->isType)) { ?>
			case '<?= $column->reference->propertyName ?>':
				/**
				 * Sets the value for the <?= $column->reference->variableType ?> object referenced by <?= $column->variableName ?> <?php if ($column->identity) { print '(Read-Only PK)'; } elseif ($column->primaryKey) { print '(PK)'; } elseif ($column->unique) { print '(Unique)'; } elseif ($column->notNull) { print '(Not Null)'; } ?>

				 * @param <?= $column->reference->variableType ?> $value
				 * @return <?= $column->reference->variableType ?>

				 */
				if (null === $value) {
					$this-><?= $column->variableName ?> = null;
					$this-><?= $column->reference->variableName ?> = null;
					return null;
				}

				// Make sure $value actually is <?= $column->reference->variableType ?> object
				try {
					$value = Type::cast($value, <?= $column->reference->variableType ?>::class);
				} catch (InvalidCastException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}

				// Make sure $value is a SAVED <?= $column->reference->variableType ?> object
				if (null === $value-><?= $codegen->tableArray[strtolower($column->reference->table)]->columnArray[strtolower($column->reference->column)]->propertyName ?>) {
					throw new CogException('Unable to set an unsaved <?= $column->reference->propertyName ?> for this <?= $table->className ?>');
				}

				// Update Local Member Variables
				$this-><?= $column->reference->variableName ?> = $value;
				$this-><?= $column->variableName ?> = $value-><?= $codegen->tableArray[strtolower($column->reference->table)]->columnArray[strtolower($column->reference->column)]->propertyName ?>;

				// Return $value
				return $value;

<?php } ?>
<?php } ?>
<?php foreach ($table->reverseReferenceArray as $reverseReference) { ?>
<?php if ($reverseReference->unique) { ?>
			case '<?= $reverseReference->objectPropertyName ?>':
				/**
				 * Sets the value for the <?= $reverseReference->variableType ?> object referenced by <?= $reverseReference->objectMemberVariable ?> (Unique)
				 * @param <?= $reverseReference->variableType ?> $value
				 * @return <?= $reverseReference->variableType ?>

				 */
				if (null === $value) {
					$this-><?= $reverseReference->objectMemberVariable ?> = null;

					// Make sure we update the adjoined <?= $reverseReference->variableType ?> object the next time we call save()
					$this->blnDirty<?= $reverseReference->objectPropertyName ?> = true;

					return null;
				}

				// Make sure $value actually is <?= $reverseReference->variableType ?> object
				try {
					$value = Type::cast($value, '<?= $reverseReference->variableType ?>');
				} catch (InvalidCastException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}

				// Are we setting <?= $reverseReference->objectMemberVariable ?> to a DIFFERENT $value?
				if ((!$this-><?= $reverseReference->objectPropertyName ?>) || ($this-><?= $reverseReference->objectPropertyName ?>-><?= $codegen->getTable($reverseReference->table)->primaryKeyColumnArray[0]->propertyName ?> != $value-><?= $codegen->getTable($reverseReference->table)->primaryKeyColumnArray[0]->propertyName ?>)) {
					// Yes -- therefore, set the "Dirty" flag to true
					// to make sure we update the adjoined <?= $reverseReference->variableType ?> object the next time we call save()
					$this->blnDirty<?= $reverseReference->objectPropertyName ?> = true;

					// Update Local Member Variable
					$this-><?= $reverseReference->objectMemberVariable ?> = $value;
				} else {
					// Nope -- therefore, make no changes
				}

				// Return $value
				return $value;

<?php } ?>
<?php } ?>
			default:
				try {
					return parent::__set($name, $value);
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}