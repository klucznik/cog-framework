<?php declare(strict_types=1);

namespace Cog\Test;

use Cog\Codegen\CodeGenRunner;
use Cog\Codegen\DatabaseCodeGen;
use Cog\Codegen\ForeignKey;
use Cog\Codegen\Index;
use Cog\Database\Database;
use Cog\Database\FieldType;
use Cog\Exceptions\CogException;
use Cog\Util\FileSystem;
use Exception;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * The schema analysis phase of DatabaseCodeGen - everything its constructor
 * does between reading the settings and being ready to generate - driven by
 * FakeSchemaAdapter rather than the fixture database.
 *
 * TestCodegen shows the generator handling a well-formed schema. The analysis
 * also has to reject or warn about the malformed ones, and the fixture cannot
 * hold those without breaking every other test that reads it. So each branch
 * gets a schema of its own here, built in memory: an association table with
 * three columns, a table named after a reserved word, a foreign key with no
 * index behind it. No database is involved.
 *
 * The generator looks its adapter up in Database::$databases and, when checking
 * for duplicate table names, walks CodeGenRunner::$codegenArray. Both are
 * statics the bootstrap has already populated, so each test works against a
 * spare database index and an emptied runner array, and tearDown() puts the
 * runner array back.
 */
class TestCodegenAnalysis extends TestCase {

	/** Spare index in Database::$databases; the fixture ORM binds to 1 and TestDatabase uses 0. */
	private const int DATABASE_INDEX = 99;

	private const string SETTINGS = '<database index="99">
			<templates path="/codegen"/>
			<className prefix="" suffix=""/>
			<associatedObjectName prefix="" suffix=""/>
			<namespace data="App\Data" type="App\Type"/>
			<typeTableIdentifier suffix="_type"/>
			<associationTableIdentifier suffix="_assn"/>
			<stripFromTableName prefix=""/>
			<excludeTables pattern="" list=""/>
			<includeTables pattern="" list=""/>
		</database>';

	private ?array $savedCodegenArray = null;

	/** @var string|null scratch docroot for the tests that generate, removed in tearDown() */
	private ?string $docroot = null;

	protected function setUp(): void {
		$this->savedCodegenArray = isset(CodeGenRunner::$codegenArray) ? CodeGenRunner::$codegenArray : null;
		CodeGenRunner::$codegenArray = [];
	}

	protected function tearDown(): void {
		unset(Database::$databases[self::DATABASE_INDEX]);
		CodeGenRunner::$codegenArray = $this->savedCodegenArray ?? [];

		if ($this->docroot !== null && is_dir($this->docroot)) {
			// The templates are symlinked in; removeDirectory() must not see the link.
			if (is_link($this->docroot . '/codegen')) {
				unlink($this->docroot . '/codegen');
			}
			FileSystem::removeDirectory($this->docroot);
		}
	}

	/** A throwaway docroot with the repository's templates linked in as /codegen, for tests that generate. */
	private function scratchDocroot(): string {
		$this->docroot = sys_get_temp_dir() . '/cog-analysis-test-' . bin2hex(random_bytes(8));
		mkdir($this->docroot);

		if (@symlink(dirname(__DIR__, 2) . '/codegen', $this->docroot . '/codegen') === false) {
			$this->markTestSkipped('cannot symlink the templates into a scratch docroot');
		}

		// The class headers name the application; the runner normally sets it.
		if (!isset(CodeGenRunner::$applicationName)) {
			CodeGenRunner::$applicationName = 'TestCodegenAnalysis';
		}

		return $this->docroot;
	}

	//
	// Building schemas
	//

	private static function schema(): FakeSchemaAdapter {
		return new FakeSchemaAdapter(self::DATABASE_INDEX);
	}

	/** The usual auto-increment integer primary key. */
	private static function id(string $name = 'id'): FakeSchemaField {
		return new FakeSchemaField($name, FieldType::INTEGER, primaryKey: true, notNull: true, identity: true);
	}

	private static function integer(string $name, bool $notNull = true, bool $primaryKey = false): FakeSchemaField {
		return new FakeSchemaField($name, FieldType::INTEGER, primaryKey: $primaryKey, notNull: $notNull);
	}

	private static function varchar(string $name, bool $unique = false, bool $primaryKey = false): FakeSchemaField {
		return new FakeSchemaField($name, FieldType::VARCHAR, primaryKey: $primaryKey, notNull: true, unique: $unique, maxLength: 100);
	}

	/** A nullable JSON column, with the byte limit MySQL reports for one as its length. */
	private static function json(string $name): FakeSchemaField {
		return new FakeSchemaField($name, FieldType::JSON, maxLength: 4294967295);
	}

	private static function index(string $keyName, array $columnNames, bool $unique = false, bool $primaryKey = false): Index {
		$index = new Index($keyName);
		$index->columnNameArray = $columnNames;
		$index->unique = $unique;
		$index->primaryKey = $primaryKey;

		return $index;
	}

