<?php
// Quick test to check if departments API is working
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Department API Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .test-box { border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; }
        .error { background: #f8d7da; border-color: #f5c6cb; }
        .info { background: #d1ecf1; border-color: #bee5eb; }
        code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Department System Diagnostic</h1>
    
    <div class="test-box info">
        <h3>Test 1: API Response (Raw JSON)</h3>
        <p>Loading departments from API...</p>
        <div id="apiResult"></div>
    </div>

    <div class="test-box info">
        <h3>Test 2: Parse & Display</h3>
        <p id="parseResult">Processing...</p>
    </div>

    <div class="test-box info">
        <h3>Test 3: Department Count</h3>
        <p id="countResult">Checking...</p>
    </div>

    <div class="test-box info">
        <h3>Test 4: Select Dropdown Test</h3>
        <label for="test_select">Try selecting a department:</label>
        <select id="test_select" style="padding: 8px; margin-top: 10px; cursor: pointer; width: 100%; max-width: 400px;">
            <option value="">Choose department...</option>
        </select>
        <p style="margin-top: 10px; color: green;" id="selectTest"></p>
    </div>

    <script>
        // Test API
        async function testAPI() {
            try {
                console.log('Fetching: /MediQueue/api/public/get_departments.php');
                const response = await fetch('./api/public/get_departments.php');
                console.log('Response Status:', response.status);
                
                const data = await response.json();
                console.log('Response Data:', data);
                
                // Show raw JSON
                document.getElementById('apiResult').innerHTML = 
                    '<pre>' + JSON.stringify(data, null, 2) + '</pre>' +
                    '<p style="color: ' + (data.success ? 'green' : 'red') + ';">' + 
                    (data.success ? '✅ API Working' : '❌ API Error: ' + data.message) + 
                    '</p>';
                
                // Parse results
                if (data.success && data.departments) {
                    document.getElementById('parseResult').innerHTML = 
                        '<span style="color: green;">✅ Successfully parsed ' + data.departments.length + ' departments</span>';
                    
                    document.getElementById('countResult').innerHTML = 
                        '<span style="color: green;">✅ Found ' + data.count + ' active department(s)</span>';
                    
                    // Populate test dropdown
                    const select = document.getElementById('test_select');
                    data.departments.forEach(dept => {
                        const option = document.createElement('option');
                        option.value = dept.id;
                        option.textContent = dept.name + ' (' + dept.prefix + ')';
                        select.appendChild(option);
                        console.log('Added:', dept.name);
                    });
                    
                    document.getElementById('selectTest').innerHTML = 
                        '✅ Dropdown populated with ' + data.departments.length + ' departments. Try selecting one!';
                } else {
                    document.getElementById('parseResult').innerHTML = 
                        '<span style="color: red;">❌ No departments in response</span>';
                    document.getElementById('countResult').innerHTML = 
                        '<span style="color: red;">❌ Count: 0</span>';
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('apiResult').innerHTML = 
                    '<p style="color: red;">❌ Error: ' + error.message + '</p>' +
                    '<p>Make sure you\'re accessing this from the correct URL</p>';
                document.getElementById('parseResult').innerHTML = 
                    '<span style="color: red;">❌ Failed to parse API response</span>';
            }
        }

        // Add change listener to test dropdown
        document.getElementById('test_select').addEventListener('change', function() {
            if (this.value) {
                document.getElementById('selectTest').innerHTML = 
                    '✅ You selected: ' + this.options[this.selectedIndex].text;
            }
        });

        // Run test on load
        testAPI();
    </script>
</body>
</html>