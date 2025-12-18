<?php
session_start();
require_once('../vendor/autoload.php');
include('../php/auth_admin.php');

// Get all the data needed for the report
$adminID = $_SESSION['user_id'];

// MongoDB Connection
$mongoClient = new MongoDB\Client("mongodb://localhost:27017");
$authoritiesCollection = $mongoClient->chainguard->traffic_authorities;
$driverCollection = $mongoClient->chainguard->drivers;

try {
    $objectID = new MongoDB\BSON\ObjectId($adminID);
} catch (Exception $e) {
    error_log("Invalid admin ID in session: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid admin session']);
    exit;
}

$adminDocument = $authoritiesCollection->findOne(['_id' => $objectID]);
if ($adminDocument && isset($adminDocument['blockchainUserID'])) {
    $blockchainUserID = $adminDocument['blockchainUserID'];
    $adminName = $adminDocument['name'] ?? 'Admin User';
    // Get number of driver from drivers collection (MongoDB)
    $driverCount = $driverCollection->countDocuments();
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Admin not found']);
    exit;
}

// Get violations and appeals data using the same multi-curl approach as in dashboard
$mh = curl_multi_init();

$violationsUrl = "http://localhost:3000/api/queryAllViolations?adminID=" . urlencode($blockchainUserID);
$appealsUrl = "http://localhost:3000/api/queryAllAppeals?adminID=" . urlencode($blockchainUserID);

$violationsCh = curl_init($violationsUrl);
curl_setopt($violationsCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($violationsCh, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

$appealsCh = curl_init($appealsUrl);
curl_setopt($appealsCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($appealsCh, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

curl_multi_add_handle($mh, $violationsCh);
curl_multi_add_handle($mh, $appealsCh);

$running = null;
do {
    curl_multi_exec($mh, $running);
} while ($running);

$violationsResponse = curl_multi_getcontent($violationsCh);
$appealsResponse = curl_multi_getcontent($appealsCh);

// Process data same as in dashboard
// (Same violation processing code as in admin_dashboard.php)
if (empty($violationsResponse)) {
    $violations = [];
    $totalViolations = 0;
    $totalFines = 0;
    $violationsByType = [
        'Dangerous Driving' => 0,
        'Poor Vehicle Condition' => 0,
        'Parking Violations' => 0,
        'Licensing and Documentation' => 0,
        'Safety Regulations' => 0
    ];
} else {
    $violations = json_decode($violationsResponse, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || isset($violations['error'])) {
        $violations = [];
        $totalViolations = 0;
        $totalFines = 0;
        $violationsByType = [
            'Dangerous Driving' => 0,
            'Poor Vehicle Condition' => 0,
            'Parking Violations' => 0,
            'Licensing and Documentation' => 0,
            'Safety Regulations' => 0
        ];
    } else {
        $totalViolations = count($violations);
        
        // Calculate total fines that have been paid
        $totalFines = array_reduce($violations, function($carry, $violation) {
            if (isset($violation['paymentStatus']) && $violation['paymentStatus'] === true) {
                return $carry + (float)$violation['penaltyAmount'];
            }
            return $carry;
        }, 0);

        // Count violations by type
        $violationsByType = [
            'Dangerous Driving' => 0,
            'Poor Vehicle Condition' => 0,
            'Parking Violations' => 0,
            'Licensing and Documentation' => 0,
            'Safety Regulations' => 0
        ];
        
        foreach ($violations as $violation) {
            $type = $violation['violationType'] ?? 'Unknown';
            if (isset($violationsByType[$type])) {
                $violationsByType[$type]++;
            }
        }
    }
}

// Process the appeals data
if (empty($appealsResponse)) {
    $appeals = [];
    $totalAppeals = 0;
} else {
    $appeals = json_decode($appealsResponse, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || isset($appeals['error'])) {
        $appeals = [];
        $totalAppeals = 0;
    } else {
        $totalAppeals = count($appeals);
    }
}

// Calculate monthly trends (simplified from dashboard)
$monthlyData = array_fill(0, 12, 0);
$currentYear = date('Y');

if (isset($violations) && is_array($violations)) {
    foreach ($violations as $violation) {
        if (isset($violation['timestamp'])) {
            $date = new DateTime($violation['timestamp']);
            $year = (int)$date->format('Y');
            $month = (int)$date->format('n') - 1; // 0-indexed months
            
            if ($year == $currentYear) {
                $monthlyData[$month]++;
            }
        }
    }
}

// Clean up curl handles
curl_multi_remove_handle($mh, $violationsCh);
curl_multi_remove_handle($mh, $appealsCh);
curl_multi_close($mh);

// Current timestamp for the report
$reportDate = date('Y-m-d H:i:s');

// Generate HTML for the PDF report
$html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .report-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .report-title {
            font-size: 24px;
            font-weight: bold;
            color: #2baf60;
        }
        .report-subtitle {
            font-size: 14px;
            color: #777;
        }
        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .stats-row {
            display: table-row;
        }
        .stat-card {
            display: table-cell;
            width: 25%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .stat-title {
            font-size: 14px;
            color: #555;
            margin-bottom: 5px;
        }
        .stat-value {
            font-size: 22px;
            font-weight: bold;
            color: #202124;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0 10px;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #777;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="report-header">
        <div class="report-title">ChainGuard Dashboard Report</div>
        <div class="report-subtitle">Generated on ' . $reportDate . ' by ' . htmlspecialchars($adminName) . '</div>
    </div>

    <div class="section-title">Summary Statistics</div>
    <div class="stats-grid">
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-title">Total Violations</div>
                <div class="stat-value">' . $totalViolations . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Fines Collected</div>
                <div class="stat-value">RM ' . number_format($totalFines, 2, '.', ',') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Pending Appeals</div>
                <div class="stat-value">' . $totalAppeals . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Registered Users</div>
                <div class="stat-value">' . $driverCount . '</div>
            </div>
        </div>
    </div>

    <div class="section-title">Violations by Type</div>
    <table>
        <thead>
            <tr>
                <th>Violation Type</th>
                <th>Count</th>
                <th>Percentage</th>
            </tr>
        </thead>
        <tbody>';

foreach ($violationsByType as $type => $count) {
    $percentage = $totalViolations > 0 ? round(($count / $totalViolations) * 100, 1) : 0;
    $html .= '
            <tr>
                <td>' . htmlspecialchars($type) . '</td>
                <td>' . $count . '</td>
                <td>' . $percentage . '%</td>
            </tr>';
}

$html .= '
        </tbody>
    </table>

    <div class="section-title">Monthly Violation Trends (' . $currentYear . ')</div>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th>Violations</th>
            </tr>
        </thead>
        <tbody>';

$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

foreach ($monthlyData as $i => $count) {
    $html .= '
            <tr>
                <td>' . $months[$i] . '</td>
                <td>' . $count . '</td>
            </tr>';
}

$html .= '
        </tbody>
    </table>

    <div class="footer">
        ChainGuard Traffic Violation Management System<br>
        This report contains confidential information. Please handle with care.
    </div>
</body>
</html>';

// Use HTML2PDF to generate PDF
use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Exception\ExceptionFormatter;

try {
    $html2pdf = new Html2Pdf('P', 'A4', 'en');
    $html2pdf->writeHTML($html);
    
    // Output the PDF as a download
    $pdfFileName = 'ChainGuard_Dashboard_Report_' . date('Y-m-d') . '.pdf';
    $html2pdf->output($pdfFileName, 'D');
} catch (Html2PdfException $e) {
    $html2pdf->clean();
    $formatter = new ExceptionFormatter($e);
    echo $formatter->getHtmlMessage();
}
?>