	private static function foreignKey(string $columnName, string $referencedTable, string $referencedColumn = 'id'): ForeignKey {
		return new ForeignKey('fk_' . $columnName, [$columnName], $referencedTable, [$referencedColumn]);
	}

	/** A schema holding one well-formed table, `widget`, plus whatever the test adds. */
	private static function widgetSchema(): FakeSchemaAdapter {
		return self::schema()->addTable('widget', [self::id(), self::varchar('name')]);
	}

	/** Two-column association table between `widget` and `gadget`, shaped the way the generator wants it. */
	private static function addWidgetGadgetAssociation(FakeSchemaAdapter $schema): FakeSchemaAdapter {
		return $schema
			->addTable('gadget', [self::id(), self::varchar('name')])
			->addTable('widget_gadget_assn',
				[self::integer('widget_id', primaryKey: true), self::integer('gadget_id', primaryKey: true)],
				[self::index('gadget_id', ['gadget_id'])],
				[self::foreignKey('widget_id', 'widget'), self::foreignKey('gadget_id', 'gadget')]
			);
	}

	/**
	 * Run the analysis against a schema. Settings are named "tag.attribute",
	 * e.g. 'excludeTables.pattern', and override the defaults in SETTINGS.
	 */
	private function analyze(FakeSchemaAdapter $schema, array $settings = [], string $docroot = '/docroot'): DatabaseCodeGen {
		$xml = new SimpleXMLElement(self::SETTINGS);
		foreach ($settings as $path => $value) {
			[$tag, $attribute] = explode('.', $path);
			$xml->{$tag}[$attribute] = $value;
		}

		Database::$databases[self::DATABASE_INDEX] = $schema;

		return new DatabaseCodeGen($docroot, ['/codegen'], $xml);
	}

	//
	// Table classification
	//

	public function testTablesAreClassifiedByNameSuffix() {
		$schema = self::addWidgetGadgetAssociation(self::widgetSchema())
			->addTable('color_type', [self::id(), self::varchar('name', unique: true)], rows: [[1, 'Red']]);

		$codegen = $this->analyze($schema);

		$this->assertSame('', $codegen->errors);
		$this->assertSame(['widget', 'gadget'], array_keys($codegen->tableArray));
		$this->assertSame(['color_type'], array_keys($codegen->typeTableArray));
	}

	public function testTypeTableSuffixSettingIsAList() {
		$schema = self::schema()->addTable('color_enum', [self::id(), self::varchar('name', unique: true)], rows: [[1, 'Red']]);

		$codegen = $this->analyze($schema, ['typeTableIdentifier.suffix' => '_type, _enum']);

		$this->assertSame(['color_enum'], array_keys($codegen->typeTableArray));
		$this->assertSame([], $codegen->tableArray);
	}

	public function testExcludeListDropsTheNamedTables() {
		$schema = self::widgetSchema()->addTable('audit_log', [self::id(), self::varchar('entry')]);

		$codegen = $this->analyze($schema, ['excludeTables.list' => 'audit_log']);

		$this->assertSame(['widget'], array_keys($codegen->tableArray));
	}

	public function testExcludePatternDropsMatchingTables() {
		$schema = self::widgetSchema()->addTable('tmp_scratch', [self::id(), self::varchar('note')]);

		$codegen = $this->analyze($schema, ['excludeTables.pattern' => '^tmp_']);

		$this->assertSame(['widget'], array_keys($codegen->tableArray));
	}

	public function testIncludeListOverridesAnExclusion() {
		$schema = self::schema()
			->addTable('tmp_keep', [self::id(), self::varchar('note')])
			->addTable('tmp_drop', [self::id(), self::varchar('note')]);

		$codegen = $this->analyze($schema, ['excludeTables.pattern' => '^tmp_', 'includeTables.list' => 'tmp_keep']);

		$this->assertSame(['tmp_keep'], array_keys($codegen->tableArray));
	}

	public function testIncludePatternOverridesAnExclusion() {
		$schema = self::schema()
			->addTable('tmp_keep', [self::id(), self::varchar('note')])
			->addTable('tmp_drop', [self::id(), self::varchar('note')]);

		$codegen = $this->analyze($schema, ['excludeTables.pattern' => '^tmp_', 'includeTables.pattern' => 'keep$']);

		$this->assertSame(['tmp_keep'], array_keys($codegen->tableArray));
	}

	/** Two generators in one run cannot both own a table: the classes they would emit collide. */
	public function testTableNameUsedByAnotherGeneratorIsAnError() {
		$first = $this->analyze(self::widgetSchema());
		CodeGenRunner::$codegenArray[] = $first;

		$second = $this->analyze(self::widgetSchema());

		$this->assertStringContainsString('Duplicate Table Name Used: widget', $second->errors);
	}

