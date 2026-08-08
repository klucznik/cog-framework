<?php

namespace Cog\Test;

use Cog\Exceptions\CogException;
use Cog\Exceptions\UndefinedPropertyException;
use Cog\Query\QQ;
use Cog\Query\QueryBuilder;
use Generated\Node\QQNodeObj;

/**
 * The builder on its own, driven directly rather than through QQ nodes.
 *
 * Statements are compared with whitespace collapsed - the builder formats with
 * \r\n and four-space indents, and none of that is what these tests are about.
 */
class TestQueryBuilder extends QueryTestCase {

	private function builder(string $table = 'person'): QueryBuilder {
		$queryBuilder = new QueryBuilder($this->database, $table);
		$queryBuilder->addSelectItem($table, 'id', 'id');
		$queryBuilder->addFromItem($table);

		return $queryBuilder;
	}

	private static function normalize(string $sql): string {
		return trim(preg_replace('/\s+/', ' ', $sql));
	}

	private function assertStatement(string $expected, QueryBuilder $queryBuilder): void {
		$this->assertEquals($expected, self::normalize($queryBuilder->getStatement()));
	}

	//////////////////////////////
	// Aliases
	//////////////////////////////

	public function testTableAliasesAreAllocatedInOrder() {
		$queryBuilder = $this->builder();

		$this->assertEquals('t0', $queryBuilder->getTableAlias('person'));
		$this->assertEquals('t1', $queryBuilder->getTableAlias('obj'));
		$this->assertEquals('t2', $queryBuilder->getTableAlias('asset'));

		// Asking again returns the alias already handed out
		$this->assertEquals('t0', $queryBuilder->getTableAlias('person'));
		$this->assertEquals('t1', $queryBuilder->getTableAlias('obj'));
	}

	public function testColumnAliasesAreAllocatedInOrder() {
		$queryBuilder = $this->builder();
		$queryBuilder->addSelectItem('person', 'name', 'name');
		$queryBuilder->addSelectItem('obj', 'id', 'obj__id');

		$this->assertEquals(['id' => 'a0', 'name' => 'a1', 'obj__id' => 'a2'], $queryBuilder->columnAliasArray);
	}

	/** Selecting the same full alias twice reuses its column alias rather than burning a new one. */
	public function testRepeatedSelectItemKeepsItsAlias() {
		$queryBuilder = $this->builder();
		$queryBuilder->addSelectItem('person', 'name', 'name');
		$queryBuilder->addSelectItem('person', 'name', 'name');
		$queryBuilder->addSelectItem('person', 'email', 'email');

		$this->assertEquals(['id' => 'a0', 'name' => 'a1', 'email' => 'a2'], $queryBuilder->columnAliasArray);
		$this->assertEquals(1, substr_count($queryBuilder->getStatement(), '`name`'));
	}

	//////////////////////////////
	// Statement shapes
	//////////////////////////////

	public function testPlainStatement() {
		$this->assertStatement('SELECT `t0`.`id` AS `a0` FROM `person` AS `t0`', $this->builder());
	}

	public function testDistinct() {
		$queryBuilder = $this->builder();
		$queryBuilder->setDistinctFlag();

		$this->assertStatement('SELECT DISTINCT `t0`.`id` AS `a0` FROM `person` AS `t0`', $queryBuilder);
	}

	public function testCountOnly() {
		$queryBuilder = $this->builder();
		$queryBuilder->setCountOnlyFlag();

		// The select list is dropped entirely - only the row count is asked for
		$this->assertStatement('SELECT COUNT(*) AS q_row_count FROM `person` AS `t0`', $queryBuilder);
	}

	/** Counting distinct rows needs the select list back, wrapped in a derived table. */
	public function testCountOnlyWithDistinct() {
		$queryBuilder = $this->builder();
		$queryBuilder->setCountOnlyFlag();
		$queryBuilder->setDistinctFlag();

		$this->assertStatement(
			'SELECT COUNT(*) AS q_row_count FROM (SELECT DISTINCT `t0`.`id` AS `a0` FROM `person` AS `t0` ) as q_count_table',
			$queryBuilder
		);
	}

	public function testWhereClause() {
		$queryBuilder = $this->builder();
		$queryBuilder->addWhereItem('`t0`.`id` = 1');

		$this->assertStatement('SELECT `t0`.`id` AS `a0` FROM `person` AS `t0` WHERE `t0`.`id` = 1', $queryBuilder);
	}

	/** A tautological where clause is what QQ::all() produces, and it is not worth emitting. */
	public function testTautologicalWhereClauseIsDropped() {
		$queryBuilder = $this->builder();
		$queryBuilder->addWhereItem('1=1');

		$this->assertStringNotContainsString('WHERE', $queryBuilder->getStatement());
	}

	public function testGroupByHavingAndOrderBy() {
		$queryBuilder = $this->builder();
		$queryBuilder->addGroupByItem('`t0`.`id`');
		$queryBuilder->addHavingItem('COUNT(*) > 0');
		$queryBuilder->addOrderByItem('`t0`.`id` DESC');

		$this->assertStatement(
			'SELECT `t0`.`id` AS `a0` FROM `person` AS `t0` GROUP BY `t0`.`id` Having COUNT(*) > 0 ORDER BY `t0`.`id` DESC',
			$queryBuilder
		);
	}

