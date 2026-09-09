<?php declare(strict_types=1);

namespace Cog\Test;

use App\Data\BlogPost;
use App\Data\Category;
use App\Data\Obj;
use App\Data\Person;
use Carbon\Carbon;
use Cog\Database\Exceptions\OptimisticLockingException;
use Cog\Database\Exceptions\UndefinedPrimaryKeyException;
use Cog\Exceptions\CogException;
use Cog\Exceptions\InvalidCastException;
use Cog\Query\QQ;
use Generated\Node\QQNodePerson;

/**
 * The write path of the generated ORM classes: save(), delete(), reload() and
 * the helpers around them, plus the per-index finders the templates emit.
 *
 * TestQuery and its siblings cover reading through the generated classes.
 * Nothing else exercises what they write, so this is the suite that fails when
 * a template change breaks INSERT, UPDATE, DELETE or the optimistic locking
 * check - which the lint and hasMethod() assertions in TestCodegen cannot see.
 *
 * Every test runs inside a transaction that tearDown() rolls back, so the
 * fixture rows TestDatabase asserts on are never changed. Two consequences
 * shape the tests. AUTO_INCREMENT values are consumed even on rollback, so new
 * ids are always taken from save() rather than assumed. And truncate() is DDL,
 * which MySQL commits implicitly, so it is deliberately not exercised here.
 */