	//
	// Table and column names
	//

	public function testReservedWordTableNameIsAnErrorAndTheTableIsDropped() {
		$codegen = $this->analyze(self::schema()->addTable('class', [self::id(), self::varchar('name')]));

		$this->assertStringContainsString("Table 'class' has a table name which is a PHP reserved word", $codegen->errors);
		$this->assertSame([], $codegen->tableArray);
	}

	public function testTableNameStartingWithADigitIsAnErrorAndTheTableIsDropped() {
		$codegen = $this->analyze(self::schema()->addTable('1st_widget', [self::id(), self::varchar('name')]));

		$this->assertStringContainsString("Table '1st_widget' can only contain characters that are alphanumeric or _", $codegen->errors);
		$this->assertSame([], $codegen->tableArray);
	}

	public function testColumnNameWithADashIsAnErrorAndTheColumnIsSkipped() {
		$codegen = $this->analyze(self::schema()->addTable('widget', [self::id(), self::varchar('bad-name')]));

		$this->assertStringContainsString('Invalid column name in table widget: bad-name. Dashes are not allowed.', $codegen->errors);
		$this->assertSame(['id'], array_keys($codegen->getTable('widget')->columnArray));
	}

	public function testColumnNameStartingWithADigitIsAnErrorAndTheTableIsDropped() {
		$codegen = $this->analyze(self::schema()->addTable('widget', [self::id(), self::varchar('1col')]));

		$this->assertStringContainsString("Table 'widget' has an invalid column name: '1col'", $codegen->errors);
		$this->assertSame([], $codegen->tableArray);
	}

	public function testTableWithoutAPrimaryKeyIsAnErrorAndTheTableIsDropped() {
		$codegen = $this->analyze(self::schema()->addTable('nopk', [self::varchar('name')]));

		$this->assertStringContainsString('Table nopk does not have any defined primary keys.', $codegen->errors);
		$this->assertSame([], $codegen->tableArray);
	}

	public function testGetTableRejectsAnUnknownTable() {
		$codegen = $this->analyze(self::widgetSchema());

		try {
			$codegen->getTable('nowhere');
			$this->fail('an unknown table should not be looked up silently');
		} catch (CogException $exception) {
			$this->assertStringContainsString('Table does not exist or does not have a defined Primary Key: nowhere', $exception->getMessage());
		}
	}

	public function testGetColumnRejectsAnUnknownColumn() {
		$codegen = $this->analyze(self::widgetSchema());

		$this->assertSame('name', $codegen->getColumn('widget', 'NAME')->name, 'lookups are case-insensitive');

		try {
			$codegen->getColumn('widget', 'ghost');
			$this->fail('an unknown column should not be looked up silently');
		} catch (CogException $exception) {
			$this->assertStringContainsString('Column does not exist in widget: ghost', $exception->getMessage());
		}
	}

	//
	// Indexes
	//

	/**
	 * The primary key index comes from the columns, not from the adapter. Like
	 * any other single-column index it marks its column, and a single-column
	 * primary key is unique whether or not the adapter flags it so - MySQL
	 * reports PRI and UNIQUE as different flags and only ever sets one.
	 */
	public function testPrimaryKeyIndexIsDerivedFromTheColumns() {
		$table = $this->analyze(self::widgetSchema())->getTable('widget');

		$this->assertCount(1, $table->indexArray);
		$index = $table->indexArray[0];
		$this->assertSame('pk_widget', $index->keyName);
		$this->assertTrue($index->primaryKey);
		$this->assertTrue($index->unique);
		$this->assertSame(['id'], $index->columnNameArray);
		$this->assertTrue($table->columnArray['id']->indexed);
		$this->assertTrue($table->columnArray['id']->unique);
	}

	public function testCompositePrimaryKeyColumnsAreNotUniqueOnTheirOwn() {
		$schema = self::schema()->addTable('pair', [self::integer('a', primaryKey: true), self::integer('b', primaryKey: true)]);

		$table = $this->analyze($schema)->getTable('pair');

		$this->assertSame(['a', 'b'], $table->indexArray[0]->columnNameArray);
		$this->assertFalse($table->columnArray['a']->unique);
		$this->assertFalse($table->columnArray['a']->indexed);
		$this->assertFalse($table->columnArray['b']->unique);
	}

	public function testSingleColumnIndexesMarkTheirColumn() {
		$schema = self::schema()->addTable('widget',
			[self::id(), self::varchar('name'), self::varchar('code')],
			[self::index('name', ['name'], unique: true), self::index('code', ['code']), self::index('name_code', ['name', 'code'])]
		);

		$table = $this->analyze($schema)->getTable('widget');

		$this->assertCount(4, $table->indexArray);
		$this->assertTrue($table->columnArray['name']->indexed);
		$this->assertTrue($table->columnArray['name']->unique);
		$this->assertTrue($table->columnArray['code']->indexed);
		$this->assertFalse($table->columnArray['code']->unique);
	}

