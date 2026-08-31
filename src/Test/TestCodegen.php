<?php

namespace Cog\Test;

use Carbon\Carbon;
use Cog\Exceptions\CogException;
use PHPUnit\Framework\TestCase;

/**
 * Asserts that the code generation performed by CodegenFixture during bootstrap
 * actually produced a usable ORM layer for the `cog_framework_test` fixture database.
 *
 * This is the first file in the test suite on purpose: everything the generated
 * classes are used for downstream depends on it having worked, so when codegen
 * breaks the failure should be reported here rather than as a pile of
 * "class not found" errors further down.
 */
class TestCodegen extends TestCase {

	/**
	 * ORM tables in cog_framework_test.sql, and the class names they generate to.
	 *
	 * `blog_type` is not here: the `_type` suffix makes it a type table, covered
	 * by testGeneratedTypeClass. Neither is `tag_obj_assn`: the `_assn` suffix
	 * makes it an association table, which produces methods on Tag and Obj
	 * rather than a class of its own (testAssociationTableGeneratesNoClass).
	 */
	private const array TABLE_CLASSES = [
		'asset' => 'Asset',
		'blog_post' => 'BlogPost',
		'category' => 'Category',
		'obj' => 'Obj',
		'person' => 'Person',
		'person_profile' => 'PersonProfile',
		'tag' => 'Tag',
	];

	/**
	 * Generation happens here rather than in the PHPUnit bootstrap so that it is
	 * recorded by code coverage. It is idempotent, so only the first test in the
	 * suite does the work; the call is repeated per test to keep this class
	 * runnable on its own through --filter.
	 */
	public function setUp(): void {
		CodegenFixture::generate();
	}

	public function testGenerationSucceeded() {
		$this->assertNull(CodegenFixture::getError(), 'code generation failed during bootstrap');
	}

	public function testReport() {
		$report = implode("\n", CodegenFixture::getReport());

		$this->assertNotEmpty($report);
		$this->assertStringNotContainsStringIgnoringCase('failed', $report);

		foreach (self::TABLE_CLASSES as $className) {
			$this->assertStringContainsString($className, $report);
		}
	}

	/**
	 * The generated abstract classes, one per table, plus the query nodes that
	 * go with them. These are overwritten on every run.
	 */
	public function testGeneratedFiles() {
		foreach (self::TABLE_CLASSES as $className) {
			$this->assertFileExists(CodegenFixture::getBuildPath('generated/Data/' . $className . 'Gen.php'));
			$this->assertFileExists(CodegenFixture::getBuildPath('generated/Node/QQNode' . $className . '.php'));
			$this->assertFileExists(CodegenFixture::getBuildPath('generated/Node/QQReverseReferenceNode' . $className . '.php'));
		}
	}

	/**
	 * The subclasses meant to be edited by hand. They carry OverwriteFlag="false",
	 * so they are only written when missing.
	 */
	public function testGeneratedSubclassFiles() {
		foreach (self::TABLE_CLASSES as $className) {
			$this->assertFileExists(CodegenFixture::getBuildPath('app/Data/' . $className . '.php'));
		}
	}

	public function testGeneratedFilesAreValidPhp() {
		$files = [];
		$directory = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(CodegenFixture::getBuildPath('generated'), \FilesystemIterator::SKIP_DOTS)
		);

		/** @var \SplFileInfo $file */
		foreach ($directory as $file) {
			if ($file->getExtension() === 'php') {
				$files[] = $file->getPathname();
			}
		}

		$this->assertNotEmpty($files, 'no generated files to lint');

