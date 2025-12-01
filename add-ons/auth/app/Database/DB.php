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

    private static function connect(): PDO
    {
        if (self::$pdo !== null) return self::$pdo;

        try {
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

            $tz = (new DateTime('now', new DateTimeZone(TIMEZONE)))->format('P');
            self::$pdo->exec("SET time_zone = '$tz'");

            return self::$pdo;
        } catch (PDOException $e) {
            self::handleError($e);
        } catch (DateMalformedStringException) {
            self::handleError(new PDOException('Invalid timezone configuration.'));
        }
    }

    /**
     * Handle database errors by logging and redirecting.
     *
     * @param PDOException $e
     *
     * @return never
     */
    private static function handleError(PDOException $e): never
    {
        LogController::log($e->getMessage(), LogType::DATABASE);
        PageController::redirect('error/500');
        exit;
    }

    /**
     * Execute a query with optional parameters.
     *
     * @param string $query
     * @param array $params
     *
     * @return object
     */
    private static function execute(string $query, array $params = []): object
    {
        try {
            $pdo = self::connect();
            $stmt = $pdo->prepare($query);

            foreach ($params as $key => $value) {
                $type = match (true) {
                    is_int($value) => PDO::PARAM_INT,
                    is_bool($value) => PDO::PARAM_BOOL,
                    $value === null => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR,
                };
                $stmt->bindValue($key, $value, $type);
            }

            $stmt->execute();
            return $stmt;
        } catch (PDOException $e) {
            self::handleError($e);
        }
    }

    /**
     * Fetch multiple records from a table.
     *
     * @param array $SELECT
     * @param string $FROM
     * @param array $WHERE
     *
     * @return array
     */
    public static function select(array $SELECT, string $FROM, array $WHERE = []): array
    {
        $cols = $SELECT === ['*'] ? '*' : implode(', ', $SELECT);
        $whereClause = self::buildWhereClause($WHERE);
        $query = "SELECT $cols FROM $FROM" . ($whereClause ? " WHERE $whereClause" : '');

        return self::execute($query, $WHERE)->fetchAll();
    }

    /**
     * Fetch a single record from a table.
     *
     * @param array $SELECT
     * @param string $FROM
     * @param array $WHERE
     *
     * @return array|null
     */
    public static function single(array $SELECT, string $FROM, array $WHERE = []): ?array
    {
        $cols = $SELECT === ['*'] ? '*' : implode(', ', $SELECT);
        $whereClause = self::buildWhereClause($WHERE);
        $query = "SELECT $cols FROM $FROM" . ($whereClause ? " WHERE $whereClause" : '') . ' LIMIT 1';

        $result = self::execute($query, $WHERE)->fetch();
        return $result ?: null;
    }

    /**
     * Insert a record into a table.
     *
     * @param string $INTO
     * @param array $VALUES
     *
     * @return bool
     */
    public static function insert(string $INTO, array $VALUES): bool
    {
        $columns = implode(', ', array_keys($VALUES));
        $placeholders = ':' . implode(', :', array_keys($VALUES));
        $query = "INSERT INTO $INTO ($columns) VALUES ($placeholders)";

        self::execute($query, $VALUES);
        return true;
    }

    /**
     * Update records in a table.
     *
     * @param string $UPDATE
     * @param array $SET
     * @param array $WHERE
     *
     * @return bool
     */
    public static function update(string $UPDATE, array $SET, array $WHERE): bool
    {
        $setClause = implode(', ', array_map(static fn($key) => "$key = :set_$key", array_keys($SET)));
        $whereClause = self::buildWhereClause($WHERE, 'where_');
        $query = "UPDATE $UPDATE SET $setClause WHERE $whereClause";

        $params = [];
        foreach ($SET as $key => $value) $params[":set_$key"] = $value;
        foreach ($WHERE as $key => $value) $params[":where_$key"] = $value;

        self::execute($query, $params);
        return true;
    }

    /**
     * Delete records from a table.
     *
     * @param string $FROM
     * @param array $WHERE
     *
     * @return bool
     */
    public static function delete(string $FROM, array $WHERE): bool
    {
        $whereClause = self::buildWhereClause($WHERE);
        $query = "DELETE FROM $FROM WHERE $whereClause";

        self::execute($query, $WHERE);
        return true;
    }

    public static function query(string $query, array $params = []): array
    {
        return self::execute($query, $params)->fetchAll();
    }

    public static function queryOne(string $query, array $params = []): ?array
    {
        $result = self::execute($query, $params)->fetch();
        return $result ?: null;
    }

    public static function lastInsertId(): string
    {
        return self::connect()->lastInsertId();
    }

    private static function buildWhereClause(array $where, string $prefix = ''): string
    {
        if (empty($where)) return '';
        return implode(' AND ', array_map(static fn($key) => "$key = :$prefix$key", array_keys($where)));
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
}
