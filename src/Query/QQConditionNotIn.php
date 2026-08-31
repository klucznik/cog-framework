<?php

namespace Cog\Query;

class QQConditionNotIn extends QQConditionIn {

	/** @inheritdoc */
	public function updateQueryBuilder(QueryBuilder $queryBuilder): void {
		$operand = $this->operand;

		if ($operand instanceof QQNamedValue) {
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' NOT IN (' . $operand->parameter() . ')');
		} else if ($operand instanceof QQSubQueryNode) {
			$queryBuilder->addWhereItem($this->queryNode->getColumnAlias($queryBuilder) . ' NOT IN ' . $operand->getColumnAlias($queryBuilder));
		} else {
			$parameters = [];
			foreach ($operand as $mixParameter) {
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
