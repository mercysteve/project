<?php
// confirm_booking_guest.php - Page for non-logged-in guests to register/login after booking submission

session_start(); // Start the session

// Enable full PHP error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include common functions (for styling consistency)
require_once 'functions.php'; // Ensure functions.php is accessible

$message = '';

// Check if there's a pending booking in the session
if (!isset($_SESSION['pending_booking']) || empty($_SESSION['pending_booking'])) {
    // If no pending booking, redirect them to the home page or a message page
    $_SESSION['status_message_index'] = '<div class="error-message">No booking details found. Please try booking again.</div>';
    header("Location: index.php");
    exit();
}

// Display booking summary if available
$pendingBooking = $_SESSION['pending_booking'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Your Booking - Supremacy Hotel</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cinzel', serif;
            background-color: #121212;
            color: #f0e6c4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .container {
            background-color: #1e1e1e;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
            padding: 30px;
            width: 100%;
            max-width: 600px;
            text-align: center;
        }
        h1 {
            font-size: 28px;
            color: #d4af37;
            margin-bottom: 20px;
        }
        .booking-summary {
            background-color: #2a2a2a;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: left;
        }
        .booking-summary h2 {
            color: #f0e6c4;
            margin-bottom: 15px;
            font-size: 22px;
            text-align: center;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }
        .booking-summary p {
            margin-bottom: 8px;
            font-size: 16px;
        }
        .booking-summary p strong {
            color: #d4af37;
            margin-right: 5px;
        }
        .action-links {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }
        .action-links a {
            padding: 12px;
            background-color: #d4af37;
            color: #121212;
            text-decoration: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.2s ease;
            display: block; /* Make links take full width */
        }
        .action-links a:hover {
            background-color: #c2a135;
            transform: translateY(-2px);
        }
        .message, .error-message, .success-message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
        }
        .error-message {
            background-color: #4a1a1a;
            color: #ff6b6b;
            border: 1px solid #ff6b6b;
        }
        .success-message {
            background-color: #1a4a1a;
            color: #2ecc71;
            border: 1px solid #2ecc71;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Your Booking Is Almost Complete!</h1>
        <div class="booking-summary">
            <h2>Booking Details:</h2>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($pendingBooking['name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($pendingBooking['email']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($pendingBooking['phone']); ?></p>
            <p><strong>Check-in:</strong> <?php echo htmlspecialchars($pendingBooking['checkin']); ?></p>
            <p><strong>Check-out:</strong> <?php echo htmlspecialchars($pendingBooking['checkout']); ?></p>
            <p><strong>Guests:</strong> <?php echo htmlspecialchars($pendingBooking['guests']); ?></p>
            <p><strong>Room Type:</strong> <?php echo htmlspecialchars($pendingBooking['roomType']); ?></p>
            <p><strong>Nights:</strong> <?php echo htmlspecialchars($pendingBooking['nights']); ?></p>
            <p><strong>Total:</strong> KES <?php echo number_format($pendingBooking['totalAmount'], 2); ?></p>
        </div>
        <p>To confirm and manage your booking, please:</p>
        <div class="action-links">
            <a href="guest_register.php">Register Now</a>
            <a href="guest_login.php">Login to Your Account</a>
        </div>
        <p style="margin-top: 20px; font-size: 14px; color: #aaa;">
            You can also <a href="index.php" style="color: #d4af37;">go back to the homepage</a>
            if you wish to abandon this booking.
        </p>
    </div>
</body>
</html>
