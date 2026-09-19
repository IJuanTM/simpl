<?php

declare(strict_types=1);

namespace app\Database\Migrations;

use app\Database\DB;
use InvalidArgumentException;

/**
 * Fluent builder for a CREATE TABLE statement's column/index/foreign-key definitions, built up
 * via a Schema::create() callback and materialized by build().
 */
class Blueprint
{
    private array $columns = [];
    private array $indexes = [];
    private array $foreigns = [];
    private ?string $primaryKey = null;
    private ?int $startAt = null;
    private readonly string $table;

    public function __construct(string $table)
    {
        $this->table = self::identifier($table);
    }

    /**
     * Validates a table/column/index name before it's interpolated backtick-quoted into DDL, matching
     * the \w+ whitelist DB::sanitize() applies to DML identifiers.
     *
     * @param string $name
     *
     * @return string
     *
     * @throws InvalidArgumentException When $name isn't a valid \w+ identifier.
     */
    public static function identifier(string $name): string
    {
        if (!preg_match('/^\w+$/', $name)) throw new InvalidArgumentException("Invalid identifier: $name");
        return $name;
    }

    /**
     * Assemble and run the CREATE TABLE statement. Called explicitly by Schema::create()
     * once the table definition callback has finished, rather than from a destructor.
     *
     * @return void
     */
    public function build(): void
    {
        $parts = $this->columns;

        if ($this->primaryKey !== null) $parts[] = "PRIMARY KEY ($this->primaryKey)";

        array_push($parts, ...$this->foreigns, ...$this->indexes);

        $sql = "CREATE TABLE `$this->table` (\n  " . implode(",\n  ", $parts) . "\n) ENGINE = " . DB_SCHEMA_DEFAULTS['engine'];

        if ($this->startAt !== null) $sql .= ",\n  AUTO_INCREMENT = $this->startAt";

        DB::raw($sql . ';');
    }

    /**
     * Adds a BIGINT UNSIGNED column.
     */
    public function bigintUnsigned(string $name, bool $notNull = false, mixed $default = NoDefault::VALUE): static
    {
        return $this->addColumn($name, 'BIGINT UNSIGNED', $notNull, $default);
    }

    /**
     * Appends a column definition, applying an optional CHARACTER SET, NOT NULL, and DEFAULT
     * clauses (NoDefault::VALUE means no DEFAULT clause at all; see its docblock).
     */
    private function addColumn(string $name, string $type, bool $notNull, mixed $default, ?string $charset = null): static
    {
        $definition = '`' . self::identifier($name) . "` $type";

        if ($charset !== null) $definition .= " CHARACTER SET $charset";
        if ($notNull) $definition .= ' NOT NULL';

        if ($default !== NoDefault::VALUE) {
            $definition .= match (true) {
                $default === null => ' DEFAULT NULL',
                $default === 'CURRENT_TIMESTAMP' => ' DEFAULT CURRENT_TIMESTAMP',
                is_bool($default) => ' DEFAULT ' . (int)$default,
                is_int($default) || is_float($default) => " DEFAULT $default",
                default => " DEFAULT '" . str_replace("'", "''", (string)$default) . "'"
            };
        }

        $this->columns[] = $definition;
        return $this;
    }

    /**
     * Adds a SMALLINT UNSIGNED column.
     */
    public function smallintUnsigned(string $name, bool $notNull = false, mixed $default = NoDefault::VALUE): static
    {
        return $this->addColumn($name, 'SMALLINT UNSIGNED', $notNull, $default);
    }

    /**
     * Adds an INT column.
     */
    public function int(string $name, bool $notNull = false, mixed $default = NoDefault::VALUE): static
    {
        return $this->addColumn($name, 'INT', $notNull, $default);
    }

    /**
     * Adds an INT UNSIGNED column.
     */
    public function intUnsigned(string $name, bool $notNull = false, mixed $default = NoDefault::VALUE): static
    {
        return $this->addColumn($name, 'INT UNSIGNED', $notNull, $default);
    }

    /**
     * Adds a TINYINT column.
     */
    public function tinyint(string $name, bool $notNull = false, mixed $default = NoDefault::VALUE): static
    {
        return $this->addColumn($name, 'TINYINT', $notNull, $default);
    }

