# CLAUDE.md

Cog is a PHP framework built on Symfony components, with a database code generator that produces
typed ORM classes from an existing MySQL schema.

[README.md](README.md) covers installation, bootstrapping and test-database setup. This file covers
the conventions and traps that are not visible from reading a single file.

## Commands

```bash
composer test        # phpunit - runs the code generator first, via the bootstrap
./cog db:codegen     # regenerate the ORM from codegen.xml
./cog list           # discover the available console commands
```

The suite needs a reachable `cog_framework_test` MySQL database; without it nothing runs. See README.

## Layout

PSR-4 is `Cog\ => src`, and the directory tree mirrors the namespace exactly. `src/Codegen/_functions.php`
is additionally pulled in through composer's `files` autoload.

| Path | Responsibility |
| --- | --- |
| `src/Base.php` | Root class for nearly everything; the magic property pattern below |
| `src/BaseApplication.php` | Lifecycle: error handler, service container, routing, command dirs |
| `src/Kernel.php` | Hand-rolled `HttpKernelInterface`: request → controller → response |
| `src/Path.php` | Static registry of web/app roots, CLI-vs-web detection |
| `src/Type.php` | Type string constants and `Type::cast()` |
| `src/Codegen/` | The schema → ORM generator and its schema value objects |
| `src/Command/` | Console commands (one per file, see discovery rules below) |
| `src/Console/` | `CommandApplication` (directory scanning) and the traits commands opt into |
| `src/Controller/` | `ControllerBase` and `#[Route]` attribute discovery |
| `src/Database/` | Connection registry, adapters, result/row/field abstraction |
| `src/Query/` | The `QQ` query-object layer and `QueryBuilder` |
| `src/Test/` | The PHPUnit suite itself, shipped inside `src/` |
| `src/Util/` | Strings, notation conversion, filesystem, templates, URLs |

## Code style

- **Tabs.** K&R braces on the same line, including for classes and methods. This is not PSR-12.
- PHP >= 8.5. Properties and class constants are **typed throughout** `src/` -
  `public const string`, `private const array`, `protected ?string $tableName = null`.
  There are no untyped ones left, so a new one is a regression rather than a matter of
  matching the surrounding file.
- `declare(strict_types=1)` on **new** files. It is not repo-wide - only five files in `src/`
  have it - and it should not be retrofitted onto existing ones as a drive-by change, since
  it turns silent scalar coercion into a `TypeError` in code never exercised under it.
- `readonly` is rare in `src/` - `BaseConfig` uses it deliberately for its set-once properties,
  and that is currently the only place. This is an observation, not a rule: use it where
  set-once semantics genuinely apply, and don't retrofit it elsewhere as a drive-by change.
- Plain camelCase members everywhere, generated ORM output included: a column is a property named
  after it (`firstName`), and the member caching a lazily loaded object is `loaded`-prefixed
  (`loadedAuthor`). Do not introduce Hungarian prefixes.

## The Base property pattern

The most-repeated idiom in the codebase. `src/Codegen/Column.php` is the reference implementation.

- Backing fields are **private and typed**.
- The public surface is documented in a `@property` / `@property-read` block on the class.
- `__get`/`__set` are a `switch ($name)`, whose `default:` delegates to `parent::__get($name)`
  wrapped in `catch (CogException $exception) { $exception->incrementOffset(); throw $exception; }`
  so the reported error points at the caller rather than at `Base`.
- Setters cast through `Type::cast($value, Type::STRING)` and friends.
- Computed pseudo-properties (no backing field) live in `__get` only, and are `@property-read`.

`Cog\Base::__get`/`__set` themselves always throw `UndefinedPropertyException`, so a missing `case`
surfaces as an undefined-property error, not a silent null.

The generated ORM classes are the exception: they declare no `__get`/`__set`/`__isset` at all.
`column_properties.tpl.php` emits every column as a **typed public property** - `public protected(set)`
for identity and timestamp columns, with a `set` hook on foreign-key columns that drops the cached
object - and `reference_properties.tpl.php` emits each referenced or adjoined object (`author`,
`personProfile`) as a **hooked property** whose `get` lazy-loads into a `loaded*` member. A wrong type
is a `TypeError` at the assignment rather than a `Type::cast()`. `Cog\Base` is still the parent, so a
typo in a property name lands in its `UndefinedPropertyException` instead of creating a dynamic property.
Two consequences: `isset()` and `??` on a reference run its `get` hook, so they load it (once); and
`??` on an undeclared name fetches through `Cog\Base::__get`, so a typo under `??` throws too.

## Codegen templates