	/** The adapter reports the PRIMARY index too; the one derived from the columns already covers it. */
	public function testAnIndexOnTheSameColumnsIsNotAddedTwice() {
		$schema = self::schema()->addTable('widget', [self::id(), self::varchar('name')], [self::index('PRIMARY', ['id'], unique: true, primaryKey: true)]);

		$table = $this->analyze($schema)->getTable('widget');

		$this->assertCount(1, $table->indexArray);
	}

	public function testIndexOnNoColumnsIsAnError() {
		$schema = self::schema()->addTable('widget', [self::id(), self::varchar('name')], [self::index('empty', [])]);

		$codegen = $this->analyze($schema);

		$this->assertStringContainsString('Index empty in table widget indexes on no columns.', $codegen->errors);
		$this->assertCount(1, $codegen->getTable('widget')->indexArray);
	}

	public function testIndexOnAMissingColumnIsAnError() {
		$schema = self::schema()->addTable('widget', [self::id(), self::varchar('name')], [self::index('ghost', ['ghost'])]);

		$codegen = $this->analyze($schema);

		$this->assertStringContainsString('Index ghost in table widget indexes on the column ghost, which does not appear to exist.', $codegen->errors);
		$this->assertCount(1, $codegen->getTable('widget')->indexArray);
	}

	//
	// Foreign keys
	//

	public function testForeignKeyBecomesAReferenceAndAReverseReference() {
		$schema = self::widgetSchema()->addTable('gadget',
			[self::id(), self::integer('widget_id')],
			[self::index('widget_id', ['widget_id'])],
			[self::foreignKey('widget_id', 'widget')]
		);

		$codegen = $this->analyze($schema);

		$this->assertSame('', $codegen->errors);
		$this->assertSame('', $codegen->warnings);

		$reference = $codegen->getColumn('gadget', 'widget_id')->reference;
		$this->assertSame('fk_widget_id', $reference->keyName);
		$this->assertSame('widget', $reference->table);
		$this->assertSame('id', $reference->column);
		$this->assertSame('Widget', $reference->variableType);
		$this->assertFalse($reference->isType);

		$reverse = $codegen->getTable('widget')->reverseReferenceArray;
		$this->assertCount(1, $reverse);
		$this->assertSame('gadget', $reverse[0]->table);
		$this->assertSame('widget_id', $reverse[0]->column);
		$this->assertSame('Gadget', $reverse[0]->variableType);
		$this->assertTrue($reverse[0]->notNull);
		$this->assertFalse($reverse[0]->unique);
		$this->assertSame(1, $codegen->getTable('gadget')->referenceCount);
	}

	public function testForeignKeyToATypeTableIsATypeReferenceWithoutAReverseReference() {
		$schema = self::schema()
			->addTable('color_type', [self::id(), self::varchar('name', unique: true)], rows: [[1, 'Red']])
			->addTable('widget', [self::id(), self::integer('color_id')], [self::index('color_id', ['color_id'])], [self::foreignKey('color_id', 'color_type')]);

		$codegen = $this->analyze($schema);

		$this->assertTrue($codegen->getColumn('widget', 'color_id')->reference->isType);
		$this->assertSame([], $codegen->getTable('color_type')->reverseReferenceArray);
	}

	public function testMultiColumnForeignKeyIsAnError() {
		$schema = self::widgetSchema()->addTable('gadget',
			[self::id(), self::integer('widget_id'), self::integer('widget_rev')],
			[],
			[new ForeignKey('fk_widget', ['widget_id', 'widget_rev'], 'widget', ['id', 'rev'])]
		);

		$codegen = $this->analyze($schema);

		$this->assertStringContainsString('Foreign Key fk_widget in table gadget keys on multiple columns.', $codegen->errors);
		$this->assertNull($codegen->getColumn('gadget', 'widget_id')->reference);
	}

	public function testForeignKeyOnAMissingColumnIsAnError() {
		$schema = self::widgetSchema()->addTable('gadget', [self::id()], [], [self::foreignKey('ghost_id', 'widget')]);

		$codegen = $this->analyze($schema);

		$this->assertStringContainsString('Foreign Key fk_ghost_id in table gadget indexes on a column that does not appear to exist.', $codegen->errors);
	}

	public function testForeignKeyToAMissingTableIsAnError() {
		$schema = self::schema()->addTable('gadget', [self::id(), self::integer('nowhere_id')], [self::index('nowhere_id', ['nowhere_id'])], [self::foreignKey('nowhere_id', 'nowhere')]);

		$codegen = $this->analyze($schema);

		$this->assertStringContainsString('Foreign Key fk_nowhere_id in table gadget references a table nowhere that does not appear to exist.', $codegen->errors);
		$this->assertNull($codegen->getColumn('gadget', 'nowhere_id')->reference);
	}

