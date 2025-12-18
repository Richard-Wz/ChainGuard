<?php
session_start();

// Prevent caching of the page
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// Ensure the user is logged in and has 'driver' role
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

// debug
// print_r($blockchainUserID);

$apiUrl = $apiUrl = "http://localhost:3000/api/queryMyViolations?driverID=" . urlencode($blockchainUserID);
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

// Filter for Violation (no longer in use but keep for reference) - Redesigned to reference with object at API level
// if (is_array($violations)) {
//     $violations = array_filter($violations, function($violation) {
//         return !empty(trim($violation['violationType'])) &&
//                 !empty(trim($violation['location'])) &&
//                 !empty(trim($violation['licensePlateNumber'])) &&
//                 !empty(trim($violation['adminID']));
//     });
// }

// Retrieve filter criteria from the query string.
$filterDate = isset($_GET['date']) ? $_GET['date'] : '';
$filterType = isset($_GET['violationType']) ? $_GET['violationType'] : '';

// If filters are set, filter the violations array.
if (is_array($violations) && !empty($violations)) {
    $violations = array_filter($violations, function($violation) use ($filterDate, $filterType) {
        $match = true;
        // Date filter: check that 'timestamp' exists before filtering.
        if ($filterDate !== '') {
            if (!isset($violation['timestamp']) || date("Y-m-d", strtotime($violation['timestamp'])) !== $filterDate) {
                $match = false;
            }
        }
        // Violation type filter (case-insensitive) with check for existence.
        if ($filterType !== '') {
            if (!isset($violation['violationType']) || strcasecmp($violation['violationType'], $filterType) !== 0) {
                $match = false;
            }
        }
        return $match;
    });
    
    // Sort violations by timestamp in descending order (newest first)
    usort($violations, function($a, $b) {
        $timeA = strtotime($a['timestamp']);
        $timeB = strtotime($b['timestamp']);
        return $timeB - $timeA; 
    });
}

// Pagination Control
// Set number of records per page
$pageSize = 10;

// Determine current page from query string; default to 1
$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($currentPage < 1) {
    $currentPage = 1;
}

// Total violations count
$totalViolations = is_array($violations) ? count($violations) : 0;

// Calculate total pages
$totalPages = ceil($totalViolations / $pageSize);

// Slice the violations array for the current page
$startIndex = ($currentPage - 1) * $pageSize;
$paginatedViolations = is_array($violations) ? array_slice($violations, $startIndex, $pageSize) : [];
?>

