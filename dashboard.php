<?php
// dashboard.php - Hotel Bookings Dashboard (Enhanced UI, Room Occupancy, Filterable Active Bookings, and Booked Out Guests)

session_start();

// Authentication check: If user is not logged in, redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['status_message'] = '<div class="error-message">Please log in to access the dashboard.</div>';
    header("Location: login.php");
    exit();
}

// Enable full PHP error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration file (establishes $conn)
require_once 'db_config.php';

// Include common functions file (sanitize_input is defined here)
require_once 'functions.php';

$message = ''; // Initialize an empty message variable for status feedback
$activeBookings = []; // Array to store active, filtered booking data
$bookedOutBookings = []; // Array to store booked out guests data
$totalActiveBookings = 0;
$totalActiveRevenue = 0;
$totalBookedOutGuests = 0; // New: count of unique booked out guests

// Initialize room occupancy data for all room types (based on *active, filtered* bookings)
$roomOccupancy = [
    'Standard Room' => ['bookings_count' => 0, 'guests_count' => 0],
    'Spacious Room' => ['bookings_count' => 0, 'guests_count' => 0],
    'Master Ensuite' => ['bookings_count' => 0, 'guests_count' => 0]
];

// Get filter parameters from GET request
$filterStartDate = sanitize_input($conn, $_GET['startDate'] ?? '');
$filterEndDate = sanitize_input($conn, $_GET['endDate'] ?? '');

// Retrieve any status message that might have been set in the session
if (isset($_SESSION['status_message'])) {
    $statusOutput = $_SESSION['status_message'];
    if (strpos($statusOutput, '<div class="') === 0) {
        $message = $statusOutput;
    } else {
        $message = '<div class="message">' . htmlspecialchars($statusOutput) . '</div>';
    }
    unset($_SESSION['status_message']); // Clear the message after displaying it once
}

// ----------------------------------------------------------------------
// Fetch ACTIVE Bookings with Optional Date Filters:
// ----------------------------------------------------------------------
$sqlActiveBookings = "SELECT id, full_name, email, phone_number, check_in_date, check_out_date, nights, num_guests, room_type, total_amount, submission_time, status FROM bookings WHERE status = 'Active'";
$activeWhereClauses = [];
$activeParamTypes = "";
$activeBindParams = [];

// Add date filters if provided for active bookings
if (!empty($filterStartDate)) {
    $activeWhereClauses[] = "check_in_date >= ?";
    $activeParamTypes .= "s";
    $activeBindParams[] = &$filterStartDate;
}
if (!empty($filterEndDate)) {
    $activeWhereClauses[] = "check_out_date <= ?";
    $activeParamTypes .= "s";
    $activeBindParams[] = &$filterEndDate;
}

if (!empty($activeWhereClauses)) {
    $sqlActiveBookings .= " AND " . implode(" AND ", $activeWhereClauses);
}
$sqlActiveBookings .= " ORDER BY submission_time DESC";


if (!empty($activeWhereClauses)) {
    if ($stmtActive = $conn->prepare($sqlActiveBookings)) {
        call_user_func_array([$stmtActive, 'bind_param'], array_merge([$activeParamTypes], $activeBindParams));
        $stmtActive->execute();
        $resultActive = $stmtActive->get_result();
    } else {
        $message .= '<div class="error-message">Database error preparing active bookings filter: ' . $conn->error . '</div>';
        $resultActive = false;
    }
} else {
    $resultActive = $conn->query($sqlActiveBookings);
}

if ($resultActive) {
    if ($resultActive->num_rows > 0) {
        while ($row = $resultActive->fetch_assoc()) {
            $activeBookings[] = $row;
            $totalActiveRevenue += $row['total_amount'];
            
            if (isset($roomOccupancy[$row['room_type']])) {
                $roomOccupancy[$row['room_type']]['bookings_count']++;
                $roomOccupancy[$row['room_type']]['guests_count'] += $row['num_guests'];
            }
        }
        $totalActiveBookings = count($activeBookings);
    } else {
        $message .= '<div class="info-message">No active bookings found for the selected date range.</div>';
    }
    $resultActive->free();
    if (isset($stmtActive)) { $stmtActive->close(); }
} else {
    if (empty($message)) {
        $message .= '<div class="error-message">Error: Could not retrieve active bookings. ' . $conn->error . '</div>';
    }
}

