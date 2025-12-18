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

// print_r($blockchainUserID);

// Node.js API URL
$apiUrl = 'http://localhost:3000/api/queryMyAppeals?driverID=' . $blockchainUserID;
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
$response = curl_exec($ch);

if (curl_errno($ch)) {
    error_log("cURL error: " . curl_error($ch));
    $appeals = [];
} else {
    $appeals = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        $appeals = [];
    }
}
curl_close($ch);

if (is_array($appeals) && isset($appeals['error'])) {
    error_log("API error: " . $appeals['error']);
    $appeals = [];
}

// Extract driver names from the driver collection
foreach ($appeals as &$appeal) {
    $driverDoc = $driverCollection->findOne([
        'blockchainClientID' => $appeal['driverID']
    ]);
    if ($driverDoc && !empty($driverDoc['fullName'])) {
        $appeal['driverFullName'] = $driverDoc['fullName'];
    } else {
        $appeal['driverFullName'] = 'Unknown Driver';
    }
}
// Remove the reference to the last element
unset($appeal); 

$filterDate = isset($_GET['date']) ? $_GET['date'] : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';

if (is_array($appeals) && !empty($appeals)) {
    $appeals = array_filter($appeals, function($appeal) use ($filterDate, $filterStatus) {
        $match = true;
        
        // Date filter
        if ($filterDate !== '') {
            if (!isset($appeal['timestamp']) || date("Y-m-d", strtotime($appeal['timestamp'])) !== $filterDate) {
                $match = false;
            }
        }
        
        // Status filter
        if ($filterStatus !== '') {
            if (!isset($appeal['status']) || $appeal['status'] !== $filterStatus) {
                $match = false;
            }
        }
        
        return $match;
    });

    // Sort appeals by timestamp in descending order (newest first)
    usort($appeals, function($a, $b) {
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
$totalAppeals = is_array($appeals) ? count($appeals) : 0;

// Calculate total pages
$totalPages = ceil($totalAppeals / $pageSize);

// Slice the violations array for the current page
$startIndex = ($currentPage - 1) * $pageSize;
$paginatedAppeals = is_array($appeals) ? array_slice($appeals, $startIndex, $pageSize) : [];

?>

<!DOCTYPE html>
<head>
    <html lang="en">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
        <meta http-equiv="Pragma" content="no-cache" />
        <meta http-equiv="Expires" content="0" />
        <title>ChainGuard - Appeal</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">   
        <link rel="stylesheet" href="../css/driver_appeal.css">
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

        <div class="appeal-container">
            <div class="filter-form">
                <form method="GET" action="driver_appeal.php">    
                    <!-- Status filter -->
                    <select name="status" id="status" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="Pending" <?php echo (isset($_GET['status']) && $_GET['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="Approved" <?php echo (isset($_GET['status']) && $_GET['status'] === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo (isset($_GET['status']) && $_GET['status'] === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                    <!-- Date filter -->
                    <input type="date" name="date" id="date" value="<?php echo isset($_GET['date']) ? htmlspecialchars($_GET['date']) : ''; ?>" onchange="this.form.submit()">
                </form>
            </div>

            <table class="appeal-table">
                <thead>
                    <tr>
                        <th>Appeal ID</th>
                        <th>Violation ID</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($paginatedAppeals) && is_array($paginatedAppeals)): ?>
                        <?php foreach ($paginatedAppeals as $appeal): ?>
                            <tr
                                data-appeal-id="<?php echo htmlspecialchars($appeal['appealID']); ?>"
                                data-violation-id="<?php echo htmlspecialchars($appeal['violationID']); ?>"
                                data-driver-name="<?php echo htmlspecialchars($appeal['driverFullName']); ?>"
                                data-appeal-text="<?php echo htmlspecialchars($appeal['appealText']); ?>"
                                data-status="<?php echo htmlspecialchars($appeal['status']); ?>"
                                data-date="<?php echo date("M d, Y h:i A", strtotime($appeal['timestamp'])); ?>"
                                data-image="<?php echo htmlspecialchars($appeal['evidence']); ?>"
                            >
                                <td>
                                    <span class="appeal-id" title="<?php echo htmlspecialchars($appeal['appealID']); ?>">
                                        <?php echo htmlspecialchars($appeal['appealID']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="violation-id" title="<?php echo htmlspecialchars($appeal['violationID']); ?>">
                                        <?php echo htmlspecialchars($appeal['violationID']); ?>
                                    </span>
                                </td>
                                <td><?php echo date("M d, Y", strtotime($appeal['timestamp'])); ?></td>
                                <td>
                                    <span class="status <?php echo htmlspecialchars($appeal['status']); ?>">
                                        <?php echo htmlspecialchars($appeal['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No appeal records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="table-footer">
                <span>Showing <?php echo count($paginatedAppeals); ?> of <?php echo $totalAppeals; ?> results</span>
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
            <div class="make-appeal-container">
                <a href="../php/appeal_submission.php">
                    <button class="make-appeal-button">Make Appeal</button>
                </a>
            </div>
        </div>
    </div>

    <!-- Pop Up Modal -->
    <div id="appealModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2>Appeal Details</h2>
            <div class="modal-details">
                <p><strong>Appeal ID:</strong><span id="modelAppealID"></span></p>
                <p><strong>Violation ID:</strong><span id="modalViolationID"></span></p>
                <p><strong>Driver Name:</strong> <span id="modalDriverName"></span></p>
                <p><strong>Appeal Text:</strong> <span id="modalAppealText"></span></p>
                <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                <p><strong>Date:</strong> <span id="modalDate"></span></p>
                <div id="modalImageContainer">
                    <img id="modalImage" src="" alt="Appeal Image">
                </div>
            </div>
        </div>
    </div>

    <script src="../scripts/driver_appeal.js"></script>
</body>
</html>