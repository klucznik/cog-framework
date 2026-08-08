<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>
<?php foreach ($table->columnArray as $column) { ?>
 * @property<?php if ($column->identity || $column->timestamp) {
		print '-read';
	} ?> <?= $column->variableType ?> $<?= $column->propertyName ?> <?php if ($column->comment) {
		print $column->comment;
	} else {
		print 'the value for ' . $column->variableName;
	} ?> <?php if ($column->identity) {
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

<?php } ?>
<?php foreach ($table->columnArray as $column) { ?>
<?php if ($column->reference && (!$column->reference->isType)) { ?>
 * @property<?php if ($column->identity) {
			print '-read';
		} ?> <?= $column->reference->variableType ?> $<?= $column->reference->propertyName ?> the value for the <?= $column->reference->variableType ?> object referenced by <?= $column->variableName ?> <?php if ($column->identity) {
			print '(Read-Only PK)';
		} else if ($column->primaryKey) {
			print '(PK)';
		} else if ($column->unique) {
			print '(Unique)';
		} else if ($column->notNull) {
			print '(Not Null)';
		} ?>

<?php } ?>
<?php } ?>
<?php foreach ($table->reverseReferenceArray as $reverseReference) { ?>
<?php if ($reverseReference->unique) { ?>
 * @property <?= $reverseReference->variableType ?> $<?= $reverseReference->objectPropertyName ?> the value for the <?= $reverseReference->variableType ?> object that uniquely references this <?= $table->className ?>

<?php } ?>
<?php } ?>
<?php foreach ($table->manyToManyReferenceArray as $reference) { ?>
 * @property-read <?= $reference->variableType ?> $_<?= $reference->objectDescription ?> the value for the private _obj<?= $reference->objectDescriptionUppercase ?> (Read-Only) if set due to an expansion on the <?= $reference->table ?> association table
 * @property-read <?= $reference->variableType ?>[] $_<?= $reference->objectDescription ?>Array the value for the private _obj<?= $reference->objectDescriptionUppercase ?>Array (Read-Only) if set due to an ExpandAsArray on the <?= $reference->table ?> association table
<?php } ?><?php foreach ($table->reverseReferenceArray as $reference) { ?><?php if (!$reference->unique) { ?>
 * @property-read <?= $reference->variableType ?> $_<?= $reference->objectDescription ?> the value for the private _obj<?= $reference->objectDescriptionUppercase ?> (Read-Only) if set due to an expansion on the <?= $reference->table ?>.<?= $reference->column ?> reverse relationship
 * @property-read <?= $reference->variableType ?>[] $_<?= $reference->objectDescription ?>Array the value for the private _obj<?= $reference->objectDescriptionUppercase ?>Array (Read-Only) if set due to an ExpandAsArray on the <?= $reference->table ?>.<?= $reference->column ?> reverse relationship
<?php } ?><?php } ?>
 * @property-read boolean $__Restored whether this object was restored from the database (as opposed to created new)