<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>
///////////////////////////////
	// PROTECTED MEMBER OBJECTS
	///////////////////////////////

<?php foreach ($table->columnArray as $column) { ?>
<?php if ($column->reference && (!$column->reference->isType)) { ?>
	/**
	 * Protected member variable that contains the object pointed by the reference
	 * in the database column <?= $table->name ?>.<?= $column->name ?>.
	 *
	 * NOTE: Always use the <?= $column->reference->propertyName ?> property getter to correctly retrieve this <?= $column->reference->variableType ?> object.
	 * (Because this class implements late binding, this variable reference MAY be null.)
	 * @var <?= $column->reference->variableType ?>|null $<?= $column->reference->variableName ?>

	 */
	protected ?<?= $column->reference->variableType ?> $<?= $column->reference->variableName ?> = null;

<?php } ?>
<?php } ?>
<?php foreach ($table->reverseReferenceArray as $objReverseReference) { ?>
<?php if ($objReverseReference->unique) { ?>
	/**
	 * Protected member variable that contains the object which points to
	 * this object by the reference in the unique database column <?= $objReverseReference->table ?>.<?= $objReverseReference->column ?>.
	 *
	 * NOTE: Always use the <?= $objReverseReference->objectPropertyName ?> property getter to correctly retrieve this <?= $objReverseReference->variableType ?> object.
	 * (Because this class implements late binding, this variable reference MAY be null.)
	 * @var <?= $objReverseReference->variableType ?>|null <?= $objReverseReference->objectMemberVariable ?>

	 */
	protected ?<?= $objReverseReference->variableType ?> $<?= $objReverseReference->objectMemberVariable ?> = null;

	/**
	 * Used internally to manage whether the adjoined <?= $objReverseReference->objectDescription ?> object
	 * needs to be updated on save.
	 *
	 * NOTE: Do not manually update this value
	 */
	protected bool $blnDirty<?= $objReverseReference->objectPropertyName ?> = false;

<?php } ?>
<?php } ?>
