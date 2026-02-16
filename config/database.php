<?php
/**
 * Database Configuration & Connection
 * 
 * Provides a singleton PDO connection with prepared statement support
 * for SQL injection prevention. Uses environment variables or defaults.
 */

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    // Database credentials - update these for your environment
    private const DB_HOST = '127.0.0.1';
    private const DB_PORT = '3306';
    private const DB_NAME = 'user_directory';
    private const DB_USER = 'root';
    private const DB_PASS = '';
    private const DB_CHARSET = 'utf8mb4';

    /**
     * Get singleton PDO connection
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: self::DB_HOST;
            $port = getenv('DB_PORT') ?: self::DB_PORT;
            $dbname = getenv('DB_NAME') ?: self::DB_NAME;
            $user = getenv('DB_USER') ?: self::DB_USER;
            $pass = getenv('DB_PASS') ?: self::DB_PASS;

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=" . self::DB_CHARSET;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // Use real prepared statements
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . self::DB_CHARSET,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    'error'   => 'Database connection failed',
                    'message' => $e->getMessage()
                ]);
                exit;
            }
        }

        return self::$instance;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
