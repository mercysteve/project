<?php
// guest_login.php - Guest Login Form and Processing (UPDATED FOR MY_BOOKINGS INTEGRATION)

session_start(); // Start the session

// Enable full PHP error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration file
require_once 'db_config.php';
// Include common functions file
require_once 'functions.php';

// If guest is already logged in, redirect to their bookings dashboard
// This prevents direct access to login.php if already authenticated
if (isset($_SESSION['guest_loggedin']) && $_SESSION['guest_loggedin'] === true) {
    header("Location: my_bookings.php");
    exit();
}

// Process login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = sanitize_input($conn, $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $_SESSION['guest_status_message'] = '<div class="error-message">Both email and password are required.</div>';
        $_SESSION['login_message'] = '<div class="error-message">Both email and password are required.</div>'; // Specific message for login form
        header("Location: my_bookings.php"); // Redirect back to my_bookings
        exit();
    } else {
        $sql = "SELECT id, full_name, email, phone_number, password_hash FROM guests WHERE email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($guestId, $fullName, $guestEmail, $phoneNumber, $hashedPassword);
                $stmt->fetch();

                if (password_verify($password, $hashedPassword)) {
                    // Password is correct, set guest session variables
                    $_SESSION['guest_loggedin'] = true;
                    $_SESSION['guest_id'] = $guestId;
                    $_SESSION['guest_full_name'] = $fullName;
                    $_SESSION['guest_email'] = $guestEmail;
                    $_SESSION['guest_phone_number'] = $phoneNumber;

                    // Check for and process pending booking
                    if (isset($_SESSION['pending_booking']) && !empty($_SESSION['pending_booking'])) {
                        $pendingBooking = $_SESSION['pending_booking'];
                        unset($_SESSION['pending_booking']); // Clear pending booking from session

                        // Update the email/name/phone in the pending booking to match the logged-in guest
                        $pendingBooking['name'] = $fullName;
                        $pendingBooking['email'] = $guestEmail;
                        $pendingBooking['phone'] = $phoneNumber;

                        // Insert the pending booking into the database with the logged-in guest's ID
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
                                $guestId // Attach the logged-in guest's ID
                            );
                            if ($stmtBooking->execute()) {
                                $_SESSION['guest_status_message'] = '<div class="success-message">Login successful and your booking has been confirmed!</div>';
                            } else {
                                $_SESSION['guest_status_message'] = '<div class="error-message">Login successful, but there was an error confirming your booking: ' . $stmtBooking->error . '</div>';
                            }
                            $stmtBooking->close();
                        } else {
                            $_SESSION['guest_status_message'] = '<div class="error-message">Login successful, but database error confirming booking: ' . $conn->error . '</div>';
                        }
                    } else {
                        $_SESSION['guest_status_message'] = '<div class="success-message">Login successful! Welcome back!</div>';
                    }
                    header("Location: my_bookings.php"); // Redirect to my_bookings.php
                    exit();
                } else {
                    $_SESSION['guest_status_message'] = '<div class="error-message">Invalid email or password.</div>';
                    $_SESSION['login_message'] = '<div class="error-message">Invalid email or password.</div>'; // Specific message
                    header("Location: my_bookings.php"); // Redirect back to my_bookings
                    exit();
                }
            } else {
                $_SESSION['guest_status_message'] = '<div class="error-message">Invalid email or password.</div>';
                $_SESSION['login_message'] = '<div class="error-message">Invalid email or password.</div>'; // Specific message
                header("Location: my_bookings.php"); // Redirect back to my_bookings
                exit();
            }
            $stmt->close();
        } else {
            $_SESSION['guest_status_message'] = '<div class="error-message">Database error during login preparation: ' . $conn->error . '</div>';
            $_SESSION['login_message'] = '<div class="error-message">Database error during login preparation: ' . $conn->error . '</div>'; // Specific message
            header("Location: my_bookings.php"); // Redirect back to my_bookings
            exit();
        }
    }
}

// If form was not submitted or direct access, redirect to my_bookings.php
header("Location: my_bookings.php");
exit();

// Close database connection (only if it hasn't been closed by an earlier exit())
// This block is largely unreachable now due to exits above, but good practice if logic changes
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
