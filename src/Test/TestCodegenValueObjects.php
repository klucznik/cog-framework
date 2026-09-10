<?php declare(strict_types=1);

namespace Cog\Test;

use Cog\Codegen\Column;
use Cog\Codegen\ForeignKey;
use Cog\Codegen\Index;
use Cog\Codegen\ManyToManyReference;
use Cog\Codegen\Reference;
use Cog\Codegen\ReverseReference;
use Cog\Codegen\Table;
use Cog\Codegen\TypeTable;
use Cog\Exceptions\CogException;
use Cog\Exceptions\UndefinedPropertyException;
use Cog\Type;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the schema value objects the generator hands to templates.
 *
 * These classes are all instances of the Base property pattern described in
 * CLAUDE.md: private typed backing fields, a @property block, and a __get/__set
 * switch whose default delegates to Cog\Base. The failure mode is specific and
 * quiet - a property missing a case surfaces as UndefinedPropertyException from
 * inside a template, at generation time, with the offset pointing at Base rather
 * than the caller. Templates address these objects by name, so every documented
 * property is asserted here, along with the casting each setter applies.
 *
 * TestCodegenHelpers covers the naming rules; this file covers the containers
 * those names are read off.
 */
class TestCodegenValueObjects extends TestCase {

	/**
	 * A column, built by hand - Column is a property bag, so this is what the
	 * generator's analyzeTableColumn() produces, minus the database. Mirrors the
	 * helper in TestCodegenHelpers.
	 */
	private function column(string $name, string $variableType = Type::STRING, array $properties = []): Column {
		$column = new Column();
		$column->name = $name;
		$column->variableType = $variableType;
		$column->default = null;

		foreach ($properties as $property => $value) {
			$column->__set($property, $value);
		}

		return $column;
	}

	//
	// Table
	//

	/** ownerDbIndex is what binds a generated class to a numeric Database::$databases slot. */
	public function testTableOwnerDbIndex() {
		$table = new Table('person');
		$table->ownerDbIndex = 1;

		$this->assertSame(1, $table->ownerDbIndex);
	}

	/** The setter casts, so a value read out of XML as a string still lands as an int. */
	public function testTableOwnerDbIndexIsCast() {
		$table = new Table('person');
		$table->ownerDbIndex = '2';

		$this->assertSame(2, $table->ownerDbIndex);
	}

	public function testTableUnknownPropertyThrows() {
		$table = new Table('person');

		$this->expectException(UndefinedPropertyException::class);

		/** @noinspection PhpExpressionResultUnusedInspection */
		$table->noSuchProperty;
	}

	public function testTableUnknownPropertySetThrows() {
		$table = new Table('person');

		$this->expectException(UndefinedPropertyException::class);

		$table->noSuchProperty = 'x';
	}

	//
	// TableBase
	//

	public function testTableNameAndClassNames() {
		$table = new Table('blog_post');
		$table->className = 'BlogPost';
		$table->classNamePlural = 'BlogPosts';

		$this->assertSame('blog_post', $table->name);
		$this->assertSame('BlogPost', $table->className);
		$this->assertSame('BlogPosts', $table->classNamePlural);

		// The camelCase form is computed, not stored - templates use it for variables
		$this->assertSame('blogPost', $table->classNameCamelCase);
	}

	public function testTableColumnLookupMisses() {
		$table = new Table('person');
		$table->columnArray = ['id' => $this->column('id', Type::INTEGER)];

		$this->assertNull($table->getColumnByName('missing'));
		$this->assertFalse($table->hasColumn('missing'));
		$this->assertNull($table->lookupColumnPropertyName('missing'));
	}

	public function testTableLookupColumnPropertyName() {
		$table = new Table('person');
		$table->columnArray = ['first_name' => $this->column('first_name')];

		$this->assertSame('firstName', $table->lookupColumnPropertyName('first_name'));
		$this->assertTrue($table->hasColumn('first_name'));
	}

	/**
	 * With no columns at all the primary key list is null rather than an empty
	 * array - templates branch on it, so the distinction is load-bearing.
	 */
	public function testTablePrimaryKeyColumnArrayWithoutColumnsIsNull() {
		$table = new Table('person');

		$this->assertNull($table->primaryKeyColumnArray);
	}

