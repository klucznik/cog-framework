<?php

namespace Cog\Test;

use App\Data\Asset;
use App\Data\BlogPost;
use App\Data\Obj;
use App\Data\Person;
use App\Data\Tag;
use Cog\Exceptions\CogException;
use Cog\Exceptions\InvalidCastException;
use Cog\Query\QQ;
use Cog\Query\QQConditionAll;
use Cog\Query\QQConditionAnd;
use Cog\Query\QQConditionEqual;
use Cog\Util\Utils;
use Generated\Node\QQNodeAsset;
use Generated\Node\QQNodeBlogPost;
use Generated\Node\QQNodeObj;
use Generated\Node\QQNodePerson;
use Generated\Node\QQNodeTag;

/**
 * Conditions and clauses, run through the generated ORM against the cog_test
 * fixture: 3 people, 2 objs, 2 blog posts, 2 assets, 3 tags.
 *
 * Every case asserts the emitted SQL as well as the rows that come back. The
 * rows on their own would not catch a query that is accidentally right, and the
 * SQL on its own would not catch one that is plausible but wrong.
 */
class TestQuery extends QueryTestCase {

	private function person(): QQNodePerson {
		return new QQNodePerson();
	}

	//////////////////////////////
	// Comparison conditions
	//////////////////////////////

	public function testEqual() {
		$people = Person::queryArray(QQ::equal($this->person()->name, 'Maria Nowak'));

		$this->assertQueryContains("`t0`.`name` = 'Maria Nowak'");
		$this->assertCount(1, $people);
		$this->assertEquals('maria@test.net', $people[0]->email);
	}

	public function testNotEqual() {
		$people = Person::queryArray(QQ::notEqual($this->person()->id, 1));

		$this->assertQueryContains('`t0`.`id` != 1');
		$this->assertEquals(['Maria Nowak', 'Piotr Lewandowski'], self::pluck($people, 'name'));
	}

	public function testBooleanEqual() {
		$people = Person::queryArray(QQ::equal($this->person()->emailVerified, true));

		// sqlVariable() renders a true comparison as "not zero"
		$this->assertQueryContains('`t0`.`email_verified` != 0');
		$this->assertEquals(['Maria Nowak', 'Piotr Lewandowski'], self::pluck($people, 'name'));

		$people = Person::queryArray(QQ::equal($this->person()->emailVerified, false));
		$this->assertQueryContains('`t0`.`email_verified` = 0');
		$this->assertEquals(['Adam Kluczyk'], self::pluck($people, 'name'));
	}

	public function testOrdering() {
		$this->assertCount(2, Person::queryArray(QQ::greaterThan($this->person()->id, 1)));
		$this->assertQueryContains('`t0`.`id` > 1');

		$this->assertCount(3, Person::queryArray(QQ::greaterOrEqual($this->person()->id, 1)));
		$this->assertQueryContains('`t0`.`id` >= 1');

		$this->assertCount(0, Person::queryArray(QQ::lessThan($this->person()->id, 1)));
		$this->assertQueryContains('`t0`.`id` < 1');

		$this->assertCount(1, Person::queryArray(QQ::lessOrEqual($this->person()->id, 1)));
		$this->assertQueryContains('`t0`.`id` <= 1');
	}

	public function testLike() {
		$people = Person::queryArray(QQ::like($this->person()->email, '%@test.net'));

		$this->assertQueryContains("`t0`.`email` LIKE '%@test.net'");
		$this->assertCount(3, $people);

		$this->assertCount(2, Person::queryArray(QQ::notLike($this->person()->name, 'A%')));
		$this->assertQueryContains("`t0`.`name` NOT LIKE 'A%'");
	}

	/** A quote in a pattern has to be escaped into the literal, not end it. */
	public function testLikeEscapesQuotes() {
		$people = Person::queryArray(QQ::like($this->person()->name, "%O'Brien%"));

		$this->assertQueryContains("LIKE '%O\\'Brien%'");
		$this->assertEmpty($people);
	}

	public function testIn() {
		$people = Person::queryArray(QQ::in($this->person()->id, [1, 2]));

		$this->assertQueryContains('`t0`.`id` IN (1,2)');
		$this->assertEquals(['Adam Kluczyk', 'Maria Nowak'], self::pluck($people, 'name'));

		$people = Person::queryArray(QQ::notIn($this->person()->id, [1, 2]));
		$this->assertQueryContains('`t0`.`id` NOT IN (1,2)');
		$this->assertEquals(['Piotr Lewandowski'], self::pluck($people, 'name'));
	}

