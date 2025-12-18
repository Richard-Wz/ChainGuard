<?php
session_start();

// Prevent caching of the page
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// Ensure the user is logged in and has 'driver' role
include('../php/auth_driver.php');

// Change driverID to userID from the session
$userID = $_SESSION['user_id'];
require_once __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client as MongoClient;
use MongoDB\BSON\ObjectId;

$errors = array();

try {
    // Connect to MongoDB (for off-chain evidence storage if needed)
    $mongoClient = new MongoClient("mongodb://localhost:27017");

    // Query the drivers collection to get blockchainUserID
    $driversCollection = $mongoClient->chainguard->drivers;
    $driverDoc = $driversCollection->findOne(["_id" => new ObjectId($userID)]);
    $blockchainUserID = isset($driverDoc['blockchainUserID']) ? $driverDoc['blockchainUserID'] : "";
    $blockchainClientID = isset($driverDoc['blockchainClientID']) ? $driverDoc['blockchainClientID'] : "";
    $profileImage = isset($driverDoc['profileImage']) ? $driverDoc['profileImage'] : "";


    $appealCollection = $mongoClient->chainguard->appeals;

    // Process the appeal submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Get and sanitize form inputs
        $violationID = isset($_POST['violation_id']) ? filter_var($_POST['violation_id'], FILTER_SANITIZE_STRING) : '';
        $appealReason = isset($_POST['appealText']) ? filter_var($_POST['appealText'], FILTER_SANITIZE_STRING) : '';

        // Process image upload and convert to base64
        $base64Image = "";
        if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
            $tempFile = $_FILES['evidence']['tmp_name'];
            $imageData = file_get_contents($tempFile);
            $base64Image = base64_encode($imageData);
        }

        // Validate required fields
        if (empty($violationID)) {
            $errors[] = "Violation ID is required.";
        }

        if (empty($appealReason)) {
            $errors[] = "Appeal reason is required.";
        }
        
        if (empty($base64Image)) {
            $errors[] = "Evidence image is required.";
        }

        // Validate the violation belongs to the current user
        $violationsCollection = $mongoClient->chainguard->violations;
        $violationDoc = $violationsCollection->findOne(['_id' => new ObjectId($violationID)]);

        if (!$violationDoc || (string)$violationDoc['driverID'] !== $blockchainClientID) {
            $errors[] = "This violation does not belong to you.";
        }

        // Check if the violation has been paid (both violationStatus and paymentStatus are true)
        if ($violationDoc && isset($violationDoc['violationStatus']) && isset($violationDoc['paymentStatus']) && 
            $violationDoc['violationStatus'] === true && $violationDoc['paymentStatus'] === true) {
            $errors[] = "This violation has already been paid. Appeals cannot be submitted for paid violations.";
        }

        // Check if a pending or approved appeal already exists for this driver and violation
        $existingAppeal = $appealCollection->findOne([
            'violationID' => $violationID,
            'driverID' => $blockchainUserID,
            '$or' => [
                ['status' => ['$ne' => 'Pending']],
                ['status' => ['$ne' => 'Approved']]
            ]
        ]);

        if ($existingAppeal !== null) {
            if ($existingAppeal['status'] === 'Pending') {
                $errors[] = "A pending appeal for this violation already exists. Please wait for it to be processed before re-submitting.";
            } else if ($existingAppeal['status'] === 'Approved') {
                $errors[] = "This appeal has already been approved. No further appeals can be submitted for this violation.";
            }
        }

        if (!empty($errors)) {
            $_SESSION['error'] = implode(" ", $errors);
            header("Location: ../php/appeal_submission.php");
            exit;
        }

        // Generate a new appeal ID (using MongoDB ObjectId)
        $appealObjectId = new ObjectId();
        $appealID = (string)$appealObjectId;

        // Set the current timestamp (ISO 8601 format) - Malaysia time
        date_default_timezone_set("Asia/Kuala_Lumpur");
        $timestamp = date('Y-m-d\TH:i');

        // Prepare the payload for the REST API call
        $payload = array(
            "driverID"          => $blockchainUserID,
            "appealID"          => $appealID,
            "violationID"       => $violationID,
            "appealText"        => $appealReason,
            "evidence"          => $base64Image,
            "timestamp"         => $timestamp
        );
        $jsonPayload = json_encode($payload);

        // Call the Node.js REST API endpoint to submit the appeal
        $apiUrl = "http://localhost:3000/api/submitAppeal";
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        $apiResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if(curl_errno($ch)){
            $errors[] = "cURL error: " . curl_error($ch);
        }
        curl_close($ch);

        // Decode the response
        $responseData = json_decode($apiResponse, true);

        // Check the response status
        if ($httpCode != 200) {
            $_SESSION['error'] = "Failed to submit appeal. " . ($responseData['error'] ?? '');
        } else {
            $_SESSION['success'] = "Appeal submitted successfully.";
            
            $document = [
                "appealID"          => $appealID,
                "violationID"       => $violationID,
                "appealText"        => $appealReason,
                "evidence"          => $base64Image,
                "timestamp"         => $timestamp,
                "driverID"          => $blockchainUserID, 
                "status"            => "Pending",
            ];
            $appealCollection->insertOne($document);
        }
        
        // Redirect to the driver appeal page
        header("Location: ../php/appeal_submission.php");
        exit;
    }
} catch (Exception $e) {
    error_log("Error processing appeal submission: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while submitting the appeal.";
    header("Location: ../php/appeal_submission.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ChainGuard - Appeal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/appeal_submission.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Brand -->
        <div class="brand">
            <div class="brand-logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            ChainGuard
        </div>
        <!-- Menu Items -->
        <div class="menu-item">
            <a href="../php/pending_violation.php">
                <div class="menu-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                Pending Violations
            </a>
        </div>
        <div class="menu-item">
            <a href="../php/violation_history.php">
                <div class="menu-icon">
                    <i class="fas fa-history"></i>
                </div>
                Violation History
            </a>
        </div>
        <div class="menu-item active">
            <a href="../php/driver_appeal.php">
                <div class="menu-icon">
                    <i class="fas fa-gavel"></i>
                </div>
                Appeals
            </a>
        </div>
        <div class="menu-item">
            <a href="../php/driver_profile.php">
                <div class="menu-icon">
                    <i class="fas fa-user"></i>
                </div>
                Profile
            </a>
        </div>
        <div class="menu-item">
            <a href="../php/logout.php">
                <div class="menu-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                Sign Out
            </a>
        </div>
    </div>
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="hamburger-menu">
                <i class="fas fa-bars"></i>
            </div>
            <div class="page-title">Appeals</div>
            <div class="header-actions">
                <div class="user-avatar">
                    <?php if (!empty($profileImage)): ?>
                        <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile Image">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Notification Messages -->
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="notification success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $_SESSION['success']; ?></span>
                <span class="close-notification"><i class="fas fa-times"></i></span>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="notification error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $_SESSION['error']; ?></span>
                <span class="close-notification"><i class="fas fa-times"></i></span>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <div class="appeal-container">
            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" enctype="multipart/form-data" >
                <div class="appeal-section">
                    <div class="form-group">
                        <label for="violation_id">Violation ID:</label>
                        <input type="text" id="violation_id" name="violation_id" required>
                    </div>
                    <div class="form-group">
                        <label for="appeal_reason">Appeal Reason:</label>
                        <textarea name="appealText" id="appealText" rows="2" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="evidence">Supporting Evidence:</label>
                        <label for="evidence" class="custom-file-upload">Support image</label>
                        <input type="file" id="evidence" name="evidence" class="file-upload">
                        <div id="imagePreviewContainer"></div>
                    </div>
                    <div class="form-actions">
                        <a href="driver_appeal.php" class="cancel-btn">Cancel</a>
                        <button type="submit" class="submit-btn">Submit Appeal</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script src="../scripts/appeal_submission.js"></script>
</body>
</html>
