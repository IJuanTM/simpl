<?php

namespace app\Database;

use app\Controllers\LogController;
use app\Controllers\PageController;
use app\Enums\LogType;
use DateMalformedStringException;
use DateTime;
use DateTimeZone;
use PDO;
use PDOException;

class DB
{
    private static ?PDO $pdo = null;

    /**
     * This method is for selecting records from a table with specific WHERE conditions.
     *
     * @param string|array $SELECT
     * @param string $FROM
     * @param array $WHERE
     * @param string|array|null $GROUP_BY
     * @param string|array|null $ORDER_BY
     *
     * @return array
     */
    public static function select(string|array $SELECT, string $FROM, array $WHERE = [], string|array|null $GROUP_BY = null, string|array|null $ORDER_BY = null): array
    {
        // Build query components
        $cols = self::columns($SELECT);
        $table = self::sanitize($FROM);
        [$whereClause, $params] = self::buildWhere($WHERE);
        $groupByClause = self::groupByClause($GROUP_BY);
        $orderByClause = self::orderByClause($ORDER_BY);

        // Construct the final query
        $query = "SELECT $cols FROM $table" . ($whereClause ? " WHERE $whereClause" : '') . ($groupByClause ? " $groupByClause" : '') . ($orderByClause ? " $orderByClause" : '');

        // Execute and fetch results
        return self::execute($query, $params)->fetchAll();
    }

    /**
     * This method is for sanitizing and formatting column names for SQL queries.
     *
     * @param string|array $columns
     *
     * @return string
     */
    private static function columns(string|array $columns): string
    {
        // Handle wildcard and sanitize column names
        if (is_string($columns)) return $columns === '*' ? '*' : self::sanitize($columns);

        // If array, sanitize each column and join with commas
        return implode(', ', array_map(static fn($col) => self::sanitize($col), $columns));
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
        // Ensure the identifier contains only valid characters (alphanumeric and underscores)
        if (!preg_match('/^\w+$/', $identifier)) throw new PDOException("Invalid identifier: $identifier");
        return $identifier;
    }

    /**
     * This method is for constructing the WHERE clause of an SQL query.
     *
     * @param array $where
     * @param string $prefix
     *
     * @return array
     */
    private static function buildWhere(array $where, string $prefix = ''): array
    {
        if (empty($where)) return ['', []];

        $conditions = [];
        $params = [];
        $counter = 0;

        foreach ($where as $key => $value) {
            $column = self::sanitize($key);

            if (is_array($value) && count($value) === 2 && in_array($value[0], ['=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IS', 'IS NOT'])) {
                $operator = strtoupper($value[0]);
                $val = $value[1];

                if ($val === null && in_array($operator, ['IS', 'IS NOT'])) $conditions[] = "$column $operator NULL";
                else {
                    $paramKey = ":$prefix" . $key . '_' . $counter++;
                    $conditions[] = "$column $operator $paramKey";
                    $params[$paramKey] = $val;
                }
            } else {
                $paramKey = ":$prefix$key";
                $conditions[] = "$column = $paramKey";
                $params[$paramKey] = $value;
            }
        }

        return [implode(' AND ', $conditions), $params];
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
        // Return empty string if no GROUP BY
        if ($group === null) return '';

        // Normalize and sanitize columns for GROUP BY
        $cols = self::normalizeColumnsList($group);

        // Return the GROUP BY clause
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
        // Split string by commas or use array directly and sanitize each element
        $parts = is_array($input) ? $input : array_map('trim', explode(',', $input));
        $sanitized = array_map([self::class, 'sanitizeGroupElement'], $parts);

        // Return the sanitized columns as a comma-separated string
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
        // Return empty string if no ORDER BY
        if ($order === null) return '';

        // Split string by commas or use array directly and sanitize each element
        $parts = is_array($order) ? $order : array_map('trim', explode(',', $order));
        $sanitized = array_map([self::class, 'sanitizeOrderElement'], $parts);

        // Return the ORDER BY clause
        return $sanitized ? 'ORDER BY ' . implode(', ', $sanitized) : '';
    }

