<?php

declare(strict_types=1);

namespace app\Database;

use app\Controllers\PageController;
use app\Enums\ErrorCode;
use app\Utils\Log;
use DateMalformedStringException;
use DateTime;
use DateTimeZone;
use PDO;
use PDOException;

class DB
{
    private static ?PDO $pdo = null;
    private static bool $pdoHasDatabase = false;

    /**
     * This method is for selecting records from a table with optional JOIN, WHERE, OR_WHERE, GROUP BY, ORDER BY, LIMIT and OFFSET.
     *
     * @param string|array      $SELECT
     * @param string            $FROM
     * @param array             $JOIN
     * @param array             $WHERE
     * @param array             $OR_WHERE Conditions joined with OR, AND-ed with WHERE as a group
     * @param string|array|null $GROUP_BY
     * @param string|array|null $ORDER_BY
     * @param int|null          $LIMIT
     * @param int|null          $OFFSET
     *
     * @return array
     */
    public static function select(string|array $SELECT, string $FROM, array $JOIN = [], array $WHERE = [], array $OR_WHERE = [], string|array|null $GROUP_BY = null, string|array|null $ORDER_BY = null, int|null $LIMIT = null, int|null $OFFSET = null): array
    {
        $cols = self::columns($SELECT);
        $table = self::sanitize($FROM);
        $joinClause = self::buildJoin($table, $JOIN);
        [$whereClause, $params] = self::combineWhere($WHERE, $OR_WHERE);
        $groupByClause = self::groupByClause($GROUP_BY);
        $orderByClause = self::orderByClause($ORDER_BY);

        $query = "SELECT $cols FROM $table"
            . ($joinClause ? " $joinClause" : '')
            . ($whereClause ? " WHERE $whereClause" : '')
            . ($groupByClause ? " $groupByClause" : '')
            . ($orderByClause ? " $orderByClause" : '')
            . ($LIMIT !== null ? " LIMIT $LIMIT" : '')
            . ($OFFSET !== null ? " OFFSET $OFFSET" : '');

        return self::execute($query, $params)->fetchAll();
    }

    /**
     * This method is for sanitizing and formatting column names for SQL queries.
     *
     * Simple identifiers (matching \w+) are validated; anything else (e.g. "roles.name AS role",
     * aggregates, or subqueries) is passed through unchanged to support expressions. Because of
     * this, the SELECT column list must only ever contain developer-defined literals - never
     * request input.
     *
     * @param string|array $columns
     *
     * @return string
     */
    private static function columns(string|array $columns): string
    {
        if (is_string($columns)) {
            if ($columns === '*') return '*';
            if (preg_match('/^\w+$/', $columns)) return self::sanitize($columns);
            return $columns;
        }

        return implode(', ', array_map(static function ($col) {
            if (preg_match('/^\w+$/', $col)) return self::sanitize($col);
            return $col;
        }, $columns));
    }

    /**
     * This method is for sanitizing SQL identifiers like table and column names.
     *
     * @param string $identifier
     *
     * @return string
     */
    private static function sanitize(string $identifier): string
    {
        if (!preg_match('/^\w+$/', $identifier)) throw new PDOException("Invalid identifier: $identifier");
        return $identifier;
    }

    /**
     * Build LEFT JOIN clause(s) from a JOIN definition.
     * Join from main table: ['from_col', ['join_table', 'join_col']]
     * Join from another table: [['left_table', 'left_col'], ['join_table', 'join_col']]
     * Multiple joins: array of either of the above formats.
     *
     * @param string $table Sanitized main table name
     * @param array  $join
     *
     * @return string
     */
    private static function buildJoin(string $table, array $join): string
    {
        if (empty($join)) return '';

        $joins = self::isJoinSpec($join) ? [$join] : $join;
        $clauses = [];

        foreach ($joins as [$fromCol, [$joinTable, $joinCol]]) {
            $joinTableSan = self::sanitize($joinTable);
            if (is_array($fromCol)) {
                $clauses[] = "LEFT JOIN $joinTableSan ON " . self::sanitize($fromCol[0]) . '.' . self::sanitize($fromCol[1]) . " = $joinTableSan." . self::sanitize($joinCol);
            } else {
                $clauses[] = "LEFT JOIN $joinTableSan ON $table." . self::sanitize($fromCol) . " = $joinTableSan." . self::sanitize($joinCol);
            }
        }

        return implode(' ', $clauses);
    }

