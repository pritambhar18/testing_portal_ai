<?php
/**
 * config/db.php
 * MySQLi connection for testing_portal
 */

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'testing_portal';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_errno) {
    die('Database connection failed: (' . $conn->connect_errno . ') ' . $conn->connect_error);
}

// Set charset
if (! $conn->set_charset('utf8mb4')) {
    trigger_error('Error loading character set utf8mb4: ' . $conn->error, E_USER_WARNING);
}

// Return the mysqli connection for backward compatibility
return $conn;

?>