	public function testForeignKeyWithoutAnIndexGetsAVirtualOneAndANotice() {
		$schema = self::widgetSchema()->addTable('gadget', [self::id(), self::integer('widget_id')], [], [self::foreignKey('widget_id', 'widget')]);

		$codegen = $this->analyze($schema);

		$this->assertStringContainsString('Notice: It is recommended that you add a single-column index on "gadget.widget_id" for the Foreign Key fk_widget_id', $codegen->warnings);
		$indexes = $codegen->getTable('gadget')->indexArray;
		$this->assertCount(2, $indexes);
		$this->assertSame('virtualix_gadget_widget_id', $indexes[1]->keyName);
		$this->assertSame(['widget_id'], $indexes[1]->columnNameArray);
		$this->assertFalse($indexes[1]->unique);
		$this->assertNotNull($codegen->getColumn('gadget', 'widget_id')->reference, 'the reference is still created');
	}

	/**
	 * A foreign key on the primary key itself is how one table extends another.
	 * The reverse side is then a single object - a person has at most one
	 * manager row - named after the extending class rather than after the
	 * column, and the primary key index already satisfies the "index behind
	 * every foreign key" check, so nothing is warned about.
	 */
	public function testForeignKeyOnThePrimaryKeyIsAUniqueReverseReferenceNamedAfterTheExtendingClass() {
		$schema = self::schema()
			->addTable('person', [self::id(), self::varchar('name')])
			->addTable('manager', [self::id(), self::varchar('department')], [], [self::foreignKey('id', 'person')]);

		$codegen = $this->analyze($schema);

		$this->assertSame('', $codegen->errors);
		$this->assertSame('', $codegen->warnings);
		$this->assertCount(1, $codegen->getTable('manager')->indexArray, 'no virtual index is needed');

		$reverse = $codegen->getTable('person')->reverseReferenceArray[0];
		$this->assertSame('id', $reverse->column);
		$this->assertTrue($reverse->unique, 'the templates emit an array of managers otherwise');
		$this->assertSame('loadedManager', $reverse->loadedMember);
		$this->assertSame('Manager', $reverse->objectPropertyName);
		$this->assertSame('Manager', $reverse->objectDescription);
		$this->assertSame('Managers', $reverse->objectDescriptionPlural);
	}