    /**
     * Whether $item is one join spec rather than a list of them. Ambiguous only when
     * $item has 2 elements; distinguished by $item[1] - a flat [table, col] pair
     * means $item is a single spec, a nested spec means $item is a list of two.
     *
     * @param array $item
     *
     * @return bool
     */
    private static function isJoinSpec(array $item): bool
    {
        if (count($item) !== 2 || !self::isColPair($item[1])) return false;
        return is_string($item[0]) || self::isColPair($item[0]);
    }

    /**
     * Whether $v is a [table, column] pair - a 2-element array of two strings.
     */
    private static function isColPair(mixed $v): bool
    {
        return is_array($v) && count($v) === 2 && is_string($v[0]) && is_string($v[1]);
    }

    /**
     * Combine AND WHERE and OR WHERE into a single clause and params array.
     * OR conditions are wrapped in parentheses and AND-ed with the main WHERE.
     *
     * @param array $where
     * @param array $orWhere
     *
     * @return array
     */
    private static function combineWhere(array $where, array $orWhere): array
    {
        $usedParamNames = [];
        [$andClause, $andParams] = self::buildWhere($where, '', ' AND ', $usedParamNames);
        [$orClause, $orParams] = self::buildWhere($orWhere, 'or_', ' OR ', $usedParamNames);

        if ($andClause && $orClause) $clause = "$andClause AND ($orClause)";
        else if ($andClause) $clause = $andClause;
        else if ($orClause) $clause = "($orClause)";
        else $clause = '';

        return [$clause, array_merge($andParams, $orParams)];
    }

    /**
     * This method is for constructing the WHERE clause of an SQL query.
     * Keys support 'col' and 'table.col' forms.
     *
     * @param array  $where
     * @param string $prefix
     * @param string $separator Logical operator joining conditions ('AND' or 'OR')
     * @param array  $usedParamNames Tracks param names already assigned, shared across calls (e.g. by
     *                               combineWhere()'s separate AND/OR calls) so a prefixed and an
     *                               unprefixed key can't normalize to the same final placeholder.
     *
     * @return array
     */
    private static function buildWhere(array $where, string $prefix = '', string $separator = ' AND ', array &$usedParamNames = []): array
    {
        if (empty($where)) return ['', []];

        $conditions = [];
        $params = [];

        foreach ($where as $key => $value) {
            $column = self::sanitizeColumn($key);

            // Two different keys can normalize to the same param name (e.g. 'a.b' and 'a_b'
            // both become 'a_b') - only disambiguate with a suffix when that actually happens.
            $base = $prefix . str_replace('.', '_', $key);
            $paramName = $base;
            for ($suffix = 1; isset($usedParamNames[$paramName]); $suffix++) $paramName = "{$base}_$suffix";
            $usedParamNames[$paramName] = true;
            $paramKey = ":$paramName";

            if (is_array($value) && count($value) === 2 && in_array($value[0], ['=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IS', 'IS NOT'])) {
                $operator = strtoupper($value[0]);
                $val = $value[1];

                if ($val === null && in_array($operator, ['IS', 'IS NOT'])) $conditions[] = "$column $operator NULL";
                else {
                    $conditions[] = "$column $operator $paramKey";
                    $params[$paramKey] = $val;
                }
            } else {
                $conditions[] = "$column = $paramKey";
                $params[$paramKey] = $value;
            }
        }

        return [implode($separator, $conditions), $params];
    }

    /**
     * Sanitize a column name, accepting 'col' or 'table.col' forms.
     *
     * @param string $col
     *
     * @return string
     */
    private static function sanitizeColumn(string $col): string
    {
        return self::sanitizeIdentifier($col, 'column');
    }

