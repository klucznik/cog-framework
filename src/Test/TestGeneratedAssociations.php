<?php declare(strict_types=1);

namespace Cog\Test;

use App\Data\Asset;
use App\Data\Category;
use App\Data\Obj;
use App\Data\Person;
use App\Data\PersonProfile;
use App\Data\Tag;
use Cog\Database\Exceptions\UndefinedPrimaryKeyException;
use Cog\Exceptions\InvalidCastException;

/**
 * The association methods on the generated ORM classes, driven against the
 * fixture database: reverse references (Person owns Categories, Obj has
 * Assets), the unique reverse reference that makes Person->personProfile a
 * single object, and the many-to-many methods generated from tag_obj_assn and
 * from the person_person_assn graph association.
 *
 * The fixture shapes the choices below. Reverse-reference unassociate methods
 * null the foreign key, which the database refuses on a NOT NULL column, so
 * the nullable `category.owner` is the reference they are exercised on.
 * `person_profile.person_id` is NOT NULL for the same reason, so detaching a
 * profile is not exercised, only attaching one.
 *
 * Every test runs inside a transaction that tearDown() rolls back.
 */
class TestGeneratedAssociations extends QueryTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->database->transactionBegin();
	}

	public function tearDown(): void {
		$this->database->transactionRollback();
		parent::tearDown();
	}

	private static function savedPerson(): Person {
		$person = new Person();
		$person->name = 'New Person';
		$person->email = 'new@test.net';
		$person->password = 'secret';
		$person->save();

		return $person;
	}

	//
	// Reverse references (one-to-many)
	//

	public function testReverseReferenceArrayAndCount() {
		$person = Person::load(1);

		$this->assertSame(['Root'], self::pluck($person->getCategoryAsOwnerArray(), 'name'));
		$this->assertSame(1, $person->countCategoriesAsOwner());
	}

	public function testReverseReferenceOnAnUnsavedObjectIsEmptyWithoutAQuery() {
		$person = new Person();
		$before = $this->queryCount();

		$this->assertSame([], $person->getCategoryAsOwnerArray());
		$this->assertSame(0, $person->countCategoriesAsOwner());
		$this->assertSame($before, $this->queryCount());
	}

	public function testAssociateSetsTheForeignKeyOnTheChild() {
		$person = Person::load(1);
		$orphan = Category::load(3); // owner is NULL in the fixture

		$person->associateCategoryAsOwner($orphan);

		$this->assertSame(1, Category::load(3)->owner);
		$this->assertSame(['Archive', 'Root'], self::pluck($person->getCategoryAsOwnerArray(), 'name'));
	}

	public function testUnassociateClearsTheForeignKeyOnThatChildOnly() {
		$person = Person::load(1);
		$person->associateCategoryAsOwner(Category::load(3));

		$person->unassociateCategoryAsOwner(Category::load(1));

		$this->assertNull(Category::load(1)->owner);
		$this->assertSame(['Archive'], self::pluck($person->getCategoryAsOwnerArray(), 'name'));
	}

	public function testUnassociateLeavesAChildOwnedBySomeoneElseAlone() {
		Person::load(1)->unassociateCategoryAsOwner(Category::load(2)); // owned by person 2

		$this->assertSame(2, Category::load(2)->owner);
	}

	public function testUnassociateAllClearsEveryChild() {
		$person = Person::load(1);
		$person->associateCategoryAsOwner(Category::load(3));

		$person->unassociateAllCategoriesAsOwner();

		$this->assertSame(0, $person->countCategoriesAsOwner());
		$this->assertSame(0, Category::countByOwner(1));
		$this->assertSame(2, Category::load(2)->owner, 'other owners keep their categories');
	}

	public function testDeleteAssociatedDeletesThatChildRow() {
		$obj = Obj::load(1);

		$obj->deleteAssociatedAsset(Asset::load(1));

		$this->assertNull(Asset::load(1));
		$this->assertSame(['manual.pdf'], self::pluck($obj->getAssetArray(), 'filename'));
	}

	public function testDeleteAllDeletesEveryChildRow() {
		$obj = Obj::load(1);

		$obj->deleteAllAssets();

		$this->assertSame(0, $obj->countAssets());
		$this->assertSame(0, Asset::countAll());
	}

	public function testAssociateOnAnUnsavedParentThrows() {
		$this->expectException(UndefinedPrimaryKeyException::class);

		(new Person())->associateCategoryAsOwner(Category::load(3));
	}

	public function testAssociateAnUnsavedChildThrows() {
		$this->expectException(UndefinedPrimaryKeyException::class);

		Person::load(1)->associateCategoryAsOwner(new Category());
	}

	//
	// Unique reverse reference (one-to-one through person_profile.person_id)
	//

	public function testUniqueReverseReferenceIsASingleObjectOrNull() {
		$this->assertSame('Maintainer of the framework.', Person::load(1)->personProfile->bio);
		$this->assertNull(Person::load(3)->personProfile);
	}

	public function testUniqueReverseReferenceIsLoadedOnceAndCached() {
		$person = Person::load(1);
		$before = $this->queryCount();

		$first = $person->personProfile;
		$second = $person->personProfile;

		$this->assertSame($first, $second);
		$this->assertSame(1, $this->queryCount() - $before);
	}

	public function testAssigningAProfileSavesItAgainstThePerson() {
		$person = Person::load(3);
		$profile = new PersonProfile();
		$profile->bio = 'Newcomer';

		$person->personProfile = $profile;
		$person->save();

		$this->assertNotNull($profile->id, 'saving the person should save the new profile');
		$this->assertSame(3, $profile->personId);
		$this->assertSame('Newcomer', PersonProfile::loadByPersonId(3)->bio);
		$this->assertSame('Newcomer', Person::load(3)->personProfile->bio);
	}

	public function testAssigningTheWrongClassAsAProfileThrows() {
		$person = Person::load(3);

		$this->expectException(InvalidCastException::class);

		$person->personProfile = Obj::load(1);
	}

	public function testDeletingThePersonDeletesItsProfile() {
		$person = self::savedPerson();
		$profile = new PersonProfile();
		$profile->personId = $person->id;
		$profile->bio = 'Short-lived';
		$profileId = $profile->save();

		$person->delete();

		$this->assertNull(PersonProfile::load($profileId));
	}

	//
	// Many-to-many (tag_obj_assn)
	//

	public function testManyToManyArrayAndCountFromBothSides() {
		$obj = Obj::load(1);

		$this->assertSame(['php', 'testing'], self::pluck($obj->getTagArray(), 'name'));
		$this->assertSame(2, $obj->countTags());
		$this->assertSame(['First object'], self::pluck(Tag::load(1)->getObjArray(), 'label'));
		$this->assertSame(1, Tag::load(1)->countObjs());
	}

	public function testIsAssociated() {
		$obj = Obj::load(1);

		$this->assertTrue($obj->isTagAssociated(Tag::load(1)));
		$this->assertFalse($obj->isTagAssociated(Tag::load(3)));
	}

	public function testAssociateInsertsARowVisibleFromBothSides() {
		$obj = Obj::load(2);
		$tag = Tag::load(1);

		$obj->associateTag($tag);

		$this->assertTrue($obj->isTagAssociated($tag));
		$this->assertTrue($tag->isObjAssociated($obj));
		$this->assertSame(['orm', 'php'], self::pluck($obj->getTagArray(), 'name'));
		$this->assertSame(['First object', 'Second object'], self::pluck($tag->getObjArray(), 'label'));
	}

	public function testUnassociateRemovesOnlyThatRow() {
		$obj = Obj::load(1);

		$obj->unassociateTag(Tag::load(1));

		$this->assertSame(['testing'], self::pluck($obj->getTagArray(), 'name'));
		$this->assertSame(0, Tag::load(1)->countObjs());
	}

	public function testUnassociateAllRemovesEveryRowForThisSide() {
		$obj = Obj::load(1);

		$obj->unassociateAllTags();

		$this->assertSame(0, $obj->countTags());
		$this->assertSame(0, Tag::load(1)->countObjs());
		$this->assertSame(['Second object'], self::pluck(Tag::load(3)->getObjArray(), 'label'), 'other objects keep their tags');
	}

	public function testManyToManyOnAnUnsavedObjectIsEmptyWithoutAQuery() {
		$obj = new Obj();
		$before = $this->queryCount();

		$this->assertSame([], $obj->getTagArray());
		$this->assertSame(0, $obj->countTags());
		$this->assertSame($before, $this->queryCount());
	}

	public function testManyToManyAssociateOnAnUnsavedObjectThrows() {
		$this->expectException(UndefinedPrimaryKeyException::class);

		(new Obj())->associateTag(Tag::load(1));
	}

	public function testManyToManyAssociateAnUnsavedPartnerThrows() {
		$this->expectException(UndefinedPrimaryKeyException::class);

		Obj::load(1)->associateTag(new Tag());
	}

	//
	// Graph association (person_person_assn: person_id -> friend_id)
	//
	// The fixture rows are (1,2), (1,3) and (2,3). getPersonArray() follows
	// person_id -> friend_id; getParentPersonArray() follows it the other way.
	//

	public function testGraphAssociationHasADistinctMethodPerDirection() {
		$adam = Person::load(1);
		$maria = Person::load(2);

		$this->assertSame(['Maria Nowak', 'Piotr Lewandowski'], self::pluck($adam->getPersonArray(), 'name'));
		$this->assertSame([], $adam->getParentPersonArray());
		$this->assertSame(['Adam Kluczyk'], self::pluck($maria->getParentPersonArray(), 'name'));
		$this->assertTrue($adam->isPersonAssociated($maria));
		$this->assertFalse($adam->isParentPersonAssociated($maria), 'the (1,2) row only points one way');
	}

	public function testGraphAssociateAddsOneDirection() {
		$maria = Person::load(2);
		$piotr = Person::load(3);
		$this->assertFalse($piotr->isPersonAssociated($maria));

		$piotr->associatePerson($maria);

		$this->assertTrue($piotr->isPersonAssociated($maria));
		$this->assertTrue($maria->isParentPersonAssociated($piotr));
		$this->assertSame(1, $piotr->countPersons());
		$this->assertSame(['Adam Kluczyk', 'Piotr Lewandowski'], self::pluck($maria->getParentPersonArray(), 'name'));
	}

	public function testGraphAssociateFromTheOtherSideWritesTheMirroredRow() {
		$adam = Person::load(1);
		$maria = Person::load(2);

		$adam->associateParentPerson($maria); // the (2,1) row

		$this->assertTrue($maria->isPersonAssociated($adam));
		$this->assertSame(['Adam Kluczyk', 'Piotr Lewandowski'], self::pluck($maria->getPersonArray(), 'name'));
	}

	public function testGraphUnassociateRemovesOneDirection() {
		$adam = Person::load(1);
		$maria = Person::load(2);

		$adam->unassociatePerson($maria); // the (1,2) row

		$this->assertSame(['Piotr Lewandowski'], self::pluck($adam->getPersonArray(), 'name'));
		$this->assertSame(0, $maria->countParentPersons());
		$this->assertSame(1, $maria->countPersons(), 'the (2,3) row is untouched');
	}

	public function testGraphUnassociateAllOnOneSideLeavesTheOther() {
		$maria = Person::load(2);

		$maria->unassociateAllPersons(); // the (2,3) row

		$this->assertSame(0, $maria->countPersons());
		$this->assertSame(['Adam Kluczyk'], self::pluck($maria->getParentPersonArray(), 'name'), 'rows where Maria is the friend are untouched');
	}

	//
	// delete() removes the association rows of the deleted object
	//

	public function testDeletingAnObjectRemovesItsManyToManyRows() {
		$tag = new Tag();
		$tag->name = 'short-lived';
		$tag->save();
		$obj = Obj::load(1);
		$obj->associateTag($tag);
		$this->assertSame(3, $obj->countTags());

		$tag->delete();

		$this->assertSame(2, $obj->countTags());
	}

	public function testDeletingAPersonRemovesBothDirectionsOfTheGraph() {
		$person = self::savedPerson();
		$person->associatePerson(Person::load(1));
		$person->associateParentPerson(Person::load(2));
		$id = $person->id;

		$person->delete();

		$row = $this->database->query(sprintf(
			'SELECT COUNT(*) FROM `person_person_assn` WHERE `person_id` = %d OR `friend_id` = %d', $id, $id
		))->fetchRow();
		$this->assertSame(0, (int)$row[0]);
	}
}