class TestGeneratedLifecycle extends QueryTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->database->transactionBegin();
	}

	public function tearDown(): void {
		$this->database->transactionRollback();
		parent::tearDown();
	}

	/** An unsaved person with every NOT NULL column set. `email` is unique, hence the parameter. */
	private static function newPerson(string $email = 'new@test.net'): Person {
		$person = new Person();
		$person->name = 'New Person';
		$person->email = $email;
		$person->password = 'secret';

		return $person;
	}

	//
	// save()
	//

	public function testSaveInsertsANewRowAndReturnsItsId() {
		$person = self::newPerson();

		$id = $person->save();

		$this->assertIsInt($id);
		$this->assertSame($id, $person->id, 'save() should write the identity back onto the object');
		$this->assertSame('New Person', Person::load($id)->name);
		$this->assertSame(4, Person::countAll());
	}

	public function testSaveUpdatesARestoredRowAndReturnsNull() {
		$person = Person::load(1);
		$person->name = 'Renamed';

		$this->assertNull($person->save(), 'an UPDATE has no new identity to return');
		$this->assertSame('Renamed', Person::load(1)->name);
		$this->assertSame(3, Person::countAll(), 'updating must not insert');
	}

	public function testSaveWithAnExplicitIdInsertsUnderThatId() {
		$person = self::newPerson();

		$this->assertSame(1000, $person->save(1000));
		$this->assertSame(1000, $person->id);
		$this->assertSame('New Person', Person::load(1000)->name);
	}

	public function testSaveWithAnExplicitIdOnARestoredObjectInsertsACopy() {
		$person = Person::load(1);
		$person->email = 'copy@test.net';

		$person->save(1001);

		$this->assertSame('Adam Kluczyk', Person::load(1001)->name);
		$this->assertSame('klucznik@test.net', Person::load(1)->email, 'the original row is left alone');
	}

	public function testSavedObjectUpdatesOnItsNextSave() {
		$person = self::newPerson();
		$id = $person->save();
		$person->name = 'Renamed';

		$person->save();

		$this->assertSame('Renamed', Person::load($id)->name);
		$this->assertSame(4, Person::countAll(), 'the second save must not insert again');
	}

	//
	// reload(), clone(), delete()
	//

	public function testReloadDiscardsUnsavedChanges() {
		$person = Person::load(1);
		$person->name = 'Unsaved';

		$person->reload();

		$this->assertSame('Adam Kluczyk', $person->name);
	}

	public function testReloadPicksUpChangesMadeElsewhere() {
		$person = Person::load(1);
		$this->database->nonQuery("UPDATE `person` SET `name` = 'Changed elsewhere' WHERE `id` = 1");

		$person->reload();

		$this->assertSame('Changed elsewhere', $person->name);
	}

	public function testReloadOnAnUnsavedObjectThrows() {
		try {
			self::newPerson()->reload();
			$this->fail('reload() has nothing to reload for a row that was never written');
		} catch (CogException $exception) {
			$this->assertStringContainsString('unsaved', $exception->getMessage());
		}
	}

	public function testCloneIsAnUnsavedCopy() {
		$clone = Person::load(1)->clone();

		$this->assertNull($clone->id);
		$this->assertSame('Adam Kluczyk', $clone->name);

		$clone->email = 'clone@test.net';
		$id = $clone->save();

		$this->assertNotSame(1, $id);
		$this->assertSame('Adam Kluczyk', Person::load($id)->name);
	}

	public function testDeleteRemovesTheRow() {
		$person = self::newPerson();
		$id = $person->save();

		$person->delete();

		$this->assertNull(Person::load($id));
		$this->assertSame(3, Person::countAll());
	}

	public function testDeleteOnAnUnsavedObjectThrows() {
		$this->expectException(UndefinedPrimaryKeyException::class);

		self::newPerson()->delete();
	}

	//
	// Runtime defaults
	//

	/** `obj.creation_date` defaults to CURRENT_TIMESTAMP; the constructor sets it and save() has to write it. */
	public function testRuntimeDefaultIsWrittenAndReadBack() {
		$obj = new Obj();
		$obj->personId = 1;
		$obj->label = 'Timestamped';
		$written = $obj->creationDate;

		$loaded = Obj::load($obj->save());

		$this->assertInstanceOf(Carbon::class, $loaded->creationDate);
		$this->assertSame($written->format('Y-m-d H:i:s'), $loaded->creationDate->format('Y-m-d H:i:s'));
	}

	//
	// Optimistic locking (blog_post.modification_date is a timestamp column)
	//

	public function testSaveOnATimestampTableSucceedsWhenTheRowIsUnchanged() {
		$post = BlogPost::load(1);
		$post->title = 'Edited';

		$post->save();

		$this->assertSame('Edited', BlogPost::load(1)->title);
	}

	/**
	 * ON UPDATE CURRENT_TIMESTAMP moves the row's timestamp on with every
	 * successful save. Unless save() reads the new value back, the object's own
	 * next save would be refused as stale.
	 */
	public function testSaveRefreshesTheLocalTimestamp() {
		$this->database->nonQuery("UPDATE `blog_post` SET `modification_date` = '2020-01-01 00:00:00' WHERE `id` = 1");
		$post = BlogPost::load(1);
		$post->title = 'First edit';
		$post->save();

		$post->title = 'Second edit';
		$post->save();

		$this->assertSame('Second edit', BlogPost::load(1)->title);
	}

	public function testStaleSaveThrowsAndWritesNothing() {
		$post = BlogPost::load(1);
		// Another writer gets there first
		$this->database->nonQuery("UPDATE `blog_post` SET `modification_date` = '2020-01-01 00:00:00' WHERE `id` = 1");
		$post->title = 'Stale edit';

		try {
			$post->save();
			$this->fail('a save against a row modified since it was loaded should throw');
		} catch (OptimisticLockingException $exception) {
			$this->assertStringContainsString('BlogPost', $exception->getMessage());
		}

		$this->assertSame('Hello world', BlogPost::load(1)->title, 'a refused save must not reach the database');
	}

	public function testForceUpdateBypassesTheOptimisticLockingCheck() {
		$post = BlogPost::load(1);
		$this->database->nonQuery("UPDATE `blog_post` SET `modification_date` = '2020-01-01 00:00:00' WHERE `id` = 1");
		$post->title = 'Forced edit';

		$post->save(null, true);

		$this->assertSame('Forced edit', BlogPost::load(1)->title);
	}

	//
	// Reference properties (the object side of a foreign key column)
	//

	public function testAssigningAReferenceSetsTheForeignKeyColumn() {
		$post = BlogPost::load(1);
		$post->author = Person::load(2);
		$post->save();

		$reloaded = BlogPost::load(1);
		$this->assertSame(2, $reloaded->authorId);
		$this->assertSame('Maria Nowak', $reloaded->author->name);
	}

	public function testAssigningNullToANullableReferenceSavesNull() {
		$category = Category::load(1);
		$category->ownerObject = null;
		$category->save();

		$this->assertNull(Category::load(1)->owner);
	}

	public function testAssigningAnUnsavedReferenceThrows() {
		$post = BlogPost::load(1);

		try {
			$post->author = self::newPerson();
			$this->fail('a reference needs the referenced row to have an id');
		} catch (CogException $exception) {
			$this->assertStringContainsString('unsaved author', $exception->getMessage());
		}
	}

	public function testAssigningTheWrongClassToAReferenceThrows() {
		$post = BlogPost::load(1);

		$this->expectException(InvalidCastException::class);

		$post->author = Obj::load(1);
	}

	/** mock() is how a reference is set from an id alone, without a SELECT for the referenced row. */
	public function testMockedReferenceIsAssignedWithoutLoadingIt() {
		$post = BlogPost::load(1);
		$author = new Person();
		$author->mock(2);
		$before = $this->queryCount();

		$post->author = $author;

		$this->assertSame(2, $post->authorId);
		$this->assertSame($before, $this->queryCount());
	}

	//
	// Iteration and JSON
	//

	public function testGetIteratorYieldsTheColumnValuesByPropertyName() {
		$this->assertSame(
			['id' => 1, 'name' => 'Adam Kluczyk', 'email' => 'klucznik@test.net', 'emailVerified' => false, 'password' => 'f0af0f1e34c0c5f'],
			iterator_to_array(Person::load(1))
		);
	}

	public function testGetJsonEncodesTheIterator() {
		$decoded = json_decode(Person::load(2)->getJson(), true, 512, JSON_THROW_ON_ERROR);

		$this->assertSame(['id' => 2, 'name' => 'Maria Nowak', 'email' => 'maria@test.net', 'emailVerified' => true, 'password' => 'c1b8e2a90d3f764'], $decoded);
	}

	//
	// Finders generated per index
	//

	public function testUniqueIndexLoaderReturnsTheRowOrNull() {
		$this->assertSame(2, Person::loadByEmail('maria@test.net')->id);
		$this->assertNull(Person::loadByEmail('nobody@test.net'));
	}

	public function testPlainIndexLoaderReturnsAnArrayAndACount() {
		$this->assertSame(['Maria Nowak', 'Piotr Lewandowski'], self::pluck(Person::loadArrayByEmailVerified(true), 'name'));
		$this->assertSame(2, Person::countByEmailVerified(true));
		$this->assertSame([], Person::loadArrayByName('Nobody'));
		$this->assertSame(0, Person::countByName('Nobody'));
	}

	public function testForeignKeyLoaderReturnsTheChildRows() {
		$this->assertSame(['First object'], self::pluck(Obj::loadArrayByPersonId(1), 'label'));
		$this->assertSame(1, Obj::countByPersonId(1));
	}

	public function testLoadAllAndCountAll() {
		$this->assertCount(3, Person::loadAll());
		$this->assertSame(3, Person::countAll());
	}

	public function testQuerySingleReturnsOneObjectOrNull() {
		$this->assertSame('Maria Nowak', Person::querySingle(QQ::equal((new QQNodePerson())->id, 2))->name);
		$this->assertNull(Person::querySingle(QQ::equal((new QQNodePerson())->id, 999)));
	}

	public function testQueryCursorInstantiatesOneRowAtATime() {
		$cursor = Person::queryCursor(QQ::all(), QQ::orderBy((new QQNodePerson())->id));

		$names = [];
		while ($person = Person::instantiateCursor($cursor)) {
			$names[] = $person->name;
		}

		$this->assertSame(['Adam Kluczyk', 'Maria Nowak', 'Piotr Lewandowski'], $names);
	}
}
