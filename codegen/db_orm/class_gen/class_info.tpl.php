<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>///////////////////////////////
	// METHODS TO EXTRACT INFO ABOUT THE CLASS
	///////////////////////////////

	/**
	 * Static method to retrieve the Database object that owns this class.
	 * @return string Name of the table from which this class has been created.
	 */
	public static function getTableName(): string {
		return '<?= $table->name ?>';
	}

	/**
	 * Static method to retrieve the Table name from which this class has been created.
	 * @return string Name of the table from which this class has been created.
	 */
	public static function getDatabaseName(): string {
		return Database::$databases[<?= $table->className ?>::getDatabaseIndex()]->database;
	}

	/**
	 * Static method to retrieve the Database index in the configuration.inc.php file.
	 * This can be useful when there are two databases of the same name which create
	 * confusion for the developer. There are no internal uses of this function but are
	 * here to help retrieve info if need be!
	 * @return int position or index of the database in the config file.
	 */
	public static function getDatabaseIndex(): int {
		return <?= $codegen->databaseIndex ?>;
	}