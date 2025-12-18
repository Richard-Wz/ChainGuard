<?php
session_start();

// Prevent caching of the page
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// Ensure the user is logged in and has 'admin' role
include('../php/auth_admin.php');

$adminID = $_SESSION['user_id'];
require_once('../vendor/autoload.php');

// MongoDB Connection
$mongoClient = new MongoDB\Client("mongodb://localhost:27017");
$authoritiesCollection = $mongoClient->chainguard->traffic_authorities;
$driverCollection = $mongoClient->chainguard->drivers;

try {
    $objectID = new MongoDB\BSON\ObjectId($adminID);
} catch (Exception $e) {
    error_log("Invalid admin ID in session: " . $e->getMessage());
    header("Location: ../php/login.php");
    exit;
}

$adminDocument = $authoritiesCollection->findOne(['_id' => $objectID]);
if ($adminDocument && isset($adminDocument['blockchainUserID'])) {
    $blockchainUserID = $adminDocument['blockchainUserID'];
    // Get number of driver from drivers collection (MongoDB)
    $driverCount = $driverCollection->countDocuments();
} else {
    error_log("Admin document not found or blockchainUserID not set for admin ID: " . $adminID);
    header("Location: ../php/login.php");
    exit;
}

// Create a multi curl handle
$mh = curl_multi_init();

// Setup both API calls
$violationsUrl = "http://localhost:3000/api/queryAllViolations?adminID=" . urlencode($blockchainUserID);
$appealsUrl = "http://localhost:3000/api/queryAllAppeals?adminID=" . urlencode($blockchainUserID);