    /**
     * Adds a VARCHAR($length) column, optionally with a narrower CHARACTER SET than the table
     * default (e.g. 'ascii' for a column that only ever holds ASCII, to fit more chars inside the
     * index byte-length limit than the table's usual multi-byte charset would allow).
     */
    public function varchar(string $name, int $length = 255, bool $notNull = false, mixed $default = NoDefault::VALUE, ?string $charset = null): static
    {
        return $this->addColumn($name, "VARCHAR($length)", $notNull, $default, $charset);
    }

    /**
     * Adds a TEXT column. TEXT columns don't support DEFAULT values in MySQL/MariaDB, so none can be given.
     */
    public function text(string $name, bool $notNull = false): static
    {
        return $this->addColumn($name, 'TEXT', $notNull, NoDefault::VALUE);
    }

    /**
     * Adds a TIMESTAMP column.
     */
    public function timestamp(string $name, bool $notNull = false, mixed $default = NoDefault::VALUE): static
    {
        return $this->addColumn($name, 'TIMESTAMP', $notNull, $default);
    }

    /**
     * Adds an ENUM column restricted to the given values.
     */
    public function enum(string $name, array $values, bool $notNull = false, mixed $default = NoDefault::VALUE): static
    {
        $list = implode(', ', array_map(static fn($v) => "'" . str_replace("'", "''", $v) . "'", $values));
        return $this->addColumn($name, "ENUM($list)", $notNull, $default);
    }

    /**
     * Marks the most recently added column AUTO_INCREMENT, optionally starting from $startAt.
     */
    public function autoIncrement(int $startAt = 1): static
    {
        $this->columns[$this->lastColumnKey()] .= ' AUTO_INCREMENT';
        if ($startAt > 1) $this->startAt = $startAt;
        return $this;
    }

    /**
     * The array key of the most recently added column, for fluent modifiers that amend it.
     * Throws if called before any column has been added, instead of a confusing null-key TypeError.
     *
     * @throws InvalidArgumentException When no column has been added yet.
     */
    private function lastColumnKey(): int
    {
        $key = array_key_last($this->columns);
        if ($key === null) throw new InvalidArgumentException('No column to modify: call a column method first.');
        return $key;
    }

    /**
     * Adds a UNIQUE index on the most recently added column.
     */
    public function unique(): static
    {
        preg_match('/`(\w+)`/', $this->columns[$this->lastColumnKey()], $m);
        $this->indexes[] = "UNIQUE (`$m[1]`)";
        return $this;
    }

    /**
     * Adds ON UPDATE CURRENT_TIMESTAMP to the most recently added column.
     */
    public function onUpdateCurrentTimestamp(): static
    {
        $this->columns[$this->lastColumnKey()] .= ' ON UPDATE CURRENT_TIMESTAMP';
        return $this;
    }

    /**
     * Sets the table's primary key to the given column(s).
     */
    public function primary(string ...$columns): static
    {
        $this->primaryKey = implode(', ', array_map(static fn($c) => '`' . self::identifier($c) . '`', $columns));
        return $this;
    }

    /**
     * Adds a foreign key on $column referencing $refTable.$refColumn, with the given ON DELETE behavior.
     */
    public function foreign(string $column, string $refTable, string $refColumn = DB_SCHEMA_DEFAULTS['primary_key'], string $onDelete = DB_SCHEMA_DEFAULTS['foreign_key_on_delete']): static
    {
        $column = self::identifier($column);
        $refTable = self::identifier($refTable);
        $refColumn = self::identifier($refColumn);
        $this->foreigns[] = "FOREIGN KEY (`$column`) REFERENCES `$refTable` (`$refColumn`) ON DELETE $onDelete";
        return $this;
    }

    /**
     * Adds a named index on the given columns.
     */
    public function index(string $name, array $columns): static
    {
        $name = self::identifier($name);
        $cols = implode(', ', array_map(static fn($c) => '`' . self::identifier($c) . '`', $columns));
        $this->indexes[] = "INDEX `$name` ($cols)";
        return $this;
    }
}
