<?php
session_start();

// Prevent caching of the page
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// Ensure the user is logged in and has 'admin' role
include('../php/auth_admin.php');

$adminID = $_SESSION['user_id'];
$userID = $_GET['id'] ?? $_POST['id'] ?? null;
if (!$userID) {
    header("Location: admin_user_management.php");
    exit;
}

$driverProfile = null;
$profileUpdated = false;
$successMsg = '';
$errorMsg = '';

try {
    // Composer for MongoDB
    require __DIR__ . '/../vendor/autoload.php';
    // MongoDB connection
    $mongoClient = new MongoDB\Client("mongodb://localhost:27017");
    $collection = $mongoClient->chainguard->drivers;

    try {
        $objectId = new MongoDB\BSON\ObjectId($userID);
    } catch (Exception $e) {
        error_log("Invalid driver ID: " . $e->getMessage());
        header("Location: admin_user_management.php");
        exit;
    }

    // Process the form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Retrieve and sanitize inputs
        $fullName = trim(filter_input(INPUT_POST, 'fullName', FILTER_SANITIZE_STRING));
        $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
        $contactNumber = trim(filter_input(INPUT_POST, 'contactNumber', FILTER_SANITIZE_STRING));
        $licensePlate = trim(filter_input(INPUT_POST, 'licensePlate', FILTER_SANITIZE_STRING));
        $address = trim(filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING));

        // Initialize an errors array
        $errors = [];

        // Check for empty fields
        if (empty($fullName)) {
            $errors[] = "Full name cannot be empty.";
        }
        if (empty($email)) {
            $errors[] = "Email address cannot be empty.";
        }
        if (empty($contactNumber)) {
            $errors[] = "Contact number cannot be empty.";
        }
        if (empty($licensePlate)) {
            $errors[] = "License plate cannot be empty.";
        }
        if (empty($address)) {
            $errors[] = "Address cannot be empty.";
        }

        // Validate that fullName does not contain any digits
        if (!empty($fullName) && preg_match('/[0-9]/', $fullName)) {
            $errors[] = "Full name cannot contain numbers.";
        }

        // Validate the email address format
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address.";
        }

        // Validate Malaysian phone number format
        $malaysiaPhonePattern = '/^(?:\+?6?01)[0-46-9]-?[0-9]{7,8}$/';
        if (!empty($contactNumber) && !preg_match($malaysiaPhonePattern, $contactNumber)) {
            $errors[] = "Invalid Malaysian contact number.";
        }

        // Validate license plate format with your sample regex
        $licensePlateRegex = '/^[A-HJ-NP-Z]{3}\d{1,4}[A-HJ-NP-Z]?$/';
        if (!empty($licensePlate) && !preg_match($licensePlateRegex, $licensePlate)) {
            $errors[] = "Invalid license plate number format. It must be 3 letters (A-H, J-N, P-Z) followed by 1-4 digits and optionally one letter (excluding I and O) at the end.";
        }

        // If there are any validation errors, concatenate them into $errorMsg
        if (!empty($errors)) {
            $errorMsg = implode(' ', $errors);
        } else {
            // Proceed to update the driver's profile
            $updateResult = $collection->updateOne(
                ['_id' => $objectId],
                ['$set' => [
                    'fullName' => $fullName,
                    'email' => $email,
                    'contactNumber' => $contactNumber,
                    'licensePlate' => $licensePlate,
                    'address' => $address
                ]]
            );

            if ($updateResult->getModifiedCount() > 0) {
                $profileUpdated = true;
            } else {
                // No changes made to the document
                $errorMsg = "No changes were made to the selected profile.";
            }
        }
    }

    // After processing the form and before rendering the HTML
    $driverProfile = $collection->findOne(['_id' => $objectId]);
    if ($driverProfile) {
        $successMsg = $profileUpdated ? "Profile updated successfully." : "";
    } else {
        $errorMsg = "Driver profile not found.";
    }

} catch (Exception $e) {
    error_log("Error retrieving/updating profile: " . $e->getMessage());
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
        <title>ChainGuard - Edit User Information</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">   
        <link rel="stylesheet" href="../css/admin_user_profile.css">
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

        <div class="menu-item">
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

        <div class="menu-item active">
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
            <div class="page-title">Edit User Information</div>
            <div class="header-actions">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
            </div>
        </div>

        <!-- Notification Messages -->
        <?php if (!empty($successMsg)): ?>
            <div class="notification success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $successMsg; ?></span>
                <span class="close-notification"><i class="fas fa-times"></i></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMsg)): ?>
            <div class="notification error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $errorMsg; ?></span>
                <span class="close-notification"><i class="fas fa-times"></i></span>
            </div>
        <?php endif; ?>

        <div class="edit-profile-container">
            <form action="admin_user_profile.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($userID); ?>">
                <div class="form-section details-section">
                    <h2><?php echo $driverProfile ? htmlspecialchars($driverProfile['fullName']) : '';?>'s Information</h2>

                    <div class="form-group">
                        <label for="fullName">Full Name</label>
                        <input type="text" id="fullName" name="fullName" value="<?php echo $driverProfile ? htmlspecialchars($driverProfile['fullName']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo $driverProfile ? htmlspecialchars($driverProfile['email']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contactNumber">Contact Number</label>
                        <input type="tel" id="contactNumber" name="contactNumber" value="<?php echo $driverProfile ? htmlspecialchars($driverProfile['contactNumber']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="licensePlate">License Plate</label>
                        <input type="text" id="licensePlate" name="licensePlate" value="<?php echo $driverProfile ? htmlspecialchars($driverProfile['licensePlate']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3" required><?php echo $driverProfile ? htmlspecialchars($driverProfile['address']) : ''; ?></textarea>
                    </div>
                    
                    <?php if ($driverProfile && isset($driverProfile['blockchainUserID'])): ?>
                        <div class="form-group blockchain-info">
                            <label>Wallet ID</label>
                            <div class="readonly-field">
                                <span><?php echo htmlspecialchars($driverProfile['blockchainUserID']); ?></span>
                                <div class="field-note">Cannot be changed</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-actions">
                        <a href="admin_user_management.php" class="cancel-btn">Cancel</a>
                        <button type="submit" class="save-btn">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>

    </div>
    <script src="../scripts/admin_user_profile.js"></script>
</body>
</html>