    /**
     * Validates an identifier in 'col' or 'table.col' form, shared by WHERE and GROUP BY clause building.
     *
     * @param string $elem
     * @param string $context Used in the exception message (e.g. "column", "GROUP BY element")
     *
     * @return string
     */
    private static function sanitizeIdentifier(string $elem, string $context): string
    {
        $elem = trim($elem);

        if (!preg_match('/^\w+(\.\w+)?$/', $elem)) throw new PDOException("Invalid $context: $elem");
        return $elem;
    }

    /**
     * Build GROUP BY clause from string|array|null
     *
     * @param string|array|null $group
     *
     * @return string
     */
    private static function groupByClause(string|array|null $group): string
    {
        if ($group === null) return '';

        $cols = self::normalizeColumnsList($group);

        return $cols ? 'GROUP BY ' . $cols : '';
    }

    /**
     * Normalize column list (string or array) to comma separated sanitized columns.
     *
     * @param string|array $input
     *
     * @return string
     */
    private static function normalizeColumnsList(string|array $input): string
    {
        $parts = is_array($input) ? $input : array_map('trim', explode(',', $input));
        $sanitized = array_map([self::class, 'sanitizeGroupElement'], $parts);

        return implode(', ', $sanitized);
    }

    /**
     * Build ORDER BY clause from string|array|null
     *
     * @param string|array|null $order
     *
     * @return string
     */
    private static function orderByClause(string|array|null $order): string
    {
        if ($order === null) return '';

        $parts = is_array($order) ? $order : array_map('trim', explode(',', $order));
        $sanitized = array_map([self::class, 'sanitizeOrderElement'], $parts);

        return $sanitized ? 'ORDER BY ' . implode(', ', $sanitized) : '';
    }

