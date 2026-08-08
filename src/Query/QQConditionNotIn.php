<?php

namespace Cog\Query;

class QQConditionNotIn extends QQConditionIn {

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$mixOperand = $this->mixOperand;

		if ($mixOperand instanceof QQNamedValue) {
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' NOT IN (' . $mixOperand->parameter() . ')');
		} else if ($mixOperand instanceof QQSubQueryNode) {
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' NOT IN ' . $mixOperand->getColumnAlias($queryBuilder));
		} else {
			$parameters = [];
			foreach ($mixOperand as $mixParameter) {
				$parameters[] = $queryBuilder->database->sqlVariable($mixParameter);
			}
			if (\count($parameters)) {
				$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' NOT IN (' . implode(',', $parameters) . ')');
			} else {
				$queryBuilder->addWhereItem('1=1');
			}
		}
	}
}
