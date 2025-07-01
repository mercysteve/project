<?php
// my_bookings.php - Personal dashboard for logged-in guests, or login/register forms for not logged-in guests

session_start(); // Start the session

// Enable full PHP error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration and common functions
require_once 'db_config.php';
require_once 'functions.php'; // For sanitize_input

$isGuestLoggedIn = (isset($_SESSION['guest_loggedin']) && $_SESSION['guest_loggedin'] === true && isset($_SESSION['guest_id']));
$guestId = $isGuestLoggedIn ? $_SESSION['guest_id'] : null;
$guestFullName = $isGuestLoggedIn ? htmlspecialchars($_SESSION['guest_full_name']) : '';

$message = ''; // Initialize message variable for status feedback
$activeBookings = []; // Array to store active/upcoming bookings
$pastBookings = []; // Array to store booked out/cancelled bookings
$totalGuestBookings = 0; // Total of ALL bookings for the guest
$totalGuestRevenue = 0; // Total revenue from ALL bookings for the guest

// Retrieve any status message from session (e.g., from login/register/process_booking/guest_cancel/guest_book_out)
if (isset($_SESSION['guest_status_message'])) {
    $statusOutput = $_SESSION['guest_status_message'];
    if (strpos($statusOutput, '<div class="') === 0) {
        $message = $statusOutput;
    } else {
        $message = '<div class="message">' . htmlspecialchars($statusOutput) . '</div>';
    }
    unset($_SESSION['guest_status_message']); // Clear message after displaying
}

// ----------------------------------------------------------------------
// Fetch Bookings ONLY IF Guest is Logged In:
// ----------------------------------------------------------------------
if ($isGuestLoggedIn) {
    // Fetch all bookings for the guest
    $sqlSelect = "SELECT id, full_name, email, phone_number, check_in_date, check_out_date, nights, num_guests, room_type, total_amount, submission_time, status, book_out_time FROM bookings WHERE guest_id = ? ORDER BY submission_time DESC";

    if ($stmt = $conn->prepare($sqlSelect)) {
        $stmt->bind_param("i", $guestId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $totalGuestBookings++; // Increment total count
                $totalGuestRevenue += $row['total_amount']; // Sum up total revenue

                // Categorize bookings based on status
                if ($row['status'] === 'Active') {
                    $activeBookings[] = $row;
                } else {
                    $pastBookings[] = $row;
                }
            }
        }
        $result->free();
        $stmt->close();
    } else {
        $message .= '<div class="error-message">Database error retrieving bookings: ' . $conn->error . '</div>';
    }
}

