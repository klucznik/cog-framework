<template OverwriteFlag="true" DocrootFlag="true" DirectorySuffix="" TargetDirectory="/generated/Node" TargetFileName="QQNode<?= $table->className ?><?= $reference->objectDescriptionUppercase ?>.php"/>
<?php print("<?php\n"); ?>
<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var \Cog\Codegen\ManyToManyReference $reference */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>

namespace Generated\Node;

use Cog\Query\QQNode;
use Cog\Query\QQAssociationNode;
use Cog\Exceptions\CogException;

/**
 * @property-read QQNode $<?= $reference->oppositePropertyName ?>

 * @property-read QQNode<?= $reference->variableType ?> $<?= lcfirst($reference->variableType) ?>

 * @property-read QQNode<?= $reference->variableType ?> $_childTableNode
 **/
class QQNode<?= $table->className ?><?= $reference->objectDescriptionUppercase ?> extends QQAssociationNode {

	public function __construct($parentNode) {
		$this->type = 'association';
		$this->name = '<?= strtolower($reference->objectDescription) ?>';

		$this->tableName = '<?= $reference->table ?>';
		$this->primaryKey = '<?= $reference->column ?>';
		$this->className = '<?= $reference->variableType ?>';
		$this->classNameQualified = '\<?= $codegen->namespaceData ?>\<?= $reference->variableType ?>';
		$this->propertyName = '<?= $reference->objectDescription ?>';
		$this->alias = '<?= strtolower($reference->objectDescription) ?>';

		parent::__construct($parentNode);
	}

	public function __get($name) {
		switch ($name) {
			case '<?= $reference->oppositePropertyName ?>':
				return new QQNode('<?= $reference->oppositeColumn ?>', '<?= $reference->oppositePropertyName ?>', '<?= $reference->oppositeVariableType ?>', $this);
			case '<?= lcfirst($reference->variableType) ?>':
				return new QQNode<?= $reference->variableType ?>('<?= $reference->oppositeColumn ?>', '<?= $reference->oppositePropertyName ?>', '<?= $reference->oppositeVariableType ?>', $this);
			case '_childTableNode':
				return new QQNode<?= $reference->variableType ?>('<?= $reference->oppositeColumn ?>', '<?= $reference->oppositePropertyName ?>', '<?= $reference->oppositeVariableType ?>', $this);
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
