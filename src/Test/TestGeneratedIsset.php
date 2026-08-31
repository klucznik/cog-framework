<?php declare(strict_types=1);

namespace Cog\Test;

use App\Data\BlogPost;
use App\Data\Category;
use App\Data\Person;
use App\Data\PersonProfile;

/**
 * isset(), empty() and ?? against the generated ORM classes.
 *
 * These all route through __isset(), which PHP consults before __get(). Cog\Base
 * used to declare one returning false unconditionally, so every magic property on
 * every generated class reported as unset: isset() false, empty() true, and
 * `$person->name ?? $default` handing back the default even with a name set -
 * silently, with no error. Removing that fixed ??; generating a real __isset()
 * per class fixes isset() and empty() too.
 *
 * The other half of the contract is that none of it costs a query. __get()
 * lazy-loads references and adjoined objects, so a generated __isset() that
 * delegated to it would put a SELECT behind what reads as a null check - an N+1
 * waiting to happen. It reads the backing fields instead, and the tests below
 * assert the query count does not move.
 *
 * That guarantee covers isset(), not empty(). PHP evaluates empty($x) as
 * !isset($x) || !$x, so a property that __isset() reports as set is then read
 * through __get() to test its truthiness. empty() on a set reference therefore
 * loads it; no __isset() implementation can change that.
 */
class TestGeneratedIsset extends QueryTestCase {

	//
	// Scalar columns
	//

	public function testScalarColumnThatIsSet() {
		$person = Person::load(1);

		$this->assertTrue(isset($person->name));
		$this->assertFalse(empty($person->name));
		$this->assertSame('Adam Kluczyk', $person->name ?? 'fallback');
	}

	/** A nullable column with no value reports as unset, which is what isset() means. */
	public function testScalarColumnThatIsNull() {
		$profile = PersonProfile::load(2);

		$this->assertNull($profile->website);
		$this->assertFalse(isset($profile->website));
		$this->assertTrue(empty($profile->website));
		$this->assertSame('fallback', $profile->website ?? 'fallback');
	}

	public function testUnknownPropertyIsNotSet() {
		$person = Person::load(1);

		$this->assertFalse(isset($person->noSuchProperty));
		$this->assertSame('fallback', $person->noSuchProperty ?? 'fallback');
	}

	/** An id is set on a loaded row and absent on a new one. */
	public function testPrimaryKeyReflectsWhetherTheRowIsSaved() {
		$this->assertTrue(isset(Person::load(1)->id));
		$this->assertFalse(isset((new Person())->id));
	}

	//
	// References: answered from the foreign key, without loading
	//

	public function testReferenceWithAForeignKeySet() {
		$blogPost = BlogPost::load(1);
		$before = $this->queryCount();

		$this->assertTrue(isset($blogPost->author));

		$this->assertSame($before, $this->queryCount(), 'isset() on a reference must not load it');
	}

	/**
	 * empty() is not free the way isset() is, and cannot be made so.
	 *
	 * PHP evaluates empty($x) as !isset($x) || !$x, so once __isset() answers true
	 * it calls __get() to test the value's truthiness - which loads the reference.
	 * Nothing in __isset() can prevent that. isset() is the query-free check;
	 * empty() on a set reference costs the load it would have cost anyway.
	 */
	public function testEmptyOnASetReferenceLoadsIt() {
		$blogPost = BlogPost::load(1);
		$before = $this->queryCount();

		$this->assertFalse(empty($blogPost->author));

		$this->assertGreaterThan($before, $this->queryCount());
	}

	/** When __isset() answers false, empty() short-circuits and stays free. */
	public function testEmptyOnAnUnsetReferenceDoesNotLoad() {
		$category = Category::load(3);
		$before = $this->queryCount();

		$this->assertTrue(empty($category->ownerObject));

		$this->assertSame($before, $this->queryCount());
	}

	public function testReferenceWithANullForeignKey() {
		// category 3 has no owner
		$category = Category::load(3);
		$before = $this->queryCount();

		$this->assertNull($category->owner);
		$this->assertFalse(isset($category->ownerObject));

		$this->assertSame($before, $this->queryCount());
	}

	/** Reading the reference still loads it - only isset() is free. */
	public function testReadingAReferenceStillLoadsIt() {
		$blogPost = BlogPost::load(1);
		$before = $this->queryCount();

		$this->assertSame('Adam Kluczyk', $blogPost->author->name);

		$this->assertGreaterThan($before, $this->queryCount(), 'reading a reference is expected to load it');
	}

	//
	// Adjoined objects and unexpanded collections
	//

	/**
	 * An adjoined object that has not been loaded reads as not set: saying
	 * otherwise would mean querying for it, which isset() must not do.
	 */
	public function testUnloadedAdjoinedObjectIsNotSet() {
		$person = Person::load(1);
		$before = $this->queryCount();

		$this->assertFalse(isset($person->personProfile));

		$this->assertSame($before, $this->queryCount(), 'isset() on an adjoined object must not load it');
	}

	/** Once it is in hand, it is set. */
	public function testLoadedAdjoinedObjectIsSet() {
		$person = Person::load(1);

		$this->assertNotNull($person->personProfile);
		$this->assertTrue(isset($person->personProfile));
	}

	/** A collection that was never expanded is not set, rather than an empty array. */
	public function testUnexpandedCollectionIsNotSet() {
		$person = Person::load(1);
		$before = $this->queryCount();

		$this->assertFalse(isset($person->_objArray));
		$this->assertFalse(isset($person->_blogPostAsAuthorArray));

		$this->assertSame($before, $this->queryCount());
	}

	//
	// The whole surface stays query-free
	//

	/** Every documented property of a loaded row, tested at once, costing nothing. */
	public function testIssetNeverQueries() {
		$person = Person::load(1);
		$before = $this->queryCount();

		foreach (['id', 'name', 'email', 'emailVerified', 'password', 'personProfile',
			'_objArray', '_blogPostAsAuthorArray', '_personArray', 'noSuchProperty'] as $property) {
			isset($person->$property);
		}

		$this->assertSame($before, $this->queryCount());
	}
}