Templates live in `codegen/<prefix>/<module>/`, where prefix is `db_orm` or `db_type` and module is
`class_gen`, `class_nodes` or `class_subclass`. Only `_*.tpl.php` files are entry points;
`_main.tpl.php` `include`s the partials beside it. A partial may `return;` early to emit nothing -
`class_gen/object_construct.tpl.php` does exactly that.

`<templates path="..."/>` in `codegen.xml` is **repeatable**. Paths are docroot-relative and applied
in document order, so an application layers its own directory over the one shipped here:

```xml
<templates path="/vendor/klucznik/cog-framework/codegen"/>
<templates path="/templates/codegen"/>
```

A later directory can both **add** modules (the common case - a module absent from every other layer
is simply generated as well) and **override** one. The unit of override is the whole
`<prefix>/<module>` directory, never an individual template: because entry points pull their
partials in with `include __DIR__ . '/partial.tpl.php'`, a per-file merge would let an override load
siblings from the layer below it. So overriding one partial means copying its entire module
directory. `Utils::resolveModuleDir()` is where last-wins is decided, and `Utils::hasTemplates()` -
which is what makes the `aggregate_db_orm` group optional - goes through the same lookup.

`Cog\Codegen\Utils` holds the generator's stateless helpers: settings lookup, the template directory
resolution above, `evaluatePHP()`, and the `goBack()`/`pluralize()` that templates call. They are all
static, so templates use `\Cog\Codegen\Utils::goBack(2)` rather than reaching through `$codegen`.
`Utils::pluralize()` duplicates `Cog\Util\StringUtils::pluralize()` on purpose - the latter resolves
its inflector from the DI container, which codegen cannot assume has been booted.

`Cog\Codegen\VariableNameCreator` is the same idea for naming: every pure `Column` -> name function
lives there, all static, so templates call `VariableNameCreator::translationNameForColumn($column)`
rather than going through `$codegen`. What stayed on `DatabaseCodeGenBase` are the ones that need
generator state - `classNameFromTableName()` and `variableNameFromTable()` read `$classPrefix` and
`stripPrefixFromTable()` - plus the `*ForUniqueReverseReference` / `*ForManyToManyReference` family,
which take something other than a `Column`.

**The first line of an entry-point template is a `<template/>` tag**, parsed by
`Cog\Codegen\CodeGen::generateFile()`:

```
<template OverwriteFlag="true" DocrootFlag="true" DirectorySuffix="" TargetDirectory="/generated/Data" TargetFileName="..."/>
```

- `OverwriteFlag="false"` means "hand-editable subclass, write once, never clobber".
- `TargetDirectory` is resolved against the docroot passed to `CodeGenRunner::run()` when
  `DocrootFlag` is true. The templates path from `codegen.xml` must also live under that docroot.

Rules that have already cost real bugs:

- **`TargetDirectory` must agree with the namespace the template emits.** PSR-4 resolves classes by
  path, so a mismatch produces files that lint clean and never load.
- The generated/hand-written split is `Generated\Data|Node|Type` → `/generated/*` (rewritten on
  every run) versus `App\Data|Type` → `/app/*` (written once, safe to edit).
- **Property initializers must be constant expressions.** A `DEFAULT CURRENT_TIMESTAMP` column
  cannot initialize a `DateTimeImmutable`-typed property. `Column::hasCurrentTimestampDefault()` detects one,
  `Column::getDefaultAsString()` returns a run-time expression (`new DateTimeImmutable()`) rather than a
  literal, and `object_construct.tpl.php` emits a constructor to apply it. `timestamp`
  columns are deliberately excluded - they are the optimistic-locking token and the database
  maintains them.
- Table name suffixes drive the whole shape of the output: `_type` produces an enumerated type class
  built from the table's rows, `_assn` produces many-to-many methods on both sides rather than an
  entity class of its own.