	public function testBetween() {
		$people = Person::queryArray(QQ::between($this->person()->id, 1, 2));

		$this->assertQueryContains("`t0`.`id` BETWEEN '1' AND '2'");
		$this->assertCount(2, $people);

		$people = Person::queryArray(QQ::notBetween($this->person()->id, 1, 2));
		$this->assertQueryContains("`t0`.`id` NOT BETWEEN '1' AND '2'");
		$this->assertEquals(['Piotr Lewandowski'], self::pluck($people, 'name'));
	}

	public function testNullChecks() {
		$posts = BlogPost::queryArray(QQ::isNull((new QQNodeBlogPost())->body));

		$this->assertQueryContains('`t0`.`body` IS NULL');
		$this->assertEmpty($posts);

		$posts = BlogPost::queryArray(QQ::isNotNull((new QQNodeBlogPost())->body));
		$this->assertQueryContains('`t0`.`body` IS NOT NULL');
		$this->assertCount(2, $posts);
	}

	/** Comparing two columns of the same row, rather than a column to a value. */
	public function testComparisonAgainstAnotherNode() {
		$posts = BlogPost::queryArray(QQ::equal((new QQNodeBlogPost())->objId, (new QQNodeBlogPost())->id));

		$this->assertQueryContains('`t0`.`obj_id` = `t0`.`id`');
		$this->assertCount(2, $posts);
	}

	//////////////////////////////
	// Logical conditions
	//////////////////////////////

	public function testAndCondition() {
		$people = Person::queryArray(QQ::andCondition(
			QQ::equal($this->person()->emailVerified, true),
			QQ::like($this->person()->name, 'M%')
		));

		$this->assertQueryContains('( `t0`.`email_verified` != 0 AND `t0`.`name` LIKE \'M%\' )');
		$this->assertEquals(['Maria Nowak'], self::pluck($people, 'name'));
	}

	public function testOrCondition() {
		$people = Person::queryArray(QQ::orCondition(
			QQ::equal($this->person()->id, 1),
			QQ::equal($this->person()->id, 3)
		));

		$this->assertQueryContains('( `t0`.`id` = 1 OR `t0`.`id` = 3 )');
		$this->assertEquals(['Adam Kluczyk', 'Piotr Lewandowski'], self::pluck($people, 'name'));
	}

	public function testNestedConditions() {
		$people = Person::queryArray(QQ::andCondition(
			QQ::equal($this->person()->emailVerified, true),
			QQ::orCondition(
				QQ::like($this->person()->name, 'M%'),
				QQ::like($this->person()->name, 'P%')
			)
		));

		// The inner group keeps its own brackets
		$this->assertQueryContains('AND ( `t0`.`name` LIKE \'M%\' OR `t0`.`name` LIKE \'P%\' ) )');
		$this->assertCount(2, $people);
	}

	public function testNot() {
		$people = Person::queryArray(QQ::not(QQ::equal($this->person()->id, 1)));

		$this->assertQueryContains('(NOT `t0`.`id` = 1 )');
		$this->assertCount(2, $people);
	}

	/** all() emits 1=1, which getStatement() then drops rather than emitting a pointless WHERE. */
	public function testAll() {
		$people = Person::queryArray(QQ::all());

		$this->assertQueryNotContains('WHERE');
		$this->assertCount(3, $people);
	}

	public function testNone() {
		$people = Person::queryArray(QQ::none());

		$this->assertQueryContains('WHERE');
		$this->assertQueryContains('1=0');
		$this->assertEmpty($people);
	}

	public function testLogicalConditionRejectsNonConditions() {
		$this->expectException(CogException::class);
		QQ::andCondition(QQ::equal($this->person()->id, 1), 'not a condition');
	}

	public function testLogicalConditionRejectsEmptyArgumentList() {
		$this->expectException(CogException::class);
		QQ::orCondition();
	}

	//////////////////////////////
	// Factories and helpers
	//////////////////////////////

