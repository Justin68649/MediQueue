<?php
// File: api/admin/backup_database.php
// Database Backup API

require_once '../../config/config.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 401 Unauthorized');
    echo "Unauthorized access";
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Set filename
$filename = "backup_" . date('Y-m-d_H-i-s') . ".sql";

// Set headers for download
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');

try {
    // Get all tables
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $output = "-- Database Backup\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- Database: " . DB_NAME . "\n\n";
    $output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $output .= "START TRANSACTION;\n";
    $output .= "SET time_zone = \"+00:00\";\n\n";
    
    foreach ($tables as $table) {
        // Get create table syntax
        $stmt = $conn->query("SHOW CREATE TABLE `$table`");
        $create = $stmt->fetch(PDO::FETCH_ASSOC);
        $output .= "-- Table structure for table `$table`\n";
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        $output .= $create['Create Table'] . ";\n\n";
        
        // Get data
        $stmt = $conn->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) > 0) {
            $output .= "-- Dumping data for table `$table`\n";
            
            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . addslashes($value) . "'";
                    }
                }
                $output .= "INSERT INTO `$table` VALUES (" . implode(", ", $values) . ");\n";
            }
            $output .= "\n";
        }
    }
    
    $output .= "COMMIT;\n";
    
    echo $output;
    
    // Log audit
    logAudit($_SESSION['user_id'], 'backup_database', "Created database backup: {$filename}");
    
} catch (Exception $e) {
    error_log("Error in backup_database.php: " . $e->getMessage());
    echo "-- Error creating backup: " . $e->getMessage();
}
exit;
?>