	public function testTableIndexArray() {
		$table = new Table('person');
		$index = new Index('PRIMARY');
		$index->primaryKey = true;
		$index->columnNameArray = ['id'];

		$table->indexArray = [$index];

		$this->assertCount(1, $table->indexArray);
		$this->assertSame($index, $table->indexArray[0]);
	}

	/**
	 * An array expansion is what forces the generator to emit array-typed
	 * accessors: any many-to-many, or a non-unique reverse reference.
	 */
	public function testTableHasImmediateArrayExpansions() {
		$table = new Table('person');
		$this->assertFalse($table->hasImmediateArrayExpansions());

		$unique = new ReverseReference();
		$unique->unique = true;
		$table->reverseReferenceArray = [$unique];
		$this->assertFalse($table->hasImmediateArrayExpansions());

		$many = new ReverseReference();
		$many->unique = false;
		$table->reverseReferenceArray = [$unique, $many];
		$this->assertTrue($table->hasImmediateArrayExpansions());
	}

	public function testTableHasImmediateArrayExpansionsForManyToMany() {
		$table = new Table('person');
		$table->manyToManyReferenceArray = [new ManyToManyReference()];

		$this->assertTrue($table->hasImmediateArrayExpansions());
	}

	//
	// Index
	//

	public function testIndexProperties() {
		$index = new Index('ix_person_email');
		$index->unique = true;
		$index->primaryKey = false;
		$index->columnNameArray = ['email'];

		$this->assertSame('ix_person_email', $index->keyName);
		$this->assertTrue($index->unique);
		$this->assertFalse($index->primaryKey);
		$this->assertSame(['email'], $index->columnNameArray);
	}

	/** Virtual indexes are synthesised without a name, so null has to survive the constructor. */
	public function testIndexAcceptsNullKeyName() {
		$this->assertNull((new Index(null))->keyName);
	}

	public function testIndexCastsItsSetters() {
		$index = new Index('ix');
		$index->unique = 1;
		$index->primaryKey = 0;

		$this->assertTrue($index->unique);
		$this->assertFalse($index->primaryKey);
	}

	/**
	 * Null-coalescing reaches __get on a real Base subclass.
	 *
	 * These value objects declare __get and no __isset, which is what lets ?? fall
	 * through. Base used to declare an __isset() returning false unconditionally,
	 * and PHP consults that before __get - so `$index->keyName ?? $default` handed
	 * back the default even with a key name set, silently. Removing it fixed every
	 * Base subclass at once.
	 *
	 * isset() and empty() still report a magic property as unset; they do not
	 * consult __get. That is a visible false answer rather than a silent wrong
	 * value, which is why it is left alone.
	 */
	public function testNullCoalescingReachesTheMagicGetter() {
		$index = new Index('ix_person_email');

		$this->assertFalse(method_exists($index, '__isset'), 'an __isset here would break ?? again');
		$this->assertSame('ix_person_email', $index->keyName ?? 'fallback');
	}

	public function testIndexUnknownPropertyThrows() {
		$this->expectException(UndefinedPropertyException::class);

		/** @noinspection PhpExpressionResultUnusedInspection */
		(new Index('ix'))->noSuchProperty;
	}

	//
	// ForeignKey
	//

	public function testForeignKeyProperties() {
		$foreignKey = new ForeignKey('fk_blog_post_author', ['author_id'], 'person', ['id']);

		$this->assertSame('fk_blog_post_author', $foreignKey->keyName);
		$this->assertSame(['author_id'], $foreignKey->columnNameArray);
		$this->assertSame('person', $foreignKey->referenceTableName);
		$this->assertSame(['id'], $foreignKey->referenceColumnNameArray);
	}

	/** ForeignKey is read-only: it declares no __set, so Base refuses every write. */
	public function testForeignKeyIsReadOnly() {
		$foreignKey = new ForeignKey('fk', ['a'], 't', ['id']);

		$this->expectException(CogException::class);

		$foreignKey->keyName = 'other';
	}

	public function testForeignKeyUnknownPropertyThrows() {
		$this->expectException(UndefinedPropertyException::class);

		/** @noinspection PhpExpressionResultUnusedInspection */
		(new ForeignKey('fk', ['a'], 't', ['id']))->noSuchProperty;
	}

	//
	// TypeTable
	//

