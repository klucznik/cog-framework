<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>
/**
	 * Make a new unsaved clone of this <?= $table->className ?> end return it
	 * @return <?= $table->className ?>

	 */
	public function clone(): <?= $table->className ?> {
		$clone = new <?= $table->className ?>();

<?php foreach ($table->columnArray as $column) { ?>
<?php if (!$column->identity) { ?>
<?php if (!$column->timestamp) { ?>
		$clone-><?= $column->propertyName ?> = $this-><?= $column->variableName ?>;
<?php } ?>
<?php } ?><?php } ?>

		return $clone;
	}