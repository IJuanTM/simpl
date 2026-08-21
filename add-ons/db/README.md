# DB Add-on

A database layer for Simpl: a query builder, a schema/migration DDL builder, generic migration/seeder runners, and a cron-style task scheduler. Extracted out of the auth add-on so any add-on that needs persistence (or scheduled tasks) can depend on it without pulling in auth-specific tables.

The dividing line: this add-on owns the *generic engine*, never domain-specific data. The list of which tables to create or which data to seed is supplied by whichever add-on defines them (see
[`auth`](../auth/README.md) for a working example).

## What's included

- **`DB`** - `select`/`single`/`insert`/`update`/`delete`/`exists`/`count`/`query`/`raw`, with identifier sanitization (whitelist regex) and parameterized values throughout. Supports `=`,
  `!=`, `<>`, `>`, `>=`, `<`, `<=`, `LIKE`, `NOT LIKE`, `IS`, `IS NOT` as WHERE operators, plus
  `JOIN`, `GROUP BY`, `ORDER BY`, `OR WHERE`, transactions, and `useDatabase()`/`lastInsertId()`.
- **`Blueprint`** / **`Schema`** - a small fluent DDL builder (`varchar`, `int`, `timestamp`,
  `enum`, `foreign`, `index`, `primary`, `unique`, `autoIncrement`, ...) for defining tables inside a migration's `Schema::create('table', function (Blueprint $table) { ... })` callback.
- **`DatabaseMigrator`** - runs migration classes registered via `DatabaseMigrator::register(SomeMigration::class)`, tracking what's already run in its own `migrations` table. Add an add-on's own migrations from its own Config file, in dependency order.
- **`DatabaseSeeder`** - same pattern for seeders, via `DatabaseSeeder::register(SomeSeeder::class)`.
- **`Scheduler`** / **`ScheduledTask`** - register a named, callable task with a cron expression or interval (`Scheduler::task('name', fn() => ...)->daily()`), then `Scheduler::run()` executes whatever's due, persisting run history in its own `scheduler_runs` table (registered as this add-on's own migration - it's scheduler bookkeeping, not domain data).
- **CLI scripts** (wired up as composer commands on install): `composer migrate` / `migrate:fresh`
  / `migrate:rollback`, `composer seed` / `seed:fresh`, `composer cron:test`.

## Configuration

Set your database credentials in `.env` (merged in automatically on install):

```env
DB_SERVER=localhost
DB_NAME=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

Schema defaults (engine, charset, collation, foreign key behavior, primary key column name) live in `src/app/Config/database.php`.

## Tests

Ships a PHPUnit suite (`tests/`, merges into a project's `tests/`) covering `DB`'s private SQL-builders, `Blueprint`'s column/index/foreign-key builders, `ScheduledTask`'s cron-field matching, and `DatabaseMigrator`/`DatabaseSeeder`'s `register()` accumulation - all via reflection against pure logic, no live database needed. Once installed, run `composer test` from your project's root the same way you would for the framework itself. `DatabaseMigrator::run()`/
`rollback()`, `DatabaseSeeder::run()`/`truncate()`, `Schema`, and any real migration class aren't covered here - they call `DB::useDatabase()`/`DB::raw()` as their first line and need a real connection.

## Requirements

- **PHP**: >= 8.5
- **Extensions**: PDO, pdo_mysql
- **Database**: MySQL >= 9.5.0 or MariaDB >= 12.1.2

## Used by

- [`auth`](../auth/README.md) - depends on this add-on for its query builder, migrations, seeders, and scheduled cleanup tasks (deactivating unverified users, pruning rate-limit cache, ...).
