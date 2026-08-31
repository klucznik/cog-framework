# Cog Framework

A PHP framework built on Symfony components, with a database code generator that
produces typed model classes from an existing MySQL schema.

## Requirements

- PHP 8.5 or newer
- Extensions: `mbstring`, `json`, `dom`, `libxml`, `mysqli`, `simplexml`

## Installation

```bash
composer require klucznik/cog-framework
```

## Bootstrapping

The framework is initialized once per request through `Cog\BaseApplication::initialize()`.
The repository ships a working example of this wiring:

- [`prepend.inc.php`](prepend.inc.php) — loads the Composer autoloader and initializes the application
- [`public/index.php`](public/index.php) — main web entry point 
- [`cog`](cog) — the console command

These are reference examples, not files you install. Copy them into your own project
and adjust the paths to your Composer autoloader.

Cog\BaseApplication is meant to be extended, you should create your own Application class with your own modifications / customizations.


## Code generation

The generator reads a `codegen.xml` configuration file describing the database
connection and output paths. Copy [`codegen.xml-dist`](codegen.xml-dist) to
`codegen.xml`, fill in your settings, then run:

```bash
./cog db:codegen
```

## Testing

The suite runs against a MySQL fixture database. Load it once:

```bash
mysql -u root -p < src/Test/cog_framework_test.sql
```

Connection details come from the `COG_TEST_DB_*` environment variables; override
the defaults in [`phpunit.xml.dist`](phpunit.xml.dist) by copying it to
`phpunit.xml` (git-ignored). Then:

```bash
composer test
```

The bootstrap runs the code generator against that database before any test
executes, into the git-ignored `.phpunit.codegen/` build directory, and registers
an autoloader for the resulting `Generated\` and `App\` classes. `TestCodegen`
runs first and reports any generation failure; the rest of the suite can rely on
the generated ORM layer being present.

## License

MIT — see [LICENSE](LICENSE).