// ----------------------------------------------------------------------
// Fetch BOOKED OUT Bookings:
// ----------------------------------------------------------------------
$sqlBookedOut = "SELECT id, full_name, email, phone_number, check_in_date, check_out_date, nights, num_guests, room_type, total_amount, submission_time, status, book_out_time FROM bookings WHERE status = 'Booked Out' ORDER BY book_out_time DESC";

if ($resultBookedOut = $conn->query($sqlBookedOut)) {
    if ($resultBookedOut->num_rows > 0) {
        while ($row = $resultBookedOut->fetch_assoc()) {
            $bookedOutBookings[] = $row;
        }
        $totalBookedOutGuests = count($bookedOutBookings);
    } else {
        // No specific message if no booked-out guests, as it's a separate section.
    }
    $resultBookedOut->free();
} else {
    $message .= '<div class="error-message">Error: Could not retrieve booked out guests. ' . $conn->error . '</div>';
}


// Close the database connection
$conn->close();

// Encode active bookings data to JSON for JavaScript (guest details dropdown)
$bookingsJson = json_encode($activeBookings);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Bookings Dashboard - Supremacy Hotel</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* General body styling for dark theme and Cinzel font */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #121212;
            color: #f0e6c4;
            margin: 0;
            padding: 0; /* Remove body padding as layout will handle it */
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        /* Main wrapper for header, sidebar, and content */
        .header-container {
            width: 100%;
            background-color: #1a1a1a;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
            padding: 10px 20px;
            box-sizing: border-box;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .admin-header {
            width: 100%; /* Make header full width of its container */
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #f0e6c4;
            font-size: 1.1em;
            max-width: 1200px; /* Constrain internal header content */
            margin: 0 auto; /* Center internal header content */
        }
        .admin-header .logout-btn {
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
        .admin-header .logout-btn:hover {
            background-color: #c82333;
        }

        /* Layout for sidebar and main content */
        .main-wrapper {
            display: flex;
            flex: 1; /* Allows main content to grow and take available height */
            width: 100%;
            max-width: 1200px; /* Max width for the entire dashboard content */
            margin: 20px auto; /* Center the main content area */
            border-radius: 12px;
            overflow: hidden; /* Contains children, especially for rounded corners */
            background-color: #1e1e1e; /* Match main card background */
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.7);
        }

        /* Sidebar Styling */
        .sidebar {
            width: 250px; /* Fixed width for the sidebar */
            background-color: #2a2a2a; /* Darker background for sidebar */
            padding: 20px 0;
            border-right: 1px solid #333; /* Separator from content */
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.4);
            display: flex;
            flex-direction: column;
            flex-shrink: 0; /* Prevent sidebar from shrinking */
        }
        .sidebar h2 {
            font-family: 'Cinzel', serif;
            color: #d4af37;
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(212, 175, 55, 0.3);
            font-size: 1.5em;
            padding: 0 15px;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1; /* Allows menu list to grow */
        }
        .sidebar ul li a {
            display: block;
            padding: 15px 25px;
            color: #f0e6c4;
            text-decoration: none;
            font-size: 1.05em;
            border-bottom: 1px solid #333;
            transition: background-color 0.3s ease, color 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .sidebar ul li:last-child a {
            border-bottom: none;
        }
        .sidebar ul li a:hover,
        .sidebar ul li a.active-menu-item {
            background-color: #d4af37;
            color: #121212;
            font-weight: bold;
            box-shadow: inset 5px 0 15px rgba(0, 0, 0, 0.2);
        }
        .sidebar ul li a i {
            color: inherit; /* Icon color inherits from link */
        }
        
        /* Main Content Area */
        .content-area {
            flex: 1; /* Takes remaining space */
            padding: 25px;
            overflow-y: auto; /* Enable scrolling for content if it overflows */
            display: flex; /* Use flexbox for internal layout of cards */
            flex-direction: column;
            gap: 25px; /* Spacing between cards */
        }

        /* Individual Content Sections (Hidden/Shown by JS) */
        .content-section {
            display: none; /* Hidden by default */
            width: 100%;
        }
        .content-section.active {
            display: flex; /* Show as flex column */
            flex-direction: column;
            gap: 25px; /* Maintain gap between cards within a section */
        }

        /* Dashboard Card Styling (re-applied for flex layout within content-section) */
        .dashboard-card {
            background-color: #1a1a1a; /* Adjusted to match content area better */
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4); /* Lighter shadow for internal cards */
            padding: 25px;
            transition: none; /* Remove hover transform from top-level card */
        }
        /* No hover effect on dashboard-card as it's part of the static layout */
        .dashboard-card:hover {
            transform: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
        }

        .dashboard-card h2 {
            font-family: 'Cinzel', serif;
            font-size: 24px;
            color: #d4af37;
            margin-bottom: 15px;
            text-align: center;
            border-bottom: 1px solid rgba(212, 175, 55, 0.3);
            padding-bottom: 10px;
        }

        /* Specific Card Styles - ensure internal flex/grid properties remain */
        .summary-card {
            display: flex;
            flex-direction: row; /* Always row for summary */
            justify-content: space-around;
            gap: 20px;
            text-align: center;
            background-color: #2a2a2a;
            border: 1px solid #d4af37;
        }
        .summary-card .summary-item {
            flex: 1;
            padding: 10px;
        }
        .summary-card p {
            font-size: 1.8em;
            font-weight: bold;
            color: #f5e8c7;
            margin-bottom: 5px;
        }
        .summary-card span {
            font-size: 0.9em;
            color: #aaa;
        }

        .room-occupancy-card {
            background-color: #1a1a1a;
            border: 1px solid #d4af37;
            padding: 25px;
            overflow-x: auto;
        }
        .room-occupancy-card table {
            width: 100%;
            min-width: 400px;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.95em;
        }
        .room-occupancy-card th, .room-occupancy-card td {
            border: 1px solid #333;
            padding: 12px 15px;
            text-align: left;
            word-wrap: break-word;
        }
        .room-occupancy-card th {
            background-color: #333;
            color: #d4af37;
            font-weight: bold;
        }
        .room-occupancy-card tr:nth-child(even) {
            background-color: #222;
        }
        .room-occupancy-card tr:hover {
            background-color: #2a2a2a;
        }


        .guest-selector-card {
            background-color: #262626;
            border: 1px solid #c2a135;
        }
        .guest-selector-card select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #555;
            border-radius: 8px;
            background-color: #333;
            color: #f0e6c4;
            font-size: 1em;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23f0e6c4%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%00-13.2-6.2H18.2c-4.1%200-7.9%201.5-10.9%204.6-3.2%203-5.2%207.2-5.2%2011.6%200%204.4%202%208.7%205.2%2011.8l124.7%20124.8c3.1%203.1%207.4%205.1%2011.8%205.1s8.7-2%2011.8-5.1L287%2093c3.1-3.2%205.2-7.4%205.2-11.8%200-4.4-2-8.7-5.2-11.9z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 1em auto;
            cursor: pointer;
            transition: border-color 0.3s ease;
        }
        .guest-selector-card select:focus {
            outline: none;
            border-color: #f0e68c;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.3);
        }

        .selected-guest-details-card {
            background-color: #202020;
            border: 1px solid #f0e68c;
            min-height: 250px;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 20px;
        }
        .selected-guest-details-card #guestDetailsContent {
            width: 100%;
            text-align: left;
        }
        .selected-guest-details-card #guestDetailsContent h3 {
            font-family: 'Cinzel', serif;
            color: #d4af37;
            margin-bottom: 10px;
            font-size: 1.5em;
            text-align: center;
        }
        .selected-guest-details-card #guestDetailsContent p {
            margin-bottom: 8px;
            font-size: 1em;
            color: #f0e6c4;
            display: flex;
            align-items: center;
        }
        .selected-guest-details-card #guestDetailsContent p i {
            margin-right: 10px;
            color: #d4af37;
            font-size: 1.1em;
            width: 25px;
            text-align: center;
        }
        .selected-guest-details-card #guestDetailsContent p strong {
            color: #f5e8c7;
            margin-right: 5px;
        }
        .selected-guest-details-card #guestDetailsContent .no-selection {
            color: #aaa;
            font-style: italic;
        }

        /* All Bookings Filter Section */
        .all-bookings-filter-card {
            background-color: #262626;
            border: 1px solid #c2a135;
            padding: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            justify-content: center;
        }
        .all-bookings-filter-card label {
            font-weight: bold;
            color: #f0e6c4;
        }
        .all-bookings-filter-card input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #555;
            border-radius: 5px;
            background-color: #333;
            color: #f0e6c4;
            font-size: 0.95em;
        }
        .all-bookings-filter-card button {
            padding: 10px 20px;
            background-color: #d4af37;
            color: #121212;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .all-bookings-filter-card button:hover {
            background-color: #c2a135;
        }


        /* Active & Booked Out Bookings Table */
        .active-bookings-table-card, .booked-out-bookings-table-card {
            background-color: #1a1a1a;
            border: 1px solid #d4af37;
            padding: 25px;
            overflow-x: auto;
        }
        .active-bookings-table-card table, .booked-out-bookings-table-card table {
            width: 100%;
            min-width: 700px;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.9em;
        }
        .active-bookings-table-card th, .active-bookings-table-card td,
        .booked-out-bookings-table-card th, .booked-out-bookings-table-card td {
            border: 1px solid #333;
            padding: 12px 15px;
            text-align: left;
            word-wrap: break-word;
        }
        .active-bookings-table-card th, .booked-out-bookings-table-card th {
            background-color: #333;
            color: #d4af37;
            font-weight: bold;
        }
        .active-bookings-table-card tr:nth-child(even), .booked-out-bookings-table-card tr:nth-child(even) {
            background-color: #222;
        }
        .active-bookings-table-card tr:hover, .booked-out-bookings-table-card tr:hover {
            background-color: #2a2a2a;
        }
        .no-bookings {
            text-align: center;
            padding: 40px;
            color: #aaa;
            font-size: 1.2em;
        }


        /* Action Buttons (at bottom, outside main-wrapper) */
        .action-buttons {
            text-align: center;
            margin-top: 30px;
            margin-bottom: 20px; /* Space from bottom of page */
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
            width: 100%;
            max-width: 1200px;
        }
        .action-buttons button, .action-buttons a {
            padding: 12px 25px;
            background-color: #d4af37;
            color: #121212;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            white-space: nowrap;
        }
        .action-buttons button:hover, .action-buttons a:hover {
            background-color: #c2a135;
            transform: translateY(-2px);
        }

        /* Message box for status feedback */
        .message-container {
            width: 100%;
            max-width: 1200px;
            margin: 20px auto 0; /* Align with main-wrapper */
            padding: 0 20px; /* Internal padding */
            box-sizing: border-box;
        }
        .message, .error-message, .success-message, .info-message {
            background-color: #2a2a2a;
            color: #f0e6c4;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #d4af37;
        }
        .error-message { background-color: #4a1a1a; color: #ff6b6b; border: 1px solid #ff6b6b; }
        .success-message { background-color: #1a4a1a; color: #2ecc71; border: 1px solid #2ecc71; }
        .info-message { background-color: #1a3a4a; color: #87CEEB; border: 1px solid #87CEEB; }

        /* Responsive Table Styling for Mobile Devices */
        @media (max-width: 768px) {
            .main-wrapper {
                flex-direction: column; /* Stack sidebar and content */
                margin: 10px; /* Adjust margin for small screens */
                padding: 0;
            }
            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #333; /* Separator for stacked layout */
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
                padding: 10px 0;
            }
            .sidebar h2 {
                display: none; /* Hide sidebar title on small screens */
            }
            .sidebar ul {
                display: flex; /* Make menu items horizontal */
                flex-wrap: wrap;
                justify-content: center;
            }
            .sidebar ul li {
                flex: 1 1 auto; /* Allow items to grow/shrink */
                max-width: 50%; /* Max two items per row */
            }
            .sidebar ul li a {
                padding: 10px 15px;
                text-align: center;
                border-bottom: none;
                border-right: 1px solid #333; /* Vertical separator for menu items */
                font-size: 0.9em;
                justify-content: center; /* Center icon and text */
                flex-direction: column; /* Stack icon and text */
                gap: 5px;
            }
            .sidebar ul li:nth-child(2n) a { /* Remove right border for every second item (or adjust based on number of items) */
                border-right: none;
            }
            .sidebar ul li:last-child a {
                border-right: none;
            }

            .content-area {
                padding: 15px;
            }
            .admin-header {
                flex-direction: column;
                gap: 10px;
            }
            .active-bookings-table-card table, .booked-out-bookings-table-card table,
            .room-occupancy-card table {
                min-width: unset;
                /* Mobile table display adjustments for stacked rows */
                table, thead, tbody, th, td, tr { display: block; }
                thead tr { position: absolute; top: -9999px; left: -9999px; }
                tr { border: 1px solid #333; margin-bottom: 15px; border-radius: 8px; overflow: hidden; }
                td { border: none; border-bottom: 1px solid #333; position: relative; padding-left: 50%; text-align: right; }
                td:last-child { border-bottom: 0; }
                td:before { position: absolute; top: 0; left: 6px; width: 45%; padding-right: 10px; white-space: nowrap; text-align: left; font-weight: bold; color: #d4af37; }
            }
            /* Specific labels for active bookings table */
            .active-bookings-table-card td:nth-of-type(1):before { content: "Booking ID"; }
            .active-bookings-table-card td:nth-of-type(2):before { content: "Full Name"; }
            .active-bookings-table-card td:nth-of-type(3):before { content: "Email"; }
            .active-bookings-table-card td:nth-of-type(4):before { content: "Phone"; }
            .active-bookings-table-card td:nth-of-type(5):before { content: "Check-in"; }
            .active-bookings-table-card td:nth-of-type(6):before { content: "Check-out"; }
            .active-bookings-table-card td:nth-of-type(7):before { content: "Nights"; }
            .active-bookings-table-card td:nth-of-type(8):before { content: "Guests"; }
            .active-bookings-table-card td:nth-of-type(9):before { content: "Room Type"; }
            .active-bookings-table-card td:nth-of-type(10):before { content: "Total Amount"; }
            .active-bookings-table-card td:nth-of-type(11):before { content: "Submission Time"; }

            /* Specific labels for booked out guests table */
            .booked-out-bookings-table-card td:nth-of-type(1):before { content: "Booking ID"; }
            .booked-out-bookings-table-card td:nth-of-type(2):before { content: "Full Name"; }
            .booked-out-bookings-table-card td:nth-of-type(3):before { content: "Room Type"; }
            .booked-out-bookings-table-card td:nth-of-type(4):before { content: "Guests"; }
            .booked-out-bookings-table-card td:nth-of-type(5):before { content: "Check-in"; }
            .booked-out-bookings-table-card td:nth-of-type(6):before { content: "Check-out"; }
            .booked-out-bookings-table-card td:nth-of-type(7):before { content: "Booked Out Time"; }

            /* Specific labels for room occupancy table */
            .room-occupancy-card td:nth-of-type(1):before { content: "Room Type:"; }
            .room-occupancy-card td:nth-of-type(2):before { content: "Bookings:"; }
            .room-occupancy-card td:nth-of-type(3):before { content: "Guests:"; }

            .summary-card {
                flex-direction: column; /* Stack summary items vertically */
            }
            .all-bookings-filter-card {
                flex-direction: column;
            }
            .all-bookings-filter-card input[type="date"],
            .all-bookings-filter-card button {
                width: 100%;
            }
            .action-buttons {
                flex-direction: column;
            }
            .action-buttons button, .action-buttons a {
                width: 100%;
            }
        }

        /* Desktop Layout (min-width 768px) */
        @media (min-width: 768px) {
            .summary-card {
                flex-direction: row; /* Horizontal for larger screens */
                justify-content: space-around;
            }
        }
    </style>
</head>
<body>
    <div class="header-container">
        <div class="admin-header">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>!</span>
            <form action="logout.php" method="post">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>

    <div class="message-container">
        <?php if (!empty($message)): ?>
            <?php echo $message; ?>
        <?php endif; ?>
    </div>

    <div class="main-wrapper">
        <div class="sidebar">
            <h2>Dashboard Menu</h2>
            <ul>
                <li><a href="#" class="active-menu-item" onclick="showSection('overview-section', this)"><i class="fas fa-tachometer-alt"></i> Dashboard Overview</a></li>
                <li><a href="#" onclick="showSection('guest-management-section', this)"><i class="fas fa-users-cog"></i> Guest Management</a></li>
                <li><a href="#" onclick="showSection('active-bookings-section', this)"><i class="fas fa-concierge-bell"></i> Active Bookings</a></li>
                <li><a href="#" onclick="showSection('booked-out-section', this)"><i class="fas fa-bed"></i> Booked Out Guests</a></li>
            </ul>
        </div>

        <div class="content-area">
            <!-- 1. Dashboard Overview Section -->
            <div id="overview-section" class="content-section active">
                <div class="dashboard-card summary-card">
                    <div class="summary-item">
                        <h2><i class="fas fa-book"></i> Active Bookings</h2>
                        <p><?php echo $totalActiveBookings; ?></p>
                        <span>Current (Filtered)</span>
                    </div>
                    <div class="summary-item">
                        <h2><i class="fas fa-wallet"></i> Active Revenue</h2>
                        <p>KES <?php echo number_format($totalActiveRevenue, 2); ?></p>
                        <span>Estimated (Filtered)</span>
                    </div>
                    <div class="summary-item">
                        <h2><i class="fas fa-sign-out-alt"></i> Booked Out</h2>
                        <p><?php echo $totalBookedOutGuests; ?></p>
                        <span>Guests Checked Out</span>
                    </div>
                </div>

                <div class="dashboard-card room-occupancy-card">
                    <h2><i class="fas fa-hotel"></i> Room Occupancy Summary (Active, Filtered)</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Room Type</th>
                                <th>Number of Bookings</th>
                                <th>Total Guests Occupying</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roomOccupancy as $type => $data): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($type); ?></td>
                                    <td><?php echo htmlspecialchars($data['bookings_count']); ?></td>
                                    <td><?php echo htmlspecialchars($data['guests_count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 2. Guest Management Section -->
            <div id="guest-management-section" class="content-section">
                <div class="dashboard-card guest-selector-card">
                    <h2><i class="fas fa-users-cog"></i> View Guest Details (Active, Filtered)</h2>
                    <select id="guestSelect" onchange="displaySelectedGuestDetails()">
                        <option value="">-- Select a Guest --</option>
                        <?php
                        foreach ($activeBookings as $booking) { // Use active bookings for dropdown
                            $bookingId = $booking['id'];
                            $fullName = htmlspecialchars($booking['full_name']);
                            echo '<option value="' . $bookingId . '">' . $fullName . ' (Booking ID: ' . $bookingId . ')</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="dashboard-card selected-guest-details-card">
                    <div id="guestDetailsContent">
                        <p class="no-selection"><i class="fas fa-info-circle"></i> Select a guest from the dropdown to view their active booking details here.</p>
                    </div>
                </div>
            </div>

            <!-- 3. Active Bookings Section -->
            <div id="active-bookings-section" class="content-section">
                <div class="dashboard-card all-bookings-filter-card">
                    <h2><i class="fas fa-filter"></i> Filter Active Bookings by Date</h2>
                    <form method="GET" action="dashboard.php" id="filterForm">
                        <div>
                            <label for="startDate">Check-in from:</label>
                            <input type="date" id="startDate" name="startDate" value="<?php echo htmlspecialchars($filterStartDate); ?>">
                        </div>
                        <div>
                            <label for="endDate">Check-out to:</label>
                            <input type="date" id="endDate" name="endDate" value="<?php echo htmlspecialchars($filterEndDate); ?>">
                        </div>
                        <button type="submit"><i class="fas fa-search"></i> Apply Filter</button>
                        <?php if (!empty($filterStartDate) || !empty($filterEndDate)): ?>
                            <button type="button" onclick="clearFilter()" style="background-color: #555;"><i class="fas fa-undo"></i> Clear Filter</button>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="dashboard-card active-bookings-table-card">
                    <h2><i class="fas fa-concierge-bell"></i> Active Guest Bookings (Filtered)</h2>
                    <?php if (empty($activeBookings)): ?>
                        <p class="no-bookings">No active bookings found for the selected filter criteria.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Phone Number</th>
                                    <th>Check-in Date</th>
                                    <th>Check-out Date</th>
                                    <th>Nights</th>
                                    <th>Guests</th>
                                    <th>Room Type</th>
                                    <th>Total (KES)</th>
                                    <th>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeBookings as $booking): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($booking['id'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['full_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['phone_number'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['check_in_date'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['check_out_date'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['nights'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['num_guests'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['room_type'] ?? 'N/A'); ?></td>
                                        <td><?php echo number_format(htmlspecialchars($booking['total_amount'] ?? 0), 2); ?></td>
                                        <td><?php echo htmlspecialchars($booking['submission_time'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 4. Booked Out Guests Section -->
            <div id="booked-out-section" class="content-section">
                <div class="dashboard-card booked-out-bookings-table-card">
                    <h2><i class="fas fa-bed"></i> Booked Out Guests</h2>
                    <?php if (empty($bookedOutBookings)): ?>
                        <p class="no-bookings">No guests have booked out yet.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Full Name</th>
                                    <th>Room Type</th>
                                    <th>Guests</th>
                                    <th>Check-in Date</th>
                                    <th>Check-out Date</th>
                                    <th>Booked Out Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookedOutBookings as $booking): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($booking['id'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['full_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['room_type'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['num_guests'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['check_in_date'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['check_out_date'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['book_out_time'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['status'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="action-buttons">
        <button onclick="window.location.href='index.php'"><i class="fas fa-arrow-left"></i> Back to Hotel Website</button>
        <?php if (!empty($activeBookings)): ?>
            <a href="download_report.php?startDate=<?php echo htmlspecialchars($filterStartDate); ?>&endDate=<?php echo htmlspecialchars($filterEndDate); ?>" download="hotel_bookings_report.json"><i class="fas fa-file-download"></i> Download Active (JSON)</a>
            <a href="download_csv_report.php?startDate=<?php echo htmlspecialchars($filterStartDate); ?>&endDate=<?php echo htmlspecialchars($filterEndDate); ?>" download="hotel_bookings_report.csv"><i class="fas fa-file-csv"></i> Download Active (CSV)</a>
        <?php endif; ?>
        <form method="post" action="clear_bookings.php" onsubmit="return confirm('Are you sure you want to clear ALL bookings? This action cannot be undone.');">
            <button type="submit" style="background-color: #e74c3c;"><i class="fas fa-trash-alt"></i> Clear All Bookings</button>
        </form>
    </div>

    <script>
        // Pass PHP active bookings data to JavaScript for the guest details dropdown
        const activeBookingsData = <?php echo $bookingsJson; ?>;

        const guestSelect = document.getElementById('guestSelect');
        const guestDetailsContent = document.getElementById('guestDetailsContent');

        function displaySelectedGuestDetails() {
            const selectedBookingId = guestSelect.value;
            guestDetailsContent.innerHTML = ''; // Clear previous content

            if (!selectedBookingId) {
                guestDetailsContent.innerHTML = '<p class="no-selection"><i class="fas fa-info-circle"></i> Select a guest from the dropdown to view their active booking details here.</p>';
                return;
            }

            // Find the selected booking using its ID from the active bookings data
            const selectedBooking = activeBookingsData.find(booking => booking.id == selectedBookingId);

            if (selectedBooking) {
                // Format total amount for display
                const formattedAmount = parseFloat(selectedBooking.total_amount).toLocaleString('en-KE', {
                    style: 'currency',
                    currency: 'KES',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                guestDetailsContent.innerHTML = `
                    <h3><i class="fas fa-user-check"></i> Details for ${selectedBooking.full_name}</h3>
                    <p><i class="fas fa-id-badge"></i> <strong>Booking ID:</strong> ${selectedBooking.id}</p>
                    <p><i class="fas fa-envelope"></i> <strong>Email:</strong> ${selectedBooking.email}</p>
                    <p><i class="fas fa-phone"></i> <strong>Phone:</strong> ${selectedBooking.phone_number}</p>
                    <p><i class="fas fa-calendar-alt"></i> <strong>Check-in:</strong> ${selectedBooking.check_in_date}</p>
                    <p><i class="fas fa-calendar-check"></i> <strong>Check-out:</strong> ${selectedBooking.check_out_date}</p>
                    <p><i class="fas fa-moon"></i> <strong>Nights:</strong> ${selectedBooking.nights}</p>
                    <p><i class="fas fa-users"></i> <strong>Guests:</strong> ${selectedBooking.num_guests}</p>
                    <p><i class="fas fa-bed"></i> <strong>Room Type:</strong> ${selectedBooking.room_type}</p>
                    <p><i class="fas fa-money-bill-wave"></i> <strong>Total Amount:</strong> ${formattedAmount}</p>
                    <p><i class="fas fa-clock"></i> <strong>Submitted:</strong> ${selectedBooking.submission_time}</p>
                `;
            } else {
                guestDetailsContent.innerHTML = '<p class="no-selection"><i class="fas fa-exclamation-triangle"></i> Active booking details not found for selected guest.</p>';
            }
        }

        // Function to clear the date filters
        function clearFilter() {
            window.location.href = 'dashboard.php'; // Redirects to dashboard without any GET parameters
        }

        // --- New JavaScript for Tabbed Navigation ---
        function showSection(sectionId, clickedElement) {
            // Hide all content sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });

            // Show the selected section
            document.getElementById(sectionId).classList.add('active');

            // Remove 'active-menu-item' class from all menu items
            document.querySelectorAll('.sidebar ul li a').forEach(item => {
                item.classList.remove('active-menu-item');
            });

            // Add 'active-menu-item' class to the clicked menu item
            if (clickedElement) {
                clickedElement.classList.add('active-menu-item');
            }
        }

        // Initialize display on page load to show the first section
        document.addEventListener('DOMContentLoaded', () => {
            // Check if there's a specific section requested in URL hash (e.g., dashboard.php#booked-out-section)
            const hash = window.location.hash.substring(1); // Get hash without '#'
            if (hash && document.getElementById(hash)) {
                // Find the corresponding menu item and activate it
                const menuItem = document.querySelector(`.sidebar ul li a[onclick*="${hash}"]`);
                showSection(hash, menuItem);
            } else {
                // Default to showing the "Dashboard Overview" section
                showSection('overview-section', document.querySelector('.sidebar ul li a.active-menu-item'));
            }
        });

        // Add a listener to the filter form to ensure it updates the URL hash for active bookings
        document.getElementById('filterForm').addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent default form submission
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            let params = [];
            if (startDate) params.push(`startDate=${startDate}`);
            if (endDate) params.push(`endDate=${endDate}`);
            const queryString = params.join('&');
            // Redirect with query string AND hash for active bookings section
            window.location.href = `dashboard.php?${queryString}#active-bookings-section`;
        });
    </script>
</body>
</html>
