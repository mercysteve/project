<?php
// db_config.php

// Database credentials
define('DB_SERVER', 'localhost'); // Your MySQL server host (often 'localhost')
define('DB_USERNAME', 'root');   // Your MySQL username (e.g., 'root' for XAMPP/WAMP)
define('DB_PASSWORD', '');       // Your MySQL password (empty for XAMPP/WAMP default)
define('DB_NAME', 'supremacy_hotel'); // Correct database name with underscore

// Attempt to connect to MySQL database
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>





  