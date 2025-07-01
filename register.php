<?php
// register.php - Admin Registration Form and Processing

session_start(); // Start the session for messages

// Enable full PHP error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration file
require_once 'db_config.php';
require_once 'functions.php';

$message = ''; // Initialize message variable

// Process registration form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitize_input($conn, $_POST['username'] ?? '');
    $email = sanitize_input($conn, $_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; // Do NOT sanitize password directly, hash it
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
        $message = '<div class="error-message">All fields are required.</div>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<div class="error-message">Invalid email format.</div>';
    } elseif ($password !== $confirmPassword) {
        $message = '<div class="error-message">Passwords do not match.</div>';
    } elseif (strlen($password) < 6) {
        $message = '<div class="error-message">Password must be at least 6 characters long.</div>';
    } else {
        // Check if username or email already exists
        $checkSql = "SELECT id FROM admins WHERE username = ? OR email = ?";
        if ($stmt = $conn->prepare($checkSql)) {
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $stmt->store_result(); // Store result to check num_rows

            if ($stmt->num_rows > 0) {
                $message = '<div class="error-message">Username or Email already registered.</div>';
            } else {
                // Hash the password securely
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Insert new admin into the database
                $insertSql = "INSERT INTO admins (username, email, password_hash) VALUES (?, ?, ?)";
                if ($stmtInsert = $conn->prepare($insertSql)) {
                    $stmtInsert->bind_param("sss", $username, $email, $hashedPassword);
                    if ($stmtInsert->execute()) {
                        $_SESSION['status_message'] = '<div class="success-message">Registration successful! You can now log in.</div>';
                        header("Location: login.php"); // Redirect to login page
                        exit();
                    } else {
                        $message = '<div class="error-message">Error registering: ' . $stmtInsert->error . '</div>';
                    }
                    $stmtInsert->close();
                } else {
                    $message = '<div class="error-message">Database error during insert preparation: ' . $conn->error . '</div>';
                }
            }
            $stmt->close();
        } else {
            $message = '<div class="error-message">Database error during check preparation: ' . $conn->error . '</div>';
        }
    }
}

// Close the database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration - Supremacy Hotel</title>
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
        .register-container {
            background-color: #1e1e1e;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
            padding: 30px;
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        h1 {
            font-size: 28px;
            color: #d4af37;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #333;
            border-radius: 5px;
            background-color: #2a2a2a;
            color: #f0e6c4;
            box-sizing: border-box;
        }
        input[type="submit"] {
            width: 100%;
            padding: 12px;
            background-color: #d4af37;
            color: #121212;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin-top: 20px;
        }
        input[type="submit"]:hover {
            background-color: #c2a135;
            transform: translateY(-2px);
        }
        .message {
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
        .login-link {
            margin-top: 20px;
            font-size: 14px;
            color: #f0e6c4;
        }
        .login-link a {
            color: #d4af37;
            text-decoration: none;
            font-weight: bold;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h1>Admin Registration</h1>
        <?php echo $message; ?>
        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <input type="submit" value="Register">
        </form>
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>
