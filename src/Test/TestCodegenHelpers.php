<?php

namespace Cog\Test;

use Cog\Codegen\Column;
use Cog\Codegen\DatabaseCodeGen;
use Cog\Codegen\ForeignKey;
use Cog\Codegen\Reference;
use Cog\Codegen\Table;
use Cog\Codegen\Utils;
use Cog\Codegen\VariableNameCreator;
use Cog\Database\FieldType;
use Cog\Exceptions\UndefinedPropertyException;
use Cog\Type;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * Unit tests for the pure logic inside Cog\Codegen.
 *
 * TestCodegen covers the generator end to end: it asserts on the files produced
 * from the cog_test database. That catches a generator that broke, but not a
 * generator that renamed something - a wrong calculateObjectDescription() still
 * emits a file that lints clean, loads fine, and is simply called the wrong
 * thing. The naming rules are pinned here instead, one assertion per branch, and
 * without a database: nothing in this file connects to one.
 */
class TestCodegenHelpers extends TestCase {

	/**
	 * Settings mirroring codegen.xml, with %s left for the fragments individual
	 * tests vary. See settingsXml() below.
	 */
	private const string SETTINGS_TEMPLATE = '<database index="%s">
			<templates path="/codegen"/>
			<className prefix="%s" suffix="%s"/>
			<associatedObjectName prefix="%s" suffix="%s"/>
			<namespace data="%s" type="%s"/>
			<typeTableIdentifier suffix="_type"/>
			<associationTableIdentifier suffix="_assn"/>
			<stripFromTableName prefix="%s"/>
			<excludeTables pattern="" list=""/>
			<includeTables pattern="" list=""/>
			<relationships></relationships>
			<relationshipsScript filepath="%s" format="%s"/>
			<columnCommentForMetaControl delimiter=""/>
		</database>';

	/**
	 * A settings node for the code generator. Every attribute the constructor
	 * reads is present, so a test only names the ones it cares about.
	 *
	 * @param array $overrides any of: index, classPrefix, classSuffix,
	 *     objectPrefix, objectSuffix, namespaceData, namespaceType,
	 *     stripPrefix, scriptPath, scriptFormat
	 */
	private function settingsXml(array $overrides = []): SimpleXMLElement {
		$settings = array_merge([
			'index' => '1',
			'classPrefix' => '',
			'classSuffix' => '',
			'objectPrefix' => '',
			'objectSuffix' => '',
			'namespaceData' => 'App\Data',
			'namespaceType' => 'App\Type',
			'stripPrefix' => '',
			'scriptPath' => '',
			'scriptFormat' => '',
		], $overrides);

		return new SimpleXMLElement(sprintf(
			self::SETTINGS_TEMPLATE,
			$settings['index'],
			$settings['classPrefix'],
			$settings['classSuffix'],
			$settings['objectPrefix'],
			$settings['objectSuffix'],
			htmlspecialchars($settings['namespaceData']),
			htmlspecialchars($settings['namespaceType']),
			$settings['stripPrefix'],
			htmlspecialchars($settings['scriptPath']),
			$settings['scriptFormat']
		));
	}

	/**
	 * A code generator whose database analysis has been stubbed out. The naming
	 * helpers read properties that only the constructor sets, so the constructor
	 * has to run - it is just the analyzeDatabase() call at the end of it that
	 * needs a connection.
	 *
	 * @param array $overrides see settingsXml()
	 */
	private function codegen(array $overrides = []): CodegenHelperHarness {
		return new CodegenHelperHarness('/docroot', ['/codegen'], $this->settingsXml($overrides));
	}