	/**
	 * A type table is generated from its own rows, so these four arrays are the
	 * entire payload: ids to names, ids to PHP constant tokens, and any extra
	 * columns beyond the two required ones.
	 */
	public function testTypeTableProperties() {
		$typeTable = new TypeTable('blog_type');
		$typeTable->nameArray = [1 => 'Draft', 2 => 'Published'];
		$typeTable->tokenArray = [1 => 'DRAFT', 2 => 'PUBLISHED'];
		$typeTable->extraFieldNamesArray = ['sort_order'];
		$typeTable->extraPropertyArray = [1 => ['sort_order' => 10], 2 => ['sort_order' => 20]];

		$this->assertSame([1 => 'Draft', 2 => 'Published'], $typeTable->nameArray);
		$this->assertSame([1 => 'DRAFT', 2 => 'PUBLISHED'], $typeTable->tokenArray);
		$this->assertSame(['sort_order'], $typeTable->extraFieldNamesArray);
		$this->assertSame([1 => ['sort_order' => 10], 2 => ['sort_order' => 20]], $typeTable->extraPropertyArray);
	}

	/** TypeTable is still a table, so the inherited properties keep working. */
	public function testTypeTableInheritsTableProperties() {
		$typeTable = new TypeTable('blog_type');
		$typeTable->className = 'BlogType';

		$this->assertSame('blog_type', $typeTable->name);
		$this->assertSame('BlogType', $typeTable->className);
		$this->assertSame('blogType', $typeTable->classNameCamelCase);
	}

	public function testTypeTableUnknownPropertyThrows() {
		$this->expectException(UndefinedPropertyException::class);

		/** @noinspection PhpExpressionResultUnusedInspection */
		(new TypeTable('blog_type'))->noSuchProperty;
	}

	//
	// Reference
	//

	public function testReferenceProperties() {
		$reference = new Reference();
		$reference->keyName = 'fk_blog_post_author';
		$reference->table = 'person';
		$reference->column = 'id';
		$reference->propertyName = 'author';
		$reference->variableType = 'Person';
		$reference->isType = false;

		$this->assertSame('fk_blog_post_author', $reference->keyName);
		$this->assertSame('person', $reference->table);
		$this->assertSame('id', $reference->column);
		$this->assertSame('author', $reference->propertyName);
		$this->assertSame('Person', $reference->variableType);
		$this->assertFalse($reference->isType);
	}

	/**
	 * The derived names are computed from the property name. The uppercase form
	 * comes back as a Symfony ByteString rather than a string, despite the
	 * @property-read block saying string; templates interpolate it, so the
	 * distinction only shows up under a strict comparison like this one.
	 */
	public function testReferenceComputedNames() {
		$reference = new Reference();
		$reference->propertyName = 'author';

		$this->assertSame('Author', (string)$reference->propertyNameUppercase);
		$this->assertSame('loadedAuthor', $reference->loadedMember);
	}

	public function testReferenceUnknownPropertyThrows() {
		$this->expectException(UndefinedPropertyException::class);

		/** @noinspection PhpExpressionResultUnusedInspection */
		(new Reference())->noSuchProperty;
	}

	//
	// ReverseReference
	//

	public function testReverseReferenceProperties() {
		$reverse = new ReverseReference();
		$reverse->keyName = 'fk_blog_post_author';
		$reverse->table = 'blog_post';
		$reverse->column = 'author_id';
		$reverse->notNull = true;
		$reverse->unique = false;
		$reverse->variableType = 'BlogPost';
		$reverse->propertyName = 'blogPost';
		$reverse->objectDescription = 'BlogPostAsAuthor';
		$reverse->objectDescriptionPlural = 'BlogPostsAsAuthor';

		$this->assertSame('blog_post', $reverse->table);
		$this->assertSame('author_id', $reverse->column);
		$this->assertTrue($reverse->notNull);
		$this->assertFalse($reverse->unique);
		$this->assertSame('BlogPost', $reverse->variableType);
		$this->assertSame('BlogPostAsAuthor', $reverse->objectDescription);
		$this->assertSame('BlogPostsAsAuthor', $reverse->objectDescriptionPlural);
	}

