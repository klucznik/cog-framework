<?php
/** @var \Cog\Codegen\CodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
?><template OverwriteFlag="false" DocrootFlag="true" DirectorySuffix="" TargetDirectory="/app/Data" TargetFileName="<?= $table->className ?>.php"/>
<?php print('<?php' . "\n"); ?>

namespace <?= $codegen->namespaceData ?>;

use Generated\Data\<?= $table->className ?>Gen;
use Cog\Exceptions\CogException;
use Cog\Exceptions\InvalidCastException;

/**
 * The <?= $table->className ?> class defined here contains any
 * customized code for the <?= $table->className ?> class in the
 * Object Relational Model.  It represents the "<?= $table->name ?>" table
 * in the database, and extends from the code generated abstract <?= $table->className ?>Gen
 * class, which contains all the basic CRUD-type functionality as well as
 * basic methods to handle relationships and index-based loading.
 *
 * @package <?= Cog\Codegen\CodeGenRunner::$applicationName ?>

 * @subpackage DataObjects
 *
 */
class <?= $table->className ?> extends <?= $table->className ?>Gen {
	/**
	 * Default "to string" handler
	 * Allows pages to _p()/echo()/print() this object, and to define the default
	 * way this object would be outputted.
	 *
	 * Can also be called directly via $obj<?= $table->className ?>->__toString().
	 *
	 * @return string a nicely formatted string representation of this object
	 */
	public function __toString() {
		return sprintf('<?= $table->className ?> Object <?php foreach ($table->primaryKeyColumnArray as $column) { ?>%s - <?php } ?><?php \Cog\Codegen\Utils::goBack(3); ?>', <?php foreach ($table->primaryKeyColumnArray as $column) { ?> $this-><?= $column->variableName ?>, <?php } ?><?php \Cog\Codegen\Utils::goBack(2); ?>);
	}


	<?php include __DIR__ . '/example_load_methods.tpl.php'; ?>



	<?php include __DIR__ . '/example_properties.tpl.php'; ?>



	<?php include __DIR__ . '/example_initialization.tpl.php'; ?>
}