	/**
	 * The unique flag is what the templates switch on, so the generated class is
	 * the real test of it: an inheritance chain has to come out as one adjoined
	 * object on the parent, not as an array of managers.
	 */
	public function testInheritanceChainGeneratesASingleObjectReverseReference() {
		$schema = self::schema()
			->addTable('person', [self::id(), self::varchar('name')])
			->addTable('manager', [self::id(), self::varchar('department')], [], [self::foreignKey('id', 'person')]);
		$codegen = $this->analyze($schema, [], $this->scratchDocroot());

		$this->assertTrue($codegen->generateTable($codegen->getTable('person')));

		$file = $this->docroot . '/generated/Data/PersonGen.php';
		$source = file_get_contents($file);
		$this->assertStringContainsString('public ?Manager $Manager {', $source);
		// The loader is named from the column's property name, so the casing is LoadByid;
		// PHP resolves method names case-insensitively.
		$this->assertStringContainsStringIgnoringCase('Manager::loadById(', $source);
		$this->assertStringContainsString('$this->loadedManager', $source);
		$this->assertStringNotContainsString('getManagerArray', $source);
		$this->assertStringNotContainsString('unassociateAllManagers', $source);

		exec(sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($file)), $output, $status);
		$this->assertSame(0, $status, "the generated class does not lint:\n" . implode("\n", $output));
	}

	/**
	 * A primary key the database does not assign can be changed after the row was
	 * restored, so save() has to remember the value it was restored with. The
	 * generated class keeps that in a `__`-prefixed shadow of the column property.
	 */
	public function testNonIdentityPrimaryKeyKeepsItsRestoredValue() {
		$schema = self::schema()
			->addTable('country', [self::varchar('code', primaryKey: true), self::varchar('name')]);
		$codegen = $this->analyze($schema, [], $this->scratchDocroot());

		$this->assertTrue($codegen->generateTable($codegen->getTable('country')));

		$file = $this->docroot . '/generated/Data/CountryGen.php';
		$source = file_get_contents($file);
		$this->assertStringContainsString('public ?string $code = null;', $source);
		$this->assertStringContainsString('protected ?string $__code = null;', $source);
		$this->assertStringContainsString('$this->__code = $this->code;', $source);

		exec(sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($file)), $output, $status);
		$this->assertSame(0, $status, "the generated class does not lint:\n" . implode("\n", $output));
	}

	public function testForeignKeyToANonPrimaryKeyColumnIsWarned() {
		$schema = self::schema()
			->addTable('other', [self::id(), self::varchar('code', unique: true)], [self::index('code', ['code'], unique: true)])
			->addTable('widget', [self::id(), self::varchar('other_code')], [self::index('other_code', ['other_code'])], [self::foreignKey('other_code', 'other', 'code')]);

		$codegen = $this->analyze($schema);

		$this->assertSame('', $codegen->errors);
		$this->assertStringContainsString(
			'Warning: Invalid Relationship created in Other class (for foreign key "fk_other_code") -- column "code" is not the single-column primary key for the referenced "other" table',
			$codegen->warnings
		);
	}

	public function testForeignKeyIntoACompositePrimaryKeyIsWarned() {
		$schema = self::schema()
			->addTable('pair', [self::integer('a', primaryKey: true), self::integer('b', primaryKey: true)])
			->addTable('widget', [self::id(), self::integer('pair_a')], [self::index('pair_a', ['pair_a'])], [self::foreignKey('pair_a', 'pair', 'a')]);

		$codegen = $this->analyze($schema);

		$this->assertSame('', $codegen->errors);
		$this->assertStringContainsString('column "a" is not the single-column primary key for the referenced "pair" table', $codegen->warnings);
	}

	//
	// Association tables
	//

	public function testAssociationTableGivesBothTablesAManyToManyReference() {
		$codegen = $this->analyze(self::addWidgetGadgetAssociation(self::widgetSchema()));

		$this->assertSame('', $codegen->errors);

		$widgetSide = $codegen->getTable('widget')->manyToManyReferenceArray;
		$this->assertCount(1, $widgetSide);
		$this->assertSame('fk_widget_id', $widgetSide[0]->keyName);
		$this->assertSame('widget_gadget_assn', $widgetSide[0]->table);
		$this->assertSame('widget_id', $widgetSide[0]->column);
		$this->assertSame('gadget_id', $widgetSide[0]->oppositeColumn);
		$this->assertSame('gadget', $widgetSide[0]->associatedTable);
		$this->assertSame('Gadget', $widgetSide[0]->variableType);
		$this->assertSame(1, $codegen->getTable('widget')->referenceCount);

		$gadgetSide = $codegen->getTable('gadget')->manyToManyReferenceArray;
		$this->assertCount(1, $gadgetSide);
		$this->assertSame('gadget_id', $gadgetSide[0]->column);
		$this->assertSame('widget', $gadgetSide[0]->associatedTable);
		$this->assertSame('Widget', $gadgetSide[0]->variableType);
	}

	public function testAssociationTableNeedsExactlyTwoColumns() {
		$schema = self::widgetSchema()->addTable('widget_gadget_assn',
			[self::integer('widget_id', primaryKey: true), self::integer('gadget_id', primaryKey: true), self::varchar('note')]
		);

		$codegen = $this->analyze($schema);

		$this->assertStringContainsString('AssociationTable widget_gadget_assn does not have exactly 2 columns.', $codegen->errors);
		$this->assertSame([], $codegen->getTable('widget')->manyToManyReferenceArray);
	}

	public function testAssociationTableColumnsMustBeNotNull() {
		$schema = self::widgetSchema()->addTable('widget_gadget_assn',
			[self::integer('widget_id', primaryKey: true), self::integer('gadget_id', notNull: false, primaryKey: true)]
		);

		$codegen = $this->analyze($schema);

		$this->assertStringContainsString("AssociationTable widget_gadget_assn's two columns must both be not null", $codegen->errors);
	}

	public function testAssociationTableNeedsACompositePrimaryKey() {
		$schema = self::widgetSchema()->addTable('widget_gadget_assn',
			[self::integer('widget_id', primaryKey: true), self::integer('gadget_id')]
		);

		$codegen = $this->analyze($schema);

		$this->assertStringContainsString('AssociationTable widget_gadget_assn only support two-column composite Primary Keys.', $codegen->errors);
	}

	public function testAssociationTableNeedsExactlyTwoForeignKeys() {
		$schema = self::widgetSchema()->addTable('widget_gadget_assn',
			[self::integer('widget_id', primaryKey: true), self::integer('gadget_id', primaryKey: true)],
			[],
			[self::foreignKey('widget_id', 'widget')]
		);

		$codegen = $this->analyze($schema);

		$this->assertStringContainsString('AssociationTable widget_gadget_assn does not have exactly 2 foreign keys. Code Gen analysis found 1.', $codegen->errors);
	}

	public function testAssociationTableWithAMultiColumnForeignKeyIsAnError() {
		$schema = self::widgetSchema()
			->addTable('gadget', [self::id(), self::varchar('name')])
			->addTable('widget_gadget_assn',
				[self::integer('widget_id', primaryKey: true), self::integer('gadget_id', primaryKey: true)],
				[],
				[new ForeignKey('fk_both', ['widget_id', 'gadget_id'], 'widget', ['id', 'rev']), self::foreignKey('gadget_id', 'gadget')]
			);

		$codegen = $this->analyze($schema);

		$this->assertStringContainsString('AssociationTable widget_gadget_assn has multi-column foreign keys.', $codegen->errors);
		$this->assertSame([], $codegen->getTable('widget')->manyToManyReferenceArray);
	}

	/** An association into a table that was excluded from generation is simply not generated either. */
	public function testAssociationTableLinkedToAnExcludedTableIsSkipped() {
		$codegen = $this->analyze(self::addWidgetGadgetAssociation(self::widgetSchema()), ['excludeTables.list' => 'gadget']);

		$this->assertSame('', $codegen->errors);
		$this->assertSame(['widget'], array_keys($codegen->tableArray));
		$this->assertSame([], $codegen->getTable('widget')->manyToManyReferenceArray);
	}

	public function testAssociationTableWithAForeignKeyToAnUnknownTableThrows() {
		$schema = self::widgetSchema()->addTable('widget_gadget_assn',
			[self::integer('widget_id', primaryKey: true), self::integer('gadget_id', primaryKey: true)],
			[],
			[self::foreignKey('widget_id', 'widget'), self::foreignKey('gadget_id', 'nowhere')]
		);

		try {
			$this->analyze($schema);
			$this->fail('an association into a table the generator has never seen cannot be resolved');
		} catch (Exception $exception) {
			$this->assertStringContainsString('AssociationTable widget_gadget_assn has foreign keys that cannot be resolved.', $exception->getMessage());
		}
	}

	//
	// Type tables
	//

	public function testTypeTableFirstColumnMustBeAnIntegerPrimaryKey() {
		$schema = self::schema()->addTable('color_type', [self::varchar('code', primaryKey: true), self::varchar('name', unique: true)]);

		$codegen = $this->analyze($schema);

		$this->assertStringContainsString("TypeTable color_type's first column is not a PK integer.", $codegen->errors);
	}

	public function testTypeTableSecondColumnMustBeAUniqueVarchar() {
		$schema = self::schema()->addTable('color_type', [self::id(), self::varchar('name')]);

		$codegen = $this->analyze($schema);

		$this->assertStringContainsString("TypeTable color_type's second column is not a unique VARCHAR.", $codegen->errors);
	}

	public function testTypeTableRowsBecomeNamesAndTokensOrderedById() {
		$schema = self::schema()->addTable('color_type', [self::id(), self::varchar('name', unique: true)], rows: [[2, 'Dark Red'], [1, 'Red']]);

		$typeTable = $this->analyze($schema)->getTable('color_type');

		$this->assertSame([1 => 'Red', 2 => 'Dark Red'], $typeTable->nameArray);
		$this->assertSame([1 => 'Red', 2 => 'Dark_Red'], $typeTable->tokenArray, 'tokens keep their case here; the type template upper-cases them');
		$this->assertSame([], $typeTable->extraFieldNamesArray);
	}

	/** Names are emitted inside single-quoted PHP strings, so they are escaped at analysis time. */
	public function testTypeTableNameQuotesAreEscapedForTheGeneratedSource() {
		$schema = self::schema()->addTable('vendor_type', [self::id(), self::varchar('name', unique: true)], rows: [[1, "O'Neil \\ Sons"]]);

		$typeTable = $this->analyze($schema)->getTable('vendor_type');

		$this->assertSame([1 => "O\\'Neil \\\\ Sons"], $typeTable->nameArray);
		$this->assertSame([1 => 'ONeil__Sons'], $typeTable->tokenArray, 'spaces become underscores and anything else non-alphanumeric is dropped');
	}

	public function testTypeTableNameThatIsAReservedWordGetsAnUnderscoreAndAWarning() {
		$schema = self::schema()->addTable('keyword_type', [self::id(), self::varchar('name', unique: true)], rows: [[1, 'Class'], [2, 'Other']]);

		$codegen = $this->analyze($schema);

		$this->assertStringContainsString('Warning: TypeTable keyword_type contains a type name which is a reserved word: class.', $codegen->warnings);
		$this->assertSame([1 => '_Class', 2 => 'Other'], $codegen->getTable('keyword_type')->tokenArray);
	}

	public function testTypeTableNameWithNoTokenCharactersIsAWarning() {
		$schema = self::schema()->addTable('symbol_type', [self::id(), self::varchar('name', unique: true)], rows: [[1, '***']]);

		$codegen = $this->analyze($schema);

		$this->assertStringContainsString('Warning: TypeTable symbol_type contains an invalid type name: ***', $codegen->warnings);
	}

	public function testTypeTableExtraColumnsBecomeExtraProperties() {
		$schema = self::schema()->addTable('priority_type',
			[self::id(), self::varchar('name', unique: true), self::integer('sort_order'), self::integer('is_default')],
			rows: [[1, 'Low', '30', '0'], [2, 'High', '10', '1']]
		);

		$typeTable = $this->analyze($schema)->getTable('priority_type');

		$this->assertSame(['sortOrder', 'isDefault'], $typeTable->extraFieldNamesArray);
		$this->assertSame([
			1 => ['sortOrder' => '30', 'isDefault' => '0'],
			2 => ['sortOrder' => '10', 'isDefault' => '1'],
		], $typeTable->extraPropertyArray);
	}

	//
	// Reporting
	//

	public function testReportLabelCountsTheTables() {
		$this->assertSame(
			'There were no tables available to attempt code generation.',
			$this->analyze(self::schema())->getReportLabel()
		);
		$this->assertSame(
			'There was 1 table available to attempt code generation:',
			$this->analyze(self::widgetSchema())->getReportLabel()
		);
		$this->assertSame(
			'There were 2 tables available to attempt code generation:',
			$this->analyze(self::widgetSchema()->addTable('color_type', [self::id(), self::varchar('name', unique: true)]))->getReportLabel()
		);
	}

	public function testTitleNamesTheDatabaseBehindTheIndex() {
		$codegen = $this->analyze(self::widgetSchema());

		$this->assertSame('Database Index #99 (Fake Schema Adapter (FakeSchema) / fake / fake_db)', $codegen->getTitle());

		unset(Database::$databases[self::DATABASE_INDEX]);
		$this->assertSame('Database Index #99 (N/A)', $codegen->getTitle());
	}

	public function testConfigXmlEchoesTheSettingsItWasBuiltFrom() {
		$codegen = $this->analyze(self::widgetSchema(), ['excludeTables.pattern' => '^tmp_', 'typeTableIdentifier.suffix' => '_type, _enum']);

		$xml = $codegen->getConfigXml();

		$this->assertStringContainsString('<database index="99">', $xml);
		$this->assertStringContainsString('<templates path="/codegen"/>', $xml);
		$this->assertStringContainsString('<namespace data="App\Data" type="App\Type"/>', $xml);
		$this->assertStringContainsString('<typeTableIdentifier suffix="_type,_enum"/>', $xml);
		$this->assertStringContainsString('<excludeTables pattern="^tmp_" list=""/>', $xml);
	}

	//
	// JSON columns
	//

	/**
	 * A JSON column keeps its text in the column property, so loading and saving never
	 * re-encode it, and gains a decoded accessor beside it. getIterator() emits the decoded
	 * value, or getJson() would hand clients a string of escaped JSON.
	 */
	public function testJsonColumnGetsADecodedAccessor() {
		$schema = self::schema()
			->addTable('document', [self::id(), self::json('settings')]);
		$codegen = $this->analyze($schema, [], $this->scratchDocroot());

		$this->assertSame('', $codegen->errors);
		$this->assertTrue($codegen->generateTable($codegen->getTable('document')));

		$file = $this->docroot . '/generated/Data/DocumentGen.php';
		$source = file_get_contents($file);
		$this->assertStringContainsString('public ?string $settings = null;', $source);
		$this->assertStringContainsString('public mixed $settingsDecoded {', $source);
		$this->assertStringContainsString("\$iArray['settings'] = Utils::decodeJsonColumn(\$this->settings);", $source);
		$this->assertStringNotContainsString('SETTINGS_MAX_LENGTH', $source, 'the byte limit MySQL reports for JSON is not a length');

		exec(sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($file)), $output, $status);
		$this->assertSame(0, $status, "the generated class does not lint:\n" . implode("\n", $output));

		require_once $file;
		$document = new \Generated\Data\DocumentGen();

		$document->settingsDecoded = ['theme' => 'dark', 'panels' => new \stdClass()];
		$this->assertSame('{"theme":"dark","panels":{}}', $document->settings, 'assigning the accessor writes the text');

		$document->settings = '{"theme":"light","panels":[]}';
		$this->assertSame('light', $document->settingsDecoded->theme, 'reading the accessor decodes the text');
		$this->assertSame('{"id":null,"settings":{"theme":"light","panels":[]}}', $document->getJson(), 'the JSON is nested, not an escaped string');

		$document->settingsDecoded = null;
		$this->assertNull($document->settings, 'null is SQL NULL');
	}

	public function testJsonAccessorNameTakenByAnotherColumnIsAnError() {
		$codegen = $this->analyze(self::schema()->addTable('document', [self::id(), self::json('settings'), self::varchar('settings_decoded')]));

		$this->assertStringContainsString("Table 'document' has JSON column 'settings', whose decoded accessor settingsDecoded collides with another column's property", $codegen->errors);
		$this->assertSame([], $codegen->tableArray);
	}
}