    /**
     * This method is for preparing and executing a query with optional parameters.
     *
     * @param string $query
     * @param array  $params
     *
     * @return object
     */
    private static function execute(string $query, array $params = []): object
    {
        try {
            $stmt = self::connect()->prepare($query);

            foreach ($params as $key => $value) $stmt->bindValue($key, $value, match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR
            });

            $stmt->execute();

            return $stmt;
        } catch (PDOException $e) {
            self::handleError($e);
        }
    }

    /**
     * This method is for establishing a database connection using PDO.
     * When $withDatabase is false, no schema is selected (used during migrations).
     *
     * @param bool $withDatabase
     *
     * @return PDO
     */
    private static function connect(bool $withDatabase = true): PDO
    {
        // Otherwise $withDatabase would be silently ignored after the first connect().
        if (self::$pdo !== null) {
            if ($withDatabase && !self::$pdoHasDatabase) self::useDatabase(DB_NAME);
            return self::$pdo;
        }

        try {
            self::$pdo = new PDO(
                'mysql:host=' . DB_SERVER . ($withDatabase ? ';dbname=' . DB_NAME : ''),
                DB_USERNAME,
                DB_PASSWORD,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            self::$pdoHasDatabase = $withDatabase;

            $tz = new DateTime('now', new DateTimeZone(TIMEZONE))->format('P');
            self::$pdo->exec("SET time_zone = '$tz'");

            return self::$pdo;
        } catch (PDOException $e) {
            self::handleError($e);
        } catch (DateMalformedStringException) {
            self::handleError(new PDOException('Invalid timezone configuration.'));
        }
    }

    /**
     * This method is for switching the active database on the current connection.
     *
     * @param string $name
     *
     * @return void
     */
    public static function useDatabase(string $name): void
    {
        $name = self::sanitizeBacktickQuoted($name);

        try {
            if (self::$pdo === null) self::connect(false);
            self::$pdo->exec("USE `$name`");
            self::$pdoHasDatabase = true;
        } catch (PDOException $e) {
            throw new PDOException("Failed to select database '$name': " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Validate an identifier that will be interpolated backtick-quoted (unlike sanitize()'s
     * \w+ whitelist, needed where identifiers are interpolated unquoted elsewhere in this
     * class). A real database name can contain characters \w+ would reject (e.g. a hyphen),
     * so only the backtick itself - which would let the value escape its quoting - is unsafe.
     *
     * @param string $identifier
     *
     * @return string
     */
    private static function sanitizeBacktickQuoted(string $identifier): string
    {
        if (str_contains($identifier, '`')) throw new PDOException("Invalid identifier: $identifier");
        return $identifier;
    }

    /**
     * This method is for handling database errors by logging the error and redirecting to an error page.
     *
     * @param PDOException $e
     *
     * @return never
     */
    private static function handleError(PDOException $e): never
    {
        Log::error($e->getMessage());

        // CLI scripts (migrate/seed) need a real exception to report failure with a non-zero exit code.
        if (PHP_SAPI === 'cli') throw new PDOException($e->getMessage(), (int)$e->getCode(), $e);

        PageController::error(ErrorCode::INTERNAL_ERROR);
        exit;
    }

    /**
     * This method is for selecting a single record from a table with optional JOIN, WHERE, OR_WHERE, GROUP BY and ORDER BY.
     *
     * @param string|array      $SELECT
     * @param string            $FROM
     * @param array             $JOIN
     * @param array             $WHERE
     * @param array             $OR_WHERE Conditions joined with OR, AND-ed with WHERE as a group
     * @param string|array|null $GROUP_BY
     * @param string|array|null $ORDER_BY
     *
     * @return array|null
     */
    public static function single(string|array $SELECT, string $FROM, array $JOIN = [], array $WHERE = [], array $OR_WHERE = [], string|array|null $GROUP_BY = null, string|array|null $ORDER_BY = null): ?array
    {
        $cols = self::columns($SELECT);
        $table = self::sanitize($FROM);
        $joinClause = self::buildJoin($table, $JOIN);
        [$whereClause, $params] = self::combineWhere($WHERE, $OR_WHERE);
        $groupByClause = self::groupByClause($GROUP_BY);
        $orderByClause = self::orderByClause($ORDER_BY);
        $query = "SELECT $cols FROM $table"
            . ($joinClause ? " $joinClause" : '')
            . ($whereClause ? " WHERE $whereClause" : '')
            . ($groupByClause ? " $groupByClause" : '')
            . ($orderByClause ? " $orderByClause" : '')
            . ' LIMIT 1';

        return self::execute($query, $params)->fetch() ?: null;
    }

    /**
     * This method is for inserting records into a table with specific VALUES.
     *
     * @param string $INTO
     * @param array  $VALUES
     *
     * @return bool
     */
    public static function insert(string $INTO, array $VALUES): bool
    {
        if (empty($VALUES)) throw new PDOException('Cannot insert empty values');

        $table = self::sanitize($INTO);
        $columns = implode(', ', array_map(static fn($col) => self::sanitize($col), array_keys($VALUES)));
        $placeholders = ':' . implode(', :', array_keys($VALUES));
        $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";

        self::execute($query, $VALUES);
        return true;
    }

    /**
     * This method is for updating records in a table with specific SET values and WHERE conditions.
     *
     * @param string $UPDATE
     * @param array  $SET
     * @param array  $WHERE
     *
     * @return bool
     */
    public static function update(string $UPDATE, array $SET, array $WHERE): bool
    {
        if (empty($SET)) throw new PDOException('Cannot update with empty values');
        if (empty($WHERE)) throw new PDOException('UPDATE requires WHERE clause for safety');

        $table = self::sanitize($UPDATE);
        $setClause = implode(', ', array_map(static fn($key) => self::sanitize($key) . " = :set_$key", array_keys($SET)));
        [$whereClause, $whereParams] = self::buildWhere($WHERE, 'where_');
        $query = "UPDATE $table SET $setClause WHERE $whereClause";

        $params = [];
        foreach ($SET as $key => $value) $params[":set_$key"] = $value;
        $params = array_merge($params, $whereParams);

        self::execute($query, $params);
        return true;
    }

    /**
     * This method is for deleting records from a table with specific WHERE conditions.
     *
     * @param string $FROM
     * @param array  $WHERE
     *
     * @return bool
     */
    public static function delete(string $FROM, array $WHERE): bool
    {
        if (empty($WHERE)) throw new PDOException('DELETE requires WHERE clause for safety');

        $table = self::sanitize($FROM);
        [$whereClause, $params] = self::buildWhere($WHERE);
        $query = "DELETE FROM $table WHERE $whereClause";

        self::execute($query, $params);
        return true;
    }

    /**
     * This method is for checking the existence of a record in a table with specific WHERE conditions.
     *
     * @param string $FROM
     * @param array  $WHERE
     *
     * @return bool
     */
    public static function exists(string $FROM, array $WHERE): bool
    {
        $table = self::sanitize($FROM);
        [$whereClause, $params] = self::buildWhere($WHERE);
        $query = "SELECT 1 FROM $table WHERE $whereClause LIMIT 1";

        return self::execute($query, $params)->fetch() !== false;
    }

    /**
     * This method is for counting rows in a table with optional JOIN, WHERE and OR_WHERE conditions.
     * If $GROUP_BY is provided, the count of groups is returned.
     *
     * @param string            $FROM
     * @param array             $JOIN
     * @param array             $WHERE
     * @param array             $OR_WHERE Conditions joined with OR, AND-ed with WHERE as a group
     * @param string|array|null $GROUP_BY
     *
     * @return int
     */
    public static function count(string $FROM, array $JOIN = [], array $WHERE = [], array $OR_WHERE = [], string|array|null $GROUP_BY = null): int
    {
        $table = self::sanitize($FROM);
        $joinClause = self::buildJoin($table, $JOIN);
        [$whereClause, $params] = self::combineWhere($WHERE, $OR_WHERE);

        if ($GROUP_BY === null) {
            $query = "SELECT COUNT(*) as count FROM $table"
                . ($joinClause ? " $joinClause" : '')
                . ($whereClause ? " WHERE $whereClause" : '');
            return (int)self::execute($query, $params)->fetch()['count'];
        }

        $groupCols = self::normalizeColumnsList($GROUP_BY);
        $sub = "SELECT 1 FROM $table"
            . ($joinClause ? " $joinClause" : '')
            . ($whereClause ? " WHERE $whereClause" : '')
            . " GROUP BY $groupCols";
        $query = "SELECT COUNT(*) as count FROM ($sub) AS grp";

        return (int)self::execute($query, $params)->fetch()['count'];
    }

    /**
     * This method is for executing a raw DDL statement.
     *
     * @param string $sql
     *
     * @return void
     */
    public static function raw(string $sql): void
    {
        try {
            if (self::$pdo === null) self::connect(false);
            self::$pdo->exec($sql);
        } catch (PDOException $e) {
            throw new PDOException("Raw query failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * This method is for executing a raw query and returning the result set as an array.
     *
     * @param string $query
     * @param array  $params
     *
     * @return array
     */
    public static function query(string $query, array $params = []): array
    {
        return self::execute($query, $params)->fetchAll();
    }

    public static function lastInsertId(): string
    {
        return self::connect()->lastInsertId();
    }

    public static function beginTransaction(): bool
    {
        return self::connect()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::connect()->commit();
    }

    public static function rollback(): bool
    {
        return self::connect()->rollBack();
    }

    /**
     * Sanitize a GROUP BY element. Accepts 'col' and optional table.col.
     *
     * @param string $elem
     *
     * @return string
     */
    private static function sanitizeGroupElement(string $elem): string
    {
        return self::sanitizeIdentifier($elem, 'GROUP BY element');
    }

    /**
     * Sanitize an ORDER BY element. Accepts 'col', 'col ASC' or 'col DESC' and optional table.col.
     *
     * @param string $elem
     *
     * @return string
     */
    private static function sanitizeOrderElement(string $elem): string
    {
        $elem = trim($elem);

        if (preg_match('/^(\w+(\.\w+)?)(\s+(ASC|DESC))?$/i', $elem, $m)) return $m[1] . (isset($m[4]) ? ' ' . strtoupper($m[4]) : '');

        throw new PDOException("Invalid ORDER BY element: $elem");
    }
}
