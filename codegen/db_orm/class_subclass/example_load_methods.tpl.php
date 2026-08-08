// Override or create new load/count methods
	// (For obvious reasons, these methods are commented out...
	// but feel free to use these as a starting point)
/*
	public static function loadArrayBySample($strParam1, $intParam2, $optionalClauses = null) {
		// This will return an array of <?= $table->className ?> objects
		return <?= $table->className ?>::queryArray(
			QQ::andCondition(
				QQ::equal((new \Generated\Node\QQNode<?= $table->className ?>)->Param1, $strParam1),
				QQ::greaterThan((new \Generated\Node\QQNode<?= $table->className ?>)->Param2, $intParam2)
			),
			$optionalClauses
		);
	}

	public static function loadBySample($strParam1, $intParam2, $optionalClauses = null) {
		// This will return a single <?= $table->className ?> object
		return <?= $table->className ?>::querySingle(
			QQ::andCondition(
				QQ::equal((new \Generated\Node\QQNode<?= $table->className ?>)->Param1, $strParam1),
				QQ::greaterThan((new \Generated\Node\QQNode<?= $table->className ?>)->Param2, $intParam2)
			),
			$optionalClauses
		);
	}

	public static function countBySample($strParam1, $intParam2, $optionalClauses = null) {
		// This will return a count of <?= $table->className ?> objects
		return <?= $table->className ?>::queryCount(
			QQ::andCondition(
				QQ::equal((new \Generated\Node\QQNode<?= $table->className ?>)->Param1, $strParam1),
				QQ::equal((new \Generated\Node\QQNode<?= $table->className ?>)->Param2, $intParam2)
			),
			$optionalClauses
		);
	}

	public static function loadArrayBySample($strParam1, $intParam2, $optionalClauses) {
		// Performing the load manually (instead of using Object Oriented Query)

		// Get the Database Object for this Class
		$database = <?= $table->className ?>::getDatabase();

		// Properly Escape All Input Parameters using database->sqlVariable()
		$strParam1 = $database->sqlVariable($strParam1);
		$intParam2 = $database->sqlVariable($intParam2);

		// Setup the SQL Query
		$query = sprintf('
			SELECT
				<?= $escapeIdentifierBegin ?><?= $table->name ?><?= $escapeIdentifierEnd ?>.*
			FROM
				<?= $escapeIdentifierBegin ?><?= $table->name ?><?= $escapeIdentifierEnd ?> AS <?= $escapeIdentifierBegin ?><?= $table->name ?><?= $escapeIdentifierEnd ?>

			WHERE
				param_1 = %s AND
				param_2 < %s',
			$strParam1, $intParam2);

		// Perform the Query and Instantiate the Result
		$dbResult = $database->query($query);
		return <?= $table->className ?>::instantiateDbResult($dbResult);
	}
*/
