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
use Cog\Query\QueryBuilder;
use Generated\Node\QQNodeAsset;
use Generated\Node\QQNodeBlogPost;
use Generated\Node\QQNodeObj;
use Generated\Node\QQNodePerson;

/**
 * Traversal: following a node into another table, and pulling that table's rows
 * back with the result.
 *
 * The fixture wires this up three different ways - `obj.person_id` is a plain
 * reference, `asset`/`blog_post` point back at `obj` as reverse references, and
 * `tag_obj_assn` joins `tag` and `obj` as a many-to-many.
 */
class TestQueryJoins extends QueryTestCase {

	//////////////////////////////
	// References
	//////////////////////////////

	public function testReferenceJoin() {
		$objs = Obj::queryArray(QQ::equal((new QQNodeObj())->person->name, 'Adam Kluczyk'));

		$this->assertQueryContains('LEFT JOIN `person` AS `t1` ON `t0`.`person_id` = `t1`.`id`');
		$this->assertQueryContains("WHERE `t1`.`name` = 'Adam Kluczyk'");
		$this->assertEquals(['First object'], self::pluck($objs, 'label'));
	}

	public function testOrderByJoinedColumn() {
		$objs = Obj::queryArray(QQ::all(), QQ::orderBy((new QQNodeObj())->person->name, false));

		$this->assertQueryContains('LEFT JOIN `person`');
		$this->assertQueryContains('ORDER BY `t1`.`name` DESC');
		$this->assertEquals('Second object', $objs[0]->label);
	}

	/** Two hops: blog_post → obj → person. */
	public function testJoinAcrossTwoReferences() {
		$posts = BlogPost::queryArray(QQ::equal((new QQNodeBlogPost())->obj->person->name, 'Maria Nowak'));

		$this->assertQueryContains('LEFT JOIN `obj`');
		$this->assertQueryContains('LEFT JOIN `person`');
		$this->assertEquals(['Draft post'], self::pluck($posts, 'title'));
	}

	//////////////////////////////
	// Reverse references
	//////////////////////////////

	public function testReverseReferenceJoin() {
		$people = Person::queryArray(QQ::equal((new QQNodePerson())->obj->label, 'First object'));

		$this->assertQueryContains('LEFT JOIN `obj` AS `t1` ON `t0`.`id` = `t1`.`person_id`');
		$this->assertEquals(['Adam Kluczyk'], self::pluck($people, 'name'));
	}

	public function testReverseReferenceWithNamedRelationship() {
		$people = Person::queryArray(QQ::equal((new QQNodePerson())->blogPostAsAuthor->title, 'Hello world'));

		$this->assertQueryContains('LEFT JOIN `blog_post` AS `t1` ON `t0`.`id` = `t1`.`author_id`');
		$this->assertEquals(['Adam Kluczyk'], self::pluck($people, 'name'));
	}

	/** A reverse reference multiplies rows, so a distinct clause is what collapses them. */
	public function testReverseReferenceProducesDuplicatesWithoutDistinct() {
		$objs = Obj::queryArray(QQ::isNotNull((new QQNodeObj())->asset->id));
		$this->assertCount(2, $objs, 'obj 1 has two assets, so it comes back twice');

		$objs = Obj::queryArray(QQ::isNotNull((new QQNodeObj())->asset->id), QQ::distinct());
		$this->assertCount(1, $objs);
	}

	//////////////////////////////
	// Association tables
	//////////////////////////////

	public function testAssociationJoin() {
		$objs = Obj::queryArray(QQ::equal((new QQNodeObj())->tag->tag->name, 'php'));

		// Two hops: through the association table, then onto tag itself
		$this->assertQueryContains('LEFT JOIN `tag_obj_assn` AS `t1` ON `t0`.`id` = `t1`.`obj_id`');
		$this->assertQueryContains('LEFT JOIN `tag` AS `t2` ON `t1`.`tag_id` = `t2`.`id`');
		$this->assertEquals(['First object'], self::pluck($objs, 'label'));
	}

	public function testAssociationJoinFromTheOtherSide() {
		$tags = Tag::queryArray(QQ::equal((new \Generated\Node\QQNodeTag())->obj->obj->label, 'Second object'));

		$this->assertQueryContains('LEFT JOIN `tag_obj_assn`');
		$this->assertQueryContains('LEFT JOIN `obj`');
		$this->assertEquals(['orm'], self::pluck($tags, 'name'));
	}

