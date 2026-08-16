<?php

declare(strict_types=1);

namespace app\Database\Migrations;

use app\Database\DB;

class Blueprint
{
    private array $columns = [];
    private array $indexes = [];
    private array $foreigns = [];
    private ?string $primaryKey = null;
    private ?int $startAt = null;

    public function __construct(private readonly string $table)
    {
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

    public function bigintUnsigned(string $name, bool $notNull = false, mixed $default = PHP_INT_MIN): static
    {
        return $this->addColumn("`$name` BIGINT UNSIGNED", $notNull, $default);
    }

    private function addColumn(string $definition, bool $notNull, mixed $default): static
    {
        if ($notNull) $definition .= ' NOT NULL';

        if ($default !== PHP_INT_MIN) {
            $definition .= match (true) {
                $default === null => ' DEFAULT NULL',
                $default === 'CURRENT_TIMESTAMP' => ' DEFAULT CURRENT_TIMESTAMP',
                is_int($default) || is_float($default) => " DEFAULT $default",
                default => " DEFAULT '$default'"
            };
        }

        $this->columns[] = $definition;
        return $this;
    }

    public function smallintUnsigned(string $name, bool $notNull = false, mixed $default = PHP_INT_MIN): static
    {
        return $this->addColumn("`$name` SMALLINT UNSIGNED", $notNull, $default);
    }

    public function int(string $name, bool $notNull = false, mixed $default = PHP_INT_MIN): static
    {
        return $this->addColumn("`$name` INT", $notNull, $default);
    }

    public function intUnsigned(string $name, bool $notNull = false, mixed $default = PHP_INT_MIN): static
    {
        return $this->addColumn("`$name` INT UNSIGNED", $notNull, $default);
    }

    public function tinyint(string $name, bool $notNull = false, mixed $default = PHP_INT_MIN): static
    {
        return $this->addColumn("`$name` TINYINT", $notNull, $default);
    }

    public function varchar(string $name, int $length = 255, bool $notNull = false, mixed $default = PHP_INT_MIN): static
    {
        return $this->addColumn("`$name` VARCHAR($length)", $notNull, $default);
    }

    public function text(string $name, bool $notNull = false): static
    {
        // TEXT columns do not support DEFAULT values in MySQL/MariaDB
        return $this->addColumn("`$name` TEXT", $notNull, PHP_INT_MIN);
    }

    public function timestamp(string $name, bool $notNull = false, mixed $default = PHP_INT_MIN): static
    {
        return $this->addColumn("`$name` TIMESTAMP", $notNull, $default);
    }

    public function enum(string $name, array $values, bool $notNull = false, mixed $default = PHP_INT_MIN): static
    {
        $list = implode(', ', array_map(static fn($v) => "'$v'", $values));
        return $this->addColumn("`$name` ENUM($list)", $notNull, $default);
    }

    public function autoIncrement(int $startAt = 1): static
    {
        $this->columns[array_key_last($this->columns)] .= ' AUTO_INCREMENT';
        if ($startAt > 1) $this->startAt = $startAt;
        return $this;
    }

    public function unique(): static
    {
        preg_match('/`(\w+)`/', $this->columns[array_key_last($this->columns)], $m);
        $this->indexes[] = "UNIQUE (`$m[1]`)";
        return $this;
    }

    public function onUpdateCurrentTimestamp(): static
    {
        $this->columns[array_key_last($this->columns)] .= ' ON UPDATE CURRENT_TIMESTAMP';
        return $this;
    }

    public function primary(string ...$columns): static
    {
        $this->primaryKey = implode(', ', array_map(static fn($c) => "`$c`", $columns));
        return $this;
    }

    public function foreign(string $column, string $refTable, string $refColumn = DB_SCHEMA_DEFAULTS['primary_key'], string $onDelete = DB_SCHEMA_DEFAULTS['foreign_key_on_delete']): static
    {
        $this->foreigns[] = "FOREIGN KEY (`$column`) REFERENCES `$refTable` (`$refColumn`) ON DELETE $onDelete";
        return $this;
    }

    public function index(string $name, array $columns): static
    {
        $cols = implode(', ', array_map(static fn($c) => "`$c`", $columns));
        $this->indexes[] = "INDEX `$name` ($cols)";
        return $this;
    }
}
