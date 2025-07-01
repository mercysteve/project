<?php
// clear_bookings.php

session_start();

// Include database configuration
require_once 'db_config.php';
require_once 'functions.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // SQL to truncate the table (deletes all rows and resets auto-increment)
    $sql = "TRUNCATE TABLE bookings";

    if ($conn->query($sql) === TRUE) {
        $message = "All bookings cleared successfully from the database.";
    } else {
        $message = "Error clearing bookings: " . $conn->error;
    }
} else {
    $message = "Invalid request to clear bookings.";
}

// Close connection
$conn->close();

// Redirect back to the dashboard with a message
$_SESSION['status_message'] = $message;
header("Location: dashboard.php");
exit;
?>




