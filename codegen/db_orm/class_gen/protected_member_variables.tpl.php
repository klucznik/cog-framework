<?php

use Cog\Type;

/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>
///////////////////////////////////////////////////////////////////////
	// PROTECTED MEMBER VARIABLES and TEXT FIELD MAXIMUM LENGTHS (if applicable)
	///////////////////////////////////////////////////////////////////////

<?php

foreach ($table->columnArray as $column) { ?>
<?php if (($column->variableType === Type::STRING) && is_numeric($column->length)) { ?>
	public const int <?= $column->constantPropertyName ?>_MAX_LENGTH = <?= $column->length ?>;
<?php } ?>
	/**
	 * @var ?<?= $column->variableTyped ?> $<?= $column->variableName ?> Protected member variable that maps to the database <?php if ($column->primaryKey) {
		print 'PK ';
	} ?><?php if ($column->identity) {
		print 'Identity ';
	} ?>column <?= $table->name ?>.<?= $column->name ?>

	<?php if ($column->comment) { ?>		 * <?= $column->comment ?>
	<?php } ?>
*/
<?php if ($column->primaryKey) { ?>
	protected ?<?= $column->variableTyped ?> $<?= $column->variableName ?> = null;
<?php } elseif ($column->reference) { ?>
	protected ?<?= $column->variableTyped ?> $<?= $column->variableName ?> = null;
<?php } elseif ($column->hasCurrentTimestampDefault()) { ?>
	protected ?<?= $column->variableTyped ?> $<?= $column->variableName ?> = null;
<?php } else { ?>
	protected ?<?= $column->variableTyped ?> $<?= $column->variableName ?> = <?= $column->getDefaultAsString() ?>;
<?php } ?>
<?php if (!$column->identity && $column->primaryKey) { ?>
	/**
	 * Protected internal member variable that stores the original version of the PK column value (if restored)
	 * Used by save() to update a PK column during UPDATE
	 * @var <?= $column->variableTyped ?> __<?= $column->variableName ?>;
	 */
	protected <?= $column->variableTyped ?> $__<?= $column->variableName ?>;
<?php } ?>

<?php } ?>
<?php foreach ($table->manyToManyReferenceArray as $reference) { ?>
	/**
	 * Private member variable that stores a reference to a single <?= $reference->objectDescription ?> object
	 * (of type <?= $reference->variableType ?>), if this <?= $table->className ?> object was restored with
	 * an expansion on the <?= $reference->table ?> association table.
	 * @var <?= $reference->variableTyped ?>|null $_obj<?= $reference->objectDescriptionUppercase ?>;
	 */
	private ?<?= $reference->variableTyped ?> $_obj<?= $reference->objectDescriptionUppercase ?> = null;

	/**
	 * Private member variable that stores a reference to an array of <?= $reference->objectDescription ?> objects
	 * (of type <?= $reference->variableType ?>[]), if this <?= $table->className ?> object was restored with
	 * an ExpandAsArray on the <?= $reference->table ?> association table.
	 * @var <?= $reference->variableTyped ?>[]|null $_obj<?= $reference->objectDescriptionUppercase ?>Array;
	 */
	private ?array $_obj<?= $reference->objectDescriptionUppercase ?>Array = null;

<?php } ?>
<?php foreach ($table->reverseReferenceArray as $reference) { ?><?php if (!$reference->unique) { ?>
	/**
	 * Private member variable that stores a reference to a single <?= $reference->objectDescription ?> object
	 * (of type <?= $reference->variableType ?>), if this <?= $table->className ?> object was restored with
	 * an expansion on the <?= $reference->table ?> association table.
	 * @var <?= $reference->variableTyped ?>|null $_obj<?= $reference->objectDescriptionUppercase ?>;
	 */
	private ?<?= $reference->variableTyped ?> $_obj<?= $reference->objectDescriptionUppercase ?> = null;

	/**
	 * Private member variable that stores a reference to an array of <?= $reference->objectDescription ?> objects
	 * (of type <?= $reference->variableType ?>[]), if this <?= $table->className ?> object was restored with
	 * an ExpandAsArray on the <?= $reference->table ?> association table.
	 * @var <?= $reference->variableType ?>[]|null $_obj<?= $reference->objectDescriptionUppercase ?>Array;
	 */
	private ?array $_obj<?= $reference->objectDescriptionUppercase ?>Array = null;

<?php } ?><?php } ?>
	/**
	 * Protected array of virtual attributes for this object (e.g. extra/other calculated and/or non-object bound
	 * columns from the run-time database query result for this object).  Used by instantiateDbRow and
	 * getVirtualAttribute.
	 * @var string[] $__strVirtualAttributeArray
	 */
	protected array $__strVirtualAttributeArray = [];

	/**
	 * Protected internal member variable that specifies whether this object is Restored from the database.
	 * Used by save() to determine if save() should perform a db UPDATE or INSERT.
	 * @var bool __blnRestored;
	 */
	protected bool $__blnRestored = false;
