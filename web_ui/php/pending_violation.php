<?php

// Stripe Payment Gateway is using the free testing key

session_start();

// Prevent caching of the page
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// Include the authentication check
include('../php/auth_driver.php');

$driverID = $_SESSION['user_id'];
require_once __DIR__ . '/../vendor/autoload.php';

$mongoClient = (new MongoDB\Client("mongodb://localhost:27017"));
$driverCollection = $mongoClient->chainguard->drivers;

try {
    $objectID = new MongoDB\BSON\ObjectId($driverID);
} catch (Exception $e) {
    error_log("Invalid driver id in session: " . $e->getMessage());
    header("Location: ../php/login.php");
    exit;
}

$driverDocument = $driverCollection->findOne(['_id' => $objectID]);
if ($driverDocument && isset($driverDocument['blockchainUserID'])) {
    $blockchainUserID = $driverDocument['blockchainUserID'];
    
    // Get profile image if available
    $profileImage = '';
    if (isset($driverDocument['profileImage'])) {
        $profileImage = $driverDocument['profileImage'];
    }
} else {
    error_log("Driver document not found or blockchainUserID missing.");
    header("Location: ../php/login.php");
    exit;
}

$apiUrl = "http://localhost:3000/api/queryMyViolations?driverID=" . urlencode($blockchainUserID);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
$response = curl_exec($ch);

if (curl_errno($ch)) {
    error_log("cURL error: " . curl_error($ch));
    $violations = [];
} else {
    $violations = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        $violations = [];
    }
}
curl_close($ch);

if (is_array($violations) && isset($violations['error'])) {
    error_log("API error: " . $violations['error']);
    $violations = [];
}

if (is_array($violations)) {
    $violations = array_filter($violations, function($violation) {
        return !empty(trim($violation['violationType'])) &&
                !empty(trim($violation['location'])) &&
                !empty(trim($violation['licensePlateNumber'])) &&
                !empty(trim($violation['adminID']));
    });
}

// Filter for pending violations (paymentStatus false, and violationStatus false)
$pendingViolations = [];
if (is_array($violations)) {
    $pendingViolations = array_filter($violations, function($violation) {
        // A violation is pending when both paymentStatus and violationStatus are false
        return isset($violation['paymentStatus']) && isset($violation['violationStatus']) && $violation['paymentStatus'] === false && $violation['violationStatus'] === false;
    });
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
        <title>ChainGuard - Pending Violation</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">   
        <link rel="stylesheet" href="../css/pending_violation.css">
        <!-- Add Stripe JS -->
        <script src="https://js.stripe.com/v3/"></script>
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
        <div class="menu-item active">
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
            <div class="page-title">Pending Violations</div>
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

        <!-- Notification for payment success or error -->
        <?php if (isset($_SESSION['payment_success'])): ?>
            <div class="notification success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['payment_message']); ?></span>
                <span class="close-notification"><i class="fas fa-times"></i></span>
                <?php unset($_SESSION['payment_success']); ?>
                <?php unset($_SESSION['payment_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['payment_error'])): ?>
            <div class="notification error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['payment_error']); ?></span>
                <span class="close-notification"><i class="fas fa-times"></i></span>
                <?php unset($_SESSION['payment_error']); ?>
            </div>
        <?php endif; ?>

        <!-- Violations Record Container -->
        <div class="violations-container">
            <?php if (!empty($pendingViolations)): ?>
                <?php foreach ($pendingViolations as $violation): ?>
                    <div class="violation-item">
                        <div class="violation-details">
                            <div class="violation-title"><?php echo htmlspecialchars($violation['violationType']); ?></div>
                            <div class="violation-location"><?php echo htmlspecialchars($violation['location']); ?></div>
                            <div class="violation-metadata">
                                <span class="violation-date">
                                    <i class="far fa-calendar date-icon"></i>
                                    <?php echo date("M d, Y", strtotime($violation['timestamp'])); ?>
                                </span>
                                <span class="violation-time">
                                    <i class="far fa-clock time-icon"></i>
                                    <?php echo date("h:i A", strtotime($violation['timestamp'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="violation-actions">
                            <div class="violation-status">
                                <span class="status-tag">Unpaid</span>
                            </div>
                            <div class="violation-amount">RM <?php echo htmlspecialchars(number_format($violation['penaltyAmount'], 2)); ?></div>
                            <button class="pay-button" 
                                data-violation-id="<?php echo htmlspecialchars($violation['violationID']); ?>"
                                data-amount="<?php echo htmlspecialchars($violation['penaltyAmount']); ?>"
                                data-violation-type="<?php echo htmlspecialchars($violation['violationType']); ?>">
                                Pay Now
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-violation">No pending violations.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add a loading spinner for payment processing -->
    <div id="payment-processing" class="payment-overlay" style="display: none;">
        <div class="spinner-container">
            <div class="spinner"></div>
            <p>Processing payment...</p>
        </div>
    </div>

    <script src="../scripts/pending_violation.js"></script>
</body>
</html>