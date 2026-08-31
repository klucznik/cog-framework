<?php declare(strict_types=1);

namespace Cog\Test;

use App\Data\Obj;
use App\Data\Person;
use Cog\Exceptions\CogException;
use Cog\Exceptions\InvalidCastException;
use Cog\Exceptions\UndefinedPropertyException;
use Cog\Query\QQ;
use Generated\Node\QQNodeObj;
use Generated\Node\QQNodePerson;

/**
 * The query layer's internals: the node base class, expansion merging, and the
 * condition branches the other query tests never reach.
 *
 * These are the parts a caller never names directly. QQBaseNode is where every
 * node's state lives - thirteen properties read through __get - and
 * mergeExpansionNode is what reconciles two expandAsArray clauses into one node
 * tree. Neither had coverage, which matters because the conditions reached only
 * through a QQNamedValue or a sub-query are exactly the ones a refactor would
 * break without anything noticing.
 *
 * TestQuery and TestQueryClauses cover the clauses as a caller uses them; this
 * file covers what those clauses are made of.
 */
class TestQueryInternals extends QueryTestCase {

	private function person(): QQNodePerson {
		return new QQNodePerson();
	}

	private function obj(): QQNodeObj {
		return new QQNodeObj();
	}

	//
	// QQBaseNode: the property surface
	//

	/**
	 * Every node carries the identity the query builder joins and aliases on.
	 * A column node knows its own name and the table it hangs off.
	 */
	public function testColumnNodeProperties() {
		$node = $this->person()->name;

		$this->assertSame('name', $node->name);
		$this->assertSame('name', $node->propertyName);
		$this->assertSame('Name', $node->propertyNameUppercase);
		$this->assertSame('person', $node->rootTableName);
		$this->assertSame('VarChar', $node->type);
		$this->assertNotNull($node->parentNode);
	}

	/** A table node names the table it stands for, and its primary key. */
	public function testTableNodeProperties() {
		$node = $this->person();

		$this->assertSame('person', $node->getNodeName());
		$this->assertSame('person', $node->rootTableName);
		$this->assertSame('person', $node->tableName);
		$this->assertSame('id', $node->primaryKey);
		$this->assertNull($node->parentNode);
	}

	/** A reference node points at the table it joins to, not the one it hangs off. */
	public function testReferenceNodeProperties() {
		$node = $this->obj()->person;

		$this->assertSame('person', $node->tableName);
		$this->assertSame('id', $node->primaryKey);
		$this->assertSame('Person', $node->className);
		$this->assertFalse((bool)$node->isType);
	}

	/**
	 * A node's own `name` is shadowed when the table has a column called `name` -
	 * __get resolves the column first - which is why the error messages use
	 * getNodeName() instead.
	 */
	public function testGetNodeNameIsNotShadowedByAColumn() {
		$node = $this->person();

		$this->assertSame('person', $node->getNodeName());
		// Reading ->name on the same node yields the column node, not the string
		$this->assertNotSame('person', $node->name);
	}

	public function testUnknownNodePropertyThrows() {
		$this->expectException(UndefinedPropertyException::class);

		/** @noinspection PhpExpressionResultUnusedInspection */
		$this->person()->name->noSuchProperty;
	}

	public function testUnknownNodePropertySetThrows() {
		$this->expectException(UndefinedPropertyException::class);

		$this->person()->name->noSuchProperty = 'x';
	}

	//
	// QQBaseNode: the two writable properties
	//

	public function testAliasIsWritable() {
		$node = $this->person()->name;
		$node->alias = 'personName';

		$this->assertSame('personName', $node->alias);
	}

	/** The setter casts, so anything stringable lands as a string. */
	public function testAliasIsCast() {
		$node = $this->person()->name;
		$node->alias = 12;

		$this->assertSame('12', $node->alias);
	}

	public function testExpandAsArrayIsWritableAndCast() {
		$node = $this->obj()->asset;

		$node->expandAsArray = true;
		$this->assertTrue($node->expandAsArray);

		$node->expandAsArray = 0;
		$this->assertFalse($node->expandAsArray);
	}

	//
	// QQBaseNode: alias composition and child access
	//

	/** The extended alias is the chain of aliases from the root down. */
	public function testExtendedAlias() {
		$this->assertSame('person', $this->person()->extendedAlias());
		$this->assertSame('person__name', $this->person()->name->extendedAlias());
		$this->assertSame('obj__person_id__name', $this->obj()->person->name->extendedAlias());
	}

	/** Reading a child registers it on the parent, which is what firstChild returns. */
	public function testFirstChild() {
		$node = $this->person();
		$this->assertNull($node->firstChild(), 'a node with no children read yet has none');

		$child = $node->name;

		$this->assertSame($child->name, $node->firstChild()->name);
		$this->assertArrayHasKey('name', $node->childNodeArray);
	}

	//
	// mergeExpansionNode: reconciling two expandAsArray clauses
	//
	// QueryBuilder::addExpandAsArrayNode() walks each expanded node up to its root
	// and merges the second tree into the first, so one query can expand more than
	// one collection.
	//