	public function testComparisonShortcuts() {
		$node = $this->person()->id;

		$this->assertCount(1, Person::queryArray(QQ::_($node, '=', 1)));
		$this->assertCount(2, Person::queryArray(QQ::_($node, '!=', 1)));
		$this->assertCount(2, Person::queryArray(QQ::_($node, '>', 1)));
		$this->assertCount(3, Person::queryArray(QQ::_($node, '>=', 1)));
		$this->assertCount(0, Person::queryArray(QQ::_($node, '<', 1)));
		$this->assertCount(1, Person::queryArray(QQ::_($node, '<=', 1)));
		$this->assertCount(2, Person::queryArray(QQ::_($node, 'in', [1, 2])));
		$this->assertCount(1, Person::queryArray(QQ::_($node, 'not in', [1, 2])));
		$this->assertCount(2, Person::queryArray(QQ::_($node, 'between', 1, 2)));
		$this->assertCount(1, Person::queryArray(QQ::_($node, 'not between', 1, 2)));
		$this->assertCount(1, Person::queryArray(QQ::_($this->person()->name, 'like', 'Maria%')));
		$this->assertCount(2, Person::queryArray(QQ::_($this->person()->name, 'not like', 'Maria%')));
		$this->assertCount(0, BlogPost::queryArray(QQ::_((new QQNodeBlogPost())->body, 'is null', null)));
		$this->assertCount(2, BlogPost::queryArray(QQ::_((new QQNodeBlogPost())->body, 'is not null', null)));
	}

	/** The symbol is trimmed and lowercased before it is matched. */
	public function testComparisonShortcutNormalisesTheSymbol() {
		$this->assertCount(1, Person::queryArray(QQ::_($this->person()->name, ' LIKE ', 'Maria%')));
	}

	public function testComparisonShortcutRejectsUnknownOperator() {
		$this->expectException(CogException::class);
		QQ::_($this->person()->id, '<=>', 1);
	}

	public function testConditionsArrayHelper() {
		$this->assertInstanceOf(QQConditionAll::class, QQ::conditionsArrayHelper([]));

		$single = QQ::equal($this->person()->id, 1);
		$this->assertSame($single, QQ::conditionsArrayHelper([$single]));

		$this->assertInstanceOf(QQConditionAnd::class, QQ::conditionsArrayHelper([
			QQ::equal($this->person()->id, 1),
			QQ::equal($this->person()->emailVerified, false)
		]));
	}

	/** The helper is how application code builds a condition list up incrementally. */
	public function testConditionsArrayHelperInAQuery() {
		$conditions = [QQ::equal($this->person()->emailVerified, true)];
		$conditions[] = QQ::like($this->person()->name, 'P%');

		$people = Person::queryArray(QQ::conditionsArrayHelper($conditions));

		$this->assertEquals(['Piotr Lewandowski'], self::pluck($people, 'name'));
	}

	public function testClauseFactory() {
		$clauses = QQ::clause(QQ::distinct(), null, QQ::limitInfo(1));

		// Nulls are dropped rather than collected
		$this->assertCount(2, $clauses);
	}

	public function testClauseFactoryRejectsNonClauses() {
		$this->expectException(CogException::class);
		QQ::clause(QQ::equal($this->person()->id, 1));
	}

	//////////////////////////////
	// Clauses
	//////////////////////////////

	public function testOrderBy() {
		$people = Person::queryArray(QQ::all(), QQ::orderBy($this->person()->name));

		$this->assertQueryContains('ORDER BY `t0`.`name`');
		$this->assertQueryNotContains('DESC');
		$this->assertEquals('Adam Kluczyk', $people[0]->name);

		$people = Person::queryArray(QQ::all(), QQ::orderBy($this->person()->name, false));
		$this->assertQueryContains('ORDER BY `t0`.`name` DESC');
		$this->assertEquals('Piotr Lewandowski', $people[0]->name);
	}

	public function testOrderByMultipleNodes() {
		$assets = Asset::queryArray(QQ::all(), QQ::orderBy(
			(new QQNodeAsset())->objId,
			(new QQNodeAsset())->filename, false
		));

		$this->assertQueryContains('ORDER BY `t0`.`obj_id`');
		$this->assertQueryContains('`t0`.`filename` DESC');
		$this->assertEquals('manual.pdf', $assets[0]->filename);
	}

