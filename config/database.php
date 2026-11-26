<?php
/**
 * MADAM CAKES — PDO connection layer.
 * Every query in the application goes through prepared statements here.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);

        try {
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            self::$pdo->exec("SET time_zone = '+03:00'");
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . PHP_EOL);
                exit(1);
            }
            exit(APP_DEBUG
                ? '<h1>Database connection failed</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>'
                : '<h1>Service temporarily unavailable</h1><p>Please try again shortly.</p>');
        }

        return self::$pdo;
    }

    /** Run a prepared statement and return it. */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** Single scalar value (first column of first row). */
    public static function value(string $sql, array $params = [], mixed $default = null): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();
        return $value === false ? $default : $value;
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    public static function begin(): void
    {
        if (!self::pdo()->inTransaction()) {
            self::pdo()->beginTransaction();
        }
    }

    public static function commit(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->commit();
        }
    }

    public static function rollback(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }
}

/** Shorthand used across the application. */
function db(): PDO
{
    return Database::pdo();
}
