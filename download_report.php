<?php
// download_report.php

$bookingsFile = 'bookings.json';

if (file_exists($bookingsFile)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="hotel_bookings_report.json"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($bookingsFile));
    readfile($bookingsFile);
    exit;
} else {
    // Optionally redirect back or show an error
    header('Location: dashboard.php?message=No report file found.');
    exit;
}
?>
