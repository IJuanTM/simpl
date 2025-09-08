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

/**
 * The Database class is used for connecting to the database and executing queries.
 */
class Database
{
    private PDO $pdo;
    private object $stmt;

    public function __construct()
    {
        try {
            // Create a new PDO instance
            $this->pdo = new PDO(
                'mysql:host=' . DB_SERVER . ';dbname=' . DB_NAME,
                DB_USERNAME,
                DB_PASSWORD,
                [
                    PDO::ATTR_PERSISTENT => true,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]
            );

            // Set database connection to use UTC timezone
            $this->pdo->exec("SET time_zone = '" . (new DateTime('now', new DateTimeZone(TIMEZONE)))->format('P') . "'");
        } catch (PDOException $e) {
            $this->handleError($e);
        } catch (DateMalformedStringException) {
            $this->handleError(new PDOException('Invalid timezone configuration.'));
        }
    }

    /**
     * This method is for handling database errors by logging the error and redirecting to an error page.
     *
     * @param PDOException $e
     */
    private function handleError(PDOException $e): void
    {
        // Log error
        LogController::log($e->getMessage(), LogType::DATABASE);

        // Redirect to error page
        PageController::redirect('error/500');
    }

    /**
     * This method is for preparing a query for execution.
     *
     * @param string $query
     */
    final public function query(string $query): void
    {
        try {
            $this->stmt = $this->pdo->prepare($query);
        } catch (PDOException $e) {
            $this->handleError($e);
        }
    }

    /**
     * This method is for binding a value to a parameter.
     *
     * @param int|string $param
     * @param mixed $value
     * @param int|null $type
     */
    final public function bind(int|string $param, mixed $value, int|null $type = null): void
    {
        // Determine the type if not provided explicitly
        $type = $type ?? match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };

        try {
            $this->stmt->bindValue($param, $value, $type);
        } catch (PDOException $e) {
            $this->handleError($e);
        }
    }

    /**
     * This method is for executing a prepared statement and returning the result set as an array.
     *
     * @return array
     */
    final public function fetchAll(): array
    {
        try {
            $this->execute();

            return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->handleError($e);

            return [];
        }
    }

    /**
     * This method is for executing a prepared statement.
     */
    final public function execute(): void
    {
        try {
            $this->stmt->execute();
        } catch (PDOException $e) {
            $this->handleError($e);
        }
    }

    /**
     * This method is for executing a prepared statement and returning the first row of the result set.
     *
     * @return array|null
     */
    final public function single(): array|null
    {
        try {
            $this->execute();

            return $this->stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            $this->handleError($e);

            return null;
        }
    }

    /**
     * This method is for getting the number of rows affected by the last SQL statement.
     *
     * @return int
     */
    final public function rowCount(): int
    {
        try {
            $this->execute();

            return $this->stmt->rowCount();
        } catch (PDOException $e) {
            $this->handleError($e);

            return 0;
        }
    }
}
