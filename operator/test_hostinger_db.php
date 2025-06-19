<?php
require_once 'includes/config.php';

echo "Testing Hostinger database connection...\n";
echo "Host: $host\n";
echo "Database: $dbname\n";
echo "Username: $username\n";
echo "Port: $port\n";

// Test different connection methods
$connection_methods = [
    "mysql:host=$host;dbname=$dbname;port=$port",
    "mysql:host=$host;dbname=$dbname",
    "mysql:host=$host;port=$port;dbname=$dbname"
];

foreach ($connection_methods as $index => $dsn) {
    echo "\n--- Testing connection method " . ($index + 1) . " ---\n";
    echo "DSN: $dsn\n";
    
    try {
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✅ Connection successful!\n";
        
        // Test if we can query the database
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✅ Query test successful: " . $result['test'] . "\n";
        
        // Check if projects table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'projects'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Projects table exists!\n";
            
            // Check table structure
            $stmt = $pdo->query("DESCRIBE projects");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "Table structure:\n";
            foreach ($columns as $column) {
                echo "- {$column['Field']}: {$column['Type']}\n";
            }
            
            // Check if completed column exists
            $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'completed'");
            if ($stmt->rowCount() > 0) {
                echo "✅ Completed column exists!\n";
            } else {
                echo "❌ Completed column does NOT exist!\n";
            }
            
        } else {
            echo "❌ Projects table does NOT exist!\n";
        }
        
        break; // Stop testing if connection works
        
    } catch (PDOException $e) {
        echo "❌ Connection failed: " . $e->getMessage() . "\n";
        echo "Error code: " . $e->getCode() . "\n";
    }
}

// Test network connectivity
echo "\n--- Testing network connectivity ---\n";
$host_parts = parse_url($host);
$test_host = $host_parts['host'] ?? $host;
$test_port = $port;

echo "Testing connection to $test_host:$test_port\n";
$connection = @fsockopen($test_host, $test_port, $errno, $errstr, 5);
if ($connection) {
    echo "✅ Network connection successful!\n";
    fclose($connection);
} else {
    echo "❌ Network connection failed: $errstr ($errno)\n";
}
?> 