// Violations curl handle
$violationsCh = curl_init($violationsUrl);
curl_setopt($violationsCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($violationsCh, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

// Appeals curl handle
$appealsCh = curl_init($appealsUrl);
curl_setopt($appealsCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($appealsCh, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

// Add the handles to multi curl
curl_multi_add_handle($mh, $violationsCh);
curl_multi_add_handle($mh, $appealsCh);

// Execute all queries simultaneously
$running = null;
do {
    curl_multi_exec($mh, $running);
} while ($running);

// Get the results
$violationsResponse = curl_multi_getcontent($violationsCh);
$appealsResponse = curl_multi_getcontent($appealsCh);

// Process violations
if (empty($violationsResponse)) {
    error_log("cURL error for violations: " . curl_error($violationsCh));
    $violations = [];
    $totalViolations = 0;
    $totalFines = 0;
    // For Graph Data (Types of Violation)
    $totalViolations_DangerousDriving = 0;
    $totalViolations_PoorVehicleCondition = 0;
    $totalViolations_ParkingViolations = 0;
    $totalViolations_LicensingAndDocumentation = 0;
    $totalViolations_SafetyRegulations = 0;
} else {
    $violations = json_decode($violationsResponse, true);
    // Remove debug print that pollutes the output
    // print_r("Violation Debug: " . $violationsResponse);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error for violations: " . json_last_error_msg());
        $violations = [];
        $totalViolations = 0;
        $totalFines = 0;
        $totalViolations_DangerousDriving = 0;
        $totalViolations_PoorVehicleCondition = 0;
        $totalViolations_ParkingViolations = 0;
        $totalViolations_LicensingAndDocumentation = 0;
        $totalViolations_SafetyRegulations = 0;
    } else if (isset($violations['error'])) {
        // Handle API errors properly
        error_log("API error for violations: " . $violations['error']);
        $violations = [];
        $totalViolations = 0;
        $totalFines = 0;
        $totalViolations_DangerousDriving = 0;
        $totalViolations_PoorVehicleCondition = 0;
        $totalViolations_ParkingViolations = 0;
        $totalViolations_LicensingAndDocumentation = 0;
        $totalViolations_SafetyRegulations = 0;
    } else {
        $totalViolations = count($violations);
        
        // Calculate total fines that have been paid
        $totalFines = array_reduce($violations, function($carry, $violation) {
            if (isset($violation['paymentStatus']) && $violation['paymentStatus'] === true) {
                return $carry + (float)$violation['penaltyAmount'];
            }
            return $carry;
        }, 0);

        $totalViolations_DangerousDriving = array_reduce($violations, function($carry, $violation) {
            return $carry + ($violation['violationType'] === 'Dangerous Driving' ? 1 : 0);
        }, 0);
        $totalViolations_PoorVehicleCondition = array_reduce($violations, function($carry, $violation) {
            return $carry + ($violation['violationType'] === 'Poor Vehicle Condition' ? 1 : 0);
        }, 0);
        $totalViolations_ParkingViolations = array_reduce($violations, function($carry, $violation) {
            return $carry + ($violation['violationType'] === 'Parking Violations' ? 1 : 0);
        }, 0);
        $totalViolations_LicensingAndDocumentation = array_reduce($violations, function($carry, $violation) {
            return $carry + ($violation['violationType'] === 'Licensing and Documentation' ? 1 : 0);
        }, 0);
        $totalViolations_SafetyRegulations = array_reduce($violations, function($carry, $violation) {
            return $carry + ($violation['violationType'] === 'Safety Regulations' ? 1 : 0);
        }, 0);
    }
}

// Calculate violation counts grouped by year, month, and week
$violationsByYearAndMonth = [];
$availableYears = [];

if (isset($violations) && is_array($violations)) {
    foreach ($violations as $violation) {
        if (isset($violation['timestamp'])) {
            // Convert timestamp string to DateTime object
            $date = new DateTime($violation['timestamp']);
            $year = (int)$date->format('Y');
            $month = (int)$date->format('n') - 1; // 0-indexed months
            
            // Initialize year array if it doesn't exist
            if (!isset($violationsByYearAndMonth[$year])) {
                $violationsByYearAndMonth[$year] = array_fill(0, 12, 0);
                $availableYears[] = $year;
            }
            
            // Increment the count for this month in this year
            $violationsByYearAndMonth[$year][$month]++;
        }
    }
}

// Calculate violation counts by day for each month
$violationsByDayInMonth = [];

if (isset($violations) && is_array($violations)) {
    foreach ($violations as $violation) {
        if (isset($violation['timestamp'])) {
            // Convert timestamp string to DateTime object
            $date = new DateTime($violation['timestamp']);
            $year = (int)$date->format('Y');
            $month = (int)$date->format('n'); // 1-12
            $day = (int)$date->format('j'); // 1-31
            
            // Initialize year and month arrays if they don't exist
            if (!isset($violationsByDayInMonth[$year])) {
                $violationsByDayInMonth[$year] = [];
            }
            if (!isset($violationsByDayInMonth[$year][$month])) {
                // Create array with size equal to days in month
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $violationsByDayInMonth[$year][$month] = array_fill(0, $daysInMonth, 0);
            }
            
            // Increment the count for this day (adjust index to be 0-based)
            if ($day > 0 && $day <= count($violationsByDayInMonth[$year][$month])) {
                $violationsByDayInMonth[$year][$month][$day-1]++;
            }
        }
    }
}

// Convert to JSON for JavaScript
$dailyViolationsJSON = json_encode($violationsByDayInMonth);

// Sort years in descending order (newest first)
rsort($availableYears);

// Set the current selected year (default to most recent, or current year if no data)
$currentYear = !empty($availableYears) ? $availableYears[0] : date('Y');

// Get data for the current year (or empty array if no data for that year)
$currentYearData = isset($violationsByYearAndMonth[$currentYear]) ? 
    $violationsByYearAndMonth[$currentYear] : array_fill(0, 12, 0);

// Convert to JSON for JavaScript
$monthlyViolationCountsJSON = json_encode($currentYearData);
$violationsByYearJSON = json_encode($violationsByYearAndMonth);
$availableYearsJSON = json_encode($availableYears);

// Set current month (default to current month if no data)
$currentMonth = !empty($availableYears) ? date('n') : date('n');

// Process appeals
if (empty($appealsResponse)) {
    error_log("cURL error for appeals: " . curl_error($appealsCh));
    $appeals = [];
    $totalAppeals = 0;
} else {
    $appeals = json_decode($appealsResponse, true);
    // Remove debug print that pollutes the output
    // print_r("Appeal Debug: " . $appealsResponse);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error for appeals: " . json_last_error_msg());
        $appeals = [];
        $totalAppeals = 0;
    } else if (isset($appeals['error'])) {
        // Handle API errors properly
        error_log("API error for appeals: " . $appeals['error']);
        $appeals = [];
        $totalAppeals = 0;
    } else {
        // Count pending appeals by the status = 'Pending'
        $totalAppeals = array_reduce($appeals, function($carry, $appeal) {
            return $carry + ($appeal['status'] === 'Pending' ? 1 : 0);
        }, 0);
    }
}

// Clean up
curl_multi_remove_handle($mh, $violationsCh);
curl_multi_remove_handle($mh, $appealsCh);
curl_multi_close($mh);

?>

<!DOCTYPE html>
<head>
    <html lang="en">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>ChainGuard - Dashboard</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">   
        <link rel="stylesheet" href="../css/admin_dashboard.css">
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
            <div class="page-title">Dashboard Overview</div>
            <div class="header-actions">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-container">
            <div class="action-buttons">
                <h2 class="dashboard-header">Statistics</h2>
                <div class="button-group">
                    <button class="print-btn"><i class="fas fa-download"></i>Export</button>
                </div>
            </div>
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-title">Total Violations</div>
                        <div class="stat-details">
                            <?php if ($totalViolations > 0): ?>
                                <div class="stat-value"><?php echo htmlspecialchars($totalViolations); ?></div>
                            <?php else: ?>
                                <div class="stat-value">0</div>
                                <div class="stat-empty">No violations recorded</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="stat-icon violation">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-title">Fines Collected</div>
                        <div class="stat-details">
                            <?php if ($totalFines > 0): ?>
                                <div class="stat-value">RM <?php echo htmlspecialchars(number_format($totalFines, 2, '.', ',')); ?></div>
                            <?php else: ?>
                                <div class="stat-value">RM 0.00</div>
                                <div class="stat-empty">No fines collected</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="stat-icon fine">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-title">Pending Appeals</div>
                        <div class="stat-details">
                            <?php if ($totalAppeals > 0): ?>
                                <div class="stat-value"><?php echo htmlspecialchars($totalAppeals); ?></div>
                            <?php else: ?>
                                <div class="stat-value">0</div>
                                <div class="stat-empty">No pending appeals</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="stat-icon appeal">
                        <i class="fas fa-gavel"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-title">Users</div>
                        <div class="stat-details">
                            <?php if ($driverCount > 0): ?>
                                <div class="stat-value"><?php echo htmlspecialchars($driverCount); ?></div>
                            <?php else: ?>
                                <div class="stat-value">0</div>
                                <div class="stat-empty">No users registered</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="stat-icon user">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
    
            <div class="charts-container">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Violations by Type</h3>
                    </div>
                    <div class="chart-content" id="violations-by-type-chart" 
                        data-dangerous="<?php echo htmlspecialchars($totalViolations_DangerousDriving); ?>"
                        data-vehicle="<?php echo htmlspecialchars($totalViolations_PoorVehicleCondition); ?>"
                        data-parking="<?php echo htmlspecialchars($totalViolations_ParkingViolations); ?>"
                        data-licensing="<?php echo htmlspecialchars($totalViolations_LicensingAndDocumentation); ?>"
                        data-safety="<?php echo htmlspecialchars($totalViolations_SafetyRegulations); ?>">
                        <?php if ($totalViolations > 0): ?>
                            <canvas id="violation-type-chart"></canvas>
                        <?php else: ?>
                            <div class="empty-chart-message">
                                <i class="fas fa-chart-bar"></i>
                                <p>No violation data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-header-title">
                            <h3 class="monthly-trend-title">Monthly Trends</h3>
                            <?php if (!empty($availableYears)): ?>
                                <div class="year-selector">
                                    <select id="year-filter">
                                        <?php foreach($availableYears as $year): ?>
                                            <option value="<?php echo $year; ?>" <?php echo ($year == $currentYear) ? 'selected' : ''; ?>>
                                                <?php echo $year; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="chart-content" id="monthly-trends-chart" 
                        data-monthly='<?php echo htmlspecialchars($monthlyViolationCountsJSON); ?>'
                        data-years='<?php echo htmlspecialchars($violationsByYearJSON); ?>'
                        data-available-years='<?php echo htmlspecialchars($availableYearsJSON); ?>'>
                        <?php if ($totalViolations > 0): ?>
                            <canvas id="trends-chart"></canvas>
                        <?php else: ?>
                            <div class="empty-chart-message">
                                <i class="fas fa-chart-line"></i>
                                <p>No monthly trend data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Weekly Trends -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-header-title">
                            <h3 class="weekly-trend-title">Daily Activity</h3>
                            <?php if (!empty($availableYears)): ?>
                                <div class="month-selector">
                                    <select id="month-filter">
                                        <option value="1">January</option>
                                        <option value="2">February</option>
                                        <option value="3">March</option>
                                        <option value="4">April</option>
                                        <option value="5">May</option>
                                        <option value="6">June</option>
                                        <option value="7">July</option>
                                        <option value="8">August</option>
                                        <option value="9">September</option>
                                        <option value="10">October</option>
                                        <option value="11">November</option>
                                        <option value="12">December</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="chart-content" id="daily-activity-chart" 
                        data-daily-violations='<?php echo htmlspecialchars($dailyViolationsJSON); ?>'>
                        <?php if ($totalViolations > 0): ?>
                            <canvas id="daily-chart"></canvas>
                        <?php else: ?>
                            <div class="empty-chart-message">
                                <i class="fas fa-calendar-day"></i>
                                <p>No daily activity data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <script>
                // Set the current date/time for printing
                document.querySelector('.dashboard-container').setAttribute(
                    'data-print-date', 
                    new Date().toLocaleString()
                );
            </script>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>