	public function testLimitInfo() {
		$people = Person::queryArray(QQ::all(), [QQ::orderBy($this->person()->id), QQ::limitInfo(2)]);

		$this->assertQueryContains('LIMIT 2');
		$this->assertCount(2, $people);
		$this->assertEquals('Adam Kluczyk', $people[0]->name);
	}

	public function testLimitInfoWithOffset() {
		$people = Person::queryArray(QQ::all(), [QQ::orderBy($this->person()->id), QQ::limitInfo(2, 1)]);

		$this->assertQueryContains('LIMIT 1,2');
		$this->assertCount(2, $people);
		$this->assertEquals('Maria Nowak', $people[0]->name);
	}

	public function testDistinct() {
		$people = Person::queryArray(QQ::all(), QQ::distinct());

		$this->assertQueryContains('SELECT DISTINCT');
		$this->assertCount(3, $people);
	}

	/** How hulme adds a clause to a caller-supplied list. */
	public function testDistinctAppendedToOptionalClauses() {
		$people = Person::queryArray(QQ::all(), Utils::extendArray([QQ::orderBy($this->person()->id)], QQ::distinct()));

		$this->assertQueryContains('SELECT DISTINCT');
		$this->assertQueryContains('ORDER BY');
		$this->assertCount(3, $people);
	}

	public function testCount() {
		$this->assertEquals(3, Person::queryCount(QQ::all()));
		$this->assertQueryContains('COUNT(*) AS q_row_count');

		$this->assertEquals(2, Person::queryCount(QQ::equal($this->person()->emailVerified, true)));
	}

	public function testCountAll() {
		$this->assertEquals(3, Person::countAll());
	}

	public function testGroupByWithAggregate() {
		$assets = Asset::queryArray(QQ::all(), [
			QQ::sum((new QQNodeAsset())->size, 'total'),
			QQ::groupBy((new QQNodeAsset())->objId)
		]);

		$this->assertQueryContains('SUM(`t0`.`size`) AS `__total`');
		$this->assertQueryContains('GROUP BY `t0`.`obj_id`');

		// Both fixture assets hang off obj 1, so they collapse to one row
		$this->assertCount(1, $assets);
	}

	public function testAggregateFunctions() {
		$node = static fn() => (new QQNodeAsset())->size;

		Asset::queryArray(QQ::all(), [QQ::count($node(), 'c'), QQ::groupBy((new QQNodeAsset())->objId)]);
		$this->assertQueryContains('COUNT(`t0`.`size`) AS `__c`');

		Asset::queryArray(QQ::all(), [QQ::minimum($node(), 'lo'), QQ::groupBy((new QQNodeAsset())->objId)]);
		$this->assertQueryContains('MIN(`t0`.`size`) AS `__lo`');

		Asset::queryArray(QQ::all(), [QQ::maximum($node(), 'hi'), QQ::groupBy((new QQNodeAsset())->objId)]);
		$this->assertQueryContains('MAX(`t0`.`size`) AS `__hi`');

		Asset::queryArray(QQ::all(), [QQ::average($node(), 'avg'), QQ::groupBy((new QQNodeAsset())->objId)]);
		$this->assertQueryContains('AVG(`t0`.`size`) AS `__avg`');
	}

	/** A select clause narrows the column list, but the primary key always comes along. */
	public function testSelect() {
		$people = Person::queryArray(QQ::all(), QQ::select($this->person()->name));

		$this->assertQueryContains('`t0`.`name`');
		$this->assertQueryNotContains('`t0`.`password`');
		$this->assertQueryContains('`t0`.`id`');

		$this->assertEquals('Adam Kluczyk', $people[0]->name);
	}

	public function testAlias() {
		$node = QQ::alias($this->person()->name, 'personName');

		$this->assertEquals('personName', $node->alias);
	}

	//////////////////////////////
	// Named values
	//////////////////////////////

	public function testNamedValue() {
		$people = Person::queryArray(
			QQ::equal($this->person()->name, QQ::namedValue('name')),
			null,
			['name' => 'Maria Nowak']
		);

		$this->assertQueryContains("`t0`.`name` = 'Maria Nowak'");
		$this->assertCount(1, $people);
	}

	public function testNamedValueIsEscaped() {
		$people = Person::queryArray(
			QQ::equal($this->person()->name, QQ::namedValue('name')),
			null,
			['name' => "' OR 1=1 --"]
		);

		$this->assertQueryContains("= '\\' OR 1=1 --'");
		$this->assertEmpty($people);
	}