	public function testReverseReferenceComputedNames() {
		$reverse = new ReverseReference();
		$reverse->variableType = 'BlogPost';
		$reverse->propertyName = 'blogPost';
		$reverse->objectDescription = 'blogPostAsAuthor';
		$reverse->objectDescriptionPlural = 'blogPostsAsAuthor';
		$reverse->objectPropertyName = 'personProfile';

		$this->assertSame('blogPost', $reverse->parameterName);
		$this->assertSame('loadedPersonProfile', $reverse->loadedMember);
		$this->assertSame('BlogPost', (string)$reverse->propertyNameUppercase);
		$this->assertSame('BlogPostAsAuthor', (string)$reverse->objectDescriptionUppercase);
		$this->assertSame('BlogPostsAsAuthor', (string)$reverse->objectDescriptionPluralUppercase);
	}

	public function testReverseReferenceUnknownPropertyThrows() {
		$this->expectException(UndefinedPropertyException::class);

		/** @noinspection PhpExpressionResultUnusedInspection */
		(new ReverseReference())->noSuchProperty;
	}

	//
	// ManyToManyReference
	//

	/**
	 * An association table produces one of these on each side, so both the near
	 * and the opposite half of the pair are addressed by templates.
	 */
	public function testManyToManyReferenceProperties() {
		$reference = new ManyToManyReference();
		$reference->keyName = 'fk_person_tag_person';
		$reference->table = 'person_tag_assn';
		$reference->column = 'person_id';
		$reference->oppositeColumn = 'tag_id';
		$reference->oppositeVariableType = 'Tag';
		$reference->oppositePropertyName = 'tag';
		$reference->oppositeObjectDescription = 'Tag';
		$reference->associatedTable = 'tag';
		$reference->variableType = 'Tag';
		$reference->objectDescription = 'Tag';
		$reference->objectDescriptionPlural = 'Tags';

		$this->assertSame('person_tag_assn', $reference->table);
		$this->assertSame('person_id', $reference->column);
		$this->assertSame('tag_id', $reference->oppositeColumn);
		$this->assertSame('tag', $reference->associatedTable);
		$this->assertSame('Tags', $reference->objectDescriptionPlural);
	}

	public function testManyToManyReferenceColumnArray() {
		$reference = new ManyToManyReference();
		$column = $this->column('person_id', Type::INTEGER);
		$reference->columnArray = ['person_id' => $column];

		$this->assertSame($column, $reference->columnArray['person_id']);
	}

	public function testManyToManyReferenceComputedNames() {
		$reference = new ManyToManyReference();
		$reference->variableType = 'Tag';
		$reference->objectDescription = 'tag';
		$reference->objectDescriptionPlural = 'tags';

		$this->assertSame('tag', $reference->parameterName);
		$this->assertSame('Tag', (string)$reference->objectDescriptionUppercase);
		$this->assertSame('Tags', (string)$reference->objectDescriptionPluralUppercase);
	}

	public function testManyToManyReferenceUnknownPropertyThrows() {
		$this->expectException(UndefinedPropertyException::class);

		/** @noinspection PhpExpressionResultUnusedInspection */
		(new ManyToManyReference())->noSuchProperty;
	}

	//
	// Column: the names that follow from the column name
	//

	/** The generated property is the column name in camelCase, whatever its type. */
	public function testColumnPropertyName() {
		$this->assertSame('id', $this->column('id', Type::INTEGER)->propertyName);
		$this->assertSame('firstName', $this->column('first_name', Type::STRING)->propertyName);
		$this->assertSame('emailVerified', $this->column('email_verified', Type::BOOLEAN)->propertyName);
	}

	/**
	 * A foreign key column carries both the id and the object it points at, so the
	 * two need different names. A trailing "_id" is dropped; anything else gains
	 * "_object" so the object cannot collide with the column it was mapped from.
	 */
	public function testColumnReferencePropertyName() {
		$this->assertSame('author', $this->column('author_id', Type::INTEGER)->referencePropertyName);
		$this->assertSame('personObject', $this->column('person', Type::INTEGER)->referencePropertyName);

		// Too short to be an "_id" suffix, so it takes the _object branch.
		$this->assertSame('idObject', $this->column('_id', Type::INTEGER)->referencePropertyName);
	}

	/** The label is the property name split into words; the column comment plays no part. */
	public function testColumnLabel() {
		$column = $this->column('first_name', Type::STRING, ['comment' => 'Given name; the label']);

		$this->assertSame('First name', $column->label);
	}
}