	/**
	 * A column, built by hand. Column is a property bag, so this is exactly what
	 * the generator's own analyzeTableColumn() produces, minus the database.
	 */
	private function column(string $name, string $variableType = Type::STRING, array $properties = []): Column {
		$column = new Column();
		$column->name = $name;
		$column->variableType = $variableType;
		// analyzeTableColumn() always assigns a default, and $default has no
		// initializer, so a column without one is not a state the generator can
		// reach - reading it would be an uninitialized typed property error.
		$column->default = null;
		$column->propertyName = \Cog\Util\ConvertNotation::camelCase($name);
		$column->variableName = \Cog\Util\ConvertNotation::prefixFromType($variableType) . \Cog\Util\ConvertNotation::pascalCase($name);

		foreach ($properties as $property => $value) {
			$column->__set($property, $value);
		}

		return $column;
	}

	//
	// Table and class names
	//

	/** A table name becomes a class name in PascalCase, wrapped in the configured prefix/suffix. */
	public function testClassNameFromTableName() {
		$codegen = $this->codegen();

		$this->assertSame('BlogPost', $codegen->classNameFromTableName('blog_post'));
		$this->assertSame('Person', $codegen->classNameFromTableName('person'));
		$this->assertSame('blogPost', $codegen->classNameFromTableName('blog_post', true));
	}

	public function testClassNameFromTableNameWithPrefixAndSuffix() {
		$codegen = $this->codegen(['classPrefix' => 'My', 'classSuffix' => 'Model']);

		$this->assertSame('MyBlogPostModel', $codegen->classNameFromTableName('blog_post'));
	}

	/**
	 * stripFromTableName drops a shared prefix before anything else looks at the
	 * name, so `qc_blog_post` generates BlogPost rather than QcBlogPost.
	 */
	public function testStripPrefixFromTable() {
		$codegen = $this->codegen(['stripPrefix' => 'qc_']);

		$this->assertSame('blog_post', $codegen->callStripPrefixFromTable('qc_blog_post'));
		$this->assertSame('BlogPost', $codegen->classNameFromTableName('qc_blog_post'));

		// Not a prefix match - left alone.
		$this->assertSame('blog_post', $codegen->callStripPrefixFromTable('blog_post'));

		// A table named exactly the prefix would strip to nothing, so it is left alone.
		$this->assertSame('qc_', $codegen->callStripPrefixFromTable('qc_'));
	}

	/** With no prefix configured, nothing is stripped - not even a leading underscore. */
	public function testStripPrefixFromTableWithoutConfiguredPrefix() {
		$this->assertSame('_person', $this->codegen()->callStripPrefixFromTable('_person'));
	}

	//
	// Column names
	//

	/**
	 * The Hungarian prefixes belong to generated output only, and they come from
	 * the column's variable type rather than from its database type.
	 */
	public function testVariableNameFromColumn() {
		$this->assertSame('intId', VariableNameCreator::variableNameFromColumn($this->column('id', Type::INTEGER)));
		$this->assertSame('strFirstName', VariableNameCreator::variableNameFromColumn($this->column('first_name', Type::STRING)));
		$this->assertSame('blnEmailVerified', VariableNameCreator::variableNameFromColumn($this->column('email_verified', Type::BOOLEAN)));
		$this->assertSame('dttCreationDate', VariableNameCreator::variableNameFromColumn($this->column('creation_date', Type::DATETIME)));
		$this->assertSame('fltRating', VariableNameCreator::variableNameFromColumn($this->column('rating', Type::FLOAT)));
	}

	/** The typed variant and the property name are both plain camelCase - no prefix. */
	public function testPropertyNameFromColumn() {
		$codegen = $this->codegen();
		$column = $this->column('first_name', Type::STRING);

		$this->assertSame('firstName', VariableNameCreator::propertyNameFromColumn($column));
		$this->assertSame('firstName', VariableNameCreator::variableNameFromColumnWithType($column));
		$this->assertSame('blogType', $codegen->typeNameFromColumnName('blog_type'));
	}

	/**
	 * A foreign key column carries both the id and the object it points at, so the
	 * two need different names. A trailing "_id" is dropped; anything else gains
	 * "_object" so the object cannot collide with the column it was mapped from.
	 */
	public function testReferenceColumnNameFromColumn() {
		$this->assertSame('author', VariableNameCreator::referenceColumnNameFromColumn($this->column('author_id', Type::INTEGER)));
		$this->assertSame('person_object', VariableNameCreator::referenceColumnNameFromColumn($this->column('person', Type::INTEGER)));

		// Too short to be an "_id" suffix, so it takes the _object branch.
		$this->assertSame('_id_object', VariableNameCreator::referenceColumnNameFromColumn($this->column('_id', Type::INTEGER)));
	}

