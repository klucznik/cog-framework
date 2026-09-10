<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>
///////////////////////////////
	// REFERENCED AND ADJOINED OBJECTS
	///////////////////////////////

<?php foreach ($table->columnArray as $column) { ?>
<?php if ($column->reference && (!$column->reference->isType)) { ?>
<?php
	$type = $column->reference->variableType;
	$loaded = $column->reference->loadedMember;
	$referencedKey = $codegen->tableArray[strtolower($column->reference->table)]->columnArray[strtolower($column->reference->column)]->propertyName;
?>
	/**
	 * The <?= $type ?> referenced by <?= $table->name ?>.<?= $column->name ?>, once loaded.
	 * Read <?= $column->reference->propertyName ?> instead: it loads on first access.
	 */
	protected ?<?= $type ?> $<?= $loaded ?> = null;

	/**
	 * The <?= $type ?> referenced by <?= $table->name ?>.<?= $column->name ?>, loaded on first read.
	 * Assigning one sets <?= $column->propertyName ?>; the <?= $type ?> must already be saved.
	 */
	public ?<?= $type ?> $<?= $column->reference->propertyName ?> {
		get {
			if (!$this-><?= $loaded ?> && null !== $this-><?= $column->propertyName ?>) {
				$this-><?= $loaded ?> = <?= $type ?>::load($this-><?= $column->propertyName ?>);
			}
			return $this-><?= $loaded ?>;
		}
		set {
			if (null === $value) {
				$this-><?= $column->propertyName ?> = null;
				return;
			}
			if (null === $value-><?= $referencedKey ?>) {
				throw new CogException('Unable to set an unsaved <?= $column->reference->propertyName ?> for this <?= $table->className ?>');
			}
			// The key first: its setter drops the cached object, which is then replaced
			$this-><?= $column->propertyName ?> = $value-><?= $referencedKey ?>;
			$this-><?= $loaded ?> = $value;
		}
	}

<?php } ?>
<?php } ?>
<?php foreach ($table->reverseReferenceArray as $reverseReference) { ?>
<?php if ($reverseReference->unique) { ?>
<?php
	$type = $reverseReference->variableType;
	$loaded = $reverseReference->loadedMember;
	$property = $reverseReference->objectPropertyName;
	$dirty = lcfirst($property) . 'Dirty';
	$reverseTable = $codegen->getTable($reverseReference->table);
	$reverseColumn = $reverseTable->columnArray[strtolower($reverseReference->column)];
	$reverseKey = $reverseTable->primaryKeyColumnArray[0]->propertyName;
?>
	/**
	 * The <?= $type ?> that points at this <?= $table->className ?> through the unique
	 * column <?= $reverseReference->table ?>.<?= $reverseReference->column ?>, once loaded.
	 * Read <?= $property ?> instead: it loads on first access.
	 */
	protected ?<?= $type ?> $<?= $loaded ?> = null;

	/**
	 * Whether the adjoined <?= $reverseReference->objectDescription ?> has to be written on the next save().
	 *
	 * NOTE: Do not manually update this value
	 */
	protected bool $<?= $dirty ?> = false;

	/**
	 * The <?= $type ?> that points at this <?= $table->className ?> through the unique
	 * column <?= $reverseReference->table ?>.<?= $reverseReference->column ?>, loaded on first read.
	 * Assigning one is written to the database by the next save().
	 */
	public ?<?= $type ?> $<?= $property ?> {
		get {
			if (!$this-><?= $loaded ?>) {
				$this-><?= $loaded ?> = <?= $type ?>::loadBy<?= $reverseColumn->propertyNameUppercase ?>(<?= $codegen->implodeObjectArray(', ', '$this->', '', 'propertyName', $table->primaryKeyColumnArray) ?>);
			}
			return $this-><?= $loaded ?>;
		}
		set {
			if (null === $value) {
				$this-><?= $loaded ?> = null;
				$this-><?= $dirty ?> = true;
				return;
			}
			// Only a different <?= $type ?> needs writing on save(). The current one is loaded by hand
			// rather than read through the property: a hook reading its own property makes it backed.
			if (!$this-><?= $loaded ?>) {
				$this-><?= $loaded ?> = <?= $type ?>::loadBy<?= $reverseColumn->propertyNameUppercase ?>(<?= $codegen->implodeObjectArray(', ', '$this->', '', 'propertyName', $table->primaryKeyColumnArray) ?>);
			}
			if (!$this-><?= $loaded ?> || $this-><?= $loaded ?>-><?= $reverseKey ?> != $value-><?= $reverseKey ?>) {
				$this-><?= $dirty ?> = true;
				$this-><?= $loaded ?> = $value;
			}
		}
	}

<?php } ?>
<?php } ?>
