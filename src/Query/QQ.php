<?php

namespace Cog\Query;

/**
 * Class QQ
 * QQ Class to simplify the creation of SQL statements.
 * @package Cog\Query
 */
class QQ {
	// QQ Condition Factories
	public static function all(): QQConditionAll {
		return new QQConditionAll(func_get_args());
	}

	public static function none(): QQConditionNone {
		return new QQConditionNone(func_get_args());
	}

	/**
	 * @param array | QQCondition ...$args
	 * @return QQConditionOr
	 */
	public static function orCondition(...$args): QQConditionOr {
		return new QQConditionOr(func_get_args());
	}

	/* array and/or parametrized list of QLoad objects*/
	/**
	 * @param array | QQCondition ...$args
	 * @return QQConditionAnd
	 */
	public static function andCondition(...$args): QQConditionAnd {
		return new QQConditionAnd(func_get_args());
	}

	public static function not(QQCondition $condition): QQConditionNot {
		return new QQConditionNot($condition);
	}

	public static function equal(QQNode $queryNode, $value): QQConditionEqual {
		return new QQConditionEqual($queryNode, $value);
	}

	public static function notEqual(QQNode $queryNode, $value): QQConditionNotEqual {
		return new QQConditionNotEqual($queryNode, $value);
	}

	public static function greaterThan(QQNode $queryNode, $value): QQConditionGreaterThan {
		return new QQConditionGreaterThan($queryNode, $value);
	}

	public static function greaterOrEqual(QQNode $queryNode, $value): QQConditionGreaterOrEqual {
		return new QQConditionGreaterOrEqual($queryNode, $value);
	}

	public static function lessThan(QQNode $queryNode, $value): QQConditionLessThan {
		return new QQConditionLessThan($queryNode, $value);
	}

	public static function lessOrEqual(QQNode $queryNode, $value): QQConditionLessOrEqual {
		return new QQConditionLessOrEqual($queryNode, $value);
	}

	public static function isNull(QQNode $queryNode): QQConditionIsNull {
		return new QQConditionIsNull($queryNode);
	}

	public static function isNotNull(QQNode $queryNode): QQConditionIsNotNull {
		return new QQConditionIsNotNull($queryNode);
	}

	public static function in(QQNode $queryNode, $valuesArray): QQConditionIn {
		return new QQConditionIn($queryNode, $valuesArray);
	}

	public static function notIn(QQNode $queryNode, $valuesArray): QQConditionNotIn {
		return new QQConditionNotIn($queryNode, $valuesArray);
	}

	public static function like(QQNode $queryNode, $value): QQConditionLike {
		return new QQConditionLike($queryNode, $value);
	}

	public static function notLike(QQNode $queryNode, $value): QQConditionNotLike {
		return new QQConditionNotLike($queryNode, $value);
	}

	public static function between(QQNode $queryNode, $minValue, $maxValue): QQConditionBetween {
		return new QQConditionBetween($queryNode, $minValue, $maxValue);
	}

	public static function notBetween(QQNode $queryNode, $minValue, $maxValue): QQConditionNotBetween {
		return new QQConditionNotBetween($queryNode, $minValue, $maxValue);
	}

	public static function exists(QQSubQueryNode $subQueryDefinition): QQConditionExists {
		return new QQConditionExists($subQueryDefinition);
	}

	public static function notExists(QQSubQueryNode $subQueryDefinition): QQConditionNotExists {
		return new QQConditionNotExists($subQueryDefinition);
	}

	// QQ Condition Shortcuts
	public static function _(QQNode $queryNode, $symbol, $value, $valueTwo = null): QQCondition {
		try {
			switch (strtolower(trim($symbol))) {
				case '=':
					return self::equal($queryNode, $value);
				case '!=':
					return self::notEqual($queryNode, $value);
				case '>':
					return self::greaterThan($queryNode, $value);
				case '<':
					return self::lessThan($queryNode, $value);
				case '>=':
					return self::greaterOrEqual($queryNode, $value);
				case '<=':
					return self::lessOrEqual($queryNode, $value);
				case 'in':
					return self::in($queryNode, $value);
				case 'not in':
					return self::notIn($queryNode, $value);
				case 'like':
					return self::like($queryNode, $value);
				case 'not like':
					return self::notLike($queryNode, $value);
				case 'is null':
					return self::isNull($queryNode);
				case 'is not null':
					return self::isNotNull($queryNode);
				case 'between':
					return self::between($queryNode, $value, $valueTwo);
				case 'not between':
					return self::notBetween($queryNode, $value, $valueTwo);
				default:
					throw new \Cog\Exceptions\CogException('Unknown Query Comparison Operation: ' . $symbol, 0);
			}
		} catch (\Cog\Exceptions\CogException $exception) {
			$exception->incrementOffset();
			throw $exception;
		}
	}

