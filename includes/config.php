<?php
$config = [
    'production' => [
        'host' => 'localhost', 
        'dbname' => 'u425238599_portal',
        'username' => 'u425238599_portal',
        'password' => 'Portal+-123',
        'port' => 3306
    ]
];

// Set environment to production
$environment = 'production';

// Get current configuration
$db_config = $config[$environment];

// Database connection details
$host = $db_config['host'];
$dbname = $db_config['dbname'];
$username = $db_config['username'];
$password = $db_config['password'];
$port = $db_config['port'];

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/php_errors.log');
?> 