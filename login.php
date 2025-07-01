<?php
// login.php - Admin Login Form and Processing

session_start(); // Start the session

// If admin is already logged in, redirect to dashboard
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: dashboard.php");
    exit();
}

// Enable full PHP error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration file
require_once 'db_config.php';
require_once 'functions.php';

$message = ''; // Initialize message variable

// Check for registration success message from session
if (isset($_SESSION['status_message'])) {
    $message = $_SESSION['status_message'];
    unset($_SESSION['status_message']); // Clear message after display
}


// Process login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = sanitize_input($conn, $_POST['identifier'] ?? ''); // Can be username or email
    $password = $_POST['password'] ?? ''; // Do NOT sanitize password directly

    if (empty($identifier) || empty($password)) {
        $message = '<div class="error-message">Both fields are required.</div>';
    } else {
        // Prepare SQL to fetch user by username or email
        $sql = "SELECT id, username, password_hash FROM admins WHERE username = ? OR email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $identifier, $identifier); // Bind identifier twice for OR condition
            $stmt->execute();
            $stmt->store_result(); // Store result to check num_rows

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($adminId, $username, $hashedPassword);
                $stmt->fetch();

                // Verify the provided password against the hashed password
                if (password_verify($password, $hashedPassword)) {
                    // Password is correct, set session variables
                    $_SESSION['loggedin'] = true;
                    $_SESSION['admin_id'] = $adminId;
                    $_SESSION['username'] = $username;

                    $_SESSION['status_message'] = '<div class="success-message">Login successful! Welcome, ' . htmlspecialchars($username) . '!</div>';
                    header("Location: dashboard.php"); // Redirect to dashboard
                    exit();
                } else {
                    $message = '<div class="error-message">Invalid username/email or password.</div>';
                }
            } else {
                $message = '<div class="error-message">Invalid username/email or password.</div>';
            }
            $stmt->close();
        } else {
            $message = '<div class="error-message">Database error during login preparation: ' . $conn->error . '</div>';
        }
    }
}

// Close database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Supremacy Hotel</title>
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
        .login-container {
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
        .register-link {
            margin-top: 20px;
            font-size: 14px;
            color: #f0e6c4;
        }
        .register-link a {
            color: #d4af37;
            text-decoration: none;
            font-weight: bold;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Admin Login</h1>
        <?php echo $message; ?>
        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="identifier">Username or Email:</label>
                <input type="text" id="identifier" name="identifier" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <input type="submit" value="Login">
        </form>
        <div class="register-link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>
</body>
</html>