	// QQSubQuery Factories
	public static function subSql($sql, $parentQueryNodes = null): QQSubQuerySqlNode {
		$parentQueryNodeArray = func_get_args();
		return new QQSubQuerySqlNode($sql, $parentQueryNodeArray);
	}

	public static function virtual($name, ?QQSubQueryNode $subQueryDefinition = null): QQVirtualNode {
		return new QQVirtualNode($name, $subQueryDefinition);
	}

	// QQClause Factories
	public static function clause( /* parametrized list of QQClause objects */): array {
		$clauseArray = [];

		foreach (func_get_args() as $clause) {
			if ($clause) {
				if (!($clause instanceof QQClause)) {
					throw new \Cog\Exceptions\CogException('Non Cog\Query\QQClause object was passed in to Cog\Query\QQ::Clause');
				}

				$clauseArray[] = $clause;
			}
		}

		return $clauseArray;
	}

	public static function orderBy(/* array and/or parametrized list of QQNode objects*/): QQOrderBy {
		return new QQOrderBy(func_get_args());
	}

	public static function groupBy(/* array and/or parametrized list of QQNode objects*/): QQGroupBy {
		return new QQGroupBy(func_get_args());
	}

	public static function having(QQSubQuerySqlNode $node): QQHavingClause {
		return new QQHavingClause($node);
	}

	public static function count($node, $attributeName): QQCount {
		return new QQCount($node, $attributeName);
	}

	public static function sum($node, $attributeName): QQSum {
		return new QQSum($node, $attributeName);
	}

	public static function minimum($node, $attributeName): QQMinimum {
		return new QQMinimum($node, $attributeName);
	}

	public static function maximum($node, $attributeName): QQMaximum {
		return new QQMaximum($node, $attributeName);
	}

	public static function average($node, $attributeName): QQAverage {
		return new QQAverage($node, $attributeName);
	}

	public static function expand($node, ?QQCondition $joinCondition = null, ?QQSelect $select = null): QQExpand|QQExpandVirtualNode {
		//if (gettype($node) == 'string')
		//  return new Cog\Query\QQExpandVirtualNode(new Cog\Query\QQVirtualNode($node));

		if ($node instanceof QQVirtualNode) {
			return new QQExpandVirtualNode($node);
		}

		return new QQExpand($node, $joinCondition, $select);
	}

	public static function expandAsArray($node, ?QQSelect $select = null): QQExpandAsArray {
		return new QQExpandAsArray($node, $select);
	}

	public static function select(/* array and/or parametrized list of Cog\Query\QQNode objects*/): QQSelect {
		return new QQSelect(func_get_args());
	}

	public static function limitInfo(int $maxRowCount, int $offset = 0): QQLimitInfo {
		return new QQLimitInfo($maxRowCount, $offset);
	}

	public static function distinct(): QQDistinct {
		return new QQDistinct();
	}

	/**
	 * @param QQClause[]|QQClause|null $clauses
	 * @return QQSelect Cog\Query\QQSelect clause containing all the nodes from all the QQSelect clauses
	 *          from $clauses, or null if $clauses contains no QQSelect clauses
	 */
	public static function extractSelectClause($clauses): ?QQSelect {
		if ($clauses instanceof QQSelect) {
			return $clauses;
		}

		if(is_array($clauses)) {
			$hasSelects = false;
			$select = self::select();
			foreach($clauses as $clause) {
				if ($clause instanceof QQSelect) {
					$hasSelects = true;
					$select->merge($clause);
				}
			}

			return $hasSelects ? $select : null;
		}

		return null;
	}

	/**
	 * Aliased QQ Node
	 * Returns the supplied node object, after setting its alias to the value supplied
	 *
	 * @param QQBaseNode $node The node object to set alias on
	 * @param string $alias The alias to set
	 * @return QQBaseNode The same node that was passed in, but with the alias set
	 *
	 */
	public static function alias(QQBaseNode $node, $alias): QQBaseNode {
		$node->alias = $alias;
		return $node;
	}

	// NamedValue Cog\Query\QQNode
	public static function namedValue($name): QQNamedValue {
		return new QQNamedValue($name);
	}

	/**
	 * @param $conditions
	 * @return QQConditionAll|QQConditionAnd|QQCondition
	 */
	public static function conditionsArrayHelper($conditions): QQConditionAll|QQCondition|QQConditionAnd {
		return match (count($conditions)) {
			0 => self::all(),
			1 => array_pop($conditions),
			default => self::andCondition($conditions),
		};
	}
}
