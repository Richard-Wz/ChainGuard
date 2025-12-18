<?php
session_start();
// Prevent caching of the page
header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 

// Ensure the user is logged in and has 'admin' role
include('../php/auth_admin.php');
include('../php/send_email_notification.php');

$userID = $_SESSION['user_id'];

// Composer autoload
require_once __DIR__ . '/../vendor/autoload.php';

try {
    // MongoDB Connection
    $mongoClient = new MongoDB\Client("mongodb://localhost:27017");
    $collection = $mongoClient->chainguard->violations;

    try {
        $objectID = new MongoDB\BSON\ObjectId($userID);
    } catch (Exception $e) {
        error_log("Invalid user_id in session: " . $e->getMessage());
        header("Location: ../php/login.php");
        exit;
    }

    // Process the logging
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Retrieve and sanitize form input values
        $violationType     = isset($_POST['violationType']) ? filter_var($_POST['violationType'], FILTER_SANITIZE_STRING) : '';
        $violationLocation = isset($_POST['violationLocation']) ? filter_var($_POST['violationLocation'], FILTER_SANITIZE_STRING) : '';
        $timestamp         = isset($_POST['timestamp']) ? filter_var($_POST['timestamp'], FILTER_SANITIZE_STRING) : '';
        $licensePlate      = isset($_POST['vehicleNumber']) ? filter_var($_POST['vehicleNumber'], FILTER_SANITIZE_STRING) : '';
        $penaltyAmount     = isset($_POST['penaltyAmount']) ? filter_var($_POST['penaltyAmount'], FILTER_SANITIZE_STRING) : '';
        $violationRemark   = isset($_POST['violationRemark']) ? filter_var($_POST['violationRemark'], FILTER_SANITIZE_STRING) : '';

        // Process file upload for the violation image and convert it to Base64 string (optional field)
        $base64Image = "";
        if (isset($_FILES['violationImage']) && $_FILES['violationImage']['error'] === UPLOAD_ERR_OK) {
            $tempFile = $_FILES['violationImage']['tmp_name'];
            $imageData = file_get_contents($tempFile);
            $base64Image = base64_encode($imageData);
        }

        // Initialize an array to collect validation errors
        $errors = array();

        // Validate that required fields are not empty
        if (empty($violationLocation)) {
            $errors[] = "Location is required.";
        }
        if (empty($timestamp)) {
            $errors[] = "Time is required.";
        }
        if (empty($licensePlate)) {
            $errors[] = "License plate number is required.";
        }
        if (empty($penaltyAmount)) {
            $errors[] = "Penalty amount is required.";
        }
        
        // Validate the image upload
        if (empty($base64Image)) {
            $errors[] = "Violation image is required.";
        }

        // Validate license plate format using the provided regex
        $licensePlateRegex = '/^[A-HJ-NP-Z]{3}\d{1,4}[A-HJ-NP-Z]?$/';
        if (!empty($licensePlate) && !preg_match($licensePlateRegex, $licensePlate)) {
            $errors[] = "Invalid license plate number. It must be 3 letters (A-H, J-N, P-Z) followed by 1-4 digits and optionally one letter (excluding I and O) at the end.";
        }

        // Validate that penalty amount is numeric
        if (!empty($penaltyAmount) && !is_numeric($penaltyAmount)) {
            $errors[] = "Penalty amount must be numeric.";
        }

        // Retrieve the driver’s blockchainClientID based on the license plate from the drivers collection
        $driversCollection = $mongoClient->chainguard->drivers;
        $driverDocument = $driversCollection->findOne(['licensePlate' => $licensePlate]);
        if ($driverDocument) {
            $driverID = isset($driverDocument['blockchainClientID']) ? $driverDocument['blockchainClientID'] : "";
            $driverEmail = isset($driverDocument['email']) ? $driverDocument['email'] : "";
            $driverName = isset($driverDocument['fullName']) ? $driverDocument['fullName'] : "Driver";
        } else {
            $driverID = "";
            // Set warning; note that we still proceed with storing in MongoDB
            $_SESSION['warning'] = "Driver ID not found. Violation recorded in MongoDB but not on the Fabric network.";
            error_log("Driver not found for license plate: $licensePlate");
        }

        // Retrieve the admin’s blockchainClientID based on the adminID from the traffic_authorities collection
        $adminsCollection = $mongoClient->chainguard->traffic_authorities;
        $adminDocument = $adminsCollection->findOne(['_id' => $objectID]);
        if ($adminDocument) {
            $adminID = isset($adminDocument['blockchainUserID']) ? $adminDocument['blockchainUserID'] : "";
        } else {
            // If admin is not found, add an error message
            $errors[] = "Admin ID not found.";
            $adminID = "";
        }

        // If there are any validation errors, store them in session and redirect back
        if (!empty($errors)) {
            $_SESSION['error'] = implode(" ", $errors);
            header("Location: admin_logging.php");
            exit;
        }

        // Set default boolean flags
        $paymentStatus   = false;
        $violationStatus = false;
        $fabricPushed = false;

        try {
            // Insert into MongoDB's "violations" collection the violation data, including fabricPushed flag
            $violationsCollection = $mongoClient->chainguard->violations;
            $insertResult = $violationsCollection->insertOne([
                'adminID'            => $adminID,
                'violationType'      => $violationType,
                'location'           => $violationLocation,
                'penaltyAmount'      => $penaltyAmount,
                'timestamp'          => $timestamp,
                'licensePlateNumber' => $licensePlate,
                'image'              => $base64Image,
                'remark'             => $violationRemark,
                'paymentStatus'      => $paymentStatus,
                'violationStatus'    => $violationStatus,
                'driverID'           => $driverID,
                'fabricPushed'       => $fabricPushed 
            ]);

            // Retrieve the generated ObjectID as the violation ID.
            $violationID = (string)$insertResult->getInsertedId();

            // Only proceed with the API call if a valid driver ID is present
            if (!empty($driverID)) {
                // Prepare the data payload for the Node.js API, including the Base64 image string
                $data = array(
                    'adminID'            => $adminID,
                    'violationID'        => $violationID,
                    'driverID'           => $driverID,
                    'violationType'      => $violationType,
                    'location'           => $violationLocation,
                    'penaltyAmount'      => $penaltyAmount,
                    'timestamp'          => $timestamp,
                    'licensePlateNumber' => $licensePlate,
                    'image'              => $base64Image, 
                    'remark'             => $violationRemark,
                    'paymentStatus'      => $paymentStatus,
                    'violationStatus'    => $violationStatus
                );
        
                // Send the data payload to the Node.js API (Fabric network)
                $ch = curl_init('http://localhost:3000/api/createViolation');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $apiResponse = curl_exec($ch);

                if (curl_errno($ch)) {
                    $err_msg = curl_error($ch);
                    // Log the error and set an error message for the session
                    error_log("cURL error: " . $err_msg);
                    $_SESSION['error'] = "Unable to connect to the API: " . $err_msg;
                } else {
                    $responseData = json_decode($apiResponse, true);
                    if (isset($responseData['message'])) {
                        // API call successful; update fabricPushed flag in MongoDB
                        $updateResult = $violationsCollection->updateOne(
                            ['_id' => new MongoDB\BSON\ObjectId($violationID)],
                            ['$set' => ['fabricPushed' => true]]
                        );
                    } else {
                        error_log("API error: " . $apiResponse);
                        $_SESSION['error'] = "Error pushing violation to Fabric network.";
                    }
                }

                curl_close($ch);
            }

            // Send email notification to the driver after API completes successfully
            if (!empty($driverEmail)) {
                $violationDetails = [
                    'type' => $violationType,
                    'location' => $violationLocation,
                    'timestamp' => $timestamp,
                    'penalty' => $penaltyAmount,
                    'remark' => $violationRemark
                ];
                
                $emailSent = sendViolationEmail($driverEmail, $driverName, $violationDetails);
                if (!$emailSent) {
                    error_log("Failed to send email notification to: $driverEmail");
                }
            }
    
            $_SESSION['success'] = "Violation logged successfully.";
            header("Location: admin_logging.php");
            exit;
            
        } catch (Exception $e) {
            error_log("Error inserting violation record: " . $e->getMessage());
            $_SESSION['error'] = "Error logging violation record: " . $e->getMessage();
        }
    
        // Redirect to avoid re-submission on reload
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
} catch (Exception $e) {
    error_log("Error logging violation record: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title>ChainGuard - Violation Logging</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">   
    <link rel="stylesheet" href="../css/admin_logging.css">
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
            <a href="../php/admin_dashboard.php">
                <div class="menu-icon">
                    <i class="fas fa-gauge"></i>
                </div>
                Dashboard
            </a>
        </div>

        <div class="menu-item active">
            <a href="../php/admin_logging.php">
                <div class="menu-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                Violation Logging
            </a>
        </div>

        <div class="menu-item">
            <a href="../php/admin_record.php">
                <div class="menu-icon">
                    <i class="fas fa-history"></i>
                </div>
                Violation History
            </a>
        </div>

        <div class="menu-item">
            <a href="../php/admin_appeal.php">
                <div class="menu-icon">
                    <i class="fas fa-gavel"></i>
                </div>
                Appeals
            </a>
        </div>

        <div class="menu-item">
            <a href="../php/admin_user_management.php">
                <div class="menu-icon">
                    <i class="fas fa-user"></i>
                </div>
                User Management
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
            <div class="page-title">Log New Violation</div>
            <div class="header-actions">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
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

        <?php if (!empty($_SESSION['warning'])): ?>
            <div class="notification warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo $_SESSION['warning']; ?></span>
                <span class="close-notification"><i class="fas fa-times"></i></span>
            </div>
            <?php unset($_SESSION['warning']); ?>
        <?php endif; ?>

        <div class="log-container">
            <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" enctype="multipart/form-data">
                <div class="details-section">
                    <div class="form-group">
                        <label for="violationType">Violation Type</label>
                        <select id="violationType" name="violationType">
                            <option value="Dangerous Driving">Dangerous Driving</option>
                            <option value="Poor Vehicle Condition">Poor Vehicle Condition</option>
                            <option value="Parking Violations">Parking Violations</option>
                            <option value="Licensing and Documentation">Licensing and Documentation</option>
                            <option value="Safety Regulations">Safety Regulations</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="violationLocation">Location</label>
                        <textarea name="violationLocation" id="violationLocation" rows="2"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="timestamp">Time</label>
                        <input type="datetime-local" id="timestamp" name="timestamp">
                    </div>

                    <div class="form-group">
                        <label for="vehicleNumber">License Plate Number</label>
                        <input type="text" id="vehicleNumber" name="vehicleNumber">
                    </div>

                    <div class="form-group">
                        <label for="penaltyAmount">Penalty Amount</label>
                        <input type="text" id="penaltyAmount" name="penaltyAmount">
                    </div>

                    <div class="form-group">
                        <label for="violationRemark">Remark</label>
                        <textarea name="violationRemark" id="violationRemark" rows="4"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="violationImage">Violation Image</label>
                        <label for="violationImage" class="custom-file-upload">Upload image</label>
                        <input type="file" id="violationImage" name="violationImage" class="file-upload">
                        <div id="imagePreviewContainer"></div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="admin_logging.php" class="cancel-btn">Cancel</a>
                        <a href="../php/refresh_record.php" class="refresh-btn">Refresh Violations</a>
                        <button type="submit" class="save-btn">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script src="../scripts/admin_logging.js"></script>
</body>
</html>
