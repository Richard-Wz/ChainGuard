<?php
// Debug 
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (isset($_SESSION['success'])) {
    unset($_SESSION['success']);
}

// Composer for MongoDB
require __DIR__ . '/../vendor/autoload.php';

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve form input values
    $email    = isset($_POST['email']) ? $_POST['email'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // MongoDB connection
    try {
        $mongoClient = new MongoDB\Client("mongodb://localhost:27017");

        // Check drivers collection first
        $driversCollection = $mongoClient->chainguard->drivers;
        $user = $driversCollection->findOne(['email' => $email]);

        if ($user && password_verify($password, $user['password'])) {
            // Driver found and password is correct
            $_SESSION['user_id'] = (string) $user['_id'];  // Store user ID in session
            $_SESSION['email'] = $user['email'];       // Store user's email in session
            $_SESSION['user_role'] = 'driver';               // Store the user role
            header("Location: ../php/pending_violation.php"); // Redirect to driver dashboard
            exit;
        }

        // If not a driver, check traffic authorities collection
        $authoritiesCollection = $mongoClient->chainguard->traffic_authorities;
        $admin = $authoritiesCollection->findOne(['email' => $email]);

        if ($admin && password_verify($password, $admin['password'])) {
            // Traffic authority found and password is correct
            $_SESSION['user_id'] = (string) $admin['_id'];  // Store admin ID in session
            $_SESSION['email'] = $admin['email'];       // Store admin's email in session
            $_SESSION['user_role'] = 'admin';                // Store the user role
            header("Location: ../php/admin_dashboard.php");  // Redirect to admin dashboard
            exit;
        }

        // If no user is found or password is invalid, set error message in session
        $_SESSION['error'] = 'Invalid email or password. Please try again.';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error connecting to MongoDB: ' . $e->getMessage();
    }

    // Redirect to the same page to convert the POST into a GET request
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Retrieve and clear the error message from session
$error = '';
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ChainGuard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">   
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="brand-logo">
            <i class="fas fa-shield-alt"></i>
        </div>
        <h1>Welcome to ChainGuard</h1>
        <p>Please sign in to continue</p>
        <form id="login-form" method="post" action="">
            <div class="form-group">
                <label for="email">E-mail</label>
                <input
                    type="text"
                    id="email"
                    name="email"
                    placeholder="Enter your e-mail"
                    required
                />
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                />
                <div class="error-container">
                    <?php if (!empty($error)): ?>
                        <p class="error"><?php echo $error; ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <button type="submit">Sign In</button>
        </form>
        <div class="register-link">
            Don't have an account? <a href="../php/driver_registration.php">Register here</a>
        </div>  
    </div>
    <script src="../scripts/login.js"></script>
</body>
</html>
