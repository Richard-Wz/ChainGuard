<?php

session_start();
header('Content-Type: application/json');

// Prevent caching of the page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Ensure the user is logged in and has 'admin' role
include('../php/auth_admin.php');

// Initialize response array
$response = ['success' => false];

// Check if POST data exists
if (!isset($_POST['appealID']) || !isset($_POST['action'])) {
    $response['message'] = 'Missing required parameters';
    echo json_encode($response);
    exit;
}

$adminID = $_SESSION['user_id'];
$appealID = $_POST['appealID'];
$action = $_POST['action']; 
$response['post'] = $_POST; 

// Get admin's blockchainUserID from MongoDB
require_once __DIR__ . '/../vendor/autoload.php';
$mongoClient = (new MongoDB\Client("mongodb://localhost:27017"));
$adminCollection = $mongoClient->chainguard->traffic_authorities;
$appealsCollection = $mongoClient->chainguard->appeals;
$violationsCollection = $mongoClient->chainguard->violations;

// Check if adminID is a valid ObjectId
try {
    $objectID = new MongoDB\BSON\ObjectId($adminID);
} catch (Exception $e) {
    $response['message'] = 'Invalid admin ID: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}

$adminDocument = $adminCollection->findOne(['_id' => $objectID]);
if (!$adminDocument || !isset($adminDocument['blockchainUserID'])) {
    $response['message'] = 'Admin not found';
    echo json_encode($response);
    exit;
}

$blockchainUserID = $adminDocument['blockchainUserID'];
$newStatus = ($action === 'Approved') ? 'Approved' : 'Rejected';

// Prepare the API URL for the Node.js fabric network
$apiUrl = 'http://localhost:3000/api/updateAppealStatus';
$postData = [
    'adminID' => $blockchainUserID,
    'appealID' => $appealID,
    'newStatus' => $newStatus
];

// Make API call to update blockchain
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT'); 
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

$apiResponse = curl_exec($ch);

// Check for curl errors
if (curl_errno($ch)) {
    $response['message'] = 'API Error: ' . curl_error($ch);
    $response['debug_info'] = ['curl_error' => curl_error($ch)];
    curl_close($ch);
    echo json_encode($response);
    exit;
}

// Get HTTP status code
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Debug information
$response['debug_info'] = [
    'httpCode' => $httpCode,
    'rawResponse' => $apiResponse
];

// If the API endpoint doesn't exist or returns an error
if ($httpCode >= 400) {
    $response['message'] = 'API Error: Endpoint not found or not accepting requests. Status code: ' . $httpCode;
    $response['debug_info']['requestPayload'] = $postData;
    echo json_encode($response);
    exit;
}

// Parse API response
$apiResponseData = json_decode($apiResponse, true);
$response['blockchain_response'] = $apiResponseData;

// Proceed with MongoDB update if API call was successful
try {
    // Update appeal status in MongoDB
    $updateResult = $appealsCollection->updateOne(
        ['appealID' => $appealID],
        ['$set' => ['status' => $newStatus]]
    );
    
    if ($updateResult->getModifiedCount() == 0) {
        $response['mongodb_appeal_update'] = 'No documents were updated';
    } else {
        $response['mongodb_appeal_update'] = 'Updated successfully';
    }

    // If the action is 'Approved', update the violation status
    if ($action === 'Approved') {
        // Get violationID from appeal
        $appeal = $appealsCollection->findOne(['appealID' => $appealID]);
        if ($appeal && isset($appeal['violationID'])) {
            $violationID = $appeal['violationID'];
            $response['violationID'] = $violationID; 

            // Call API to update violation status in blockchain
            $violationApiUrl = 'http://localhost:3000/api/updateViolation';
            $violationPostData = [
                'adminID' => $blockchainUserID,
                'violationID' => $violationID,
                'newPaymentStatus' => false,
                'newViolationStatus' => true
            ];
            
            // Make API call
            $ch = curl_init($violationApiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT'); 
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($violationPostData));
            
            $violationResponse = curl_exec($ch);
            $violationHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Add violation API response to debug info
            $response['debug_info']['violation_api'] = [
                'httpCode' => $violationHttpCode,
                'rawResponse' => $violationResponse
            ];
            
            // Update violation in MongoDB
            $violationUpdateResult = $violationsCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($violationID)],
                ['$set' => ['paymentStatus' => false, 'violationStatus' => true]]
            );
            
            if ($violationUpdateResult->getModifiedCount() == 0) {
                $response['mongodb_violation_update'] = 'No violation documents were updated';
            } else {
                $response['mongodb_violation_update'] = 'Violation updated successfully';
            }
        } else {
            $response['violation_error'] = 'No violationID found in appeal document';
        }
    }
    
    $response['success'] = true;
    $response['message'] = 'Appeal ' . strtolower($newStatus) . ' successfully';
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Database error: ' . $e->getMessage();
    $response['error_trace'] = $e->getTraceAsString();
}

// Send single JSON response
echo json_encode($response);
?>