	/** Two collections off the same table both come back expanded. */
	public function testTwoExpandAsArrayClausesOnOneTable() {
		$objs = Obj::queryArray(
			QQ::equal($this->obj()->id, 1),
			QQ::clause(
				QQ::expandAsArray($this->obj()->asset),
				QQ::expandAsArray($this->obj()->blogPost)
			)
		);

		$this->assertQueryContains('LEFT JOIN `asset`');
		$this->assertQueryContains('LEFT JOIN `blog_post`');
		$this->assertCount(1, $objs, 'the expansions collapse back into one row');
		$this->assertCount(2, $objs[0]->_assetArray);
	}

	/** Expanding the same collection twice is idempotent rather than an error. */
	public function testTheSameExpandAsArrayClauseTwice() {
		$objs = Obj::queryArray(
			QQ::equal($this->obj()->id, 1),
			QQ::clause(
				QQ::expandAsArray($this->obj()->asset),
				QQ::expandAsArray($this->obj()->asset)
			)
		);

		$this->assertCount(1, $objs);
		$this->assertCount(2, $objs[0]->_assetArray);
	}

	/** Merging is recursive: a shared intermediate node is walked into, not replaced. */
	public function testExpandAsArrayMergesNestedPaths() {
		$people = Person::queryArray(
			QQ::equal($this->person()->id, 1),
			QQ::clause(
				QQ::expandAsArray($this->person()->obj->asset),
				QQ::expandAsArray($this->person()->obj->blogPost)
			)
		);

		$this->assertQueryContains('LEFT JOIN `obj`');
		$this->assertQueryContains('LEFT JOIN `asset`');
		$this->assertQueryContains('LEFT JOIN `blog_post`');
		// One join per table: the shared obj hop is merged, not duplicated
		$this->assertSame(1, substr_count($this->lastQuery(), 'LEFT JOIN `obj`'));
		$this->assertCount(1, $people);
	}

	/** Trees rooted at different tables cannot be reconciled. */
	public function testMergingExpansionNodesFromDifferentTablesThrows() {
		$incoming = $this->person();
		$incoming->obj;

		$this->expectException(CogException::class);
		$this->expectExceptionMessageIsOrContains('Expansion node tables must match');

		$this->obj()->mergeExpansionNode($incoming);
	}

	/**
	 * A node with no children carries no expansion, so merging it changes nothing -
	 * and is checked before the table names are, so it does not throw either.
	 */
	public function testMergingAChildlessNodeIsANoOp() {
		$target = $this->obj();
		$target->asset;

		$before = array_keys($target->childNodeArray);
		$target->mergeExpansionNode($this->person());

		$this->assertSame($before, array_keys($target->childNodeArray));
	}

	//
	// Conditions reached only through a named value or a sub-query
	//

	public function testLikeWithNamedValue() {
		$people = Person::queryArray(
			QQ::like($this->person()->name, QQ::namedValue('pattern')),
			null,
			['pattern' => 'A%']
		);

		$this->assertQueryContains('LIKE');
		$this->assertEquals(['Adam Kluczyk'], self::pluck($people, 'name'));
	}

	public function testInWithNamedValue() {
		$people = Person::queryArray(
			QQ::in($this->person()->name, QQ::namedValue('names')),
			null,
			['names' => 'Maria Nowak']
		);

		$this->assertQueryContains('IN (');
		$this->assertEquals(['Maria Nowak'], self::pluck($people, 'name'));
	}

	/** An IN against a sub-query passes the sub-query through rather than a value list. */
	public function testInWithSubQuery() {
		$people = Person::queryArray(
			QQ::in($this->person()->id, QQ::subSql('(SELECT `person_id` FROM `obj`)'))
		);

		$this->assertQueryContains('IN ((SELECT `person_id` FROM `obj`))');
		$this->assertEquals(['Adam Kluczyk', 'Maria Nowak'], self::pluck($people, 'name'));
	}

	/** An empty IN list can match nothing, which has to be said in SQL explicitly. */
	public function testInWithNoValuesMatchesNothing() {
		$people = Person::queryArray(QQ::in($this->person()->name, []));

		$this->assertQueryContains('1=0');
		$this->assertSame([], $people);
	}

	public function testBetweenWithNamedValues() {
		$people = Person::queryArray(
			QQ::between($this->person()->id, QQ::namedValue('low'), QQ::namedValue('high')),
			null,
			['low' => 1, 'high' => 2]
		);

		$this->assertQueryContains('BETWEEN');
		$this->assertEquals(['Adam Kluczyk', 'Maria Nowak'], self::pluck($people, 'name'));
	}

	//
	// The cast failures each condition guards against
	//

	public function testLikeRejectsAValueThatCannotBeAString() {
		$this->expectException(InvalidCastException::class);

		QQ::like($this->person()->name, ['not', 'a', 'string']);
	}

	public function testBetweenRejectsAMinimumThatCannotBeAString() {
		$this->expectException(InvalidCastException::class);

		QQ::between($this->person()->id, ['nope'], '2');
	}

	public function testBetweenRejectsAMaximumThatCannotBeAString() {
		$this->expectException(InvalidCastException::class);

		QQ::between($this->person()->id, '1', ['nope']);
	}

	public function testInRejectsValuesThatCannotBeAnArray() {
		$this->expectException(InvalidCastException::class);

		QQ::in($this->person()->name, new \stdClass());
	}
}
