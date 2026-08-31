<?php
	/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
	/** @var \Cog\Codegen\TypeTable $typeTable  */
?><template OverwriteFlag="true" DocrootFlag="true" DirectorySuffix="" TargetDirectory="/generated/Type" TargetFileName="<?= $typeTable->className ?>Gen.php"/>
<?php print("<?php\n"); ?>

namespace Generated\Type;

use Cog\Base;

/**
 * The <?= $typeTable->className ?> class defined here contains
 * code for the <?= $typeTable->className ?> enumerated type.  It represents
 * the enumerated values found in the "<?= $typeTable->name ?>" table
 * in the database.
 *
 * To use, you should use the <?= $typeTable->className ?> subclass which
 * extends this <?= $typeTable->className ?>Gen class.
 *
 * Because subsequent re-code generations will overwrite any changes to this
 * file, you should leave this file unaltered to prevent yourself from losing
 * any information or code changes.  All customizations should be done by
 * overriding existing or implementing new methods, properties and variables
 * in the <?= $typeTable->className ?> class.
 *
 * @package <?= Cog\Codegen\CodeGenRunner::$applicationName ?>

 * @subpackage GeneratedTypeObjects
 */
abstract class <?= $typeTable->className ?>Gen extends Base {

<?php

foreach ($typeTable->tokenArray as $key => $value) { ?>
	public const <?= strtoupper($value) ?> = <?= $key ?>;
<?php } ?>

	public const MAX_ID = <?= $typeTable->tokenArray ? max(array_keys($typeTable->tokenArray)) : 0 ?>;

	public static $NameArray = [<?php if (count($typeTable->nameArray)) { ?>

<?php foreach ($typeTable->nameArray as $key => $value) { ?>
		<?= $key ?> => '<?= $value ?>',
<?php } ?><?php \Cog\Codegen\Utils::goBack(2); ?><?php }?>

	];

	public static $TokenArray = [<?php if (count($typeTable->tokenArray)) { ?>

<?php foreach ($typeTable->tokenArray as $key => $value) { ?>
		<?= $key ?> => '<?= strtoupper($value) ?>',
<?php } ?><?php \Cog\Codegen\Utils::goBack(2); ?><?php }?>

	];

<?php if (count($typeTable->extraFieldNamesArray)) { ?>
	public static $ExtraColumnNamesArray = [
<?php foreach ($typeTable->extraFieldNamesArray as $strColName) { ?>
		'<?= $strColName ?>',
<?php } ?><?php \Cog\Codegen\Utils::goBack(2); ?>];

	public static $ExtraColumnValuesArray = [
<?php foreach ($typeTable->extraPropertyArray as $key => $arrColumns) { ?>
		<?= $key ?> => [
<?php foreach ($arrColumns as $strColName=>$strColValue) { ?>
					'<?= $strColName ?>' => '<?= str_replace("'", "\\'", $strColValue) ?>',
<?php } ?><?php \Cog\Codegen\Utils::goBack(2); ?>],
<?php } ?><?php \Cog\Codegen\Utils::goBack(2); ?>];


<?php }?>
	/**
	 * @param int $<?= $typeTable->classNameCamelCase ?>Id
	 * @return string
	 * @throws \Cog\Exceptions\CogException
	 */
	public static function ToString($<?= $typeTable->classNameCamelCase ?>Id): string {
		switch ($<?= $typeTable->classNameCamelCase ?>Id) {
<?php foreach ($typeTable->nameArray as $key => $value) { ?>
			case <?= $key ?>: return '<?= $value ?>';
<?php } ?>
			default:
				throw new \Cog\Exceptions\CogException(sprintf('Invalid <?= $typeTable->className ?>: %s', $<?= $typeTable->classNameCamelCase ?>Id));
		}
	}

	/**
	 * @param int $<?= $typeTable->classNameCamelCase ?>Id
	 * @return string
	 * @throws \Cog\Exceptions\CogException
	 */
	public static function ToToken($<?= $typeTable->classNameCamelCase ?>Id): string {
		switch ($<?= $typeTable->classNameCamelCase ?>Id) {
<?php foreach ($typeTable->tokenArray as $key => $value) { ?>
			case <?= $key ?>: return '<?= strtoupper($value) ?>';
<?php } ?>
			default:
				throw new \Cog\Exceptions\CogException(sprintf('Invalid <?= $typeTable->className ?>: %s', $<?= $typeTable->classNameCamelCase ?>Id));
		}
	}

<?php foreach ($typeTable->extraFieldNamesArray as $strColName) { ?>
	/**
	 * @param int $<?= $typeTable->classNameCamelCase ?>Id
	 * @return string
	 * @throws \Cog\Exceptions\CogException
	 */
	public static function To<?= ucfirst($strColName) ?>($<?= $typeTable->classNameCamelCase ?>Id): string {
		if (\array_key_exists($<?= $typeTable->classNameCamelCase ?>Id, self::$ExtraColumnValuesArray))
			return self::$ExtraColumnValuesArray[$<?= $typeTable->classNameCamelCase ?>Id]['<?= $strColName ?>'];
		else
			throw new \Cog\Exceptions\CogException(sprintf('Invalid <?= $typeTable->className ?>: %s', $<?= $typeTable->classNameCamelCase ?>Id));
	}

<?php } ?>
}
