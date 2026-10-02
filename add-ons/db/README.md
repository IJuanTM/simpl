# DB Add-on

A database layer for Simpl: a query builder, a schema/migration DDL builder, generic migration/seeder runners, and a cron-style task scheduler. Extracted out of the auth add-on so any add-on that needs persistence (or scheduled tasks) can depend on it without pulling in auth-specific tables.

The dividing line: this add-on owns the *generic engine*, never domain-specific data. The list of which tables to create or which data to seed is supplied by whichever add-on defines them (see [`auth`](../auth/README.md) for a working example).

## What's included

- **`DB`** - `select`/`single`/`insert`/`update`/`delete`/`exists`/`count`/`query`/`raw`, with identifier sanitization (whitelist regex) and parameterized values throughout. Supports `=`, `!=`, `<>`, `>`, `>=`, `<`, `<=`, `LIKE`, `NOT LIKE`, `IS`, `IS NOT`, `IN`, `NOT IN` as WHERE operators, plus `JOIN`, `GROUP BY`, `ORDER BY`, `OR WHERE`, transactions, and `useDatabase()`/`lastInsertId()`.
- **`Blueprint`** / **`Schema`** - a small fluent DDL builder (`varchar`, `int`, `timestamp`, `enum`, `foreign`, `index`, `primary`, `unique`, `autoIncrement`, ...) for defining tables inside a migration's `Schema::create('table', function (Blueprint $table) { ... })` callback.
- **`DatabaseMigrator`** - runs migration classes registered via `DatabaseMigrator::register(SomeMigration::class)`, tracking what's already run in its own `migrations` table. Add an add-on's own migrations from its own Config file, in dependency order.
- **`DatabaseSeeder`** - same pattern for seeders, via `DatabaseSeeder::register(SomeSeeder::class)`.
- **`Scheduler`** / **`ScheduledTask`** - register a named, callable task with a cron expression or interval (`Scheduler::task('name', fn() => ...)->daily()`), then `Scheduler::run()` executes whatever's due, persisting run history in its own `scheduler_runs` table (registered as this add-on's own migration - it's scheduler bookkeeping, not domain data).
- **CLI scripts** (wired up as composer commands on install): `composer migrate` / `migrate:fresh` / `migrate:rollback`, `composer seed` / `seed:fresh`, `composer cron:test`, `composer test:integration` (run them with `simpl migrate`, `simpl seed:fresh`, `simpl test:integration`, ... and `simpl composer cron:test`, inside the Docker `app` container when the stack is up).

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

Ships a PHPUnit suite (`tests/`, merges into a project's `tests/`) in two parts:

- **Unit and feature tests** (`simpl test`, no database needed) cover `DB`'s SQL builders, `Blueprint`'s column/index/foreign-key builders, `Schema`'s database-name validation, `ScheduledTask`'s cron-field matching, `Scheduler`'s task registration and fluent chaining, and `DatabaseMigrator`/`DatabaseSeeder`'s `register()`.
- **Integration tests** (`simpl test:integration`, against the database in `src/.env`) cover `DatabaseMigrator::run()`/`rollback()`, `DatabaseSeeder::run()`/`truncate()` and `Scheduler::run()` with due and failing tasks. They migrate the database first, run each test in a rolled-back transaction, and refuse to run against a database named `simpl`.

## Requirements

- **PHP**: >= 8.5
- **Extensions**: PDO, pdo_mysql
- **Database**: MySQL >= 9.5.0 or MariaDB >= 12.1.2

## Used by

- [`auth`](../auth/README.md) - depends on this add-on for its query builder, migrations, seeders, and scheduled cleanup tasks (deactivating unverified users, pruning rate-limit cache, ...).
