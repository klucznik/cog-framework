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

The suite needs a reachable `cog_test` MySQL database; without it nothing runs. See README.

## Layout

PSR-4 is `Cog\ => src`, and the directory tree mirrors the namespace exactly. `src/Codegen/_functions.php`
is additionally pulled in through composer's `files` autoload.

| Path | Responsibility |
| --- | --- |
| `src/Base.php` | Root class for nearly everything; the magic property pattern below |
| `src/BaseApplication.php` | Lifecycle: error handler, DI container build/dump, routing, command dirs |
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
- PHP >= 8.5. Newer code uses typed properties and **typed class constants**
  (`public const string`, `private const array`); older files such as `src/Type.php` and
  `src/Database/FieldType.php` still have untyped constants. Match the file you are editing.
- `declare(strict_types=1)` on **new** files. It is not repo-wide - only five files in `src/`
  have it - and it should not be retrofitted onto existing ones as a drive-by change, since
  it turns silent scalar coercion into a `TypeError` in code never exercised under it.
- No `readonly` anywhere in `src/`. Do not introduce it unprompted.
- Plain camelCase members in framework code. The Hungarian prefixes (`str`, `int`, `flt`, `bln`,
  `dtt`, `obj`, from `Cog\Util\ConvertNotation::prefixFromType()`) belong to **generated ORM output
  only** - never write them by hand.
- `src/Command/ContainerDebugCommand.php` and `src/Command/Descriptor/*` are copied Symfony code in
  4-space style. Leave their formatting alone.

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
  cannot initialize a `Carbon`-typed property. `Column::hasCurrentTimestampDefault()` detects one,
  `Column::getDefaultAsString()` returns a run-time expression (`new Carbon()`) rather than a
  literal, and `object_construct.tpl.php` emits a constructor to apply it. `timestamp`
  columns are deliberately excluded - they are the optimistic-locking token and the database
  maintains them.
- Table name suffixes drive the whole shape of the output: `_type` produces an enumerated type class
  built from the table's rows, `_assn` produces many-to-many methods on both sides rather than an
  entity class of its own.
- Class names written inside templates are just strings the generator never type-checks. Exceptions
  live under `Cog\Exceptions\`; getting that wrong only fails at run time, inside generated code.

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
- `src/Test/cog_test.sql` is a schema **contract**, asserted against by `TestDatabase` (table count,
  `person` indexes, `obj` foreign key) and by `TestCodegen` (type table, association table, timestamp
  column, `CURRENT_TIMESTAMP` column). Changing the schema means changing those assertions in the
  same commit. Loading the file **drops and recreates** `cog_test`.
- Credentials come from the `COG_TEST_DB_*` env vars; the local `phpunit.xml` override is git-ignored
  and holds real ones - never commit it.

## Gotchas

- `BaseApplication::$cache` switches container caching globally. With it on, edits to service
  definitions appear to do nothing until `$dirCache/container/` is cleared. The test bootstrap forces
  it off for that reason.
- `BaseApplication::$dirCache` and `$dirTemplates` default to paths inside `src/` that do not exist;
  applications are expected to reassign them.
- `Database::$databases` is a static, index-keyed registry rather than a pool, and generated ORM
  classes bind to a **specific numeric index** (the test fixture uses
  `CodegenFixture::DATABASE_INDEX = 1`). `initializeConnection()` derives the next index from
  `max(array_keys(...)) + 1`, not `count()`, so closing a connection cannot make the next one
  overwrite a live entry. Adapter classes are resolved by string concatenation from the `adapter`
  config key.
- Console command discovery is **non-recursive** over `src/Command/*.php`, and the filename must
  match the class name. Subdirectories are never scanned, which is why `Command/Descriptor/` is not
  mistaken for a pile of commands.
- `Cog\Util\NamespaceUtil` reads `composer.json` at run time, relative to `Path::$appRoot`, so the
  PSR-4 map is load-bearing well beyond the autoloader.
