<?php

use Cog\Type;

/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>
///////////////////////////////////////////////////////////////////////
	// COLUMN PROPERTIES and TEXT FIELD MAXIMUM LENGTHS (if applicable)
	///////////////////////////////////////////////////////////////////////

<?php foreach ($table->columnArray as $column) { ?>
<?php if (($column->variableType === Type::STRING) && !$column->json && is_numeric($column->length)) { ?>
	public const int <?= $column->constantPropertyName ?>_MAX_LENGTH = <?= $column->length ?>;
<?php } ?>
	/**
	 * Maps to the database <?php if ($column->primaryKey) {
		print 'PK ';
	} ?><?php if ($column->identity) {
		print 'Identity ';
	} ?>column <?= $table->name ?>.<?= $column->name ?><?php if ($column->identity) {
		print ' (Read-Only)';
	} elseif ($column->timestamp) {
		print ' (Read-Only Timestamp)';
	} ?>

<?php if ($column->comment) { ?>
	 * <?= $column->comment ?>

<?php } ?>
	 */
<?php
	$visibility = ($column->identity || $column->timestamp) ? 'public protected(set)' : 'public';
	$default = ($column->primaryKey || $column->reference || $column->hasCurrentTimestampDefault()) ? 'null' : $column->getDefaultAsString();
?>
<?php if ($column->reference && !$column->reference->isType) { ?>
	<?= $visibility ?> ?<?= $column->variableTyped ?> $<?= $column->propertyName ?> = <?= $default ?> {
		set {
			$this-><?= $column->propertyName ?> = $value;
			// The cached <?= $column->reference->variableType ?> belongs to the previous key
			$this-><?= $column->reference->loadedMember ?> = null;
		}
	}
<?php } else { ?>
	<?= $visibility ?> ?<?= $column->variableTyped ?> $<?= $column->propertyName ?> = <?= $default ?>;
<?php } ?>
<?php if ($column->json) { ?>

	/**
	 * <?= $table->name ?>.<?= $column->name ?> decoded. A JSON object comes back as a stdClass, so {} and [] stay
	 * distinct, and an integer too large for PHP comes back as a string. It is decoded afresh on every read,
	 * so changing what it returns changes nothing: assign the whole value back. Assigning null stores
	 * SQL NULL; to store the JSON literal null, assign 'null' to <?= $column->propertyName ?> itself.
	 */
	public mixed $<?= $column->decodedPropertyName ?> {
		get => Utils::decodeJsonColumn($this-><?= $column->propertyName ?>);
		set {
			$this-><?= $column->propertyName ?> = Utils::encodeJsonColumn($value);
		}
	}
<?php } ?>
<?php if (!$column->identity && $column->primaryKey) { ?>

	/**
	 * The value of <?= $column->propertyName ?> as restored from the database, so that save() can UPDATE a row whose primary key was changed
	 */
	protected ?<?= $column->variableTyped ?> $__<?= $column->propertyName ?> = null;
<?php } ?>

<?php } ?>
<?php foreach ($table->manyToManyReferenceArray as $reference) { ?>
	/**
	 * A single <?= $reference->objectDescription ?> (<?= $reference->variableType ?>), present only when this <?= $table->className ?>

	 * was restored with an expansion on the <?= $reference->table ?> association table
	 */
	public protected(set) ?<?= $reference->variableTyped ?> $_<?= $reference->objectDescription ?> = null;

	/**
	 * The <?= $reference->objectDescription ?> objects, present only when this <?= $table->className ?>

	 * was restored with an ExpandAsArray on the <?= $reference->table ?> association table
	 * @var <?= $reference->variableType ?>[]|null
	 */
	public protected(set) ?array $_<?= $reference->objectDescription ?>Array = null;

<?php } ?>
<?php foreach ($table->reverseReferenceArray as $reference) { ?><?php if (!$reference->unique) { ?>
	/**
	 * A single <?= $reference->objectDescription ?> (<?= $reference->variableType ?>), present only when this <?= $table->className ?>

	 * was restored with an expansion on the <?= $reference->table ?>.<?= $reference->column ?> reverse relationship
	 */
	public protected(set) ?<?= $reference->variableTyped ?> $_<?= $reference->objectDescription ?> = null;

	/**
	 * The <?= $reference->objectDescription ?> objects, present only when this <?= $table->className ?>

	 * was restored with an ExpandAsArray on the <?= $reference->table ?>.<?= $reference->column ?> reverse relationship
	 * @var <?= $reference->variableType ?>[]|null
	 */
	public protected(set) ?array $_<?= $reference->objectDescription ?>Array = null;

<?php } ?><?php } ?>
	/**
	 * Virtual attributes selected alongside the row (columns aliased `__name`), read through getVirtualAttribute()
	 * @var string[]
	 */
	protected array $__virtualAttributeArray = [];

	/**
	 * Whether this <?= $table->className ?> was restored from the database, as opposed to created new
	 */
	public protected(set) bool $__restored = false;
