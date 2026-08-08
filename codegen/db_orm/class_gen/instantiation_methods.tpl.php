<?php
/** @var \Cog\Codegen\DatabaseCodeGen $codegen */
/** @var \Cog\Codegen\Table $table */
/** @var string $escapeIdentifierBegin */
/** @var string $escapeIdentifierEnd */

// Preliminary calculations and helper routines here
$blnImmediateExpansions = $table->hasImmediateArrayExpansions();
$blnExtendedExpansions = $table->hasExtendedArrayExpansions($codegen);

if (count($table->primaryKeyColumnArray) > 1 && $blnImmediateExpansions) {
	throw new \Cog\Exceptions\CogException('Multi-key table with array expansion not supported.');
}

?>///////////////////////////////
	// INSTANTIATION-RELATED METHODS
	///////////////////////////////

	/**
	 * Do a possible array expansion on the given node. If the node is an ExpandAsArray node,
	 * it will add to the corresponding array in the object. Otherwise, it will follow the node
	 * so that any leaf expansions can be handled.
	 *
	 * @param RowBase $dbRow
	 * @param string $aliasPrefix
	 * @param QQBaseNode $node
	 * @param array $previousItemArray
	 * @param string[] $columnAliasArray
	 * @return bool
	 * @throws CogException
	 */

	public static function expandArray(RowBase $dbRow, string $aliasPrefix, QQBaseNode $node, array $previousItemArray, array $columnAliasArray): bool {
		if (!$node->childNodeArray) {
			return false;
		}

		$alias = $aliasPrefix . '<?= $table->primaryKeyColumnArray[0]->name ?>';
		$columnAlias = !empty($columnAliasArray[$alias]) ? $columnAliasArray[$alias] : $alias;
		$expanded = false;

		foreach ($previousItemArray as $previousItem) {
			if ($previousItem-><?= $table->primaryKeyColumnArray[0]->variableName ?> !== $dbRow->getColumn($columnAlias, '<?= $table->primaryKeyColumnArray[0]->dbType ?>')) {
				continue;
			}

			foreach ($node->childNodeArray as $childNode) {
				$propNameUppercase = $childNode->propertyNameUppercase;
				$classNameQualified = $childNode->classNameQualified;
				$expanded = false;
				$longAlias = $childNode->extendedAlias();

				if ($childNode->expandAsArray) {
					$variableName = '_obj' . $propNameUppercase . 'Array';

					if ($previousItem->$variableName === null) {
						$previousItem->$variableName = [];
					}

					if (count($previousItem->$variableName)) {
						$previousChildItems = $previousItem->$variableName;
						if ($childNode->type === 'association') {
							$childNode = $childNode->firstChild();
						}
						$nextAlias = $childNode->extendedAlias() . '__';

						$childItem = call_user_func([$classNameQualified, 'instantiateDbRow'], $dbRow, $nextAlias, $childNode, $previousChildItems, $columnAliasArray);
						if ($childItem) {
							$previousItem->{$variableName}[] = $childItem;
							$expanded = true;
						} elseif ($childItem === false) {
							$expanded = true;
						}
					}
				} else {
					// Follow single node if keys match
					$nodeType = $childNode->type;
					if ($nodeType === 'reverse_reference' || $nodeType === 'association') {
						$variableName = '_obj' . $propNameUppercase;
					} else {
						$variableName = 'obj' . $propNameUppercase;
					}

					if ($previousItem->$variableName === null) {
						return false;
					}

					$previousChildItems = [$previousItem->$variableName];
					$result = call_user_func([$classNameQualified, 'expandArray'], $dbRow, $longAlias . '__', $childNode, $previousChildItems, $columnAliasArray);

					if ($result) {
						$expanded = true;
					}
				}
			}
		}
		return $expanded;
	}

	/**
	 * Instantiate a <?= $table->className ?> from a Database Row.
	 * Takes in an optional strAliasPrefix, used in case another Object::instantiateDbRow
	 * is calling this <?= $table->className ?>::instantiateDbRow in order to perform
	 * early binding on referenced objects.
	 * @param RowBase|null $dbRow
	 * @param string $aliasPrefix
	 * @param QQBaseNode|null $expandAsArrayNode
	 * @param array|null $previousItemArray
	 * @param string[] $columnAliasArray
	 * @return <?= $table->className ?>|false|null Either <?= $table->className ?>, or false to indicate the db row was used in an expansion, or null to indicate that this leaf is a duplicate.
	 * @throws CogException
	*/
	public static function instantiateDbRow(?RowBase $dbRow = null, string $aliasPrefix = '', ?QQBaseNode $expandAsArrayNode = null, ?array $previousItemArray = null, array $columnAliasArray = []): <?= $table->className ?>|bool|null {
		// If blank row, return null
		if (!$dbRow) {
			return null;
		}

<?php if ($table->primaryKeyColumnArray)  { // Optimize top level accesses?>
		if (empty($aliasPrefix) && $previousItemArray) {
			$columnAlias = !empty($columnAliasArray['<?= $table->primaryKeyColumnArray[0]->name ?>']) ? $columnAliasArray['<?= $table->primaryKeyColumnArray[0]->name ?>'] : '<?= $table->primaryKeyColumnArray[0]->name ?>';
			$key = $dbRow->getColumn($columnAlias, '<?= $table->primaryKeyColumnArray[0]->dbType ?>');
			$previousItemArray = (!empty($previousItemArray[$key]) ? $previousItemArray[$key] : null);
		}
<?php } ?>

<?php
if ($blnImmediateExpansions || $blnExtendedExpansions) {
?>
		// See if we're doing an array expansion on the previous item
		if ($expandAsArrayNode && is_array($previousItemArray) && count($previousItemArray)) {
			if (<?= $table->className ?>::expandArray($dbRow, $aliasPrefix, $expandAsArrayNode, $previousItemArray, $columnAliasArray)) {
				return false; // db row was used but no new object was created
			}
		}
<?php
} // if
?>

		// Create a new instance of the <?= $table->className ?> object
		$toReturn = new <?= $table->className ?>();
		$toReturn->__blnRestored = true;

<?php foreach ($table->columnArray as $column) { ?>
		$alias = $aliasPrefix . '<?= $column->name ?>';
		$aliasName = !empty($columnAliasArray[$alias]) ? $columnAliasArray[$alias] : $alias;
		$toReturn-><?= $column->variableName ?> = $dbRow->getColumn($aliasName, '<?= $column->dbType ?>');
<?php if ($column->primaryKey && (!$column->identity)) { ?>
		$toReturn->__<?= $column->variableName ?> = $dbRow->getColumn($aliasName, '<?= $column->dbType ?>');
<?php } ?>
<?php } ?>

		if (is_array($previousItemArray)) {
			foreach ($previousItemArray as $previousItem) {
<?php foreach ($table->primaryKeyColumnArray as $col) { ?>
				if ($toReturn-><?= $col->propertyName ?> !== $previousItem-><?= $col->propertyName ?>) {
					continue;
				}
<?php } ?>
				// this is a duplicate leaf in a complex join
				return null; // indicates no object created and the db row has not been used
			}
		}

		// Instantiate Virtual Attributes
		$virtualPrefix = $aliasPrefix . '__';
		$virtualPrefixLength = strlen($virtualPrefix);
		foreach ($dbRow->getColumnNameArray() as $columnName => $value) {
			if (str_starts_with($columnName, $virtualPrefix)) {
				$toReturn->__strVirtualAttributeArray[substr($columnName, $virtualPrefixLength)] = $value;
			}
		}

		// Prepare to Check for Early/Virtual Binding
		$expansionAliasArray = [];
		if ($expandAsArrayNode) {
			$expansionAliasArray = $expandAsArrayNode->childNodeArray;
		}

		if (!$aliasPrefix) {
			$aliasPrefix = '<?= $table->name ?>__';
		}

<?php foreach ($table->columnArray as $column) { ?>
<?php if ($column->reference && !$column->reference->isType) { ?>
		// Check for <?= $column->reference->propertyName ?> Early Binding
		$alias = $aliasPrefix . '<?= $column->name ?>__<?= $codegen->getTable($column->reference->table)->primaryKeyColumnArray[0]->name ?>';
		$aliasName = !empty($columnAliasArray[$alias]) ? $columnAliasArray[$alias] : $alias;
		if (null !== $dbRow->getColumn($aliasName)) {
			$expansionNode = (empty($expansionAliasArray['<?= $column->name ?>']) ? null : $expansionAliasArray['<?= $column->name ?>']);
			$toReturn-><?= $column->reference->variableName ?> = <?= $column->reference->variableType ?>::instantiateDbRow($dbRow, $aliasPrefix . '<?= $column->name ?>__', $expansionNode, null, $columnAliasArray);
		}
<?php } ?>
<?php } ?>

<?php foreach ($table->reverseReferenceArray as $reference) { ?><?php if ($reference->unique) { ?>
		// Check for <?= $reference->objectDescription ?> Unique ReverseReference Binding
		$alias = $aliasPrefix . '<?= strtolower($reference->objectDescription) ?>__<?= $codegen->getTable($reference->table)->primaryKeyColumnArray[0]->name ?>';
		$aliasName = !empty($columnAliasArray[$alias]) ? $columnAliasArray[$alias] : $alias;
		if ($dbRow->ColumnExists($aliasName)) {
			if (null !== $dbRow->getColumn($aliasName)) {
				$expansionNode = (empty($expansionAliasArray['<?= strtolower($reference->objectDescription) ?>']) ? null : $expansionAliasArray['<?= strtolower($reference->objectDescription) ?>']);
				$toReturn->obj<?= $reference->objectDescription ?> = <?= $reference->variableType ?>::instantiateDbRow($dbRow, $aliasPrefix . '<?= strtolower($reference->objectDescription) ?>__', $expansionNode, null, $columnAliasArray);
			}
			else {
				// We ATTEMPTED to do an Early Bind but the Object Doesn't Exist
				// Let's set to FALSE so that the object knows not to try and re-query again
				$toReturn->obj<?= $reference->objectDescription ?> = false;
			}
		}

<?php } ?><?php } ?>

<?php foreach ($table->manyToManyReferenceArray as $reference) { ?>
		// Check for <?= $reference->objectDescription ?> Virtual Binding
		$alias = $aliasPrefix . '<?= strtolower($reference->objectDescription) ?>__<?= $reference->oppositeColumn ?>__<?= $codegen->getTable($reference->associatedTable)->primaryKeyColumnArray[0]->name ?>';
		$aliasName = !empty($columnAliasArray[$alias]) ? $columnAliasArray[$alias] : $alias;
		$expansionNode = (empty($expansionAliasArray['<?= strtolower($reference->objectDescription) ?>']) ? null : $expansionAliasArray['<?= strtolower($reference->objectDescription) ?>']);
		$expanded = ($expansionNode && $expansionNode->expandAsArray);
		if ($expanded && null === $toReturn->_obj<?= $reference->objectDescriptionUppercase ?>Array) {
			$toReturn->_obj<?= $reference->objectDescriptionUppercase ?>Array = [];
		}
		if (null !== $dbRow->getColumn($aliasName)) {
			if ($expanded) {
				$toReturn->_obj<?= $reference->objectDescriptionUppercase ?>Array[] = <?= $reference->variableType ?>::instantiateDbRow($dbRow, $aliasPrefix . '<?= strtolower($reference->objectDescription) ?>__<?= $reference->oppositeColumn ?>__', $expansionNode, null, $columnAliasArray);
			} elseif (null === $toReturn->_obj<?= $reference->objectDescriptionUppercase ?>) {
				$toReturn->_obj<?= $reference->objectDescriptionUppercase ?> = <?= $reference->variableType ?>::instantiateDbRow($dbRow, $aliasPrefix . '<?= strtolower($reference->objectDescription) ?>__<?= $reference->oppositeColumn ?>__', $expansionNode, null, $columnAliasArray);
			}
		}

<?php } ?>
<?php foreach ($table->reverseReferenceArray as $reference) { ?><?php if (!$reference->unique) { ?>
		// Check for <?= $reference->objectDescription ?> Virtual Binding
		$alias = $aliasPrefix . '<?= strtolower($reference->objectDescription) ?>__<?= $codegen->getTable($reference->table)->primaryKeyColumnArray[0]->name ?>';
		$aliasName = !empty($columnAliasArray[$alias]) ? $columnAliasArray[$alias] : $alias;
		$expansionNode = (empty($expansionAliasArray['<?= strtolower($reference->objectDescription) ?>']) ? null : $expansionAliasArray['<?= strtolower($reference->objectDescription) ?>']);
		$expanded = ($expansionNode && $expansionNode->expandAsArray);
		if ($expanded && null === $toReturn->_obj<?= $reference->objectDescriptionUppercase ?>Array) {
			$toReturn->_obj<?= $reference->objectDescriptionUppercase ?>Array = [];
		}
		if (null !== $dbRow->getColumn($aliasName)) {
			if ($expanded) {
				$toReturn->_obj<?= $reference->objectDescriptionUppercase ?>Array[] = <?= $reference->variableType ?>::instantiateDbRow($dbRow, $aliasPrefix . '<?= strtolower($reference->objectDescription) ?>__', $expansionNode, null, $columnAliasArray);
			} elseif (null === $toReturn->_obj<?= $reference->objectDescriptionUppercase ?>) {
				$toReturn->_obj<?= $reference->objectDescriptionUppercase ?> = <?= $reference->variableType ?>::instantiateDbRow($dbRow, $aliasPrefix . '<?= strtolower($reference->objectDescription) ?>__', $expansionNode, null, $columnAliasArray);
			}
		}

<?php } ?><?php } ?>
		return $toReturn;
	}

	/**
	 * Instantiate an array of <?= $table->classNamePlural ?> from a Database Result
	 * @param ResultBase|null $dbResult
	 * @param null|QQBaseNode $expandAsArrayNode
	 * @param null|string[] $columnAliasArray
	 * @return <?= $table->className ?>[]
	 * @throws CogException
	 */
	public static function instantiateDbResult(?ResultBase $dbResult = null, ?QQBaseNode $expandAsArrayNode = null, ?array $columnAliasArray = null): array {
		$toReturn = [];

		if (!$columnAliasArray) {
			$columnAliasArray = [];
		}

		// If blank resultset, then return empty array
		if (!$dbResult) {
			return $toReturn;
		}

		// Load up the return array with each row
		if ($expandAsArrayNode) {
			$previousItemArray = [];
			while ($dbRow = $dbResult->getNextRow()) {
				$item = <?= $table->className ?>::instantiateDbRow($dbRow, '', $expandAsArrayNode, $previousItemArray, $columnAliasArray);
				if ($item) {
					$toReturn[] = $item;
<?php if ($table->primaryKeyColumnArray)  {?>
					$previousItemArray[$item-><?= $table->primaryKeyColumnArray[0]->variableName ?>][] = $item;
<?php } else { ?>
					$previousItemArray[] = $item;

<?php } ?>
				}
			}
		} else {
			while ($dbRow = $dbResult->getNextRow()) {
				$toReturn[] = <?= $table->className ?>::instantiateDbRow($dbRow, '', null, null, $columnAliasArray);
			}
		}

		return $toReturn;
	}


	/**
	 * Instantiate a single <?= $table->className ?> object from a query cursor (e.g. a DB ResultSet).
	 * Cursor is automatically moved to the "next row" of the result set.
	 * Will return NULL if no cursor or if the cursor has no more rows in the resultset.
	 * @param ResultBase|null $dbResult cursor resource
	 * @return <?= $table->className ?>|null next row resulting from the query
	 * @throws CogException
	 */
	public static function instantiateCursor(?ResultBase $dbResult = null): ?<?= $table->className ?> {
		// If blank resultset, then return empty result
		if (!$dbResult) {
			return null;
		}

		// If empty resultset, then return empty result
		$dbRow = $dbResult->getNextRow();
		if (!$dbRow) {
			return null;
		}

		// We need the Column Aliases
		$columnAliasArray = $dbResult->queryBuilder->columnAliasArray;
		if (!$columnAliasArray) {
			$columnAliasArray = [];
		}

		// Pull Expansions
		$expandAsArrayNode = $dbResult->queryBuilder->expandAsArrayNode;
		if (!empty($expandAsArrayNode)) {
			throw new CogException('Cannot use instantiateCursor with expandAsArray');
		}

		// Load up the return result with a row and return it
		return <?= $table->className ?>::instantiateDbRow($dbRow, '', null, null, $columnAliasArray);
	}
