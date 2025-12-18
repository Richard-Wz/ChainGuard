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
$profileImage = '';

try {
    // Composer for MongoDB
    require __DIR__ . '/../vendor/autoload.php';
    // MongoDB connection
    $mongoClient = new MongoDB\Client("mongodb://localhost:27017");
    $collection = $mongoClient->chainguard->drivers;

    try {
        $objectID = new MongoDB\BSON\ObjectID($userID);
    } catch (Exception $e) {
        error_log("Invalid user_id in session: " . $e->getMessage());
        header("Location: ../php/login.php");
        exit;
    }

    // Retrieve the driver profile using the user ID
    $driverProfile = $collection->findOne(['_id' => $objectID]);
    
} catch (Exception $e) {
    error_log("Error retrieving profile: " . $e->getMessage());
    $errorMsg = 'Error retrieving profile: ' . $e->getMessage();
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
    <title>ChainGuard - Profile</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">   
    <link rel="stylesheet" href="../css/driver_profile.css">
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
            <a href="../php/driver_appeal.php">
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
            <div class="page-title">Profile</div>
            <div class="header-actions">
                <div class="user-avatar">
                    <?php if ($driverProfile && isset($driverProfile['profileImage'])): ?>
                        <img src="<?php echo htmlspecialchars($driverProfile['profileImage']); ?>" alt="Profile Image">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Profile Container -->
        <div class="profile-container">
            <!-- Profile Image / Avatar -->
            <div class="profile-item">
                <div class="profile-image">
                    <?php if ($driverProfile && isset($driverProfile['profileImage'])): ?>
                        <img src="<?php echo htmlspecialchars($driverProfile['profileImage']); ?>" alt="Profile Image">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile Details -->
            <div class="profile-details">
                <?php if ($driverProfile): ?>
                    <h2><?php echo htmlspecialchars($driverProfile['fullName']); ?></h2>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($driverProfile['email']); ?></p>
                    <?php if (isset($driverProfile['blockchainUserID'])): ?>
                        <p><strong>Wallet ID:</strong> <?php echo htmlspecialchars($driverProfile['blockchainUserID']); ?></p>
                    <?php endif; ?>
                    <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($driverProfile['contactNumber']); ?></p>
                    <p><strong>License Plate:</strong> <?php echo htmlspecialchars($driverProfile['licensePlate']); ?></p>
                    <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($driverProfile['address'])); ?></p>
                    <!-- <?php if (isset($driverProfile['blockchainClientID'])): ?>
                        <p><strong>Blockchain Client ID:</strong> <?php echo htmlspecialchars($driverProfile['blockchainClientID']); ?></p>
                    <?php endif; ?> -->
                <?php else: ?>
                    <p>Error retrieving profile data.</p>
                <?php endif; ?>
            </div>
            <button class="edit-profile">Edit Profile</button>
        </div>
    </div>
    <script src="../scripts/driver_profile.js"></script>
</body>
</html>
