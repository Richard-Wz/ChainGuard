<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include Composer's autoload file (adjust the path if needed)
require __DIR__ . '/../vendor/autoload.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Prevent caching of the page
    header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
    header("Pragma: no-cache"); // HTTP 1.0.
    header("Expires: 0"); // Proxies.

    // Retrieve form input values
    $fullName         = isset($_POST['fullName']) ? $_POST['fullName'] : '';
    $email            = isset($_POST['email']) ? $_POST['email'] : '';
    $contactNumber    = isset($_POST['contactNumber']) ? $_POST['contactNumber'] : '';
    $password         = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword  = isset($_POST['confirmPassword']) ? $_POST['confirmPassword'] : '';

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
            "password"      => $hashedPassword,
            "createdAt"     => new MongoDB\BSON\UTCDateTime()
        ];

        try {
            // Connect to MongoDB on localhost at port 27017
            $mongoClient = new MongoDB\Client("mongodb://localhost:27017");
            
            // Select the database "chainguard" and collection "traffic_authorities"
            $collection = $mongoClient->chainguard->traffic_authorities;
            
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
                // Insert the document into the collection
                $insertResult = $collection->insertOne($userDocument);
                
                if ($insertResult->getInsertedCount() > 0) {
                    // Set parameters for blockchain registration: org1 for admin
                    $role = "admin";
                    $org = "org1";
                    $data = array('org' => $org, 'role' => $role);

                    // Initialize cURL for Node.js API call
                    $ch = curl_init("http://localhost:3000/api/register");
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    // Execute the API call and capture the response
                    $apiResponse = curl_exec($ch);
                    curl_close($ch);

                    // Decode the JSON response from the Node.js API
                    $blockchainResponse = json_decode($apiResponse, true);

                    // Check if the response contains the required blockchain IDs
                    if (isset($blockchainResponse['userID']) && isset($blockchainResponse['clientID'])) {
                        // Update the MongoDB record with blockchain mapping using the email as identifier
                        $updateResult = $collection->updateOne(
                            ['email' => $email],
                            ['$set' => [
                                'blockchainUserID' => $blockchainResponse['userID'],
                                'blockchainClientID' => $blockchainResponse['clientID']
                            ]]
                        );
                        // Redirect to login page
                        $_SESSION['success'] = 'Registration successful! Please login.';
                        header("Location: ../php/login.php");
                        exit;
                    } else {
                        error_log("Blockchain registration failed: " . $apiResponse);
                        $_SESSION['error'] = 'Registration successful, but failed to link blockchain IDs.';
                    }
                } else {
                    $_SESSION['error'] = 'Error in saving admin registration.';
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

// Retrieve and clear the error message from session
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
        <link rel="stylesheet" href="../css/admin_registration.css">
</head>
<body>
    <div class="registration-container">
        <h1>Admin Registration</h1>
        <form id="admin-registration-form" method="post" action="">
            <!-- Grid for the first 3 rows: each row has 2 columns -->
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
            </div>
            <div class="error-container">
                    <?php if (!empty($error)): ?>
                        <p class="error"><?php echo $error; ?></p>
                    <?php endif; ?>
            </div>
            <button type="submit" class="submit-button">Register</button>
        </form>
    </div>
    <script src="../scripts/admin_registration.js"></script>
</body>
</html>

