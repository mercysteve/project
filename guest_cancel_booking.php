<?php
// guest_cancel_booking.php - Handles cancellation of a guest's booking

session_start();

// Enable full PHP error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Authentication check: Only logged-in guests can cancel their own bookings
if (!isset($_SESSION['guest_loggedin']) || $_SESSION['guest_loggedin'] !== true || !isset($_SESSION['guest_id'])) {
    $_SESSION['guest_status_message'] = '<div class="error-message">You must be logged in to cancel bookings.</div>';
    header("Location: guest_login.php");
    exit();
}

// Include database configuration and common functions
require_once 'db_config.php';
require_once 'functions.php';

$guestId = $_SESSION['guest_id']; // The ID of the currently logged-in guest
$bookingId = sanitize_input($conn, $_POST['booking_id'] ?? ''); // Get the booking ID from the form

if (empty($bookingId)) {
    $_SESSION['guest_status_message'] = '<div class="error-message">Invalid booking ID provided for cancellation.</div>';
    header("Location: my_bookings.php");
    exit();
}

// Prepare SQL statement to delete the booking, ensuring it belongs to the logged-in guest
$sqlDelete = "DELETE FROM bookings WHERE id = ? AND guest_id = ?";

if ($stmt = $conn->prepare($sqlDelete)) {
    $stmt->bind_param("ii", $bookingId, $guestId);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['guest_status_message'] = '<div class="success-message">Booking ID ' . htmlspecialchars($bookingId) . ' has been successfully cancelled.</div>';
        } else {
            $_SESSION['guest_status_message'] = '<div class="error-message">Booking ID ' . htmlspecialchars($bookingId) . ' not found or does not belong to your account.</div>';
        }
    } else {
        $_SESSION['guest_status_message'] = '<div class="error-message">Error cancelling booking: ' . $stmt->error . '</div>';
    }
    $stmt->close();
} else {
    $_SESSION['guest_status_message'] = '<div class="error-message">Database error preparing cancellation: ' . $conn->error . '</div>';
}

$conn->close();

// Redirect back to the my_bookings dashboard to show the updated list and message
header("Location: my_bookings.php");
exit();
?>
