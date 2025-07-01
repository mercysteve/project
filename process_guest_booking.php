<?php
// process_guest_booking.php - Handles booking submissions from index.php

session_start(); // Start the session to access guest login status and set messages

// Enable full PHP error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration file
require_once 'db_config.php';
// Include common functions file
require_once 'functions.php'; // <-- Ensure this line is present

$message = ''; // Initialize message variable for status feedback

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ----------------------------------------------------------------------
    // 1. Collect Data from HTML Form:
    // ----------------------------------------------------------------------
    $fullName = sanitize_input($conn, $_POST['name'] ?? '');
    $email = sanitize_input($conn, $_POST['email'] ?? '');
    $phoneNumber = sanitize_input($conn, $_POST['phone'] ?? '');
    $checkInDate = sanitize_input($conn, $_POST['checkin'] ?? '');
    $checkOutDate = sanitize_input($conn, $_POST['checkout'] ?? '');
    $numGuests = sanitize_input($conn, $_POST['guests'] ?? '');
    $roomType = sanitize_input($conn, $_POST['roomType'] ?? '');

    // Get guest_id if logged in, otherwise set to NULL
    $guestId = null;
    if (isset($_SESSION['guest_loggedin']) && $_SESSION['guest_loggedin'] === true && isset($_SESSION['guest_id'])) {
        $guestId = $_SESSION['guest_id'];
    }

    // Basic validation
    if (empty($fullName) || empty($email) || empty($phoneNumber) ||
        empty($checkInDate) || empty($checkOutDate) || empty($numGuests) ||
        empty($roomType)) {
        $message = "Error: All form fields are required for booking.";
        // If critical fields are missing, redirect back with error
        $_SESSION['status_message_index'] = '<div class="error-message">' . $message . '</div>';
        header("Location: index.php");
        exit();
    } else {
        // ----------------------------------------------------------------------
        // 2. Calculate 'nights' and 'total_amount':
        // ----------------------------------------------------------------------
        $roomPrices = [
            "Standard Room" => 15500.00,
            "Spacious Room" => 18500.00,
            "Master Ensuite" => 23500.00
        ];
        $pricePerNight = $roomPrices[$roomType] ?? 0.00;

        $dtCheckIn = new DateTime($checkInDate);
        $dtCheckOut = new DateTime($checkOutDate);

        $nights = 0;
        $totalAmount = 0.00;

        if ($dtCheckOut <= $dtCheckIn) {
            $message = "Error: Check-out date must be after check-in date.";
            $_SESSION['status_message_index'] = '<div class="error-message">' . $message . '</div>';
            header("Location: index.php");
            exit();
        } else {
            $interval = $dtCheckIn->diff($dtCheckOut);
            $nights = $interval->days;
            $totalAmount = $nights * $pricePerNight;

            // ------------------------------------------------------------------
            // DECISION POINT: Is a guest logged in?
            // ------------------------------------------------------------------
            if ($guestId !== null) {
                // Guest IS logged in: Save booking directly
                $sql = "INSERT INTO bookings (full_name, email, phone_number, check_in_date, check_out_date, nights, num_guests, room_type, total_amount, guest_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                if ($stmt = $conn->prepare($sql)) {
                    $paramType = "sssssiisdi"; // All fields + guest_id (int)
                    $bindArgs = [&$paramType, &$fullName, &$email, &$phoneNumber, &$checkInDate, &$checkOutDate, &$nights, &$numGuests, &$roomType, &$totalAmount, &$guestId];
                    
                    call_user_func_array([$stmt, 'bind_param'], $bindArgs);

                    if ($stmt->execute()) {
                        $message = "Booking submitted successfully! Booking ID: " . $stmt->insert_id;
                        $_SESSION['guest_status_message'] = '<div class="success-message">' . $message . '</div>';
                        header("Location: my_bookings.php"); // Redirect logged-in guests to their dashboard
                        exit();
                    } else {
                        $message = "Error submitting booking: " . $stmt->error;
                        $_SESSION['status_message_index'] = '<div class="error-message">' . $message . '</div>';
                        header("Location: index.php"); // Fallback to index with error
                        exit();
                    }
                    $stmt->close();
                } else {
                    $message = "Database error during booking preparation: " . $conn->error;
                    $_SESSION['status_message_index'] = '<div class="error-message">' . $message . '</div>';
                    header("Location: index.php"); // Fallback to index with error
                    exit();
                }
            } else {
                // Guest is NOT logged in: Store booking in session and redirect to confirm page
                $_SESSION['pending_booking'] = [
                    'name' => $fullName,
                    'email' => $email,
                    'phone' => $phoneNumber,
                    'checkin' => $checkInDate,
                    'checkout' => $checkOutDate,
                    'guests' => $numGuests,
                    'roomType' => $roomType,
                    'nights' => $nights,
                    'totalAmount' => $totalAmount
                ];
                $_SESSION['guest_status_message'] = '<div class="info-message">Please register or log in to confirm your booking.</div>'; // Optional info message
                header("Location: confirm_booking_guest.php"); // Redirect to the new intermediate page
                exit();
            }
        }
    }
} else {
    // If it's not a POST request, redirect to home page
    header("Location: index.php");
    exit();
}

// Close database connection (only if it hasn't been closed by an earlier exit())
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