	/** MySQL takes the row limit as a suffix, after everything else. */
	public function testLimitIsAppendedLast() {
		$queryBuilder = $this->builder();
		$queryBuilder->addOrderByItem('`t0`.`id`');
		$queryBuilder->setLimitInfo('1,2');

		$this->assertStatement(
			'SELECT `t0`.`id` AS `a0` FROM `person` AS `t0` ORDER BY `t0`.`id` LIMIT 1,2',
			$queryBuilder
		);
	}

	public function testSelectFunction() {
		$queryBuilder = $this->builder();
		$queryBuilder->addSelectFunction('COUNT', '`t0`.`id`', 'total');

		$this->assertStringContainsString('COUNT(`t0`.`id`) AS `__total`', $queryBuilder->getStatement());
	}

	//////////////////////////////
	// Joins
	//////////////////////////////

	public function testJoinItem() {
		$queryBuilder = $this->builder('obj');
		$queryBuilder->addJoinItem('person', 'person', 'obj', 'person_id', 'id');

		$this->assertStringContainsString(
			'LEFT JOIN `person` AS `t1` ON `t0`.`person_id` = `t1`.`id`',
			$queryBuilder->getStatement()
		);
	}

	/** The same join added twice is one join - joins are keyed by their own text. */
	public function testRepeatedJoinItem() {
		$queryBuilder = $this->builder('obj');
		$queryBuilder->addJoinItem('person', 'person', 'obj', 'person_id', 'id');
		$queryBuilder->addJoinItem('person', 'person', 'obj', 'person_id', 'id');

		$this->assertEquals(1, substr_count($queryBuilder->getStatement(), 'LEFT JOIN `person`'));
	}

	/**
	 * A join condition is appended to the ON clause. The alias it uses is
	 * resolved by the node itself rather than by the join being registered here,
	 * so this only pins the shape - TestQueryJoins covers the real path, where
	 * QQ::expand() registers the join and the condition together.
	 */
	public function testJoinItemWithCondition() {
		$queryBuilder = $this->builder('obj');
		$queryBuilder->addJoinItem(
			'person', 'person', 'obj', 'person_id', 'id',
			QQ::equal((new QQNodeObj())->person->emailVerified, true)
		);

		$statement = $queryBuilder->getStatement();

		$this->assertStringContainsString('`email_verified` != 0', $statement);
		$this->assertEquals(1, substr_count($statement, ' AND '));
	}

	public function testConflictingJoinConditions() {
		$queryBuilder = $this->builder('obj');
		$queryBuilder->addJoinItem(
			'person', 'person', 'obj', 'person_id', 'id',
			QQ::equal((new QQNodeObj())->person->emailVerified, true)
		);

		$this->expectException(CogException::class);
		$queryBuilder->addJoinItem(
			'person', 'person', 'obj', 'person_id', 'id',
			QQ::equal((new QQNodeObj())->person->emailVerified, false)
		);
	}

	public function testCustomSqlJoin() {
		$queryBuilder = $this->builder();
		$queryBuilder->addJoinCustomSqlItem('INNER JOIN `obj` AS `x` ON `x`.`person_id` = `t0`.`id`');

		$this->assertStringContainsString('INNER JOIN `obj` AS `x`', $queryBuilder->getStatement());
	}

	//////////////////////////////
	// Virtual nodes and properties
	//////////////////////////////

	public function testUndefinedVirtualNode() {
		$this->expectException(CogException::class);
		$this->builder()->getVirtualNode('missing');
	}

	public function testExposedProperties() {
		$queryBuilder = $this->builder();

		$this->assertSame($this->database, $queryBuilder->database);
		$this->assertEquals('person', $queryBuilder->rootTableName);
		$this->assertIsArray($queryBuilder->columnAliasArray);
		$this->assertNull($queryBuilder->expandAsArrayNode);
	}

	public function testUndefinedProperty() {
		$this->expectException(UndefinedPropertyException::class);
		$this->builder()->missingProperty;
	}

	//////////////////////////////
	// The statement has to be valid SQL, not merely well shaped
	//////////////////////////////

	public function testGeneratedStatementRuns() {
		$queryBuilder = $this->builder('obj');
		$queryBuilder->addSelectItem('obj', 'label', 'label');
		$queryBuilder->addJoinItem('person', 'person', 'obj', 'person_id', 'id');
		$queryBuilder->addWhereItem("`t1`.`name` = 'Adam Kluczyk'");
		$queryBuilder->addOrderByItem('`t0`.`id`');
		$queryBuilder->setLimitInfo('1');

		$result = $this->database->query($queryBuilder->getStatement());

		$this->assertEquals(1, $result->countRows());
		$this->assertEquals(['a0' => '1', 'a1' => 'First object'], $result->fetchArrayAssoc());
	}
}
