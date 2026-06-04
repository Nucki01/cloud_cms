<?php
/**
 * Database.php
 *
 * A simple OOP class that creates ONE shared PDO connection
 * using the Singleton pattern.  This means no matter how many
 * pages include this file, we only ever open one connection —
 * which is efficient and easy to understand.
 *
 * HOW TO USE in any PHP page:
 *   require_once 'Database.php';
 *   $db = Database::getInstance();
 *   $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
 *   $stmt->execute([$id]);
 */

class Database
{
    // --------------- Database credentials ---------------
    // Change these to match your local environment (XAMPP default shown)
    private const DB_HOST = 'localhost';
    private const DB_NAME = 'cloudcms';
    private const DB_USER = 'root';
    private const DB_PASS = '';          // XAMPP default has no password
    private const DB_CHAR = 'utf8mb4';   // supports all characters including emoji
    // ----------------------------------------------------

    // Holds the single PDO connection object
    private static ?PDO $connection = null;

    // Private constructor stops anyone calling "new Database()"
    private function __construct() {}

    /**
     * getInstance()
     * Returns the existing connection, or creates a new one
     * the very first time it is called.
     */
    public static function getInstance(): PDO
    {
        // Only create the connection if one doesn't exist yet
        if (self::$connection === null) {
            // Build the Data Source Name string PDO needs
            $dsn = 'mysql:host=' . self::DB_HOST
                 . ';dbname='    . self::DB_NAME
                 . ';charset='   . self::DB_CHAR;

            // PDO options: throw exceptions on errors, use named columns
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,  // real prepared statements
            ];

            try {
                // Create the connection and store it
                self::$connection = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
            } catch (PDOException $e) {
                // In a real app you'd log this; for student use show the message
                die('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$connection;
    }
}