	public function testReferenceNamesFromColumn() {
		$column = $this->column('author_id', Type::INTEGER);

		$this->assertSame('objAuthor', VariableNameCreator::referenceVariableNameFromColumn($column));
		$this->assertSame('author', VariableNameCreator::referencePropertyNameFromColumn($column));
		$this->assertSame('Author', VariableNameCreator::referencePropertyNameUpperCaseFromColumn($column));
	}

	/** Reverse references are named after the table they come back from. */
	public function testNamesFromTable() {
		$codegen = $this->codegen();

		$this->assertSame('objBlogPost', $codegen->variableNameFromTable('blog_post'));
		$this->assertSame('objBlogPost', $codegen->reverseReferenceVariableNameFromTable('blog_post'));
		$this->assertSame('BlogPost', $codegen->reverseReferenceVariableTypeFromTable('blog_post'));
	}

	//
	// Object descriptions - how a foreign key is described on the object that owns it
	//

	/**
	 * A column named after the table it references adds nothing, so the
	 * description is the owning table's name on its own.
	 */
	public function testObjectDescriptionFromTableNamedColumn() {
		$codegen = $this->codegen();

		$this->assertSame('blogPost', $codegen->callCalculateObjectDescription('blog_post', 'person_id', 'person', false));
		$this->assertSame('blogPost', $codegen->callCalculateObjectDescription('blog_post', 'person', 'person', false));
	}

	/** Anything else is described as "<table>As<predicate>", the predicate being what is left of the column name. */
	public function testObjectDescriptionWithPredicate() {
		$codegen = $this->codegen();

		$this->assertSame(
			'blogPostAsReviewer',
			$codegen->callCalculateObjectDescription('blog_post', 'reviewer_id', 'person', false)
		);
	}

	/** Pluralizing applies to the table half, before the predicate is appended. */
	public function testObjectDescriptionPluralized() {
		$codegen = $this->codegen();

		$this->assertSame(
			'blogPostsAsReviewer',
			$codegen->callCalculateObjectDescription('blog_post', 'reviewer_id', 'person', true)
		);
	}

	/**
	 * A self-referencing key is a hierarchy: person.parent_id keys back to person,
	 * and the thing on the other end is a child.
	 */
	public function testObjectDescriptionSelfReferencing() {
		$codegen = $this->codegen();

		$this->assertSame('Childperson', $codegen->callCalculateObjectDescription('person', 'parent_id', 'person', false));
		$this->assertSame('Childperson', $codegen->callCalculateObjectDescription('person', 'person_id', 'person', false));
		$this->assertSame('personAsManager', $codegen->callCalculateObjectDescription('person', 'manager_id', 'person', false));
	}

	/** The description is what the member variable and property names are built from. */
	public function testObjectMemberVariableAndPropertyName() {
		$codegen = $this->codegen();

		$this->assertSame(
			'objblogPostAsReviewer',
			$codegen->callCalculateObjectMemberVariable('blog_post', 'reviewer_id', 'person')
		);
		$this->assertSame(
			'blogPostAsReviewer',
			$codegen->callCalculateObjectPropertyName('blog_post', 'reviewer_id', 'person')
		);
	}

	public function testObjectMemberVariableWithConfiguredAffixes() {
		$codegen = $this->codegen(['objectPrefix' => 'Assoc', 'objectSuffix' => 'Ref']);

		$this->assertSame(
			'AssocblogPostAsReviewerRef',
			$codegen->callCalculateObjectPropertyName('blog_post', 'reviewer_id', 'person')
		);
	}

