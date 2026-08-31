<template OverwriteFlag="true" DocrootFlag="true" DirectorySuffix="" TargetDirectory="/generated/Data" TargetFileName="<?= $table->className ?>Gen.php"/>
<?php print("<?php\n"); ?>
<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>
<?php
$blnCarbon = false;
$blnTimestamp = false;
foreach ($table->columnArray as $column) {
	if ($column->timestamp) {
		$blnTimestamp = true;
	}
	if ($column->variableType == \Cog\Type::DATETIME) {
		$blnCarbon = true;
	}
}
?>

namespace Generated\Data;

<?php include __DIR__ . '/use_external_data_classes.tpl.php' ?>
use IteratorAggregate;
use ArrayIterator;
use Cog\Base;
use Cog\Query\QQ;
use Cog\Query\QueryBuilder;
use Cog\Query\QQNamedValue;
use Cog\Query\QQCondition;
use Cog\Query\QQClause;
use Cog\Query\QQGroupBy;
use Cog\Query\QQAggregationClause;
use Cog\Query\QQBaseNode;
use Cog\Query\QQSelect;
use Cog\Type;
use Cog\Database\Database;
use Cog\Database\Exceptions\UndefinedPrimaryKeyException;
use Cog\Database\ResultBase;
use Cog\Database\RowBase;
use Cog\Exceptions\CogException;
use Cog\Exceptions\InvalidCastException;
use Cog\Util\Utils;
use JsonException;
use Generated\Node\QQNode<?= $table->className ?>;
<?= $blnCarbon ? 'use Carbon\Carbon;' : '' ?>
<?= $blnTimestamp ? 'use Cog\Database\Exceptions\OptimisticLockingException;' : '' ?>

/**
 * The abstract <?= $table->className ?>Gen class defined here is
 * code-generated and contains all the basic CRUD-type functionality as well as
 * basic methods to handle relationships and index-based loading.
 *
 * To use, you should use the <?= $table->className ?> subclass which
 * extends this <?= $table->className ?>Gen class.
 *
 * Because subsequent re-code generations will overwrite any changes to this
 * file, you should leave this file unaltered to prevent yourself from losing
 * any information or code changes.  All customizations should be done by
 * overriding existing or implementing new methods, properties and variables
 * in the <?= $table->className ?> class.
 *
 * @package <?= Cog\Codegen\CodeGenRunner::$applicationName ?>

 * @subpackage GeneratedDataObjects
<?php include __DIR__ . '/property_comments.tpl.php'; ?>

 */
class <?= $table->className ?>Gen extends Base implements IteratorAggregate {

	<?php include __DIR__ . '/protected_member_variables.tpl.php'; ?>


	<?php include __DIR__ . '/protected_member_objects.tpl.php'; ?>


	<?php include __DIR__ . '/object_construct.tpl.php'; ?>

	protected static function getDefaultOptionalClauses(): array {
		return [];
	}

	<?php include __DIR__ . '/class_load_and_count_methods.tpl.php'; ?>


	<?php include __DIR__ . '/index_load_methods.tpl.php'; ?>


	<?php include __DIR__ . '/query_methods.tpl.php'; ?>


	<?php include __DIR__ . '/instantiation_methods.tpl.php'; ?>


	//////////////////////////
	// SAVE, DELETE AND RELOAD
	//////////////////////////

	<?php include __DIR__ . '/object_save.tpl.php'; ?>


	<?php include __DIR__ . '/object_delete.tpl.php'; ?>


	<?php include __DIR__ . '/object_reload.tpl.php'; ?>


	<?php include __DIR__ . '/object_clone.tpl.php'; ?>


	public function mock(<?= $codegen->parameterListFromColumnArray($table->primaryKeyColumnArray) ?>): void {
<?php foreach ($table->primaryKeyColumnArray as $column) { ?>
		$this-><?= $column->variableName ?> = $<?= $column->propertyName ?>;
<?php } ?>
		$this->__blnRestored = true;
	}


	////////////////////
	// PUBLIC OVERRIDERS
	////////////////////

	<?php include __DIR__ . '/property_get.tpl.php'; ?>


	<?php include __DIR__ . '/property_set.tpl.php'; ?>


	<?php include __DIR__ . '/property_isset.tpl.php'; ?>


	/**
	 * Lookup a VirtualAttribute value (if applicable).  Returns null if none found.
	 * @param string $name
	 * @return string|null
	 */
	public function getVirtualAttribute(string $name): ?string {
		if (array_key_exists($name, $this->__strVirtualAttributeArray)) {
			return $this->__strVirtualAttributeArray[$name];
		}
		return null;
	}


	<?php include __DIR__ . '/associated_objects_methods.tpl.php'; ?>


	<?php include __DIR__ . '/class_info.tpl.php'; ?>


	<?php include __DIR__ . '/json_methods.tpl.php'; ?>


}
