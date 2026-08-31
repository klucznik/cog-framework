<?php

namespace Cog\Query;

use Cog;

abstract class QQConditionLogical extends QQCondition {

	/** @var QQCondition[] */
	protected array $conditionArray;

	public function __construct($mixParameterArray) {
		$this->conditionArray = $this->collapseConditions($mixParameterArray);
	}

	/**
	 * @param $mixParameterArray
	 * @return QQCondition[]
	 * @throws \Cog\Exceptions\CogException
	 */
	protected function collapseConditions($mixParameterArray): array {

		$conditionArray = [];
		foreach ($mixParameterArray as $mixParameter) {
			if (is_array($mixParameter)) {
				$conditionArray = array_merge($conditionArray, $mixParameter);
			} else {
				$conditionArray[] = $mixParameter;
			}
		}

		foreach ($conditionArray as $condition) {
			if (!($condition instanceof QQCondition)) {
				throw new Cog\Exceptions\CogException('Logical Or/And clause parameters must all be QQCondition objects', 3);
			}
		}

		if (count($conditionArray)) {
			return $conditionArray;
		}

		throw new Cog\Exceptions\CogException('No parameters passed in to logical Or/And clause', 3);
	}

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$length = count($this->conditionArray);

		if ($length) {
			$queryBuilder->addWhereItem('(');

			for ($i = 0; $i < $length; $i++) {
				if (!($this->conditionArray[$i] instanceof QQCondition)) {
					throw new Cog\Exceptions\CogException($this->operator . ' clause has elements that are not Conditions');
				}

				try {
					$this->conditionArray[$i]->updateQueryBuilder($queryBuilder);
				} catch (Cog\Exceptions\CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}

				if (($i + 1) !== $length) {
					$queryBuilder->addWhereItem($this->operator);
				}
			}

			$queryBuilder->addWhereItem(')');
		}
	}
}
