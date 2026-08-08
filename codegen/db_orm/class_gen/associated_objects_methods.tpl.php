<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen  */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>
///////////////////////////////
	// ASSOCIATED OBJECTS' METHODS
	///////////////////////////////

<?php foreach ($table->reverseReferenceArray as $reverseReference) { ?><?php if (!$reverseReference->unique) { ?>
<?php include __DIR__ . '/associated_object.tpl.php'; ?>
<?php } ?><?php } ?>
<?php foreach ($table->manyToManyReferenceArray as $manyToManyReference) { ?>
<?php include __DIR__ . '/associated_object_manytomany.tpl.php'; ?>
<?php } ?>

	protected function unassociateEverything(): void {
	<?php foreach ($table->reverseReferenceArray as $reverseReference) { ?><?php if (!$reverseReference->unique) { ?>
	self::unassociateAll<?= $reverseReference->objectDescriptionPluralUppercase ?>();
	<?php } ?><?php } ?><?php \Cog\Codegen\Utils::goBack(1); ?>
	<?php foreach ($table->manyToManyReferenceArray as $manyToManyReference) { ?>
	self::unassociateAll<?= $manyToManyReference->objectDescriptionPluralUppercase ?>();
	<?php } ?><?php \Cog\Codegen\Utils::goBack(1); ?>
}

	protected function deleteEveryAssociation(): void {
<?php foreach ($table->reverseReferenceArray as $reverseReference) { ?><?php if (!$reverseReference->unique) { ?>
		self::deleteAll<?= $reverseReference->objectDescriptionPluralUppercase ?>();
<?php } ?><?php } ?>
	}