<?php
/** @var \Cog\Codegen\CodeGen $codegen  */
/** @var \Cog\Codegen\TypeTable $typeTable  */
?><template OverwriteFlag="false" DocrootFlag="true" DirectorySuffix="" TargetDirectory="/app/Type" TargetFileName="<?= $typeTable->className ?>.php"/>
<?php print('<?php' . "\n"); ?>

namespace <?= $codegen->namespaceType ?>;

use Generated\Type\<?= $typeTable->className ?>Gen;

/**
 * The <?= $typeTable->className ?> class defined here contains any
 * customized code for the <?= $typeTable->className ?> enumerated type.
 *
 * It represents the enumerated values found in the "<?= $typeTable->name ?>" table in the database,
 * and extends from the code generated abstract <?= $typeTable->className ?>Gen
 * class, which contains all the values extracted from the database.
 *
 * Type classes which are generally used to attach a type to data object.
 * However, they may be used as simple database independent enumerated type.
 *
 * @package <?= Cog\Codegen\CodeGenRunner::$applicationName ?>

 * @subpackage TypeObjects
 */
abstract class <?= $typeTable->className ?> extends <?= $typeTable->className ?>Gen {}
