<?php
session_start();

// Prevent caching of the page
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// Ensure the user is logged in and has 'admin' role
include('../php/auth_admin.php');

$adminID = $_SESSION['user_id'];
require_once __DIR__ . '/../vendor/autoload.php';

$mongoClient = (new MongoDB\Client("mongodb://localhost:27017"));  
$adminCollection = $mongoClient->chainguard->traffic_authorities;
$driverCollection = $mongoClient->chainguard->drivers;

try {
    $objectID = new MongoDB\BSON\ObjectId($adminID);
} catch (Exception $e) {
    error_log("Invalid admin id in session: " . $e->getMessage());
    header("Location: ../php/login.php");
    exit;
}

$adminDocument = $adminCollection->findOne(['_id' => $objectID]);
if ($adminDocument && isset($adminDocument['blockchainUserID'])) {
    $blockchainUserID = $adminDocument['blockchainUserID'];
} else {
    error_log("Admin document not found or blockchainUserID missing.");
    header("Location: ../php/login.php");
    exit;
}

// Node.js API URL
$apiUrl = "http://localhost:3000/api/queryAllAppeals?adminID=" . urlencode($blockchainUserID);
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

foreach ($appeals as &$appeal) {
    $driverDoc = $driverCollection->findOne([
        'blockchainClientID' => $appeal['driverID']
    ]);
    if ($driverDoc && !empty($driverDoc['fullName'])) {
        $appeal['driverFullName'] = $driverDoc['fullName'];
        $appeal['license'] = $driverDoc['licensePlate'];
        $appeal['email'] = $driverDoc['email'];
        $appeal['profileImage'] = $driverDoc['profileImage'];
    } else {
        $appeal['driverFullName'] = 'Unknown Driver';
    }
}
// Remove the reference to the last element
unset($appeal);

$filterDate = isset($_GET['date']) ? $_GET['date'] : '';
$filterLicense = isset($_GET['license']) ? $_GET['license'] : '';

if (is_array($appeals) && !empty($appeals)) {
    // Filter appeals based on date and license
    $appeals = array_filter($appeals, function($appeal) use ($filterDate, $filterLicense) {
        $match = true;
        if ($filterDate !== '') {
            if (!isset($appeal['timestamp']) || date("Y-m-d", strtotime($appeal['timestamp'])) !== $filterDate) {
                $match = false;
            }
        }
        if ($filterLicense !== '') {
            if (stripos($appeal['license'], $filterLicense) === false) {
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
        <title>ChainGuard - Appeal Management</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">   
        <link rel="stylesheet" href="../css/admin_appeal.css">
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

        <div class="menu-item active">
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
            <div class="page-title">Appeals</div>
            <div class="header-actions">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
            </div>
        </div>

        <div class="appeal-container">
            <div class="filter-form">
                <form method="GET" action="admin_appeal.php">        
                    <!-- License filter -->
                    <input type="text" name="license" id="license" placeholder="Search License Plate" 
                        value="<?php echo isset($_GET['license']) ? htmlspecialchars($_GET['license']) : ''; ?>" 
                        onchange="this.form.submit()">     
                    <!-- Date filter -->
                    <input type="date" name="date" id="date" value="<?php echo isset($_GET['date']) ? htmlspecialchars($_GET['date']) : ''; ?>" onchange="this.form.submit()">
                </form>
            </div>

            <table class="appeal-table">
                <thead>
                    <tr>
                        <th class="user-header">User</th>
                        <th>License</th>
                        <th>Violation ID</th>
                        <th>Date</th>
                        <th>Actions</th>
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
                                <td class="user-cell">
                                    <div class="user-profile">
                                        <div class="user-profile-icon">
                                            <?php if ($appeal && isset($appeal['profileImage'])): ?>
                                                <img src="<?php echo htmlspecialchars($appeal['profileImage']); ?>" alt="Profile Image">
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="user-details">
                                            <div class="user-name"><?php echo htmlspecialchars($appeal['driverFullName']); ?></div>
                                            <div class="user-email"><?php echo htmlspecialchars($appeal['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($appeal['license']); ?></td>
                                <td>
                                    <span class="violation-id" title="<?php echo htmlspecialchars($appeal['violationID']); ?>">
                                        <?php echo htmlspecialchars($appeal['violationID']); ?>
                                    </span>
                                </td>
                                

                                <td><?php echo date("M d, Y", strtotime($appeal['timestamp'])); ?></td>
                                
                                <!-- Action for accepting and rejecting --> 
                                <td>
                                    <div class="user-actions">
                                        <?php if ($appeal['status'] === 'Pending'): ?>
                                            <button class="btn btn-approve" data-id="<?php echo htmlspecialchars($appeal['appealID']); ?>">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-reject" data-id="<?php echo htmlspecialchars($appeal['appealID']); ?>">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="status <?php echo htmlspecialchars($appeal['status']); ?>"><?php echo htmlspecialchars($appeal['status']); ?></span>
                                        <?php endif; ?>
                                    </div>
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

    <script src="../scripts/admin_appeal.js"></script>
</body>
</html>