		foreach ($files as $file) {
			exec(sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($file)), $output, $status);
			$this->assertSame(0, $status, sprintf("%s is not valid PHP:\n%s", $file, implode("\n", $output)));
		}
	}

	/**
	 * The generated classes have to be loadable, not merely syntactically valid -
	 * that is what proves the templates emit namespaces and parent classes the
	 * framework actually provides.
	 */
	public function testGeneratedClassesLoad() {
		foreach (self::TABLE_CLASSES as $className) {
			$this->assertTrue(class_exists('Generated\Data\\' . $className . 'Gen'), $className . 'Gen does not load');
			$this->assertTrue(class_exists('Generated\Node\QQNode' . $className), 'QQNode' . $className . ' does not load');
			$this->assertTrue(class_exists('App\Data\\' . $className), $className . ' subclass does not load');
		}
	}

	/**
	 * A node carries the data class it selects fields from as a fully qualified
	 * name, so the query layer never has to know the application's namespace.
	 * Cog\Query\QQNode and QQAssociationNode call getSelectFields() on it, and
	 * generated instantiation code calls instantiateDbRow()/expandArray(), all
	 * through call_user_func - which fails at run time, inside generated code,
	 * if the name does not resolve. Assert it here instead.
	 */
	public function testNodeClassNameQualified() {
		foreach (self::TABLE_CLASSES as $className) {
			$nodeClass = 'Generated\Node\QQNode' . $className;
			$node = new $nodeClass();

			$this->assertNotEmpty($node->classNameQualified, $nodeClass . ' has no classNameQualified');
			$this->assertTrue(
				class_exists($node->classNameQualified),
				$nodeClass . ' points at ' . $node->classNameQualified . ', which does not load'
			);
			$this->assertStringEndsWith(
				'\\' . $node->className,
				$node->classNameQualified,
				$nodeClass . ' disagrees with its own className'
			);
			$this->assertTrue(
				method_exists($node->classNameQualified, 'getSelectFields'),
				$node->classNameQualified . ' is missing getSelectFields(), which the query layer calls on it'
			);
		}
	}

	/**
	 * codegen.xml may list several <templates/> directories, and a later one overrides
	 * an earlier one a whole module directory at a time. The fixture layers an overlay
	 * containing only db_orm/class_nodes, so the Node classes must come from it while
	 * everything else still comes from the base directory underneath.
	 */
	public function testOverlaidTemplateDirectoryWins() {
		foreach (self::TABLE_CLASSES as $className) {
			$this->assertStringContainsString(
				CodegenFixture::OVERLAY_MARKER,
				file_get_contents(CodegenFixture::getBuildPath('generated/Node/QQNode' . $className . '.php')),
				'QQNode' . $className . ' was not generated from the overlaid class_nodes templates'
			);

			$this->assertStringNotContainsString(
				CodegenFixture::OVERLAY_MARKER,
				file_get_contents(CodegenFixture::getBuildPath('generated/Data/' . $className . 'Gen.php')),
				$className . 'Gen should still come from the base template directory'
			);
		}
	}

	/** The subclass is what application code uses, and it has to extend the generated half. */
	public function testSubclassExtendsGeneratedClass() {
		foreach (self::TABLE_CLASSES as $className) {
			$this->assertTrue(
				is_subclass_of('App\Data\\' . $className, 'Generated\Data\\' . $className . 'Gen'),
				$className . ' does not extend ' . $className . 'Gen'
			);
		}
	}

	/** Columns become typed properties, and foreign keys become associated objects. */
	public function testGeneratedPersonClass() {
		$person = new \ReflectionClass('Generated\Data\PersonGen');

		foreach (['intId', 'strName', 'strEmail', 'blnEmailVerified', 'strPassword'] as $property) {
			$this->assertTrue($person->hasProperty($property), 'PersonGen is missing the ' . $property . ' property');
		}

		$this->assertTrue($person->hasMethod('load'), 'PersonGen is missing load()');
		$this->assertTrue($person->hasMethod('save'), 'PersonGen is missing save()');
		$this->assertTrue($person->hasMethod('delete'), 'PersonGen is missing delete()');
		$this->assertTrue($person->hasMethod('loadByEmail'), 'PersonGen is missing the unique-index loader loadByEmail()');
	}

	/**
	 * `obj.creation_date` is `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`. That
	 * default is not a constant expression, so it is applied by a generated
	 * constructor rather than by the property initializer.
	 */
	public function testCurrentTimestampDefault() {
		$obj = new \App\Data\Obj();

		$this->assertInstanceOf(Carbon::class, $obj->creationDate);
		$this->assertEqualsWithDelta(Carbon::now()->getTimestamp(), $obj->creationDate->getTimestamp(), 5);
	}

	/** Tables without a run-time default get no generated constructor at all. */
	public function testNoConstructorWithoutRuntimeDefaults() {
		$this->assertNull(
			(new \ReflectionClass('Generated\Data\PersonGen'))->getConstructor(),
			'PersonGen has no columns needing a run-time default, so it should not declare a constructor'
		);

		$this->assertNull(
			(new \ReflectionClass('Generated\Data\BlogPostGen'))->getConstructor(),
			'blog_post.modification_date is a database-maintained timestamp column, not a value the object should preset'
		);
	}

	/**
	 * `blog_post.modification_date` is a `timestamp` column, which the generator
	 * treats as the optimistic locking token rather than as ordinary data.
	 */
	public function testTimestampColumnDrivesOptimisticLocking() {
		$blogPost = new \ReflectionClass('Generated\Data\BlogPostGen');

		$this->assertTrue($blogPost->hasProperty('dttModificationDate'), 'BlogPostGen is missing the timestamp column property');
		$this->assertStringContainsString(
			'OptimisticLockingException',
			file_get_contents(CodegenFixture::getBuildPath('generated/Data/BlogPostGen.php')),
			'a timestamp column should make save() raise OptimisticLockingException on a stale write'
		);
	}

	/**
	 * `blog_type` carries the `_type` suffix, so it generates an enumerated type
	 * class built from the table's rows instead of an ORM class.
	 */
	public function testGeneratedTypeClass() {
		$this->assertFileExists(CodegenFixture::getBuildPath('generated/Type/BlogTypeGen.php'));
		$this->assertFileExists(CodegenFixture::getBuildPath('app/Type/BlogType.php'));

		$this->assertTrue(class_exists('App\Type\BlogType'), 'BlogType does not load');
		$this->assertTrue(is_subclass_of('App\Type\BlogType', 'Generated\Type\BlogTypeGen'));

		$this->assertFileDoesNotExist(
			CodegenFixture::getBuildPath('generated/Data/BlogTypeGen.php'),
			'a type table should not also generate an ORM class'
		);
	}

	/** The rows of the type table become constants, name and token lookups. */
	public function testGeneratedTypeClassValues() {
		$blogType = 'App\Type\BlogType';

		$this->assertSame(1, $blogType::POST);
		$this->assertSame(2, $blogType::EDITORIAL);
		$this->assertSame(2, $blogType::MAX_ID);

		$this->assertSame('Post', $blogType::ToString($blogType::POST));
		$this->assertSame('Editorial', $blogType::ToString($blogType::EDITORIAL));

		$this->assertSame([1 => 'Post', 2 => 'Editorial'], $blogType::$NameArray);
		$this->assertSame([1 => 'POST', 2 => 'EDITORIAL'], $blogType::$TokenArray);
	}

	/** An unknown id is a CogException, not a silent null. */
	public function testGeneratedTypeClassRejectsUnknownId() {
		$this->expectException(CogException::class);

		call_user_func(['App\Type\BlogType', 'ToString'], 99);
	}

	/**
	 * `tag_obj_assn` carries the `_assn` suffix and a two-column primary key, so
	 * it is read as a many-to-many link rather than as an entity.
	 */
	public function testAssociationTableGeneratesNoClass() {
		$this->assertFalse(class_exists('Generated\Data\TagObjAssnGen'), 'an association table should not generate an ORM class');
		$this->assertFileDoesNotExist(CodegenFixture::getBuildPath('generated/Data/TagObjAssnGen.php'));
		$this->assertFileDoesNotExist(CodegenFixture::getBuildPath('app/Data/TagObjAssn.php'));
	}

	/** Instead, both sides of the association get the manipulation methods. */
	public function testManyToManyMethods() {
		$tag = new \ReflectionClass('Generated\Data\TagGen');

		foreach (['getObjArray', 'countObjs', 'isObjAssociated', 'associateObj', 'unassociateObj', 'unassociateAllObjs'] as $method) {
			$this->assertTrue($tag->hasMethod($method), 'TagGen is missing ' . $method . '()');
		}

		$obj = new \ReflectionClass('Generated\Data\ObjGen');

		foreach (['getTagArray', 'countTags', 'isTagAssociated', 'associateTag', 'unassociateTag', 'unassociateAllTags'] as $method) {
			$this->assertTrue($obj->hasMethod($method), 'ObjGen is missing ' . $method . '()');
		}
	}

	/** The query nodes needed to traverse the association in both directions. */
	public function testManyToManyQueryNodes() {
		$this->assertTrue(class_exists('Generated\Node\QQNodeTagObj'), 'missing the tag -> obj association node');
		$this->assertTrue(class_exists('Generated\Node\QQNodeObjTag'), 'missing the obj -> tag association node');
	}

	/** `blog_post.author_id` keys to `person.id`, so BlogPost gets an Author object. */
	public function testGeneratedForeignKey() {
		$blogPost = new \ReflectionClass('Generated\Data\BlogPostGen');

		$this->assertTrue($blogPost->hasProperty('intAuthorId'), 'BlogPostGen is missing the author_id column property');
		$this->assertTrue($blogPost->hasProperty('objAuthor'), 'BlogPostGen is missing the Author associated object');
		$this->assertTrue($blogPost->hasMethod('loadArrayByAuthorId'), 'BlogPostGen is missing loadArrayByAuthorId()');
	}

	//
	// The reference shapes `category`, `person_profile` and `person_person_assn`
	// were added to the schema for: a self-reference, a reference to a type table,
	// a foreign key not named *_id, a one-to-one, and a self-joining association.
	//

	/** `category.parent_id` keys back to `category`, so the class references itself. */
	public function testSelfReferencingForeignKey() {
		$category = new \ReflectionClass('Generated\Data\CategoryGen');

		$this->assertTrue($category->hasProperty('intParentId'), 'CategoryGen is missing the parent_id column property');
		$this->assertTrue($category->hasProperty('objParent'), 'CategoryGen is missing the Parent associated object');
		$this->assertTrue($category->hasMethod('loadArrayByParentId'), 'CategoryGen is missing loadArrayByParentId()');
	}

	/**
	 * `category.owner` is a foreign key whose column is not named *_id, so the
	 * reference gets an _object suffix to keep it apart from the integer column.
	 */
	public function testForeignKeyColumnNotNamedId() {
		$category = new \ReflectionClass('Generated\Data\CategoryGen');

		$this->assertTrue($category->hasProperty('intOwner'), 'CategoryGen is missing the owner column property');
		$this->assertTrue($category->hasProperty('objOwnerObject'), 'CategoryGen is missing the OwnerObject associated object');
	}

	/** A reference to a `_type` table resolves to the generated Type class, not an ORM class. */
	public function testReferenceToTypeTable() {
		$category = new \ReflectionClass('Generated\Data\CategoryGen');

		$this->assertTrue($category->hasProperty('intPriorityTypeId'), 'CategoryGen is missing the priority_type_id property');
		$this->assertFalse($category->hasProperty('objPriorityType'), 'a type reference should not become an associated object');
	}

	/**
	 * `person_profile.person_id` is UNIQUE, so Person gets one adjoined object -
	 * a writable magic property - rather than the read-only array a non-unique
	 * reverse reference produces. `blog_post.author_id` is the contrast.
	 */
	public function testUniqueForeignKeyGivesASingleReverseReference() {
		$generated = file_get_contents(CodegenFixture::getBuildPath('generated/Data/PersonGen.php'));

		$this->assertStringContainsString('@property PersonProfile $personProfile', $generated);
		$this->assertStringNotContainsString('$personProfileArray', $generated);

		// The non-unique reverse reference on the same class is an array, and read-only
		$this->assertStringContainsString('@property-read BlogPost[] $_blogPostAsAuthorArray', $generated);
	}

	/** The adjoined object is saved with its parent, which needs a dirty flag to track. */
	public function testUniqueReverseReferenceIsSavedWithItsParent() {
		$person = new \ReflectionClass('Generated\Data\PersonGen');

		$dirtyFlags = array_filter(
			array_map(static fn (\ReflectionProperty $property): string => $property->getName(), $person->getProperties()),
			static fn (string $name): bool => stripos($name, 'dirty') !== false && stripos($name, 'personprofile') !== false
		);

		$this->assertNotEmpty($dirtyFlags, 'PersonGen has no dirty flag for the adjoined PersonProfile');
	}

	/**
	 * `person_person_assn` joins `person` to itself. Both sides would otherwise
	 * generate identically named methods, so the column names prefix them apart.
	 */
	public function testGraphAssociationMethodsArePrefixedApart() {
		$person = new \ReflectionClass('Generated\Data\PersonGen');

		$associationMethods = array_filter(
			array_map(static fn (\ReflectionMethod $method): string => $method->getName(), $person->getMethods()),
			static fn (string $name): bool => str_contains($name, 'associate') || str_contains($name, 'Associate')
		);

		$this->assertNotEmpty($associationMethods, 'PersonGen has no association methods for person_person_assn');

		// The two sides must not collide
		$this->assertSame(count($associationMethods), count(array_unique($associationMethods)));
		$this->assertTrue(class_exists('Generated\Node\QQNodePersonPerson'), 'missing the person -> person association node');
	}

	//
	// Type table with extra columns
	//

	/** `priority_type` carries two columns beyond the id/name pair a type table requires. */
	public function testTypeTableWithExtraColumns() {
		$this->assertFileExists(CodegenFixture::getBuildPath('generated/Type/PriorityTypeGen.php'));

		$priorityType = new \ReflectionClass('App\Type\PriorityType');

		$this->assertSame(1, $priorityType->getConstant('LOW'));
		$this->assertSame(2, $priorityType->getConstant('NORMAL'));
		$this->assertSame(3, $priorityType->getConstant('URGENT'));
	}

	/**
	 * The extra-column accessors read the id they were passed. They used to emit
	 * the class name in PascalCase as the variable name while the parameter was
	 * camelCase, so the lookup used an undefined variable and every call was a
	 * TypeError - invisible until a type table actually had extra columns.
	 */
	public function testTypeTableExtraColumnAccessorsUseTheirArgument() {
		$generated = file_get_contents(CodegenFixture::getBuildPath('generated/Type/PriorityTypeGen.php'));

		$this->assertStringContainsString('$ExtraColumnValuesArray[$priorityTypeId]', $generated);
		$this->assertStringNotContainsString('$ExtraColumnValuesArray[$PriorityTypeId]', $generated);
	}

	/**
	 * The extra-column accessors are named like the ToString/ToToken pair they sit
	 * beside, rather than carrying the column name's leading lowercase through into
	 * the method name.
	 */
	public function testTypeTableExtraColumnAccessorNaming() {
		$priorityType = new \ReflectionClass('App\Type\PriorityType');

		$this->assertTrue($priorityType->hasMethod('ToString'));
		$this->assertTrue($priorityType->hasMethod('ToToken'));
		$this->assertTrue($priorityType->hasMethod('ToSortOrder'), 'PriorityType is missing ToSortOrder()');
		$this->assertTrue($priorityType->hasMethod('ToIsDefault'), 'PriorityType is missing ToIsDefault()');
	}

	/** And they actually return the row's value for that column. */
	public function testTypeTableExtraColumnAccessorsReturnTheirValue() {
		$this->assertSame('20', \App\Type\PriorityType::ToSortOrder(\App\Type\PriorityType::NORMAL));
		$this->assertSame('10', \App\Type\PriorityType::ToSortOrder(\App\Type\PriorityType::URGENT));
		$this->assertSame('1', \App\Type\PriorityType::ToIsDefault(\App\Type\PriorityType::NORMAL));
	}
}
