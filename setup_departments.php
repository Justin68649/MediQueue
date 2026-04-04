<?php
// Check and create sample departments if needed

require_once 'config/config.php';

$db = Database::getInstance();
$conn = $db->getConnection();

echo "<h1>🔧 Department Setup & Verification</h1>";

// Check existing departments
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM departments WHERE is_active = 1");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$activeCount = $result['count'];

echo "<h2>Current Status</h2>";
echo "<p><strong>Active Departments:</strong> $activeCount</p>";

if ($activeCount == 0) {
    echo "<h2 style='color: red;'>⚠️ No Departments Found!</h2>";
    echo "<p>Creating sample departments...</p>";
    
    $departments = [
        ['name' => 'General Consultation', 'prefix' => 'GEN', 'avg_service_time' => 15],
        ['name' => 'Cardiology', 'prefix' => 'CAR', 'avg_service_time' => 30],
        ['name' => 'Pediatrics', 'prefix' => 'PED', 'avg_service_time' => 20],
        ['name' => 'Orthopedics', 'prefix' => 'ORT', 'avg_service_time' => 25],
        ['name' => 'Dental', 'prefix' => 'DEN', 'avg_service_time' => 20],
    ];
    
    try {
        foreach ($departments as $dept) {
            $insertStmt = $conn->prepare("
                INSERT INTO departments (name, prefix, avg_service_time, is_active) 
                VALUES (?, ?, ?, 1)
            ");
            $insertStmt->execute([$dept['name'], $dept['prefix'], $dept['avg_service_time']]);
            echo "<p style='color: green;'>✅ Created: " . $dept['name'] . "</p>";
        }
        echo "<h3 style='color: green;'>✅ Sample departments created successfully!</h3>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error creating departments: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<h2 style='color: green;'>✅ Departments Found!</h2>";
}

// List all departments
echo "<h2>All Departments</h2>";
$stmt = $conn->prepare("SELECT id, name, prefix, avg_service_time, is_active FROM departments ORDER BY name ASC");
$stmt->execute();
$depts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($depts)) {
    echo "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Name</th><th>Prefix</th><th>Avg Time</th><th>Active</th>";
    echo "</tr>";
    
    foreach ($depts as $d) {
        echo "<tr>";
        echo "<td>" . $d['id'] . "</td>";
        echo "<td>" . $d['name'] . "</td>";
        echo "<td>" . $d['prefix'] . "</td>";
        echo "<td>" . $d['avg_service_time'] . " min</td>";
        echo "<td>" . ($d['is_active'] ? "✅ Yes" : "❌ No") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No departments found</p>";
}

// Test API
echo "<h2>API Test</h2>";
echo "<p>Testing: /api/public/get_departments.php</p>";
echo "<pre>";

$apiUrl = 'http://localhost/MediQueue/api/public/get_departments.php';
$response = @file_get_contents($apiUrl);

if ($response === false) {
    echo "❌ Could not reach API endpoint\n";
} else {
    $data = json_decode($response, true);
    echo json_encode($data, JSON_PRETTY_PRINT);
    
    if ($data['success'] && count($data['departments']) > 0) {
        echo "\n✅ API working correctly - " . count($data['departments']) . " departments returned";
    }
}

echo "</pre>";

echo "<h2 style='margin-top: 30px;'>📝 Next Steps</h2>";
echo "<ol>";
echo "<li>Go to <a href='patient/index.php' target='_blank'>Patient Portal</a></li>";
echo "<li>Press F12 to open Developer Console</li>";
echo "<li>Refresh the page (F5)</li>";
echo "<li>Check console for messages starting with ✅</li>";
echo "<li>Try to select a department from dropdown</li>";
echo "</ol>";

echo "<h2 style='margin-top: 30px;'>🧪 Test the Dropdown</h2>";
echo "<p>Or test the department API directly: <a href='test_department_api.php' target='_blank'>test_department_api.php</a></p>";
?>
