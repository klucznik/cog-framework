<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */
?>
////////////////////////////////////////
	// METHODS for JSON Object Translation
	////////////////////////////////////////

	/**
	 * this function is required for objects that implement the IteratorAggregate interface
	 * Datetime columns are emitted as ISO-8601 UTC strings, so the iterator can be handed to json_encode as is
	 * @return ArrayIterator
	*/
	public function getIterator(): ArrayIterator {
<?php foreach ($table->columnArray as $column) { ?>
<?php if ($column->variableType === \Cog\Type::DATETIME) { ?>
		$iArray['<?= $column->propertyName ?>'] = Utils::dateTimeToJson($this-><?= $column->variableName ?>);
<?php } else { ?>
		$iArray['<?= $column->propertyName ?>'] = $this-><?= $column->variableName ?>;
<?php } ?>
<?php } ?>
		return new ArrayIterator($iArray);
	}

	public function getJson(): ?string {
		$toReturn = null;

		try {
			$toReturn = json_encode($this->getIterator(), JSON_THROW_ON_ERROR);
			if ($toReturn === false) {
				$toReturn = null;
			}
		} catch (JsonException) {}

		return $toReturn;
	}