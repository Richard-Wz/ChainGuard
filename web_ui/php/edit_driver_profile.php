<?php
session_start();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Ensure the user is logged in and has 'driver' role
include('../php/auth_driver.php');

$userID = $_SESSION['user_id'];
$driverProfile = null;
$successMsg = '';
$errorMsg = '';
$profileUpdated = false;
$imageUpdated = false;

try {
    // Composer for MongoDB
    require __DIR__ . '/../vendor/autoload.php';
    // MongoDB connection
    $mongoClient = new MongoDB\Client("mongodb://localhost:27017");
    $collection = $mongoClient->chainguard->drivers;
    $profileImage = '';

    // Retrieve the driver profile using the user ID
    $driverProfile = $collection->findOne(['_id' => new MongoDB\BSON\ObjectID($userID)]);
    if ($driverProfile && isset($driverProfile['profileImage'])) {
        $profileImage = $driverProfile['profileImage'];
    }

    try {
        $objectID = new MongoDB\BSON\ObjectID($userID);
    } catch (Exception $e) {
        error_log("Invalid user_id in session: " . $e->getMessage());
        header("Location: ../php/login.php");
        exit;
    }

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate and sanitize inputs
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
        
        // Validate that full name does not contain any digits
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
        
        // Validate license plate format
        $licensePlateRegex = '/^[A-HJ-NP-Z]{3}\d{1,4}[A-HJ-NP-Z]?$/';
        if (!empty($licensePlate) && !preg_match($licensePlateRegex, $licensePlate)) {
            $errors[] = "Invalid license plate number format. It must be 3 letters (A-H, J-N, P-Z) followed by 1-4 digits and optionally one letter (excluding I and O) at the end.";
        }
        
        // If there are any validation errors, concatenate them into $errorMsg
        if (!empty($errors)) {
            $errorMsg = implode(' ', $errors);
        } else {
            // Proceed to update the profile data if validation passes
            $updateResult = $collection->updateOne(
                ['_id' => $objectID],
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
            }
            
            // Handle profile image upload if provided
            if (isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $maxFileSize = 5 * 1024 * 1024; // Limit to 5MB
                
                if (in_array($_FILES['profileImage']['type'], $allowedTypes) && $_FILES['profileImage']['size'] <= $maxFileSize) {
                    // Read the image file content
                    $imageContent = file_get_contents($_FILES['profileImage']['tmp_name']);
                    
                    if ($imageContent !== false) {
                        // Create a MongoDB Binary object for the image
                        $imageBinary = new MongoDB\BSON\Binary($imageContent, MongoDB\BSON\Binary::TYPE_GENERIC);
                        
                        // Store metadata about the image
                        $imageMetadata = [
                            'filename' => basename($_FILES['profileImage']['name']),
                            'mimetype' => $_FILES['profileImage']['type'],
                            'size' => $_FILES['profileImage']['size'],
                            'uploaded_at' => new MongoDB\BSON\UTCDateTime()
                        ];
                        
                        // Update the profile with both the image binary and metadata
                        $updateResult = $collection->updateOne(
                            ['_id' => $objectID],
                            ['$set' => [
                                'profileImageBinary' => $imageBinary,
                                'profileImageMetadata' => $imageMetadata,
                                // Also store the base64 image for display purposes
                                'profileImage' => 'data:' . $_FILES['profileImage']['type'] . ';base64,' . base64_encode($imageContent)
                            ]]
                        );
                        
                        if ($updateResult->getModifiedCount() > 0) {
                            $imageUpdated = true;
                        } else {
                            $errorMsg = "Failed to update profile image in database.";
                        }
                    } else {
                        $errorMsg = "Failed to read image file.";
                    }
                } else {
                    $errorMsg = "Invalid file type or size. Please upload a JPG, PNG, or GIF image under 5MB.";
                }
            }
            
            // Build the success message based on what was updated
            if ($profileUpdated || $imageUpdated) {
                if ($profileUpdated && $imageUpdated) {
                    $successMsg = "Profile and avatar updated successfully!";
                } elseif ($profileUpdated) {
                    $successMsg = "Profile updated successfully!";
                } elseif ($imageUpdated) {
                    $successMsg = "Profile image updated successfully!";
                }
            } else {
                if (empty($errorMsg)) {
                    $errorMsg = "No changes were made to your profile.";
                }
            }
        }
    }

    // Retrieve the updated driver profile
    $driverProfile = $collection->findOne(['_id' => $objectID]);
    
} catch (Exception $e) {
    error_log("Error retrieving/updating profile: " . $e->getMessage());
    $errorMsg = 'Error: ' . $e->getMessage();
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
    <title>ChainGuard - Edit Profile</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">   
    <link rel="stylesheet" href="../css/driver_profile.css">
    <link rel="stylesheet" href="../css/edit_driver_profile.css">
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

        <div class="menu-item">
            <a href="../php/appeal_submission.php">
                <div class="menu-icon">
                    <i class="fas fa-gavel"></i>
                </div>
                Appeals
            </a>
        </div>

        <div class="menu-item active">
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
            <div class="page-title">Edit Profile</div>
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

        <!-- Edit Profile Form Container -->
        <div class="profile-edit-container">
            <form method="POST" action="edit_driver_profile.php" enctype="multipart/form-data">
                <!-- Profile Image -->
                <div class="form-section image-section">
                    <div class="profile-image-container">
                        <div class="profile-image-preview">
                            <?php if ($driverProfile && isset($driverProfile['profileImage'])): ?>
                                <img src="<?php echo htmlspecialchars($driverProfile['profileImage']); ?>" alt="Profile Image" id="profile-preview">
                            <?php else: ?>
                                <i class="fas fa-user" id="profile-icon"></i>
                            <?php endif; ?>
                        </div>
                        <div class="image-upload-controls">
                            <label for="profileImage" class="upload-btn">
                                <i class="fas fa-camera"></i> Change Photo
                            </label>
                            <input type="file" id="profileImage" name="profileImage" accept="image/*" style="display: none;">
                        </div>
                    </div>
                </div>

                <!-- Profile Details -->
                <div class="form-section details-section">
                    <h2>Personal Information</h2>
                    
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
                        <a href="driver_profile.php" class="cancel-btn">Cancel</a>
                        <button type="submit" class="save-btn">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script src="../scripts/edit_driver_profile.js"></script>
</body>
</html>