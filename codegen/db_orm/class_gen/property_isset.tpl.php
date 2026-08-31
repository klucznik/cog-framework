<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
?>
/**
	 * Override method to answer isset(), empty() and the left side of ??
	 *
	 * PHP consults __isset() before __get(), so without this every magic property
	 * reads as unset: isset() is false, empty() is true, and `?? $default` hands
	 * back the default even when the property holds a value.
	 *
	 * This deliberately reads the backing fields rather than calling __get().
	 * __get() lazy-loads - a reference or an adjoined object issues a SELECT the
	 * first time it is read - and isset() must not put a query behind what looks
	 * like a null check.
	 *
	 * That has one consequence worth knowing: a reference reports whether its
	 * foreign key column is set, which is the answer without loading anything,
	 * while an adjoined object reports whether it is already in hand. An adjoined
	 * object that exists in the database but has not been loaded yet reads as not
	 * set, because saying otherwise would cost a query.
	 *
	 * The query-free guarantee covers isset(), not empty(). PHP evaluates
	 * empty($x) as !isset($x) || !$x, so a property answered as set here is then
	 * read through __get() to test its truthiness - loading it. isset() is the
	 * free check; empty() on a set reference costs the load.
	 *
	 * @param string $name Name of the property to test
	 * @return bool
	 */
	public function __isset($name): bool {
		switch ($name) {
			///////////////////
			// Member Variables
			///////////////////
<?php foreach ($table->columnArray as $column) { ?>
			case '<?= $column->propertyName ?>':
				return $this-><?= $column->variableName ?> !== null;

<?php } ?>
			///////////////////
			// Member Objects
			///////////////////
<?php foreach ($table->columnArray as $column) { ?>
<?php if ($column->reference && (!$column->reference->isType)) { ?>
			case '<?= $column->reference->propertyName ?>':
				// Answered from the foreign key column, so no load is triggered
				return $this-><?= $column->variableName ?> !== null;

<?php } ?>
<?php } ?>
<?php foreach ($table->reverseReferenceArray as $reverseReference) { ?>
<?php if ($reverseReference->unique) { ?>
			case '<?= $reverseReference->objectPropertyName ?>':
				// false means early binding already established there is no such row
				return $this-><?= $reverseReference->objectMemberVariable ?> !== null
					&& $this-><?= $reverseReference->objectMemberVariable ?> !== false;

<?php } ?>
<?php } ?>
			////////////////////////////
			// Virtual Object References (Many to Many and Reverse References)
			// (If restored via a "Many-to" expansion)
			////////////////////////////

<?php foreach ($table->manyToManyReferenceArray as $reference) { ?>
			case '_<?= $reference->objectDescription ?>':
				return $this->_obj<?= $reference->objectDescriptionUppercase ?> !== null;

			case '_<?= $reference->objectDescription ?>Array':
				return $this->_obj<?= $reference->objectDescriptionUppercase ?>Array !== null;

<?php } ?><?php foreach ($table->reverseReferenceArray as $reference) { ?><?php if (!$reference->unique) { ?>
			case '_<?= $reference->objectDescription ?>':
				return $this->_obj<?= $reference->objectDescriptionUppercase ?> !== null;

			case '_<?= $reference->objectDescription ?>Array':
				return $this->_obj<?= $reference->objectDescriptionUppercase ?>Array !== null;

<?php } ?><?php } ?>
			case '__Restored':
				return true;

			default:
				return false;
		}
	}
