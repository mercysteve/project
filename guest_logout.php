<?php
// guest_logout.php - Handles Guest Logout

session_start(); // Start the session

// Unset all session variables related to guest
unset($_SESSION['guest_loggedin']);
unset($_SESSION['guest_id']);
unset($_SESSION['guest_full_name']);

// Destroy the session (or just unset specific variables if multiple session types exist)
session_destroy();

// Redirect to the guest login page or homepage
header("Location: guest_login.php"); // Or index.html
exit();
?>