    /**
     * This method is for preparing and executing a query with optional parameters.
     *
     * @param string $query
     * @param array $params
     *
     * @return object
     */
    private static function execute(string $query, array $params = []): object
    {
        try {
            // Prepare the SQL statement
            $stmt = self::connect()->prepare($query);

            // Bind parameters with appropriate data types
            foreach ($params as $key => $value) $stmt->bindValue($key, $value, match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR
            });

            // Execute the statement
            $stmt->execute();

            // Return the executed statement
            return $stmt;
        } catch (PDOException $e) {
            self::handleError($e);
        }
    }

    /**
     * This method is for establishing a database connection using PDO.
     *
     * @return PDO
     */
    private static function connect(): PDO
    {
        // Return existing connection if already established
        if (self::$pdo !== null) return self::$pdo;

        try {
            // Create a new PDO instance with persistent connection and error handling
            self::$pdo = new PDO(
                'mysql:host=' . DB_SERVER . ';dbname=' . DB_NAME,
                DB_USERNAME,
                DB_PASSWORD,
                [
                    PDO::ATTR_PERSISTENT => true,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );

            // Set the connection timezone
            $tz = (new DateTime('now', new DateTimeZone(TIMEZONE)))->format('P');
            self::$pdo->exec("SET time_zone = '$tz'");

            // Return the established connection
            return self::$pdo;
        } catch (PDOException $e) {
            self::handleError($e);
        } catch (DateMalformedStringException) {
            self::handleError(new PDOException('Invalid timezone configuration.'));
        }
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
        // Log the error message and redirect to the 500 error page
        LogController::log($e->getMessage(), LogType::DATABASE);
        PageController::redirect('error/500');
        exit;
    }

    /**
     * This method is for selecting a single record from a table with specific WHERE conditions.
     *
     * @param string|array $SELECT
     * @param string $FROM
     * @param array $WHERE
     * @param string|array|null $GROUP_BY
     * @param string|array|null $ORDER_BY
     *
     * @return array|null
     */
    public static function single(string|array $SELECT, string $FROM, array $WHERE = [], string|array|null $GROUP_BY = null, string|array|null $ORDER_BY = null): ?array
    {
        // Build query components
        $cols = self::columns($SELECT);
        $table = self::sanitize($FROM);
        [$whereClause, $params] = self::buildWhere($WHERE);
        $groupByClause = self::groupByClause($GROUP_BY);
        $orderByClause = self::orderByClause($ORDER_BY);
        $query = "SELECT $cols FROM $table" . ($whereClause ? " WHERE $whereClause" : '') . ($groupByClause ? " $groupByClause" : '') . ($orderByClause ? " $orderByClause" : '') . ' LIMIT 1';

        // Execute and fetch single result
        return self::execute($query, $params)->fetch() ?: null;
    }

    /**
     * This method is for inserting records into a table with specific VALUES.
     *
     * @param string $INTO
     * @param array $VALUES
     *
     * @return bool
     */
    public static function insert(string $INTO, array $VALUES): bool
    {
        // Ensure there are values to insert
        if (empty($VALUES)) throw new PDOException('Cannot insert empty values');

        // Build the INSERT query
        $table = self::sanitize($INTO);
        $columns = implode(', ', array_map(static fn($col) => self::sanitize($col), array_keys($VALUES)));
        $placeholders = ':' . implode(', :', array_keys($VALUES));
        $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";

        // Execute the INSERT query and return success
        self::execute($query, $VALUES);
        return true;
    }

    /**
     * This method is for updating records in a table with specific SET values and WHERE conditions.
     *
     * @param string $UPDATE
     * @param array $SET
     * @param array $WHERE
     *
     * @return bool
     */
    public static function update(string $UPDATE, array $SET, array $WHERE): bool
    {
        // Ensure there are values to set and conditions to update
        if (empty($SET)) throw new PDOException('Cannot update with empty values');
        if (empty($WHERE)) throw new PDOException('UPDATE requires WHERE clause for safety');

        // Build the UPDATE query
        $table = self::sanitize($UPDATE);
        $setClause = implode(', ', array_map(static fn($key) => self::sanitize($key) . " = :set_$key", array_keys($SET)));
        [$whereClause, $whereParams] = self::buildWhere($WHERE, 'where_');
        $query = "UPDATE $table SET $setClause WHERE $whereClause";

        // Combine parameters for SET and WHERE clauses
        $params = [];
        foreach ($SET as $key => $value) $params[":set_$key"] = $value;
        $params = array_merge($params, $whereParams);

        // Execute the UPDATE query and return success
        self::execute($query, $params);
        return true;
    }

    /**
     * This method is for deleting records from a table with specific WHERE conditions.
     *
     * @param string $FROM
     * @param array $WHERE
     *
     * @return bool
     */
    public static function delete(string $FROM, array $WHERE): bool
    {
        // Ensure there are conditions to delete
        if (empty($WHERE)) throw new PDOException('DELETE requires WHERE clause for safety');

        // Build the DELETE query
        $table = self::sanitize($FROM);
        [$whereClause, $params] = self::buildWhere($WHERE);
        $query = "DELETE FROM $table WHERE $whereClause";

        // Execute the DELETE query and return success
        self::execute($query, $params);
        return true;
    }

    /**
     * This method is for checking the existence of a record in a table with specific WHERE conditions.
     *
     * @param string $FROM
     * @param array $WHERE
     *
     * @return bool
     */
    public static function exists(string $FROM, array $WHERE): bool
    {
        // Build the EXISTS query
        $table = self::sanitize($FROM);
        [$whereClause, $params] = self::buildWhere($WHERE);
        $query = "SELECT 1 FROM $table WHERE $whereClause LIMIT 1";

        // Execute the query and return existence
        return self::execute($query, $params)->fetch() !== false;
    }

    /**
     * This method is for counting rows in a table with optional WHERE conditions.
     * If $GROUP_BY is provided, the count of groups is returned.
     *
     * @param string $FROM
     * @param array $WHERE
     * @param string|array|null $GROUP_BY
     *
     * @return int
     */
    public static function count(string $FROM, array $WHERE = [], string|array|null $GROUP_BY = null): int
    {
        // Build the COUNT query
        $table = self::sanitize($FROM);
        [$whereClause, $params] = self::buildWhere($WHERE);

        if ($GROUP_BY === null) {
            // Simple count without GROUP BY
            $query = "SELECT COUNT(*) as count FROM $table" . ($whereClause ? " WHERE $whereClause" : '');
            return (int)self::execute($query, $params)->fetch()['count'];
        }

        // Count with GROUP BY
        $groupCols = self::normalizeColumnsList($GROUP_BY);
        $sub = "SELECT 1 FROM $table" . ($whereClause ? " WHERE $whereClause" : '') . " GROUP BY $groupCols";
        $query = "SELECT COUNT(*) as count FROM ($sub) AS grp";

        // Execute and return the count of groups
        return (int)self::execute($query, $params)->fetch()['count'];
    }

    /**
     * This method is for executing a raw query and returning the result set as an array.
     *
     * @param string $query
     * @param array $params
     *
     * @return array
     */
    public static function query(string $query, array $params = []): array
    {
        // Execute the raw query and return all results
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
        $elem = trim($elem);

        // Validate the GROUP BY element format
        if (!preg_match('/^\w+(\.\w+)?$/', $elem)) throw new PDOException("Invalid GROUP BY element: $elem");
        return $elem;
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

        // Validate the ORDER BY element format
        if (preg_match('/^(\w+(\.\w+)?)(\s+(ASC|DESC))?$/i', $elem, $m)) {
            $column = $m[1];
            $dir = isset($m[4]) ? ' ' . strtoupper($m[4]) : '';
            return $column . $dir;
        }

        throw new PDOException("Invalid ORDER BY element: $elem");
    }
}