	public function testUnresolvedNamedValue() {
		$this->expectException(CogException::class);

		Person::queryArray(
			QQ::equal($this->person()->name, QQ::namedValue('name')),
			null,
			['other' => 'Maria Nowak']
		);
	}

	//////////////////////////////
	// Error paths
	//////////////////////////////

	/** A table-level node has no parent, so it cannot be one side of a comparison. */
	public function testComparisonAgainstTableNode() {
		$this->expectException(InvalidCastException::class);
		QQ::equal(new QQNodePerson(), 1);
	}

	/**
	 * The message names the offending node, and `person` has a `name` column, so
	 * $node->name resolves through the generated __get() to a child QQNode rather
	 * than to the node's own name. Building the message has to survive that.
	 */
	public function testTableNodeErrorMessageNamesTheTable() {
		$node = new QQNodePerson();

		$this->assertEquals('person', $node->getNodeName());

		try {
			QQ::equal($node, 1);
			$this->fail('a table-level node should not be comparable');
		} catch (InvalidCastException $exception) {
			$this->assertEquals('Unable to cast "person" table to Column-based QQNode', $exception->getMessage());
		}
	}

	/** Every guard that reports a table-level node shares the same message. */
	public function testTableNodeRejectedByEveryCondition() {
		$rejects = [
			'in' => static fn() => QQ::in(new QQNodePerson(), [1]),
			'like' => static fn() => QQ::like(new QQNodePerson(), 'x%'),
			'between' => static fn() => QQ::between(new QQNodePerson(), 1, 2),
			'isNull' => static fn() => QQ::isNull(new QQNodePerson()),
			'isNotNull' => static fn() => QQ::isNotNull(new QQNodePerson()),
			'orderBy' => static fn() => QQ::orderBy(new QQNodePerson()),
			'groupBy' => static fn() => QQ::groupBy(new QQNodePerson()),
			'expand' => static fn() => QQ::expand(new QQNodePerson()),
			'sum' => static fn() => QQ::sum(new QQNodePerson(), 'total'),
		];

		foreach ($rejects as $label => $reject) {
			try {
				$reject();
				$this->fail($label . ' should reject a table-level node');
			} catch (InvalidCastException $exception) {
				$this->assertEquals(
					'Unable to cast "person" table to Column-based QQNode',
					$exception->getMessage(),
					$label . ' reported the wrong message'
				);
			}
		}
	}

	public function testComparisonOperandCannotBeACondition() {
		$this->expectException(InvalidCastException::class);
		QQ::equal($this->person()->id, QQ::all());
	}

	public function testComparisonOperandCannotBeAClause() {
		$this->expectException(InvalidCastException::class);
		QQ::equal($this->person()->id, QQ::distinct());
	}

	/** Nodes are rooted in a table, and the query has to be against that same table. */
	public function testNodeFromAnotherRootTable() {
		$this->expectException(CogException::class);
		Person::queryArray(QQ::equal((new QQNodeTag())->name, 'php'));
	}

	//////////////////////////////
	// Single-row and cursor entry points
	//////////////////////////////

	public function testQuerySingle() {
		$person = Person::querySingle(QQ::equal($this->person()->email, 'maria@test.net'));

		$this->assertInstanceOf(Person::class, $person);
		$this->assertEquals('Maria Nowak', $person->name);

		$this->assertNull(Person::querySingle(QQ::equal($this->person()->email, 'nobody@test.net')));
	}

	public function testQueryCursor() {
		$cursor = Obj::queryCursor(QQ::all(), QQ::orderBy((new QQNodeObj())->id));

		$labels = [];
		while ($obj = Obj::instantiateCursor($cursor)) {
			$labels[] = $obj->label;
		}

		$this->assertEquals(['First object', 'Second object'], $labels);
	}

	public function testGeneratedIndexLoaders() {
		$this->assertEquals('Maria Nowak', Person::loadByEmail('maria@test.net')->name);
		$this->assertNull(Person::loadByEmail('nobody@test.net'));

		$this->assertCount(2, Person::loadArrayByEmailVerified(true));
		$this->assertEquals(2, Person::countByEmailVerified(true));

		$this->assertCount(3, Tag::loadAll());
	}
}