// Close the database connection (important if not needed further)
if ($conn && $conn->ping()) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isGuestLoggedIn ? 'My Bookings Dashboard' : 'Login / Register for My Bookings'; ?> - Supremacy Hotel</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* General body styling for dark theme and Cinzel font */
        body {
            font-family: 'Roboto', sans-serif; /* Changed default font for better readability in content */
            background-color: #121212;
            color: #f0e6c4;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        /* Guest Dashboard Header */
        .guest-header {
            width: 100%;
            max-width: 1000px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            margin-bottom: 20px;
            color: #f0e6c4;
            font-size: 1.1em;
            flex-wrap: wrap; /* Allow wrapping on small screens */
        }
        .guest-header h1 {
            font-family: 'Cinzel', serif;
            font-size: 2.2em; /* Adjusted for guest dashboard */
            color: #d4af37;
            margin: 0;
            text-shadow: 0 0 5px rgba(212, 175, 55, 0.3);
            flex: 1; /* Allow it to take available space */
        }
        .guest-header .welcome-message {
            font-size: 1.2em;
            font-weight: bold;
            color: #f5e8c7;
            margin-right: 20px; /* Space from logout button */
        }
        .guest-header .logout-btn {
            padding: 8px 15px;
            background-color: #dc3545;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        .guest-header .logout-btn:hover {
            background-color: #c82333;
        }

        /* Dashboard Layout Grid */
        .dashboard-layout {
            display: grid;
            grid-template-columns: 1fr; /* Default to single column */
            gap: 25px;
            width: 100%;
            max-width: 1000px; /* Max width for content */
            margin-top: 20px;
        }

        /* Dashboard Card Styling */
        .dashboard-card {
            background-color: #1e1e1e;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.6);
            padding: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.8);
        }

        .dashboard-card h2 {
            font-family: 'Cinzel', serif;
            font-size: 1.8em;
            color: #d4af37;
            margin-bottom: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(212, 175, 55, 0.3);
            padding-bottom: 10px;
        }

        /* Summary Card Specifics */
        .summary-card {
            display: flex;
            flex-direction: column; /* Stack on mobile */
            gap: 20px;
            text-align: center;
            background-color: #2a2a2a;
            border: 1px solid #d4af37;
        }
        .summary-card .summary-item p {
            font-size: 2em;
            font-weight: bold;
            color: #f5e8c7;
            margin-bottom: 5px;
        }
        .summary-card .summary-item span {
            font-size: 0.9em;
            color: #aaa;
        }

        /* Booking List Cards */
        .bookings-list-card {
            background-color: #262626;
            border: 1px solid #c2a135;
        }

        .booking-card {
            background-color: #333333; /* Darker background for individual booking cards */
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px; /* Space between booking cards */
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); /* Responsive grid for details */
            gap: 15px;
            align-items: start;
            transition: background-color 0.3s ease;
        }
        .booking-card:hover {
            background-color: #3a3a3a;
        }

        .booking-card .booking-detail {
            display: flex;
            align-items: center;
            font-size: 0.95em;
            color: #f0e6c4;
        }
        .booking-card .booking-detail i {
            margin-right: 10px;
            color: #d4af37;
            font-size: 1.1em;
            width: 20px; /* Fixed width for icon alignment */
            text-align: center;
        }
        .booking-card .booking-detail strong {
            color: #f5e8c7;
            margin-right: 5px;
        }

        .booking-card .booking-header {
            grid-column: 1 / -1; /* Header spans all columns */
            text-align: center;
            border-bottom: 1px dashed rgba(212, 175, 55, 0.4);
            padding-bottom: 10px;
            margin-bottom: 10px;
            font-family: 'Cinzel', serif;
            font-size: 1.2em;
            color: #d4af37;
            font-weight: bold;
        }
        .booking-card .booking-header .status-indicator {
            font-size: 0.8em;
            padding: 4px 8px;
            border-radius: 5px;
            margin-left: 10px;
            font-weight: normal;
            vertical-align: middle;
        }
        .booking-card .booking-header .status-active { background-color: #28a745; color: white; }
        .booking-card .booking-header .status-booked-out { background-color: #6c757d; color: white; }
        .booking-card .booking-header .status-cancelled { background-color: #dc3545; color: white; }


        /* Action buttons for each booking card */
        .booking-card .booking-actions {
            grid-column: 1 / -1; /* Actions span all columns */
            text-align: center;
            margin-top: 10px;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .booking-card .booking-actions button {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85em;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        .booking-card .booking-actions .book-out-btn {
            background-color: #28a745; /* Green for book out */
            color: #fff;
        }
        .booking-card .booking-actions .book-out-btn:hover {
            background-color: #218838;
        }
        .booking-card .booking-actions .cancel-btn {
            background-color: #e74c3c;
            color: #fff;
        }
        .booking-card .booking-actions .cancel-btn:hover {
            background-color: #c0392b;
        }


        /* No bookings message */
        .no-bookings {
            text-align: center;
            padding: 40px;
            color: #aaa;
            font-size: 1.2em;
            font-style: italic;
        }

        /* Message box for status feedback */
        .message, .error-message, .success-message, .info-message {
            background-color: #2a2a2a;
            color: #f0e6c4;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #d4af37; /* Default border */
            width: 100%;
            max-width: 1000px;
            box-sizing: border-box;
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
        .info-message {
            background-color: #1a3a4a;
            color: #87CEEB;
            border: 1px solid #87CEEB;
        }


        /* --- Styles for Login/Register Section --- */
        .tempting-section {
            background: linear-gradient(to right, #d4af37, #f0e68c); /* Gold gradient */
            color: #121212;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.4);
            animation: bounceIn 1s ease-out;
            width: 100%;
            max-width: 900px;
        }
        .tempting-section h2 {
            font-family: 'Cinzel', serif;
            font-size: 2.8em;
            margin-bottom: 20px;
            color: #121212;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
            line-height: 1.2;
        }
        .tempting-section .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .tempting-section .benefit-item {
            background-color: rgba(255, 255, 255, 0.1); /* Subtle white overlay */
            border-radius: 10px;
            padding: 20px;
            backdrop-filter: blur(5px); /* Frosted glass effect */
            -webkit-backdrop-filter: blur(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, background-color 0.3s ease;
        }
        .tempting-section .benefit-item:hover {
            transform: translateY(-5px);
            background-color: rgba(255, 255, 255, 0.2);
        }
        .tempting-section .benefit-item i {
            font-size: 2.5em;
            color: #121212;
            margin-bottom: 10px;
        }
        .tempting-section .benefit-item h3 {
            font-family: 'Roboto', sans-serif;
            font-size: 1.2em;
            color: #121212;
            margin-bottom: 5px;
        }
        .tempting-section .benefit-item p {
            font-size: 0.9em;
            color: #333;
        }

        /* Animation for tempting section */
        @keyframes bounceIn {
            0% { transform: scale(0.8); opacity: 0; }
            50% { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(1); }
        }

        .auth-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 30px;
            max-width: 800px;
            background-color: #1e1e1e;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.6);
            margin-top: 50px;
        }
        .auth-section h2 {
            font-family: 'Cinzel', serif;
            font-size: 2em;
            color: #d4af37;
            margin-bottom: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(212, 175, 55, 0.3);
            padding-bottom: 10px;
            width: 100%;
        }

        .auth-tabs {
            display: flex;
            width: 100%;
            margin-bottom: 25px;
            border-bottom: 1px solid #333;
        }
        .auth-tab-btn {
            flex: 1;
            padding: 15px;
            border: none;
            background-color: transparent;
            color: #f0e6c4;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .auth-tab-btn.active {
            background-color: #d4af37;
            color: #121212;
            box-shadow: 0 -5px 15px rgba(212, 175, 55, 0.4);
            transform: translateY(-5px);
            position: relative;
            z-index: 1;
        }
        .auth-tab-btn:not(.active):hover {
            background-color: #2a2a2a;
        }

        .auth-form-content {
            width: 100%;
        }
        .auth-form {
            display: none; /* Hidden by default */
            padding: 20px 0;
        }
        .auth-form.active {
            display: block;
        }
        .auth-form .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        .auth-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .auth-form input[type="text"],
        .auth-form input[type="email"],
        .auth-form input[type="password"],
        .auth-form input[type="tel"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #333;
            border-radius: 5px;
            background-color: #2a2a2a;
            color: #f0e6c4;
            box-sizing: border-box;
        }
        .auth-form input[type="submit"] {
            width: 100%;
            padding: 12px;
            background-color: #d4af37;
            color: #121212;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin-top: 20px;
        }
        .auth-form input[type="submit"]:hover {
            background-color: #c2a135;
            transform: translateY(-2px);
        }
        
        /* Message styling within forms */
        .auth-form .message, .auth-form .error-message, .auth-form .success-message {
            margin-top: 10px;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 0.9em;
            background-color: #2a2a2a;
            color: #f0e6c4;
            border: 1px solid #d4af37;
        }
        .auth-form .error-message {
            background-color: #4a1a1a;
            color: #ff6b6b;
            border: 1px solid #ff6b6b;
        }


        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .guest-header {
                flex-direction: column;
                align-items: flex-start; /* Align welcome message to left */
                text-align: left;
                gap: 10px;
            }
            .guest-header h1 {
                font-size: 1.8em;
            }
            .guest-header .logout-btn {
                width: 100%; /* Full width button */
            }
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
            .dashboard-card {
                padding: 20px;
            }
            .summary-card {
                flex-direction: column; /* Stack summary items vertically */
            }
            .booking-card {
                grid-template-columns: 1fr; /* Stack booking details vertically */
            }
            .booking-card .booking-detail {
                justify-content: space-between; /* Space out label and value */
            }
            .booking-card .booking-detail strong {
                flex: 1; /* Allow strong text to take space */
            }
            .booking-card .booking-actions {
                display: flex;
                justify-content: center;
            }

            .auth-section {
                padding: 20px;
            }
            .auth-tab-btn {
                font-size: 1em;
                padding: 12px;
            }

            .tempting-section {
                padding: 30px 20px;
            }
            .tempting-section h2 {
                font-size: 2em;
            }
            .tempting-section .benefits-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (min-width: 768px) {
            .dashboard-layout {
                grid-template-columns: 1fr; /* Still single column as all content is within 1 or 2 main cards */
            }
            .summary-card {
                flex-direction: row; /* Horizontal for larger screens */
                justify-content: space-around;
            }
        }
    </style>
</head>
<body>
    <?php if ($isGuestLoggedIn): ?>
        <div class="guest-header">
            <h1>My Bookings</h1>
            <span class="welcome-message">Welcome, <?php echo $guestFullName; ?>!</span>
            <form action="guest_logout.php" method="post">
                <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
            </form>
            <button class="logout-btn" style="background-color: #007bff;" onclick="window.location.href='index.php'"><i class="fas fa-home"></i> Back to Home</button>
        </div>

        <div class="message-container" style="width: 100%; max-width: 1000px;">
            <?php if (!empty($message)): ?>
                <?php echo $message; ?>
            <?php endif; ?>
        </div>

        <div class="dashboard-layout">
            <!-- Guest Summary Card -->
            <div class="dashboard-card summary-card">
                <div class="summary-item">
                    <h2><i class="fas fa-calendar-check"></i> Total Bookings</h2>
                    <p><?php echo $totalGuestBookings; ?></p>
                    <span>Across all states</span>
                </div>
                <div class="summary-item">
                    <h2><i class="fas fa-money-check-alt"></i> Total Spent</h2>
                    <p>KES <?php echo number_format($totalGuestRevenue, 2); ?></p>
                    <span>Across All Bookings</span>
                </div>
                <div class="summary-item">
                    <h2><i class="fas fa-bookmark"></i> Active Bookings</h2>
                    <p><?php echo count($activeBookings); ?></p>
                    <span>Currently Scheduled</span>
                </div>
            </div>

            <!-- Active Bookings List Card -->
            <div class="dashboard-card bookings-list-card">
                <h2><i class="fas fa-list-alt"></i> Active / Upcoming Bookings</h2>
                <?php if (empty($activeBookings)): ?>
                    <p class="no-bookings">You have no active or upcoming bookings. <br>Go to the <a href="index.php" style="color: #d4af37; text-decoration: none;">homepage</a> to make one!</p>
                <?php else: ?>
                    <?php foreach ($activeBookings as $booking): ?>
                        <div class="booking-card">
                            <div class="booking-header">
                                Booking ID: <?php echo htmlspecialchars($booking['id']); ?> (<?php echo htmlspecialchars($booking['room_type']); ?>)
                                <span class="status-indicator status-<?php echo strtolower(str_replace(' ', '-', $booking['status'])); ?>">
                                    <?php echo htmlspecialchars($booking['status']); ?>
                                </span>
                            </div>
                            <div class="booking-detail"><i class="fas fa-calendar-alt"></i> <strong>Check-in:</strong> <?php echo htmlspecialchars($booking['check_in_date']); ?></div>
                            <div class="booking-detail"><i class="fas fa-calendar-check"></i> <strong>Check-out:</strong> <?php echo htmlspecialchars($booking['check_out_date']); ?></div>
                            <div class="booking-detail"><i class="fas fa-moon"></i> <strong>Nights:</strong> <?php echo htmlspecialchars($booking['nights']); ?></div>
                            <div class="booking-detail"><i class="fas fa-users"></i> <strong>Guests:</strong> <?php echo htmlspecialchars($booking['num_guests']); ?></div>
                            <div class="booking-detail"><i class="fas fa-money-bill-wave"></i> <strong>Total:</strong> KES <?php echo number_format($booking['total_amount'], 2); ?></div>
                            <div class="booking-detail"><i class="fas fa-clock"></i> <strong>Submitted:</strong> <?php echo htmlspecialchars($booking['submission_time']); ?></div>
                            <div class="booking-actions">
                                <form method="post" action="guest_book_out_booking.php" onsubmit="return confirm('Are you sure you want to book out of this reservation (ID: <?php echo htmlspecialchars($booking['id']); ?>)?');" style="display:inline-block;">
                                    <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars($booking['id']); ?>">
                                    <button type="submit" class="book-out-btn"><i class="fas fa-sign-out-alt"></i> Book Out Now</button>
                                </form>
                                <form method="post" action="guest_cancel_booking.php" onsubmit="return confirm('Are you sure you want to cancel this booking (ID: <?php echo htmlspecialchars($booking['id']); ?>)?');" style="display:inline-block;">
                                    <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars($booking['id']); ?>">
                                    <button type="submit" class="cancel-btn"><i class="fas fa-times-circle"></i> Cancel Booking</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Past / Completed Bookings List Card -->
            <div class="dashboard-card bookings-list-card">
                <h2><i class="fas fa-history"></i> Past / Completed Bookings</h2>
                <?php if (empty($pastBookings)): ?>
                    <p class="no-bookings">You have no past or completed bookings yet.</p>
                <?php else: ?>
                    <?php foreach ($pastBookings as $booking): ?>
                        <div class="booking-card">
                            <div class="booking-header">
                                Booking ID: <?php echo htmlspecialchars($booking['id']); ?> (<?php echo htmlspecialchars($booking['room_type']); ?>)
                                <span class="status-indicator status-<?php echo strtolower(str_replace(' ', '-', $booking['status'])); ?>">
                                    <?php echo htmlspecialchars($booking['status']); ?>
                                </span>
                            </div>
                            <div class="booking-detail"><i class="fas fa-calendar-alt"></i> <strong>Check-in:</strong> <?php echo htmlspecialchars($booking['check_in_date']); ?></div>
                            <div class="booking-detail"><i class="fas fa-calendar-check"></i> <strong>Check-out:</strong> <?php echo htmlspecialchars($booking['check_out_date']); ?></div>
                            <div class="booking-detail"><i class="fas fa-moon"></i> <strong>Nights:</strong> <?php echo htmlspecialchars($booking['nights']); ?></div>
                            <div class="booking-detail"><i class="fas fa-users"></i> <strong>Guests:</strong> <?php echo htmlspecialchars($booking['num_guests']); ?></div>
                            <div class="booking-detail"><i class="fas fa-money-bill-wave"></i> <strong>Total:</strong> KES <?php echo number_format($booking['total_amount'], 2); ?></div>
                            <div class="booking-detail"><i class="fas fa-clock"></i> <strong>Submitted:</strong> <?php echo htmlspecialchars($booking['submission_time']); ?></div>
                            <?php if ($booking['status'] === 'Booked Out' && !empty($booking['book_out_time'])): ?>
                                <div class="booking-detail" style="grid-column: 1 / -1;"><i class="fas fa-sign-out-alt"></i> <strong>Booked Out Time:</strong> <?php echo htmlspecialchars($booking['book_out_time']); ?></div>
                            <?php endif; ?>
                            <!-- No actions for past/completed bookings -->
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Not Logged In: Display Tempting Section + Login/Register Forms -->
        <div class="tempting-section">
            <h2>Unlock Your Hotel Booking History!</h2>
            <p style="font-size: 1.1em; margin-top: 15px; color: #333;">
                Log in or register now to seamlessly manage all your stays at Supremacy Hotel.
            </p>
            <div class="benefits-grid">
                <div class="benefit-item">
                    <i class="fas fa-history"></i>
                    <h3>View Past Bookings</h3>
                    <p>Access details of all your previous reservations.</p>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-cogs"></i>
                    <h3>Manage Current Stays</h3>
                    <p>Modify or cancel upcoming bookings with ease.</p>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-bolt"></i>
                    <h3>Faster Future Bookings</h3>
                    <p>Your details will be pre-filled for quicker reservations.</p>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-envelope-open-text"></i>
                    <h3>Exclusive Offers</h3>
                    <p>Receive personalized deals and promotions.</p>
                </div>
            </div>
        </div>

        <div class="auth-section">
            <h2>Get Started</h2>
            <?php if (!empty($message)): ?>
                <?php echo $message; ?>
            <?php endif; ?>

            <div class="auth-tabs">
                <button class="auth-tab-btn active" id="loginTabBtn" onclick="showAuthForm('login')">Login to Your Account</button>
                <button class="auth-tab-btn" id="registerTabBtn" onclick="showAuthForm('register')">Create New Account</button>
            </div>

            <div class="auth-form-content">
                <!-- Login Form -->
                <form id="loginForm" class="auth-form active" action="guest_login.php" method="POST">
                    <p style="text-align: center; margin-bottom: 20px; color: #aaa;">Login to view your existing bookings.</p>
                    <div class="form-group">
                        <label for="login_email">Email:</label>
                        <input type="email" id="login_email" name="email" required value="<?php echo htmlspecialchars($_SESSION['pending_booking']['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="login_password">Password:</label>
                        <input type="password" id="login_password" name="password" required>
                    </div>
                    <input type="submit" value="Login">
                    <?php
                        // Display any specific login error/success messages
                        if (isset($_SESSION['login_message'])) {
                            echo $_SESSION['login_message'];
                            unset($_SESSION['login_message']);
                        }
                    ?>
                </form>

                <!-- Registration Form -->
                <form id="registerForm" class="auth-form" action="guest_register.php" method="POST">
                    <p style="text-align: center; margin-bottom: 20px; color: #aaa;">Don't have an account? Register to save your booking!</p>
                    <div class="form-group">
                        <label for="register_full_name">Full Name:</label>
                        <input type="text" id="register_full_name" name="full_name" required value="<?php echo htmlspecialchars($_SESSION['pending_booking']['name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="register_email">Email:</label>
                        <input type="email" id="register_email" name="email" required value="<?php echo htmlspecialchars($_SESSION['pending_booking']['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="register_phone_number">Phone Number:</label>
                        <input type="tel" id="register_phone_number" name="phone_number" required value="<?php echo htmlspecialchars($_SESSION['pending_booking']['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="register_password">Password:</label>
                        <input type="password" id="register_password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="register_confirm_password">Confirm Password:</label>
                        <input type="password" id="register_confirm_password" name="confirm_password" required>
                    </div>
                    <input type="submit" value="Register">
                     <?php
                        // Display any specific registration error/success messages
                        if (isset($_SESSION['register_message'])) {
                            echo $_SESSION['register_message'];
                            unset($_SESSION['register_message']);
                        }
                    ?>
                </form>
            </div>
            <button class="logout-btn" style="background-color: #007bff; margin-top: 30px;" onclick="window.location.href='index.php'"><i class="fas fa-home"></i> Back to Homepage</button>
        </div>
    <?php endif; ?>

    <script>
        // Function to switch between login and registration forms
        function showAuthForm(formType) {
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            const loginTabBtn = document.getElementById('loginTabBtn');
            const registerTabBtn = document.getElementById('registerTabBtn');

            if (formType === 'login') {
                loginForm.classList.add('active');
                registerForm.classList.remove('active');
                loginTabBtn.classList.add('active');
                registerTabBtn.classList.remove('active');
            } else if (formType === 'register') {
                registerForm.classList.add('active');
                loginForm.classList.remove('active');
                registerTabBtn.classList.add('active');
                loginTabBtn.classList.remove('active');
            }
        }

        // Handle initial display based on which message is present (e.g., if a registration error occurred)
        document.addEventListener('DOMContentLoaded', function() {
            const loginMessage = `<?php echo addslashes(isset($_SESSION['login_message']) ? $_SESSION['login_message'] : ''); ?>`;
            const registerMessage = `<?php echo addslashes(isset($_SESSION['register_message']) ? $_SESSION['register_message'] : ''); ?>`;

            if (registerMessage.includes('error-message') || registerMessage.includes('success-message')) {
                showAuthForm('register');
            } else if (loginMessage.includes('error-message') || loginMessage.includes('success-message')) {
                showAuthForm('login');
            } else {
                // Default to login form if no specific message
                showAuthForm('login');
            }
        });
    </script>
</body>
</html>
