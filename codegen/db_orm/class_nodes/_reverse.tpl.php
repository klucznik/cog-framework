<template OverwriteFlag="true" DocrootFlag="true" DirectorySuffix="" TargetDirectory="/generated/Node" TargetFileName="QQReverseReferenceNode<?= $table->className ?>.php"/>
<?php print("<?php\n"); ?>
<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>

namespace Generated\Node;

use Cog\Query\QQBaseNode;
use Cog\Query\QQNode;
use Cog\Query\QQReverseReferenceNode;
use Cog\Exceptions\CogException;

/**
<?php foreach ($table->columnArray as $column) { ?>
 * @property-read QQNode $<?= $column->propertyName ?>

<?php if ($column->reference && (!$column->reference->isType)) { ?>
 * @property-read QQNode<?= $column->reference->variableType ?> $<?= $column->reference->propertyName ?>

<?php } ?>
<?php } ?>
<?php foreach ($table->manyToManyReferenceArray as $reference) { ?>
 * @property-read QQNode<?= $table->className ?><?= $reference->objectDescription ?> $<?= $reference->objectDescription ?>

<?php } ?>
<?php foreach ($table->reverseReferenceArray as $reference) { ?>
 * @property-read QQReverseReferenceNode<?= $reference->variableType ?> $<?= $reference->objectDescription ?>

<?php } ?>
<?php $objPkColumn = $table->primaryKeyColumnArray[0]; ?>
* @property-read QQNode<?php if ($objPkColumn->reference && (!$objPkColumn->reference->isType)) { print $objPkColumn->reference->variableType; } ?> $primaryKeyNode
**/
class QQReverseReferenceNode<?= $table->className ?> extends QQReverseReferenceNode {

	public function __construct(QQBaseNode $parentNode, $name, $type, $foreignKey, $propertyName = null) {
		$this->tableName = '<?= $table->name ?>';
		$this->primaryKey = '<?= $table->primaryKeyColumnArray[0]->name ?>';
		$this->className = '<?= $table->className ?>';
		$this->classNameQualified = '\<?= $codegen->namespaceData ?>\<?= $table->className ?>';

		parent::__construct($parentNode, $name, $type, $foreignKey, $propertyName);
	}

	public function __get($name) {
		switch ($name) {
<?php foreach ($table->columnArray as $column) { ?>
			case '<?= $column->propertyName ?>':
				return new QQNode('<?= $column->name ?>', '<?= $column->propertyName ?>', '<?= $column->variableType ?>', $this);
<?php if ($column->reference && (!$column->reference->isType)) { ?>
			case '<?= $column->reference->propertyName ?>':
				return new QQNode<?= $column->reference->variableType ?>('<?= $column->name ?>', '<?= $column->reference->propertyName ?>', '<?= $column->variableType ?>', $this);
<?php } ?>
<?php } ?>
<?php foreach ($table->manyToManyReferenceArray as $reference) { ?>
			case '<?= $reference->objectDescription ?>':
				return new QQNode<?= $table->className ?><?= $reference->objectDescription ?>($this);
<?php } ?><?php foreach ($table->reverseReferenceArray as $reference) { ?>
			case '<?= $reference->objectDescription ?>':
				return new QQReverseReferenceNode<?= $reference->variableType ?>($this, '<?= strtolower($reference->objectDescription) ?>', 'reverse_reference', '<?= $reference->column ?>', '<?= $reference->objectDescription ?>');
<?php } ?><?php $objPkColumn = $table->primaryKeyColumnArray[0]; ?>

			case 'primaryKeyNode':
				return new QQNode<?php if ($objPkColumn->reference && (!$objPkColumn->reference->isType)) {
	print $objPkColumn->reference->variableType;
} ?>('<?= $objPkColumn->name ?>', '<?= $objPkColumn->propertyName ?>', '<?= $objPkColumn->variableType ?>', $this);

			default:
				try {
					return parent::__get($name);
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}
}
