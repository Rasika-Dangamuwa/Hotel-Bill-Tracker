<?php
/**
 * Database Connection File
 * Hotel Bill Tracking System - Nestle Lanka Limited
 * 
 * This file handles all database connections and provides
 * utility functions for database operations
 */

// Prevent direct access to this file
if (!defined('ALLOW_ACCESS')) {
    die('Direct access not permitted');
}

// Database configuration
class DatabaseConfig {
    // Database connection parameters
    private const DB_HOST = 'localhost';        // Change if needed
    private const DB_NAME = 'hotel_tracking_system';
    private const DB_USER = 'root';             // Change to your MySQL username
    private const DB_PASS = '';                 // Change to your MySQL password
    private const DB_CHARSET = 'utf8mb4';
    
    // Connection settings
    private const DB_OPTIONS = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ];
    
    private static $connection = null;
    
    /**
     * Get database connection instance (Singleton pattern)
     * @return PDO Database connection
     * @throws Exception If connection fails
     */
    public static function getConnection() {
        if (self::$connection === null) {
            try {
                $dsn = "mysql:host=" . self::DB_HOST . ";dbname=" . self::DB_NAME . ";charset=" . self::DB_CHARSET;
                self::$connection = new PDO($dsn, self::DB_USER, self::DB_PASS, self::DB_OPTIONS);
                
                // Set timezone to Sri Lanka
                self::$connection->exec("SET time_zone = '+05:30'");
                
            } catch (PDOException $e) {
                // Log error (in production, log to file instead of displaying)
                error_log("Database connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed. Please try again later.");
            }
        }
        
        return self::$connection;
    }
    
    /**
     * Close database connection
     */
    public static function closeConnection() {
        self::$connection = null;
    }
    
    /**
     * Test database connection
     * @return bool True if connection successful
     */
    public static function testConnection() {
        try {
            $pdo = self::getConnection();
            $stmt = $pdo->query("SELECT 1");
            return $stmt !== false;
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * Database utility class for common operations
 */
class Database {
    private $pdo;
    
    public function __construct() {
        $this->pdo = DatabaseConfig::getConnection();
    }
    
    /**
     * Execute a prepared statement
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters for the query
     * @return PDOStatement
     * @throws Exception If query fails
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query failed: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("Database operation failed. Please try again.");
        }
    }
    
    /**
     * Get a single row from database
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return array|false Single row or false if not found
     */
    public function fetchRow($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Get all rows from database
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return array Array of rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get single value from database
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return mixed Single value
     */
    public function fetchValue($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Insert data and return last insert ID
     * @param string $sql SQL insert query
     * @param array $params Query parameters
     * @return string Last insert ID
     */
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Update/Delete data and return affected rows
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return int Number of affected rows
     */
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Begin database transaction
     */
    public function beginTransaction() {
        $this->pdo->beginTransaction();
    }
    
    /**
     * Commit database transaction
     */
    public function commit() {
        $this->pdo->commit();
    }
    
    /**
     * Rollback database transaction
     */
    public function rollback() {
        $this->pdo->rollback();
    }
    
    /**
     * Check if we're in a transaction
     * @return bool True if in transaction
     */
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }
}

/**
 * Quick database functions for simple operations
 */

/**
 * Get database instance
 * @return Database Database instance
 */
function getDB() {
    return new Database();
}

/**
 * Execute a simple query
 * @param string $sql SQL query
 * @param array $params Query parameters
 * @return PDOStatement
 */
function dbQuery($sql, $params = []) {
    $db = getDB();
    return $db->query($sql, $params);
}

/**
 * Get single row
 * @param string $sql SQL query
 * @param array $params Query parameters
 * @return array|false
 */
function dbFetchRow($sql, $params = []) {
    $db = getDB();
    return $db->fetchRow($sql, $params);
}

/**
 * Get all rows
 * @param string $sql SQL query
 * @param array $params Query parameters
 * @return array
 */
function dbFetchAll($sql, $params = []) {
    $db = getDB();
    return $db->fetchAll($sql, $params);
}

/**
 * Get single value
 * @param string $sql SQL query
 * @param array $params Query parameters
 * @return mixed
 */
function dbFetchValue($sql, $params = []) {
    $db = getDB();
    return $db->fetchValue($sql, $params);
}

/**
 * Insert data
 * @param string $sql SQL query
 * @param array $params Query parameters
 * @return string Last insert ID
 */
function dbInsert($sql, $params = []) {
    $db = getDB();
    return $db->insert($sql, $params);
}

/**
 * Execute update/delete
 * @param string $sql SQL query
 * @param array $params Query parameters
 * @return int Affected rows
 */
function dbExecute($sql, $params = []) {
    $db = getDB();
    return $db->execute($sql, $params);
}

// Set flag to allow access to this file (only if not already defined)
if (!defined('ALLOW_ACCESS')) {
    define('ALLOW_ACCESS', true);
}
?>