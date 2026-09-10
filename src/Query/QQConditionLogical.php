<?php

namespace Cog\Query;

use Cog;
use Cog\Exceptions\CogException;

abstract class QQConditionLogical extends QQCondition {

	/** @var QQCondition[] */
	protected array $conditionArray;

	public function __construct($parameterArray) {
		$this->conditionArray = $this->collapseConditions($parameterArray);
	}

	/**
	 * @param $parameterArray
	 * @return QQCondition[]
	 * @throws CogException
	 */
	protected function collapseConditions($parameterArray): array {

		$conditionArray = [];
		foreach ($parameterArray as $parameter) {
			if (is_array($parameter)) {
				$conditionArray = array_merge($conditionArray, $parameter);
			} else {
				$conditionArray[] = $parameter;
			}
		}

		foreach ($conditionArray as $condition) {
			if (!($condition instanceof QQCondition)) {
				throw new CogException('Logical Or/And clause parameters must all be QQCondition objects', 3);
			}
		}

		if (count($conditionArray)) {
			return $conditionArray;
		}

		throw new CogException('No parameters passed in to logical Or/And clause', 3);
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$length = count($this->conditionArray);

		if ($length) {
			$queryBuilder->addWhereItem('(');

			for ($i = 0; $i < $length; $i++) {
				if (!($this->conditionArray[$i] instanceof QQCondition)) {
					throw new CogException($this->operator . ' clause has elements that are not Conditions');
				}

				$this->conditionArray[$i]->updateQueryBuilder($queryBuilder);

				if (($i + 1) !== $length) {
					$queryBuilder->addWhereItem($this->operator);
				}
			}

			$queryBuilder->addWhereItem(')');
		}
	}
}
