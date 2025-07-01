<?php
// functions.php - Central place for common utility functions

/**
 * Sanitizes and escapes input data for security.
 * Prevents common vulnerabilities like SQL injection and XSS.
 *
 * @param mysqli $conn The database connection object.
 * @param string $data The input string to sanitize.
 * @return string The sanitized and escaped string.
 */
function sanitize_input($conn, $data) {
    $data = trim($data); // Remove whitespace from the beginning and end of string
    $data = stripslashes($data); // Remove backslashes
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8'); // Convert special characters to HTML entities, handles quotes
    // Escape special characters in a string for use in an SQL statement
    return mysqli_real_escape_string($conn, $data);
}

// You can add other common functions here as needed, e.g., redirect functions, message handling, etc.

?>
