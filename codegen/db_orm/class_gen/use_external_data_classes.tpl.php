<?php

/** @var \Cog\Codegen\Table $table */

$classes = [$table->className];

foreach ($table->columnArray as $column) {
	if ($column->reference && !$column->reference->isType) {
		$classes[] = $column->reference->variableType;
	}
}

foreach ($table->reverseReferenceArray as $reference) {
	if ($reference->unique) {
		$classes[] = $reference->variableType;
	}
}

foreach ($table->manyToManyReferenceArray as $reference) {
	$classes[] = $reference->variableType;
}

foreach ($table->reverseReferenceArray as $reference) {
	if (!$reference->unique) {
		$classes[] = $reference->variableType;
	}
}

foreach (array_unique($classes) as $class) { ?>
use <?= $codegen->namespaceData ?>\<?= $class ?>;
<?php }