	public function testAssociationMatchesEveryLinkedRow() {
		// obj 1 carries php and testing, obj 2 carries orm
		$objs = Obj::queryArray(QQ::in((new QQNodeObj())->tag->tag->name, ['php', 'testing']), QQ::distinct());

		$this->assertEquals(['First object'], self::pluck($objs, 'label'));
	}

	//////////////////////////////
	// Graph associations
	//////////////////////////////
	//
	// `person_person_assn` joins `person` to itself. Both sides would otherwise
	// generate identically named nodes, so the column names prefix them apart:
	// `person` follows person_id -> friend_id, `parentPerson` follows the reverse.
	//

	/** Adam is friends with Maria and Piotr. */
	public function testGraphAssociationJoin() {
		$people = Person::queryArray(
			QQ::equal((new QQNodePerson())->person->person->name, 'Maria Nowak'),
			QQ::distinct()
		);

		$this->assertQueryContains('LEFT JOIN `person_person_assn`');
		$this->assertEquals(['Adam Kluczyk'], self::pluck($people, 'name'));
	}

	/** The other side of the same association walks friend_id back to person_id. */
	public function testGraphAssociationJoinFromTheOtherSide() {
		$people = Person::queryArray(
			QQ::equal((new QQNodePerson())->parentPerson->person->name, 'Adam Kluczyk'),
			QQ::clause(QQ::distinct(), QQ::orderBy((new QQNodePerson())->name))
		);

		$this->assertQueryContains('LEFT JOIN `person_person_assn`');
		$this->assertEquals(['Maria Nowak', 'Piotr Lewandowski'], self::pluck($people, 'name'));
	}

	/**
	 * The two sides are distinct joins with distinct aliases, so using both in one
	 * query must not collapse them into each other.
	 */
	public function testBothSidesOfAGraphAssociationInOneQuery() {
		Person::queryArray(QQ::andCondition(
			QQ::isNotNull((new QQNodePerson())->person->friendId),
			QQ::isNotNull((new QQNodePerson())->parentPerson->personId)
		));

		$this->assertEquals(2, substr_count($this->lastQuery(), 'LEFT JOIN `person_person_assn`'));
	}

	/**
	 * An association node names the join table, which has no columns to select, so
	 * it can never stand in for a column. QQ's own signatures reject one first -
	 * QQ::equal() type-hints QQNode, which an association node is not - so the
	 * node's own guard is only reachable by calling it directly.
	 */
	public function testAssociationNodeIsRejectedByTheQueryApi() {
		$this->expectException(\TypeError::class);

		/** @noinspection PhpParamsInspection */
		QQ::equal((new QQNodeObj())->tag, 1);
	}

	public function testAssociationNodeRefusesToActAsAColumn() {
		$node = (new QQNodeObj())->tag;

		$this->expectException(InvalidCastException::class);

		$node->getColumnAlias($this->createStub(QueryBuilder::class));
	}

	//////////////////////////////
	// Join reuse
	//////////////////////////////

	/** Two conditions on the same related table share one join. */
	public function testJoinIsNotRepeated() {
		$objs = Obj::queryArray(QQ::andCondition(
			QQ::equal((new QQNodeObj())->person->emailVerified, false),
			QQ::like((new QQNodeObj())->person->name, 'A%')
		));

		$this->assertEquals(1, substr_count($this->lastQuery(), 'LEFT JOIN `person`'));
		$this->assertEquals(['First object'], self::pluck($objs, 'label'));
	}

	/** Expanding a table already joined by a condition does not join it again. */
	public function testExpandReusesAConditionJoin() {
		Obj::queryArray(
			QQ::equal((new QQNodeObj())->person->name, 'Adam Kluczyk'),
			QQ::expand((new QQNodeObj())->person)
		);

		$this->assertEquals(1, substr_count($this->lastQuery(), 'LEFT JOIN `person`'));
	}

	/** Conflicting join conditions on one table cannot both be honoured. */
	public function testConflictingJoinConditions() {
		$this->expectException(CogException::class);

		Obj::queryArray(QQ::all(), [
			QQ::expand((new QQNodeObj())->person, QQ::equal((new QQNodeObj())->person->emailVerified, true)),
			QQ::expand((new QQNodeObj())->person, QQ::equal((new QQNodeObj())->person->emailVerified, false))
		]);
	}

	//////////////////////////////
	// Expansion
	//////////////////////////////

