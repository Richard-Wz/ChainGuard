<?php
session_start();
require_once '../vendor/autoload.php';

// Include authentication check
include('../php/auth_driver.php');

// Check for session_id and violation_id
$sessionID = $_GET['session_id'] ?? '';
$violationID = $_GET['violation_id'] ?? '';

if (empty($sessionID) || empty($violationID)) {
    header("Location: ../php/pending_violation.php");
    exit;
}

// Set your Stripe API key
\Stripe\Stripe::setApiKey('your_test_secret_key);

// Card success (visa): 4242 4242 4242 4242
try {
    // Retrieve the session to confirm payment
    $session = \Stripe\Checkout\Session::retrieve($sessionID);
    
    // Check payment status
    if ($session->payment_status == 'paid') {
        // Echo something to test
        echo "Payment successful. Session ID: " . $sessionID;

        $mongoClient = (new MongoDB\Client("mongodb://localhost:27017"));
        $violationCollection = $mongoClient->chainguard->violations;

        // Update paymentStatus and violationStatus in MongoDB
        $violationCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($violationID)],
            ['$set' => ['paymentStatus' => true, 'violationStatus' => true]]
        );
        
        // Fetch violation details for the email
        $violationDetails = $violationCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($violationID)]);
        
        // Fetch driver information for the email
        $driverCollection = $mongoClient->chainguard->drivers;
        $driverInfo = $driverCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($_SESSION['user_id'])]);
        
        // Get admin ID from violation
        $adminID_v = $violationDetails['adminID'];

        $apiUrl = "http://localhost:3000/api/updateViolation";
        $data = [
            'adminID' => $adminID_v,
            'violationID' => $violationID,
            'newPaymentStatus' => true,
            'newViolationStatus' => true
        ];

        // Debugging
        // print_r($data);
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT'); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
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

        // Send email notification about successful payment
        include_once('../php/send_email_notification.php');
        
        $paymentDetails = [
            'violationType' => $violationDetails['violationType'],
            'amount' => $violationDetails['penaltyAmount'],
            'date' => date('Y-m-d H:i:s'),
            'violationId' => $violationID
        ];
        
        sendPaymentSuccessEmail($driverInfo['email'], $driverInfo['fullName'], $paymentDetails);
        
        $_SESSION['payment_success'] = true;
        $_SESSION['payment_message'] = "Payment successful. Your violation has been updated.";
    }
} catch (Exception $e) {
    $_SESSION['payment_error'] = $e->getMessage();
}

// Redirect back to pending violations
header("Location: ../php/pending_violation.php");
exit;
?>
