<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>
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
			///////////////////
			// Member Variables
			///////////////////
<?php foreach ($table->columnArray as $column) { ?>
			case '<?= $column->propertyName ?>':
				/**
				 * Gets the value for <?= $column->variableName ?> <?php if ($column->identity) {
		print '(Read-Only PK)';
	} else if ($column->primaryKey) {
		print '(PK)';
	} else if ($column->timestamp) {
		print '(Read-Only Timestamp)';
	} else if ($column->unique) {
		print '(Unique)';
	} else if ($column->notNull) {
		print '(Not Null)';
	} ?>

				 * @return <?= $column->variableType ?>

				 */
				return $this-><?= $column->variableName ?>;

<?php } ?>

			///////////////////
			// Member Objects
			///////////////////
<?php foreach ($table->columnArray as $column) { ?>
<?php if ($column->reference && (!$column->reference->isType)) { ?>
			case '<?= $column->reference->propertyName ?>':
				/**
				 * Gets the value for the <?= $column->reference->variableType ?> object referenced by <?= $column->variableName ?> <?php if ($column->identity) {
			print '(Read-Only PK)';
		} else if ($column->primaryKey) {
			print '(PK)';
		} else if ($column->unique) {
			print '(Unique)';
		} else if ($column->notNull) {
			print '(Not Null)';
		} ?>

				 * @return <?= $column->reference->variableType ?>

				 */
				try {
					if (!$this-><?= $column->reference->variableName ?> && null !== $this-><?= $column->variableName ?>) {
						$this-><?= $column->reference->variableName ?> = <?= $column->reference->variableType ?>::load($this-><?= $column->variableName ?>);
					}
					return $this-><?= $column->reference->variableName ?>;
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}

<?php } ?>
<?php } ?>
<?php foreach ($table->reverseReferenceArray as $reverseReference) { ?>
<?php if ($reverseReference->unique) { ?>
<?php $objReverseReferenceTable = $codegen->tableArray[strtolower($reverseReference->table)]; ?>
<?php $objReverseReferenceColumn = $objReverseReferenceTable->columnArray[strtolower($reverseReference->column)]; ?>
			case '<?= $reverseReference->objectPropertyName ?>':
				/**
				 * Gets the value for the <?= $reverseReference->variableType ?> object that uniquely references this <?= $table->className ?>

				 * by <?= $reverseReference->objectMemberVariable ?> (Unique)
				 * @return <?= $reverseReference->variableType ?>

				 */
				try {
					if ($this-><?= $reverseReference->objectMemberVariable ?> === false)
						// We've attempted early binding -- and the reverse reference object does not exist
						return null;
					if (!$this-><?= $reverseReference->objectMemberVariable ?>)
						$this-><?= $reverseReference->objectMemberVariable ?> = <?= $reverseReference->variableType ?>::LoadBy<?= $objReverseReferenceColumn->propertyName ?>(<?= $codegen->implodeObjectArray(', ', '$this->', '', 'variableName', $table->primaryKeyColumnArray) ?>);
					return $this-><?= $reverseReference->objectMemberVariable ?>;
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}

<?php } ?>
<?php } ?>

			////////////////////////////
			// Virtual Object References (Many to Many and Reverse References)
			// (If restored via a "Many-to" expansion)
			////////////////////////////

<?php foreach ($table->manyToManyReferenceArray as $reference) { ?>
			case '_<?= $reference->objectDescription ?>':
				/**
				 * Gets the value for the private _obj<?= $reference->objectDescriptionUppercase ?> (Read-Only)
				 * if set due to an expansion on the <?= $reference->table ?> association table
				 * @return <?= $reference->variableType ?>

				 */
				return $this->_obj<?= $reference->objectDescriptionUppercase ?>;

			case '_<?= $reference->objectDescription ?>Array':
				/**
				 * Gets the value for the private _obj<?= $reference->objectDescriptionUppercase ?>Array (Read-Only)
				 * if set due to an ExpandAsArray on the <?= $reference->table ?> association table
				 * @return <?= $reference->variableType ?>[]
				 */
				return $this->_obj<?= $reference->objectDescriptionUppercase ?>Array;

<?php } ?><?php foreach ($table->reverseReferenceArray as $reference) { ?><?php if (!$reference->unique) { ?>
			case '_<?= $reference->objectDescription ?>':
				/**
				 * Gets the value for the private _obj<?= $reference->objectDescriptionUppercase ?> (Read-Only)
				 * if set due to an expansion on the <?= $reference->table ?>.<?= $reference->column ?> reverse relationship
				 * @return <?= $reference->variableType ?>

				 */
				return $this->_obj<?= $reference->objectDescriptionUppercase ?>;

			case '_<?= $reference->objectDescription ?>Array':
				/**
				 * Gets the value for the private _obj<?= $reference->objectDescriptionUppercase ?>Array (Read-Only)
				 * if set due to an ExpandAsArray on the <?= $reference->table ?>.<?= $reference->column ?> reverse relationship
				 * @return <?= $reference->variableType ?>[]
				 */
				return $this->_obj<?= $reference->objectDescriptionUppercase ?>Array;

<?php } ?><?php } ?>

			case '__Restored':
				return $this->__blnRestored;

			default:
				try {
					return parent::__get($name);
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}