	/**
	 * For an association table, the description starts from the referenced table.
	 * When the association table name is nothing but the two table names and the
	 * "_assn" suffix, there is no predicate left and the plural stands alone.
	 */
	public function testObjectDescriptionForAssociation() {
		$codegen = $this->codegen();

		$this->assertSame(
			'objs',
			$codegen->callCalculateObjectDescriptionForAssociation('tag_obj_assn', 'tag', 'obj', true)
		);
		$this->assertSame(
			'obj',
			$codegen->callCalculateObjectDescriptionForAssociation('tag_obj_assn', 'tag', 'obj', false)
		);
	}

	/** Whatever survives that stripping becomes an "As" predicate. */
	public function testObjectDescriptionForAssociationWithPredicate() {
		$codegen = $this->codegen();

		$this->assertSame(
			'personsAsEditor',
			$codegen->callCalculateObjectDescriptionForAssociation('blog_post_person_editor_assn', 'blog_post', 'person', true)
		);
	}

	/**
	 * A self-referencing association table is a directed graph, and which end is
	 * the parent is read out of the column names.
	 */
	public function testGraphPrefixArray() {
		$codegen = $this->codegen();

		$this->assertSame(
			['', 'parent'],
			$codegen->callCalculateGraphPrefixArray([
				$this->foreignKey('parent_id'),
				$this->foreignKey('person_id'),
			])
		);

		$this->assertSame(
			['parent', ''],
			$codegen->callCalculateGraphPrefixArray([
				$this->foreignKey('child_id'),
				$this->foreignKey('person_id'),
			])
		);

		// Neither end says which it is, so the first column is taken as the parent.
		$this->assertSame(
			['parent', ''],
			$codegen->callCalculateGraphPrefixArray([
				$this->foreignKey('from_id'),
				$this->foreignKey('to_id'),
			])
		);
	}

	private function foreignKey(string $columnName): ForeignKey {
		return new ForeignKey('fk_' . $columnName, [$columnName], 'person', ['id']);
	}

	//
	// Types
	//

	/** Every database field type the framework knows has a PHP type it maps onto. */
	public function testVariableTypeFromDbType() {
		$codegen = $this->codegen();

		$this->assertSame(Type::BOOLEAN, $codegen->variableTypeFromDbType(FieldType::BIT));
		$this->assertSame(Type::STRING, $codegen->variableTypeFromDbType(FieldType::BLOB));
		$this->assertSame(Type::STRING, $codegen->variableTypeFromDbType(FieldType::CHAR));
		$this->assertSame(Type::STRING, $codegen->variableTypeFromDbType(FieldType::VARCHAR));
		$this->assertSame(Type::DATETIME, $codegen->variableTypeFromDbType(FieldType::DATE));
		$this->assertSame(Type::DATETIME, $codegen->variableTypeFromDbType(FieldType::TIME));
		$this->assertSame(Type::DATETIME, $codegen->variableTypeFromDbType(FieldType::DATETIME));
		$this->assertSame(Type::DATETIME, $codegen->variableTypeFromDbType(FieldType::TIMESTAMP));
		$this->assertSame(Type::FLOAT, $codegen->variableTypeFromDbType(FieldType::FLOAT));
		$this->assertSame(Type::INTEGER, $codegen->variableTypeFromDbType(FieldType::INTEGER));
	}

	/** An unrecognized type is fatal - generating a class with no type for a column is worse. */
	public function testVariableTypeFromUnknownDbTypeThrows() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Invalid Db Type to Convert:Geometry');

