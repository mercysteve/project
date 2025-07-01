<?php
// book_out_booking.php - Handles the administrative action of booking out a guest

session_start();

// Enable full PHP error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Authentication check: Only logged-in admins can book out guests
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['status_message'] = '<div class="error-message">Access denied. Please log in as an admin.</div>';
    header("Location: login.php");
    exit();
}

// Include database configuration and common functions
require_once 'db_config.php';
require_once 'functions.php'; // For sanitize_input

// --- Debugging: Check if POST data is received ---
if (empty($_POST) || !isset($_POST['booking_id'])) {
    $_SESSION['status_message'] = '<div class="error-message">Error: No booking ID received via POST. Form submission issue?</div>';
    header("Location: dashboard.php");
    exit();
}

$bookingId = sanitize_input($conn, $_POST['booking_id']);

if (empty($bookingId)) {
    $_SESSION['status_message'] = '<div class="error-message">Error: Booking ID is empty after sanitization.</div>';
    header("Location: dashboard.php");
    exit();
}

// --- Debugging: Check database connection status ---
if ($conn->connect_error) {
    $_SESSION['status_message'] = '<div class="error-message">Database Connection Failed: ' . $conn->connect_error . '</div>';
    header("Location: dashboard.php");
    exit();
}


// Check current status of the booking before attempting to book out
$checkSql = "SELECT status FROM bookings WHERE id = ?";
$currentStatus = '';
if ($stmtCheck = $conn->prepare($checkSql)) {
    $stmtCheck->bind_param("i", $bookingId);
    $stmtCheck->execute();
    $stmtCheck->bind_result($currentStatus);
    $stmtCheck->fetch();
    $stmtCheck->close();
} else {
    $_SESSION['status_message'] = '<div class="error-message">DB Error (check status): ' . $conn->error . '</div>';
    $conn->close();
    header("Location: dashboard.php");
    exit();
}

if ($currentStatus !== 'Active') {
    if ($currentStatus === 'Booked Out') {
        $_SESSION['status_message'] = '<div class="info-message">Booking ID ' . htmlspecialchars($bookingId) . ' is already booked out.</div>';
    } elseif ($currentStatus === 'Cancelled') {
        $_SESSION['status_message'] = '<div class="info-message">Booking ID ' . htmlspecialchars($bookingId) . ' is cancelled and cannot be booked out.</div>';
    } else {
        $_SESSION['status_message'] = '<div class="error-message">Booking ID ' . htmlspecialchars($bookingId) . ' has an unexpected status: ' . htmlspecialchars($currentStatus) . '. Cannot book out.</div>';
    }
    $conn->close();
    header("Location: dashboard.php");
    exit();
}


// Update the booking status to 'Booked Out' and set the book_out_time
$sqlUpdate = "UPDATE bookings SET status = 'Booked Out', book_out_time = NOW() WHERE id = ? AND status = 'Active'";

if ($stmt = $conn->prepare($sqlUpdate)) {
    $stmt->bind_param("i", $bookingId);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['status_message'] = '<div class="success-message">Booking ID ' . htmlspecialchars($bookingId) . ' has been successfully booked out.</div>';
        } else {
            // This might happen if status was changed by another process between check and update
            $_SESSION['status_message'] = '<div class="info-message">Booking ID ' . htmlspecialchars($bookingId) . ' was not updated. It might have been already booked out or its status changed.</div>';
        }
    } else {
        $_SESSION['status_message'] = '<div class="error-message">Error executing book-out: ' . $stmt->error . '</div>';
    }
    $stmt->close();
} else {
    $_SESSION['status_message'] = '<div class="error-message">Database error preparing book-out statement: ' . $conn->error . '</div>';
}

$conn->close();

// Redirect back to the dashboard to show the updated list and message
header("Location: dashboard.php");
exit();
?>
