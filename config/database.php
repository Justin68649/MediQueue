<?php
// File: config/database.php
// Database Connection Class

class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection(): PDO {
        return $this->conn;
    }
    
    public function beginTransaction(): bool {
        return $this->conn->beginTransaction();
    }
    
    public function commit(): bool {
        return $this->conn->commit();
    }
    
    public function rollback(): bool {
        return $this->conn->rollback();
    }
    
    public function lastInsertId(): string {
        return $this->conn->lastInsertId();
    }
}