	/**
	 * The point of expand() is one statement instead of two - asserting the
	 * value alone would also pass if the related object were lazily loaded.
	 */
	public function testExpandLoadsTheRelatedObjectInOneQuery() {
		$before = $this->queryCount();

		$objs = Obj::queryArray(QQ::equal((new QQNodeObj())->id, 1), QQ::expand((new QQNodeObj())->person));

		$this->assertEquals(1, $this->queryCount() - $before, 'expand() should not need a second query');
		$this->assertQueryContains('`t1`.`name` AS `a5`');
		$this->assertEquals('Adam Kluczyk', $objs[0]->person->name);

		$this->assertEquals($before + 1, $this->queryCount(), 'reading the expanded object should not query');
	}

	public function testExpandWithASelectClause() {
		$objs = Obj::queryArray(
			QQ::equal((new QQNodeObj())->id, 1),
			QQ::expand((new QQNodeObj())->person, null, QQ::select((new QQNodePerson())->name))
		);

		$this->assertQueryContains('`t1`.`name`');
		$this->assertQueryNotContains('`t1`.`password`');
		$this->assertEquals('Adam Kluczyk', $objs[0]->person->name);
	}

	public function testExpandWithAJoinCondition() {
		$objs = Obj::queryArray(QQ::all(), [
			QQ::orderBy((new QQNodeObj())->id),
			QQ::expand((new QQNodeObj())->person, QQ::equal((new QQNodeObj())->person->emailVerified, true))
		]);

		$this->assertQueryContains('AND `t1`.`email_verified` != 0');
		$this->assertCount(2, $objs);

		// obj 2's person passes the join condition, so it arrived with the row
		$before = $this->queryCount();
		$this->assertEquals('Maria Nowak', $objs[1]->person->name);
		$this->assertEquals($before, $this->queryCount(), 'obj 2 should have been early bound');

		// obj 1's person does not, so the accessor has to go and fetch it
		$this->assertEquals('Adam Kluczyk', $objs[0]->person->name);
		$this->assertEquals($before + 1, $this->queryCount(), 'obj 1 should have fallen back to a lazy load');
	}

	/** A reverse reference expands into an array on the parent object. */
	public function testExpandAsArrayOnAReverseReference() {
		$before = $this->queryCount();

		$objs = Obj::queryArray(QQ::equal((new QQNodeObj())->id, 1), QQ::expandAsArray((new QQNodeObj())->asset));

		$this->assertEquals(1, $this->queryCount() - $before);
		$this->assertQueryContains('LEFT JOIN `asset`');

		// Two asset rows collapse into one obj carrying both
		$this->assertCount(1, $objs);
		$this->assertEquals(['logo.png', 'manual.pdf'], self::pluck($objs[0]->_assetArray, 'filename'));
	}

	public function testExpandAsArrayOnAnAssociation() {
		$objs = Obj::queryArray(QQ::equal((new QQNodeObj())->id, 1), QQ::expandAsArray((new QQNodeObj())->tag));

		$this->assertQueryContains('LEFT JOIN `tag_obj_assn`');
		$this->assertQueryContains('LEFT JOIN `tag`');

		$this->assertCount(1, $objs);
		$this->assertEquals(['php', 'testing'], self::pluck($objs[0]->_tagArray, 'name'));
	}

	/** An object with nothing on the other side gets an empty array, not null. */
	public function testExpandAsArrayWithNoRelatedRows() {
		$objs = Obj::queryArray(QQ::equal((new QQNodeObj())->id, 2), QQ::expandAsArray((new QQNodeObj())->asset));

		$this->assertCount(1, $objs);
		$this->assertEquals([], $objs[0]->_assetArray);
	}

	//////////////////////////////
	// Expansion argument validation
	//////////////////////////////

	public function testExpandRejectsAnAssociationNode() {
		$this->expectException(CogException::class);
		QQ::expand((new QQNodeObj())->tag);
	}

	public function testExpandRejectsANonNode() {
		$this->expectException(CogException::class);
		QQ::expand('person');
	}

	public function testExpandRejectsATableNode() {
		$this->expectException(InvalidCastException::class);
		QQ::expand(new QQNodeObj());
	}

	/** expandAsArray only makes sense where the other side is a collection. */
	public function testExpandAsArrayRejectsAPlainReference() {
		$this->expectException(CogException::class);
		QQ::expandAsArray((new QQNodeObj())->person);
	}

	public function testExpandAsArrayRejectsAColumn() {
		$this->expectException(CogException::class);
		QQ::expandAsArray((new QQNodeAsset())->filename);
	}
}