		$this->codegen()->variableTypeFromDbType('Geometry');
	}

	/**
	 * Type table rows become PHP constants, so their names have to survive being
	 * turned into identifiers.
	 */
	public function testTypeTokenFromTypeName() {
		$codegen = $this->codegen();

		$this->assertSame('Blog_Post', $codegen->callTypeTokenFromTypeName('Blog Post'));
		$this->assertSame('Editors_Pick', $codegen->callTypeTokenFromTypeName("Editor's Pick"));
		$this->assertSame('_1st_Place', $codegen->callTypeTokenFromTypeName('1st Place'));
		$this->assertSame('already_a_token', $codegen->callTypeTokenFromTypeName('already_a_token'));
	}

	//
	// Signature fragments the templates paste verbatim
	//

	public function testParameterListFromColumnArray() {
		$codegen = $this->codegen();
		$columns = [$this->column('id', Type::INTEGER), $this->column('name', Type::STRING)];

		$this->assertSame('?int $id, ?string $name', $codegen->parameterListFromColumnArray($columns));
		$this->assertSame('?int $id = null, ?string $name = null', $codegen->parameterListNulledFromColumnArray($columns));
		$this->assertSame('', $codegen->parameterListFromColumnArray([]));
	}

	public function testImplodeObjectArray() {
		$codegen = $this->codegen();
		$columns = [$this->column('id', Type::INTEGER), $this->column('name', Type::STRING)];

		$this->assertSame("'id', 'name'", $codegen->implodeObjectArray(', ', "'", "'", 'name', $columns));
		$this->assertSame('', $codegen->implodeObjectArray(', ', "'", "'", 'name', []));
	}

	public function testParameterCleanupFromColumn() {
		$codegen = $this->codegen();
		$column = $this->column('name', Type::STRING);

		$this->assertSame(
			'$strName = $database->sqlVariable($strName);',
			$codegen->callParameterCleanupFromColumn($column)
		);
		$this->assertSame(
			'$strName = $database->sqlVariable($strName, true);',
			$codegen->callParameterCleanupFromColumn($column, true)
		);
	}

	//
	// Column defaults
	//

	/** A property initializer has to be a constant expression, and these are the literals emitted. */
	public function testGetDefaultAsString() {
		$this->assertSame("''", $this->column('name', Type::STRING)->getDefaultAsString());
		$this->assertSame('null', $this->column('count', Type::INTEGER)->getDefaultAsString());

		$this->assertSame('7', $this->column('count', Type::INTEGER, ['default' => 7])->getDefaultAsString());
		$this->assertSame('1.5', $this->column('rating', Type::FLOAT, ['default' => '1.5'])->getDefaultAsString());
		$this->assertSame("'draft'", $this->column('status', Type::STRING, ['default' => 'draft'])->getDefaultAsString());

		$this->assertSame('true', $this->column('active', Type::BOOLEAN, ['default' => 1])->getDefaultAsString());
		$this->assertSame('false', $this->column('active', Type::BOOLEAN, ['default' => 0])->getDefaultAsString());
	}

	/** A default containing a quote has to come back escaped, or the generated file will not parse. */
	public function testGetDefaultAsStringEscapesQuotes() {
		$this->assertSame(
			"'it\\'s'",
			$this->column('label', Type::STRING, ['default' => "it's"])->getDefaultAsString()
		);
	}

	/**
	 * A `timestamp` column is the optimistic-locking token, maintained by the
	 * database, so the object never presets it - whatever the schema default says.
	 */
	public function testGetDefaultAsStringForTimestampColumn() {
		$column = $this->column('modification_date', Type::DATETIME, [
			'timestamp' => true,
			'default' => 'CURRENT_TIMESTAMP',
		]);

		$this->assertSame('null', $column->getDefaultAsString());
		$this->assertFalse($column->hasCurrentTimestampDefault());
	}

	/**
	 * DEFAULT CURRENT_TIMESTAMP is not a constant expression, so it is emitted as
	 * a run-time expression and applied by a generated constructor instead. MySQL
	 * reports it under several spellings.
	 */
	public function testHasCurrentTimestampDefault() {
		foreach (['current_timestamp', 'CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP()', 'current_timestamp(3)', 'now()', 'localtime', 'localtimestamp'] as $default) {
			$column = $this->column('creation_date', Type::DATETIME, ['default' => $default]);

			$this->assertTrue($column->hasCurrentTimestampDefault(), $default . ' should read as a run-time default');
			$this->assertSame('new Carbon()', $column->getDefaultAsString());
		}
	}

	/** A literal datetime default is not a run-time expression - and is not emitted as one either. */
	public function testLiteralDatetimeDefaultIsNotRuntime() {
		$column = $this->column('creation_date', Type::DATETIME, [
			'notNull' => true,
			'default' => '2020-01-01 00:00:00',
		]);

		$this->assertFalse($column->hasCurrentTimestampDefault());
		$this->assertSame('null', $column->getDefaultAsString());
	}

	/** The same spelling on a non-datetime column is just a string. */
	public function testCurrentTimestampDefaultOnNonDatetimeColumn() {
		$column = $this->column('label', Type::STRING, ['default' => 'current_timestamp']);

		$this->assertFalse($column->hasCurrentTimestampDefault());
	}

	/**
	 * MySQL's zero date is not a value - it is what a nullable column reports when
	 * it has no default at all, so it is normalized to null on the way in.
	 */
	public function testZeroDateDefaultBecomesNull() {
		$this->assertNull($this->column('creation_date', Type::DATETIME, ['default' => '0000-00-00 00:00:00'])->default);
		$this->assertNull($this->column('creation_date', Type::DATETIME, ['default' => '0000-00-00'])->default);

		// NOT NULL, so the zero date is kept: there is no null for it to mean.
		$column = $this->column('creation_date', Type::DATETIME, [
			'notNull' => true,
			'default' => '0000-00-00 00:00:00',
		]);
		$this->assertSame('0000-00-00 00:00:00', $column->default);
	}

	/** Numeric defaults keep their numeric type rather than becoming quoted strings. */
	public function testDefaultCasting() {
		$this->assertSame(7, $this->column('count', Type::INTEGER, ['default' => 7])->default);
		$this->assertSame(1.5, $this->column('rating', Type::FLOAT, ['default' => '1.5'])->default);
		$this->assertSame('draft', $this->column('status', Type::STRING, ['default' => 'draft'])->default);
	}

	//
	// Column pseudo-properties
	//

	/**
	 * Note the casts: despite the @property-read string annotation, the two
	 * uppercase properties hand back the Symfony ByteString they were built from.
	 * Templates interpolate them into strings, so it has never mattered there.
	 */
	public function testColumnComputedProperties() {
		$column = $this->column('first_name', Type::STRING);

		$this->assertSame('FirstName', (string)$column->propertyNameUppercase);
		$this->assertSame('StrFirstName', (string)$column->variableNameUppercase);
		$this->assertSame('FIRST_NAME', $column->constantPropertyName);
	}

	/** variableTyped is what lands in the generated property declaration. */
	public function testColumnVariableTyped() {
		$this->assertSame('int', $this->column('id', Type::INTEGER)->variableTyped);
		$this->assertSame('string', $this->column('name', Type::STRING)->variableTyped);
		$this->assertSame('bool', $this->column('active', Type::BOOLEAN)->variableTyped);
		$this->assertSame('float', $this->column('rating', Type::FLOAT)->variableTyped);
		$this->assertSame('Carbon', $this->column('created', Type::DATETIME)->variableTyped);
	}

	public function testColumnVariableTypeJs() {
		$this->assertSame('number', $this->column('id', Type::INTEGER)->variableTypeJs);
		$this->assertSame('number', $this->column('rating', Type::FLOAT)->variableTypeJs);
		$this->assertSame('boolean', $this->column('active', Type::BOOLEAN)->variableTypeJs);
		$this->assertSame('string', $this->column('name', Type::STRING)->variableTypeJs);
		$this->assertSame('string', $this->column('created', Type::DATETIME)->variableTypeJs);
	}

	/** A missing case in the switch has to surface, not return null. */
	public function testUnknownColumnPropertyThrows() {
		$this->expectException(UndefinedPropertyException::class);

		$this->column('name')->__get('noSuchProperty');
	}

	//
	// TableBase lookups
	//

	public function testTableColumnLookup() {
		$table = new Table('person');
		$table->columnArray = [
			'id' => $this->column('id', Type::INTEGER),
			'first_name' => $this->column('first_name', Type::STRING),
		];

		$this->assertTrue($table->hasColumn('first_name'));
		$this->assertSame('first_name', $table->getColumnByName('first_name')->name);
		$this->assertSame('firstName', $table->lookupColumnPropertyName('first_name'));

		$this->assertFalse($table->hasColumn('last_name'));
		$this->assertNull($table->getColumnByName('last_name'));
		$this->assertNull($table->lookupColumnPropertyName('last_name'));
	}

	public function testTablePrimaryKeyColumnArray() {
		$table = new Table('person');
		$table->columnArray = [
			'id' => $this->column('id', Type::INTEGER, ['primaryKey' => true]),
			'first_name' => $this->column('first_name', Type::STRING, ['primaryKey' => false]),
		];

		$primaryKeys = $table->primaryKeyColumnArray;

		$this->assertCount(1, $primaryKeys);
		$this->assertSame('id', $primaryKeys[0]->name);
	}

	/** referenceCount counts foreign keys and many-to-many links together. */
	public function testTableReferenceCount() {
		$reference = new Reference();
		$reference->table = 'person';

		$table = new Table('blog_post');
		$table->columnArray = [
			'id' => $this->column('id', Type::INTEGER),
			'author_id' => $this->column('author_id', Type::INTEGER, ['reference' => $reference]),
		];

		$this->assertSame(1, $table->referenceCount);
	}

	//
	// Settings lookup
	//

	public function testLookupSettingReadsAttributes() {
		$xml = new SimpleXMLElement('<database index="2"><className prefix=" My " suffix=""/></database>');

		$this->assertSame(2, Utils::lookupSetting($xml, null, 'index', Type::INTEGER));
		$this->assertSame('My', Utils::lookupSetting($xml, 'className', 'prefix'));
		$this->assertSame('', Utils::lookupSetting($xml, 'className', 'suffix'));
	}

	public function testLookupSettingReadsNodeValues() {
		$xml = new SimpleXMLElement('<database><relationships>  person.id  </relationships></database>');

		$this->assertSame('person.id', Utils::lookupSetting($xml, 'relationships'));
	}

	public function testLookupSettingCastsBooleans() {
		$xml = new SimpleXMLElement('<database><flag on="true" off="false"/></database>');

		$this->assertTrue(Utils::lookupSetting($xml, 'flag', 'on', Type::BOOLEAN));
		$this->assertFalse(Utils::lookupSetting($xml, 'flag', 'off', Type::BOOLEAN));
	}

	/**
	 * A numeric setting that will not cast reads as null rather than aborting the
	 * run, so a missing optional setting and a malformed one behave the same way.
	 * Booleans have no such failure mode - the cast treats anything that is not
	 * empty or "false" as true.
	 */
	public function testLookupSettingReturnsNullOnUncastableValue() {
		$xml = new SimpleXMLElement('<database index="not-a-number"><flag on="maybe"/></database>');

		$this->assertNull(Utils::lookupSetting($xml, null, 'index', Type::INTEGER));
		$this->assertNull(Utils::lookupSetting($xml, null, 'missing', Type::INTEGER));
		$this->assertTrue(Utils::lookupSetting($xml, 'flag', 'on', Type::BOOLEAN));
	}

	//
	// Settings validation
	//
	// These branches all run before analyzeDatabase(), so they need no harness and
	// no database - a real DatabaseCodeGen returns from its constructor as soon as
	// it has recorded an error.
	//

	public function testMissingDatabaseIndexIsAnError() {
		$codegen = new DatabaseCodeGen('/docroot', ['/codegen'], $this->settingsXml(['index' => '0']));

		$this->assertStringContainsString('databaseIndex was invalid or not set', $codegen->errors);
	}

	/**
	 * A malformed namespace generates code that parses fine and never loads, since
	 * PSR-4 resolves by path. Worth catching at configuration time.
	 */
	public function testInvalidNamespaceIsAnError() {
		$codegen = new DatabaseCodeGen('/docroot', ['/codegen'], $this->settingsXml(['namespaceData' => '1Bad\Data']));

		$this->assertStringContainsString('namespace data="1Bad\Data" is not a valid namespace', $codegen->errors);
	}

	public function testInvalidTypeNamespaceIsAnError() {
		$codegen = new DatabaseCodeGen('/docroot', ['/codegen'], $this->settingsXml(['namespaceType' => 'App Type']));

		$this->assertStringContainsString('namespace type=', $codegen->errors);
	}

	/** An empty namespace setting falls back to the historical defaults. */
	public function testNamespaceDefaults() {
		$codegen = $this->codegen(['namespaceData' => '', 'namespaceType' => '']);

		$this->assertSame('', $codegen->errors);
		$this->assertSame('App\Data', $codegen->namespaceData);
		$this->assertSame('App\Type', $codegen->namespaceType);
	}

	/** A leading or trailing separator is normalized away, so templates can compose freely. */
	public function testNamespaceIsNormalized() {
		$codegen = $this->codegen(['namespaceData' => '\Example\Data\\']);

		$this->assertSame('', $codegen->errors);
		$this->assertSame('Example\Data', $codegen->namespaceData);
	}

	public function testMissingRelationshipsScriptIsAnError() {
		$codegen = new DatabaseCodeGen('/docroot', ['/codegen'], $this->settingsXml([
			'scriptPath' => '/nonexistent/relationships.sql',
			'scriptFormat' => 'sql',
		]));

		$this->assertStringContainsString('does not exist', $codegen->errors);
	}

	public function testInvalidRelationshipsScriptFormatIsAnError() {
		$scriptPath = tempnam(sys_get_temp_dir(), 'cog-relationships-');
		file_put_contents($scriptPath, "alter table blog_post add foreign key (author_id) references person (id);\n");

		try {
			$codegen = new DatabaseCodeGen('/docroot', ['/codegen'], $this->settingsXml([
				'scriptPath' => $scriptPath,
				'scriptFormat' => 'yaml',
			]));

			$this->assertStringContainsString('format "yaml" is invalid', $codegen->errors);
		} finally {
			unlink($scriptPath);
		}
	}
}

