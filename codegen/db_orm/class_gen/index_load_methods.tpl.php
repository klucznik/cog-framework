<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>
///////////////////////////////////////////////////
	// INDEX-BASED LOAD METHODS (Single Load and Array)
	///////////////////////////////////////////////////
<?php foreach ($table->indexArray as $index) { ?>
<?php if ($index->unique) { ?>

<?php include __DIR__ . '/index_load_single.tpl.php' ?>

<?php } ?><?php if (!$index->unique) { ?>

<?php include __DIR__ . '/index_load_array.tpl.php' ?>

<?php } ?>
<?php } ?>



	////////////////////////////////////////////////////
	// INDEX-BASED LOAD METHODS (Array via Many to Many)
	////////////////////////////////////////////////////
<?php foreach ($table->manyToManyReferenceArray as $manyToManyReference) { ?>
<?php include __DIR__ . '/index_load_array_manytomany.tpl.php' ?>

<?php } ?>

