<?php
// File: test_departments.php
// Test script to diagnose department loading issues

require_once 'config/config.php';

echo "=== MediQueue Department System Test ===\n\n";

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // 1. Check if departments table exists
    echo "1. Checking departments table...\n";
    $stmt = $conn->prepare("SHOW TABLES LIKE 'departments'");
    $stmt->execute();
    if ($stmt->fetch()) {
        echo "   ✅ departments table exists\n\n";
    } else {
        echo "   ❌ departments table NOT found\n\n";
        exit;
    }
    
    // 2. Check table structure
    echo "2. Checking table structure...\n";
    $stmt = $conn->prepare("DESCRIBE departments");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $requiredColumns = ['id', 'name', 'prefix', 'color', 'avg_service_time', 'is_active'];
    foreach ($requiredColumns as $col) {
        $found = false;
        foreach ($columns as $row) {
            if ($row['Field'] === $col) {
                $found = true;
                break;
            }
        }
        echo "   " . ($found ? "✅" : "❌") . " Column: $col\n";
    }
    echo "\n";
    
    // 3. Check for active departments
    echo "3. Checking for active departments...\n";
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM departments WHERE is_active = 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $activeCount = $result['count'];
    
    if ($activeCount > 0) {
        echo "   ✅ Found $activeCount active departments\n\n";
    } else {
        echo "   ⚠️  No active departments found!\n";
        echo "   You need to create at least one department.\n\n";
    }
    
    // 4. List all departments
    echo "4. Listing all departments:\n";
    $stmt = $conn->prepare("SELECT * FROM departments ORDER BY name ASC");
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($departments)) {
        foreach ($departments as $dept) {
            echo "   - {$dept['name']} (ID: {$dept['id']}, Prefix: {$dept['prefix']}, Active: " . 
                 ($dept['is_active'] ? 'Yes' : 'No') . ", Avg Time: {$dept['avg_service_time']} min)\n";
        }
        echo "\n";
    } else {
        echo "   ❌ No departments in database\n\n";
    }
    
    // 5. Test API endpoint
    echo "5. Testing API endpoint (simulated)...\n";
    $stmt = $conn->query("SELECT id, name, prefix, color, avg_service_time FROM departments WHERE is_active = 1 ORDER BY name ASC");
    $apiDepartments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($apiDepartments) {
        echo "   ✅ API query would return " . count($apiDepartments) . " departments\n";
        echo "   Sample JSON response:\n";
        $jsonResponse = [
            'success' => true,
            'departments' => $apiDepartments
        ];
        echo "   " . json_encode($jsonResponse, JSON_PRETTY_PRINT) . "\n\n";
    } else {
        echo "   ⚠️  API query returned no results\n\n";
    }
    
    // 6. Check if patients can access the API
    echo "6. Checking API accessibility...\n";
    if (file_exists('api/public/get_departments.php')) {
        echo "   ✅ get_departments.php exists\n\n";
    } else {
        echo "   ❌ get_departments.php NOT found\n\n";
    }
    
    // 7. Recommendations
    echo "7. Recommendations:\n";
    if ($activeCount === 0) {
        echo "   ⚠️  ACTION REQUIRED: Create at least one department\n";
        echo "   You can:\n";
        echo "   - Login to Admin Portal → Departments → Add New Department\n";
        echo "   OR\n";
        echo "   - Run SQL: INSERT INTO departments (name, prefix, avg_service_time) \n";
        echo "      VALUES ('General', 'GEN', 15);\n";
    } else {
        echo "   ✅ Department system is configured correctly\n";
        echo "   If departments are not showing in the patient portal:\n";
        echo "   1. Clear browser cache (Ctrl+Shift+Delete)\n";
        echo "   2. Hard refresh page (Ctrl+F5)\n";
        echo "   3. Check browser console (F12) for errors\n";
    }
    echo "\n";
    
    echo "=== Test Complete ===\n";
    
} catch (Exception $e) {
    echo "❌ Error during testing: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>