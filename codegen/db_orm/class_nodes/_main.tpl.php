<template OverwriteFlag="true" DocrootFlag="true" DirectorySuffix="" TargetDirectory="/generated/Node" TargetFileName="QQNode<?= $table->className ?>.php"/>
<?php print("<?php\n"); ?>
<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
/** @var string $moduleName */
?>

namespace Generated\Node;

use Cog\Query\QQNode;
use Cog\Query\QQBaseNode;
use Cog\Exceptions\CogException;


/////////////////////////////////////
// ADDITIONAL CLASSES for QUERY
/////////////////////////////////////

<?php foreach ($table->manyToManyReferenceArray as $reference) {
	$argumentArray = [
		'reference' => $reference,
		'table' => $table,
		'escapeIdentifierBegin' => $escapeIdentifierBegin,
		'escapeIdentifierEnd' => $escapeIdentifierEnd,
	];

	$codegen->generateFile($moduleName, 'many.tpl.php', $argumentArray);
} ?>
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
 * @property-read QQNode<?php if ($objPkColumn->reference && !$objPkColumn->reference->isType) { print $objPkColumn->reference->variableType; } ?> $primaryKeyNode
 **/
class QQNode<?= $table->className ?> extends QQNode {

	/**
	 * QQNode<?= $table->className ?> constructor.
	 * @param string|null $name
	 * @param string|null $propertyName
	 * @param string|null $type
	 * @param QQBaseNode|null $parentNode
	*/
	public function __construct($name = null, $propertyName = null, $type = null, ?QQBaseNode $parentNode = null) {
		$this->tableName = '<?= $table->name ?>';
		$this->primaryKey = '<?= $table->primaryKeyColumnArray[0]->name ?>';
		$this->className = '<?= $table->className ?>';
		$this->classNameQualified = '\<?= $codegen->namespaceData ?>\<?= $table->className ?>';

		if ($name === null) {
			$name = $this->tableName;
		}

		parent::__construct($name, $propertyName, $type, $parentNode);
	}

	public function __get($name) {
		switch ($name) {
<?php foreach ($table->columnArray as $column) { ?>
			case '<?= $column->propertyName ?>':
				return new QQNode('<?= $column->name ?>', '<?= $column->propertyName ?>', '<?= $column->dbType ?>', $this);
<?php if ($column->reference && (!$column->reference->isType)) { ?>
			case '<?= $column->reference->propertyName ?>':
				return new QQNode<?= $column->reference->variableType ?>('<?= $column->name ?>', '<?= $column->reference->propertyName ?>', '<?= $column->dbType ?>', $this);
<?php } ?>
<?php } ?>

<?php foreach ($table->manyToManyReferenceArray as $reference) { ?>
			case '<?= $reference->objectDescription ?>':
				return new QQNode<?= $table->className ?><?= $reference->objectDescriptionUppercase ?>($this);
<?php } ?>

<?php foreach ($table->reverseReferenceArray as $reference) { ?>
			case '<?= $reference->objectDescription ?>':
				return new QQReverseReferenceNode<?= $reference->variableType ?>($this, '<?= strtolower($reference->objectDescription) ?>', 'reverse_reference', '<?= $reference->column ?>', '<?= $reference->objectDescription ?>');
<?php } ?><?php $objPkColumn = $table->primaryKeyColumnArray[0]; ?>

			case 'primaryKeyNode':
				return new QQNode<?php if ($objPkColumn->reference && (!$objPkColumn->reference->isType)) {
	print $objPkColumn->reference->variableType;
} ?>('<?= $objPkColumn->name ?>', '<?= $objPkColumn->propertyName ?>', '<?= $objPkColumn->dbType ?>', $this);
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
