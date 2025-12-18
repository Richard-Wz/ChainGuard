<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include Composer's autoload file (adjust the path if needed)
require __DIR__ . '/../vendor/autoload.php';

session_start();

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Prevent caching of the page
    header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
    header("Pragma: no-cache"); // HTTP 1.0.
    header("Expires: 0"); // Proxies.

    // Retrieve form input values
    $fullName         = isset($_POST['fullName']) ? $_POST['fullName'] : '';
    $email            = isset($_POST['email']) ? $_POST['email'] : '';
    $contactNumber    = isset($_POST['contactNumber']) ? $_POST['contactNumber'] : '';
    $licensePlate     = isset($_POST['licensePlate']) ? $_POST['licensePlate'] : '';
    $password         = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword  = isset($_POST['confirmPassword']) ? $_POST['confirmPassword'] : '';
    $address          = isset($_POST['address']) ? $_POST['address'] : '';

    // Simple password check
    if ($password !== $confirmPassword) {
        $_SESSION['error'] = 'Passwords do not match.';
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Prepare the data document for MongoDB
        $userDocument = [
            "fullName"      => $fullName,
            "email"         => $email,
            "contactNumber" => $contactNumber,
            "licensePlate"  => $licensePlate,
            "password"      => $hashedPassword,
            "address"       => $address,
            "createdAt"     => new MongoDB\BSON\UTCDateTime()
        ];

        try {
            // Connect to MongoDB on localhost at port 27017
            $mongoClient = new MongoDB\Client("mongodb://localhost:27017");
            
            // Select the database "chainguard" and collection "drivers"
            $collection = $mongoClient->chainguard->drivers;

            // Validate if an account with the same email or contact number already exists
            $existingUser = $collection->findOne([
                '$or' => [
                    ['email' => $email],
                    ['contactNumber' => $contactNumber]
                ]
            ]);

            if ($existingUser) {
                $_SESSION['error'] = 'An account with the same email or contact number already exists.';
            } else {
                $insertResult = $collection->insertOne($userDocument);

                if ($insertResult->getInsertedCount() > 0) {
                    // Prepare blockchain registration for driver (org2)
                    $role = "driver";
                    $org = "org2";
                    $data = array('org' => $org, 'role' => $role);

                    // Initialize cURL for Node.js API call
                    $ch = curl_init('http://localhost:3000/api/register');
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    
                    $apiResponse = curl_exec($ch);
                    curl_close($ch);

                    $blockchainResponse = json_decode($apiResponse, true);

                    if (isset($blockchainResponse['userID']) && isset($blockchainResponse['clientID'])) {
                        $updateResult = $collection->updateOne(
                            ['email' => $email],
                            ['$set' => [
                                'blockchainUserID' => $blockchainResponse['userID'],
                                'blockchainClientID' => $blockchainResponse['clientID']
                            ]]
                        );

                        // Include the reusable pending violations update code
                        require_once __DIR__ . '/update_record.php';
                        
                        // Call the function to update any pending violations for this driver's license plate.
                        updatePendingViolations($mongoClient, $licensePlate, $blockchainResponse['clientID'], $email, $fullName);
    
                        // Send email notification to the driver
                        require_once __DIR__ . '/send_email_notification.php';

                        // Send welcome email to the newly registered driver
                        $emailSent = sendWelcomeEmail($email, $fullName);
                        if (!$emailSent) {
                            error_log("Failed to send violation email notification to: $email");
                        }

                        // Redirect to login page
                        $_SESSION['success'] = 'Registration successful! Please login.';
                        header("Location: ../php/login.php");
                        exit;

                    } else {
                        error_log("Blockchain registration failed: " . $apiResponse);
                        $_SESSION['error'] = 'Registration successful, but failed to link blockchain IDs.';
                    }
                } else {
                    $_SESSION['error'] = 'Error in saving user registration.';
                }
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error connecting to MongoDB: ' . $e->getMessage();
        }
    }
    // Redirect to avoid re-submission on reload
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Retrieve and clear messages from session (if any)
$error = '';
$success = '';
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>

<!DOCTYPE html>
<head>
    <html lang="en">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
        <meta http-equiv="Pragma" content="no-cache" />
        <meta http-equiv="Expires" content="0" />
        <title>Registration</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">   
        <link rel="stylesheet" href="../css/driver_registration.css">
</head>
<body>
    <div class="registration-container">
        <h1>Driver Registration</h1>
        <form id="driver-registration-form" method="post" action="">
            <div class="form-grid">
                <div class="form-group">
                    <label for="fullName">Full Name</label>
                    <input
                    type="text"
                    id="fullName"
                    name="fullName"
                    placeholder="Enter full name"
                    required
                    />

                </div>
                    <div class="form-group">
                    <label for="email">Email</label>
                    <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter email address"
                    required
                    />
                </div>

                <div class="form-group">
                    <label for="contactNumber">Contact Number</label>
                    <input
                    type="tel"
                    id="contactNumber"
                    name="contactNumber"
                    placeholder="Enter contact number"
                    required
                    />
                </div>

                <div class="form-group">
                    <label for="licensePlate">Vehicle License Plate</label>
                    <input
                    type="text"
                    id="licensePlate"
                    name="licensePlate"
                    placeholder="Enter vehicle license plate"
                    required
                    />
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter password"
                    required
                    />
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm Password</label>
                    <input
                    type="password"
                    id="confirmPassword"
                    name="confirmPassword"
                    placeholder="Confirm password"
                    required
                    />
                </div>

                <div class="form-group form-grid-full">
                    <label for="address">Address</label>
                    <textarea
                    id="address"
                    name="address"
                    rows="3"
                    placeholder="Enter your address"
                    ></textarea>
                </div>
            </div>
            <div class="error-container">
                    <?php if (!empty($error)): ?>
                        <p class="error"><?php echo $error; ?></p>
                    <?php endif; ?>
            </div>
            <button type="submit" class="submit-button">Register</button>
        </form>
    </div>
    <script src="../scripts/driver_registration.js"></script>
</body>
</html>