/**
 * A DatabaseCodeGen with its database analysis stubbed out, exposing the
 * protected naming helpers. Declared here rather than in its own file because it
 * exists only for this test - src/Test/ files are listed one by one in
 * phpunit.xml.dist, and this is not a test class.
 */
class CodegenHelperHarness extends DatabaseCodeGen {

	/** No database in these tests: everything under test runs before this point. */
	protected function analyzeDatabase(): void {}

	public function callStripPrefixFromTable(string $tableName): string {
		return $this->stripPrefixFromTable($tableName);
	}

	public function callCalculateObjectDescription(string $tableName, string $columnName, string $referencedTableName, bool $pluralize): string {
		return $this->calculateObjectDescription($tableName, $columnName, $referencedTableName, $pluralize);
	}

	public function callCalculateObjectDescriptionForAssociation(string $associationTableName, string $tableName, string $referencedTableName, bool $pluralize): string {
		return $this->calculateObjectDescriptionForAssociation($associationTableName, $tableName, $referencedTableName, $pluralize);
	}

	public function callCalculateObjectMemberVariable(string $tableName, string $columnName, string $referencedTableName): string {
		return $this->calculateObjectMemberVariable($tableName, $columnName, $referencedTableName);
	}

	public function callCalculateObjectPropertyName(string $tableName, string $columnName, string $referencedTableName): string {
		return $this->calculateObjectPropertyName($tableName, $columnName, $referencedTableName);
	}

	public function callTypeTokenFromTypeName(string $name): string {
		return $this->typeTokenFromTypeName($name);
	}

	public function callCalculateGraphPrefixArray(array $foreignKeyArray): array {
		return $this->calculateGraphPrefixArray($foreignKeyArray);
	}

	public function callParameterCleanupFromColumn(Column $column, bool $includeEquality = false): string {
		return $this->parameterCleanupFromColumn($column, $includeEquality);
	}
}
