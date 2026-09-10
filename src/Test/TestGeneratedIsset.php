<?php declare(strict_types=1);

namespace Cog\Test;

use App\Data\BlogPost;
use App\Data\Category;
use App\Data\Person;
use App\Data\PersonProfile;
use Cog\Exceptions\UndefinedPropertyException;

/**
 * isset(), empty() and ?? against the generated ORM classes.
 *
 * Every property of a generated class is a real declaration, so PHP answers
 * these natively and no __isset() is involved. What differs is the cost:
 *
 * - A column or an expansion collection is plain storage. isset() reads it and
 *   never costs a query.
 * - A reference or an adjoined object is a hooked property. isset(), empty() and
 *   ?? all run its get hook, which loads the object the first time and hands
 *   back the cached one after that. So isset() on a reference with a foreign key
 *   set costs the load - once - while a reference whose key is null is answered
 *   without a query, because the hook does not look up a null key.
 *
 * The tests assert on the query count so a change to either behaviour is caught.
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

	/**
	 * Nothing is declared under that name. isset() is false without consulting
	 * anything, but ?? fetches the value, which for an undeclared name means
	 * Cog\Base::__get - and that throws on what is, after all, a typo.
	 */
	public function testUnknownPropertyIsNotSetAndCoalescingItThrows() {
		$person = Person::load(1);

		$this->assertFalse(isset($person->noSuchProperty));

		$this->expectException(UndefinedPropertyException::class);
		$person->noSuchProperty ?? 'fallback';
	}

	/** An id is set on a loaded row and absent on a new one. */
	public function testPrimaryKeyReflectsWhetherTheRowIsSaved() {
		$this->assertTrue(isset(Person::load(1)->id));
		$this->assertFalse(isset((new Person())->id));
	}

	//
	// References: answered by the get hook, which loads once
	//

	public function testIssetOnAReferenceLoadsItOnce() {
		$blogPost = BlogPost::load(1);
		$before = $this->queryCount();

		$this->assertTrue(isset($blogPost->author));
		$this->assertSame($before + 1, $this->queryCount(), 'the first isset() runs the get hook, which loads the author');

		$this->assertTrue(isset($blogPost->author));
		$this->assertSame('Adam Kluczyk', ($blogPost->author ?? null)?->name);
		$this->assertSame($before + 1, $this->queryCount(), 'the loaded author is reused');
	}

	public function testEmptyOnASetReferenceLoadsIt() {
		$blogPost = BlogPost::load(1);
		$before = $this->queryCount();

		$this->assertFalse(empty($blogPost->author));

		$this->assertGreaterThan($before, $this->queryCount());
	}

	/** A null foreign key is answered by the hook without a lookup. */
	public function testReferenceWithANullForeignKeyIsNotSetAndCostsNothing() {
		// category 3 has no owner
		$category = Category::load(3);
		$before = $this->queryCount();

		$this->assertNull($category->owner);
		$this->assertFalse(isset($category->ownerObject));
		$this->assertTrue(empty($category->ownerObject));
		$this->assertSame('fallback', $category->ownerObject ?? 'fallback');

		$this->assertSame($before, $this->queryCount());
	}

	//
	// Adjoined objects and unexpanded collections
	//

	/** The adjoined object is looked up by the hook, so isset() loads it. */
	public function testIssetOnAnAdjoinedObjectLoadsIt() {
		$person = Person::load(1);
		$before = $this->queryCount();

		$this->assertTrue(isset($person->personProfile));

		$this->assertGreaterThan($before, $this->queryCount());
	}

	/** Once it is in hand, isset() is free. */
	public function testLoadedAdjoinedObjectIsSet() {
		$person = Person::load(1);
		$this->assertNotNull($person->personProfile);
		$before = $this->queryCount();

		$this->assertTrue(isset($person->personProfile));

		$this->assertSame($before, $this->queryCount());
	}

	/** A collection that was never expanded is not set, rather than an empty array, and costs nothing. */
	public function testUnexpandedCollectionIsNotSet() {
		$person = Person::load(1);
		$before = $this->queryCount();

		$this->assertFalse(isset($person->_objArray));
		$this->assertFalse(isset($person->_blogPostAsAuthorArray));

		$this->assertSame($before, $this->queryCount());
	}

	/** Columns and collections stay query-free however many are tested. */
	public function testIssetOnStorageNeverQueries() {
		$person = Person::load(1);
		$before = $this->queryCount();

		foreach (['id', 'name', 'email', 'emailVerified', 'password',
			'_objArray', '_blogPostAsAuthorArray', '_personArray', 'noSuchProperty'] as $property) {
			isset($person->$property);
		}

		$this->assertSame($before, $this->queryCount());
	}
}
