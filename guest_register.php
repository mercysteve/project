<?php
// guest_register.php - Guest Registration Form and Processing (UPDATED FOR MY_BOOKINGS INTEGRATION)

session_start(); // Start the session for messages

// Enable full PHP error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration file
require_once 'db_config.php';
// Include common functions file
require_once 'functions.php';

// Process registration form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName = sanitize_input($conn, $_POST['full_name'] ?? '');
    $email = sanitize_input($conn, $_POST['email'] ?? '');
    $phoneNumber = sanitize_input($conn, $_POST['phone_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $registrationError = false;
    $errorMessage = '';

    if (empty($fullName) || empty($email) || empty($phoneNumber) || empty($password) || empty($confirmPassword)) {
        $errorMessage = '<div class="error-message">All fields are required.</div>';
        $registrationError = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = '<div class="error-message">Invalid email format.</div>';
        $registrationError = true;
    } elseif ($password !== $confirmPassword) {
        $errorMessage = '<div class="error-message">Passwords do not match.</div>';
        $registrationError = true;
    } elseif (strlen($password) < 6) {
        $errorMessage = '<div class="error-message">Password must be at least 6 characters long.</div>';
        $registrationError = true;
    } else {
        // Check if email already exists
        $checkSql = "SELECT id FROM guests WHERE email = ?";
        if ($stmt = $conn->prepare($checkSql)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $errorMessage = '<div class="error-message">Email already registered. Please login or use a different email.</div>';
                $registrationError = true;
            }
            $stmt->close();
        } else {
            $errorMessage = '<div class="error-message">Database error during email check: ' . $conn->error . '</div>';
            $registrationError = true;
        }
    }

    if ($registrationError) {
        $_SESSION['guest_status_message'] = $errorMessage;
        $_SESSION['register_message'] = $errorMessage; // Specific message for register form
        header("Location: my_bookings.php"); // Redirect back to my_bookings
        exit();
    } else {
        // Proceed with registration
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $insertSql = "INSERT INTO guests (full_name, email, phone_number, password_hash) VALUES (?, ?, ?, ?)";
        if ($stmtInsert = $conn->prepare($insertSql)) {
            $stmtInsert->bind_param("ssss", $fullName, $email, $phoneNumber, $hashedPassword);
            if ($stmtInsert->execute()) {
                $newGuestId = $conn->insert_id; // Get the ID of the newly registered guest

                // Automatically log in the new guest
                $_SESSION['guest_loggedin'] = true;
                $_SESSION['guest_id'] = $newGuestId;
                $_SESSION['guest_full_name'] = $fullName;
                $_SESSION['guest_email'] = $email;
                $_SESSION['guest_phone_number'] = $phoneNumber;

                // Check for and process pending booking
                if (isset($_SESSION['pending_booking']) && !empty($_SESSION['pending_booking'])) {
                    $pendingBooking = $_SESSION['pending_booking'];
                    unset($_SESSION['pending_booking']); // Clear pending booking from session

                    // Update the email/name/phone in the pending booking to match the logged-in guest
                    $pendingBooking['name'] = $fullName;
                    $pendingBooking['email'] = $email;
                    $pendingBooking['phone'] = $phoneNumber;

                    // Insert the pending booking into the database with the new guest's ID
                    $sqlBooking = "INSERT INTO bookings (full_name, email, phone_number, check_in_date, check_out_date, nights, num_guests, room_type, total_amount, guest_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    if ($stmtBooking = $conn->prepare($sqlBooking)) {
                        $stmtBooking->bind_param("sssssiisdi",
                            $pendingBooking['name'],
                            $pendingBooking['email'],
                            $pendingBooking['phone'],
                            $pendingBooking['checkin'],
                            $pendingBooking['checkout'],
                            $pendingBooking['nights'],
                            $pendingBooking['guests'],
                            $pendingBooking['roomType'],
                            $pendingBooking['totalAmount'],
                            $newGuestId // Attach the new guest's ID
                        );
                        if ($stmtBooking->execute()) {
                            $_SESSION['guest_status_message'] = '<div class="success-message">Registration successful and your booking has been confirmed!</div>';
                        } else {
                            $_SESSION['guest_status_message'] = '<div class="error-message">Registration successful, but there was an error confirming your booking: ' . $stmtBooking->error . '</div>';
                        }
                        $stmtBooking->close();
                    } else {
                        $_SESSION['guest_status_message'] = '<div class="error-message">Registration successful, but database error confirming booking: ' . $conn->error . '</div>';
                    }
                } else {
                    $_SESSION['guest_status_message'] = '<div class="success-message">Registration successful! Welcome!</div>';
                }
                header("Location: my_bookings.php"); // Redirect to my_bookings.php
                exit();
            } else {
                $errorMessage = '<div class="error-message">Error registering: ' . $stmtInsert->error . '</div>';
                $_SESSION['guest_status_message'] = $errorMessage;
                $_SESSION['register_message'] = $errorMessage; // Specific message
                header("Location: my_bookings.php"); // Redirect back to my_bookings
                exit();
            }
            $stmtInsert->close();
        } else {
            $errorMessage = '<div class="error-message">Database error during insert preparation: ' . $conn->error . '</div>';
            $_SESSION['guest_status_message'] = $errorMessage;
            $_SESSION['register_message'] = $errorMessage; // Specific message
            header("Location: my_bookings.php"); // Redirect back to my_bookings
            exit();
        }
    }
}

// If form was not submitted or direct access, redirect to my_bookings.php
header("Location: my_bookings.php");
exit();

// Close the database connection
// This block is largely unreachable now due to exits above, but good practice if logic changes
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