- Class names written inside templates are just strings the generator never type-checks. Exceptions
  live under `Cog\Exceptions\`; getting that wrong only fails at run time, inside generated code.
- **A generated class name must be spelled with the same case everywhere it is emitted.** The
  many-to-many node is declared as `QQNode<Table><objectDescriptionUppercase>` by
  `class_nodes/many.tpl.php` (that spelling is also its file name), so every template that
  instantiates or documents it must use `objectDescriptionUppercase` too - `objectDescription`
  yields `QQNodeObjtag` for a class that lives in `QQNodeObjTag.php`. PHP resolves an already-loaded
  class case-insensitively, but PSR-4 derives the file path from the name as written, so the wrong
  spelling autoloads on a case-insensitive macOS filesystem and fails on Linux with "Class not
  found", unless another code path happened to load the correctly named class first. Neither the
  suite nor a macOS dev machine catches it; grep the emitted `new QQNode...` calls in
  `.phpunit.codegen/generated/Node/` against the file names in the same directory.

## Tests

- Test classes use a **`Test*` prefix** (not PHPUnit's `*Test` suffix) and live in `src/Test/` under
  the shipped `Cog\Test` namespace. Because that defeats suffix-based discovery, every file is
  **listed explicitly** in `phpunit.xml.dist`. Adding a test file without adding its `<file>` line
  means it silently never runs - the easiest mistake to make in this repo.
- `TestCodegen.php` is listed **first** on purpose. `src/Test/bootstrap.php` runs the generator
  before any test executes, and `CodegenFixture` records failures rather than throwing, so a broken
  generator is reported as a failed assertion instead of an unreadable bootstrap fatal.
- Generation output goes to the git-ignored `.phpunit.codegen/`; `CodegenFixture::registerAutoloader()`
  maps `Generated\` and `App\` onto it so later tests can use the generated classes.
- `TestGeneratedLifecycle` and `TestGeneratedAssociations` exercise the generated ORM's write path
  (`save()`, `delete()`, the `associate*` families, optimistic locking). Each test runs inside a
  transaction rolled back in `tearDown()`, so the fixture rows stay as the SQL file left them.
  `truncate()` is DDL and commits implicitly, so it is never called; reverse-reference
  `unassociate*` nulls the foreign key, which strict mode refuses on NOT NULL columns, so those
  run against the nullable `category.owner`.
- `TestCodegenAnalysis` drives `DatabaseCodeGen`'s schema analysis through `FakeSchemaAdapter`, a
  `Database\Base` that answers the four schema methods from arrays, so malformed schemas the
  fixture cannot hold (a three-column `_assn` table, a reserved-word table name) are tested
  without a server. It registers under `Database::$databases[99]`; keep that index free.
- `src/Test/cog_framework_test.sql` is a schema **contract**, asserted against by `TestDatabase` (table count,
  `person` indexes, `obj` foreign key) and by `TestCodegen` (type table, association table, timestamp
  column, `CURRENT_TIMESTAMP` column). Changing the schema means changing those assertions in the
  same commit. Loading the file **drops and recreates** `cog_framework_test`.
- Credentials come from the `COG_TEST_DB_*` env vars; the local `phpunit.xml` override is git-ignored
  and holds real ones - never commit it.

## Gotchas

- With caching on, the router dumps its matcher under `$dirCache/routes/`, so route edits appear
  to do nothing until that directory is cleared. The container itself is rebuilt on every request.
  The test bootstrap forces caching off for that reason.
- `Cog\Path` is **retained deliberately for backwards compatibility** and is not on a deprecation
  path. **Nothing inside `src/` references it at all** - directories and CLI-ness come off
  `BaseConfig`, and `dump:path` has been removed - but the vendored sites call `Path::isCLI()` and
  `Path::dump()`, so it stays. Its `$cliMode` is an independent sniff of the same `$_SERVER` key that
  `createConfig()` reads, not a delegation: `Path::initialize()` runs at autoload time, while
  `BaseApplication::$config` is an uninitialized typed static until `initialize()` runs, so `Path`
  cannot ask the config without fatalling on any pre-boot caller. Framework code should read
  `config()->isCli`. It is also **deliberately untested** - `TestPath`/`MockedPath` were deleted
  rather than maintained, because the class is frozen; do not add coverage back for it.
- `BaseApplication::createConfig()` derives every directory from `dirname(__DIR__)` - the framework's
  own docroot - so the defaults are already normalized and none of them need to exist to be built.
  `dirCache` is not shipped, so applications either create it or override the hook.
- `Database::$databases` is a static, index-keyed registry rather than a pool, and generated ORM
  classes bind to a **specific numeric index** (the test fixture uses
  `CodegenFixture::DATABASE_INDEX = 1`). `initializeConnection()` derives the next index from
  `max(array_keys(...)) + 1`, not `count()`, so closing a connection cannot make the next one
  overwrite a live entry. Adapter classes are resolved by string concatenation from the `adapter`
  config key.
- Console command discovery is **non-recursive** over `src/Command/*.php`, and the filename must
  match the class name. Subdirectories are never scanned, so helper classes can live in one
  without being mistaken for commands.
- `Cog\Util\NamespaceUtil` reads `composer.json` at run time, relative to `Path::$appRoot`, so the
  PSR-4 map is load-bearing well beyond the autoloader.
