<?php
// cancel_booking.php - Handles cancellation of a booking by an admin

session_start();

// Enable full PHP error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Authentication check: Only logged-in admins can cancel bookings
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['status_message'] = '<div class="error-message">Access denied. Please log in as an admin.</div>';
    header("Location: login.php");
    exit();
}

// Include database configuration and common functions
require_once 'db_config.php';
require_once 'functions.php';

$bookingId = sanitize_input($conn, $_POST['booking_id'] ?? '');

if (empty($bookingId)) {
    $_SESSION['status_message'] = '<div class="error-message">Invalid booking ID provided for cancellation.</div>';
    header("Location: dashboard.php");
    exit();
}

// Update the booking status to 'Cancelled'
$sqlUpdate = "UPDATE bookings SET status = 'Cancelled' WHERE id = ? AND status IN ('Active', 'Booked Out')"; // Can cancel active or already booked out ones

if ($stmt = $conn->prepare($sqlUpdate)) {
    $stmt->bind_param("i", $bookingId);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['status_message'] = '<div class="success-message">Booking ID ' . htmlspecialchars($bookingId) . ' has been successfully cancelled.</div>';
        } else {
            $_SESSION['status_message'] = '<div class="info-message">Booking ID ' . htmlspecialchars($bookingId) . ' not found or was already cancelled.</div>';
        }
    } else {
        $_SESSION['status_message'] = '<div class="error-message">Error cancelling booking: ' . $stmt->error . '</div>';
    }
    $stmt->close();
} else {
    $_SESSION['status_message'] = '<div class="error-message">Database error preparing cancellation statement: ' . $conn->error . '</div>';
}

$conn->close();

// Redirect back to the dashboard to show the updated list and message
header("Location: dashboard.php");
exit();
?>