<!DOCTYPE html>
<head>
    <html lang="en">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
        <meta http-equiv="Pragma" content="no-cache" />
        <meta http-equiv="Expires" content="0" />
        <title>ChainGuard - Violation History</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">   
        <link rel="stylesheet" href="../css/violation_history.css">
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

        <div class="menu-item active">
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
            <div class="page-title">Violation History</div>
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

        <div class="records-container">
            <!-- Filter Form -->
            <div class="filter-form">
                <form method="GET" action="violation_history.php">          
                    <!-- Violation type filter -->
                    <select id="violationType" name="violationType" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="Dangerous Driving" <?php if(isset($_GET['violationType']) && $_GET['violationType'] == "Dangerous Driving") echo 'selected'; ?>>Dangerous Driving</option>
                        <option value="Poor Vehicle Condition" <?php if(isset($_GET['violationType']) && $_GET['violationType'] == "Poor Vehicle Condition") echo 'selected'; ?>>Poor Vehicle Condition</option>
                        <option value="Parking Violations" <?php if(isset($_GET['violationType']) && $_GET['violationType'] == "Parking Violations") echo 'selected'; ?>>Parking Violations</option>
                        <option value="Licensing and Documentation" <?php if(isset($_GET['violationType']) && $_GET['violationType'] == "Licensing and Documentation") echo 'selected'; ?>>Licensing and Documentation</option>
                        <option value="Safety Regulations" <?php if(isset($_GET['violationType']) && $_GET['violationType'] == "Safety Regulations") echo 'selected'; ?>>Safety Regulations</option>
                    </select>
                    <!-- Date filter -->
                    <input type="date" name="date" id="date" value="<?php echo isset($_GET['date']) ? htmlspecialchars($_GET['date']) : ''; ?>" onchange="this.form.submit()">
                </form>
            </div>
            
            <table class="records-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Violation</th>
                        <th>Location</th>
                        <th>Fine</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($paginatedViolations) && is_array($paginatedViolations)): ?>
                        <?php foreach ($paginatedViolations as $violation): ?>
                            <tr 
                                data-violation-id="<?php echo htmlspecialchars($violation['violationID']); ?>"
                                data-date="<?php echo date("M d, Y h:i A", strtotime($violation['timestamp'])); ?>"
                                data-violation="<?php echo htmlspecialchars($violation['violationType']); ?>"
                                data-license="<?php echo htmlspecialchars($violation['licensePlateNumber']); ?>"
                                data-fine="RM <?php echo htmlspecialchars($violation['penaltyAmount']); ?>"
                                data-payment-status="<?php echo isset($violation['paymentStatus']) ? ($violation['paymentStatus'] ? 'true' : 'false') : 'unknown'; ?>"
                                data-violation-status="<?php echo isset($violation['violationStatus']) ? ($violation['violationStatus'] ? 'true' : 'false') : 'unknown'; ?>"
                                data-location="<?php echo htmlspecialchars($violation['location']); ?>"
                                data-remark="<?php echo htmlspecialchars($violation['remark']); ?>"
                                data-image="<?php echo htmlspecialchars($violation['image']); ?>"
                            >
                                <td><?php echo date("M d, Y", strtotime($violation['timestamp'])); ?></td>
                                <td><?php echo htmlspecialchars($violation['violationType']); ?></td>
                                <td><?php echo htmlspecialchars($violation['location']); ?></td>
                                <td>RM <?php echo htmlspecialchars($violation['penaltyAmount']); ?></td>
                                <td>
                                    <?php if(isset($violation['paymentStatus']) && isset($violation['violationStatus'])): ?>
                                        <?php if($violation['paymentStatus'] === false && $violation['violationStatus'] === false): ?>
                                            <span class="status pending">Pending</span>
                                        <?php elseif($violation['paymentStatus'] === false && $violation['violationStatus'] === true): ?>
                                            <span class="status approved">Approved</span>
                                        <?php elseif($violation['paymentStatus'] === true && $violation['violationStatus'] === true): ?>
                                            <span class="status paid">Paid</span>
                                        <?php else: ?>
                                            <span class="status unknown">Unknown</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="status unknown">Unknown</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No violation records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="table-footer">
                <span>Showing <?php echo count($paginatedViolations); ?> of <?php echo $totalViolations; ?> results</span>
                <div class="pagination">
                    <?php if ($currentPage > 1): ?>
                        <a href="?page=<?php echo $currentPage - 1; ?>&date=<?php echo urlencode($filterDate); ?>"><button>Previous</button></a>
                    <?php endif; ?>

                    <?php
                    // Display page numbers (simple version: list all pages)
                    for ($page = 1; $page <= $totalPages; $page++):
                        if ($page == $currentPage):
                    ?>
                        <a href="?page=<?php echo $page; ?>"><button class="active"><?php echo $page; ?></button></a>
                    <?php else: ?>
                        <a href="?page=<?php echo $page; ?>"><button><?php echo $page; ?></button></a>
                    <?php
                        endif;
                    endfor;
                    ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?php echo $currentPage + 1; ?>"><button>Next</button></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Pop Up -->
    <div id="violationModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2>Violation Details</h2>
            <div class="modal-details">
                <p><strong>Violation ID:</strong> 
                    <span id="modalViolationID"></span>
                    <button id="copyBtn" onclick="copyViolationID()" title="Copy Violation ID" style="border: none; background: none; cursor: pointer;">
                        <i class="fas fa-copy"></i>
                    </button>
                </p>
                <p><strong>Date:</strong> <span id="modalDate"></span></p>
                <p><strong>Violation:</strong> <span id="modalViolation"></span></p>
                <p><strong>License Plate:</strong> <span id="modalLicense"></span></p>
                <p><strong>Fine:</strong> <span id="modalFine"></span></p>
                <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                <p><strong>Location:</strong> <span id="modalLocation"></span></p>
                <p><strong>Remark:</strong> <span id="modalRemark"></span></p>
                <div id="modalImageContainer">
                    <img id="modalImage" src="" alt="Violation Image">
                </div>
            </div>
        </div>
    </div>

    <script src="../scripts/violation_history.js"></script>
</body>
</html>