<?php
session_start();

include('../php/auth_admin.php');
require '../vendor/autoload.php';

// Check if id is provided
if (!isset($_GET['id'])) {
    header("Location: admin_user_management.php");
    exit;
}

$driverId = $_GET['id'];

// Get adminID from session (this is the MongoDB _id of the admin document)
$adminID = $_SESSION['user_id'];

// Connect to MongoDB
$mongoClient = new MongoDB\Client("mongodb://localhost:27017");

// Use MongoDB\BSON\ObjectId to create an ObjectId for the driver
use MongoDB\BSON\ObjectId;
try {
    $objectId = new ObjectId($driverId);
} catch (Exception $e) {
    header("Location: admin_user_management.php");
    exit;
}

// Retrieve the driver's document from the drivers collection to extract the blockchain client ID
$driversCollection = $mongoClient->chainguard->drivers;
$driverDocument = $driversCollection->findOne(['_id' => $objectId]);
if (!$driverDocument) {
    header("Location: admin_user_management.php");
    exit;
}
if (isset($driverDocument['blockchainClientID'])) {
    $driverBlockchainID = $driverDocument['blockchainClientID'];
    $driverWalletID = $driverDocument['blockchainUserID'];
} else {
    header("Location: admin_user_management.php");
    exit;
}

// Delete the driver document and log the deletion result
$result = $driversCollection->deleteOne(['_id' => $objectId]);
$deletedDriverCount = $result->getDeletedCount();
if ($deletedDriverCount === 0) {
    error_log("Deletion failed: No driver document was removed from MongoDB.");
} else {
    error_log("Driver document deleted successfully. Deleted count: " . $deletedDriverCount);
}

// Delete violation records from the violations collection based on driver's blockchainClientID
$violationsCollection = $mongoClient->chainguard->violations;
$deleteViolationsResult = $violationsCollection->deleteMany(['driverID' => $driverBlockchainID]);
$deletedViolationsCount = $deleteViolationsResult->getDeletedCount();
error_log("Violation records deleted from MongoDB: " . $deletedViolationsCount);

// Delete appeal records from the appeals collection based on driver's blockchainClientID
$appealsCollection = $mongoClient->chainguard->appeals;
$deleteAppealsResult = $appealsCollection->deleteMany(['driverID' => $driverWalletID]);
$deletedAppealsCount = $deleteAppealsResult->getDeletedCount();
error_log("Appeal records deleted from MongoDB: " . $deletedAppealsCount);

// Retrieve blockchainUserID (admin wallet ID) from the traffic_authorities collection using session adminID
$authoritiesCollection = $mongoClient->chainguard->traffic_authorities;
try {
    $adminObjectId = new ObjectId($adminID);
} catch (Exception $e) {
    header("Location: admin_user_management.php");
    exit;
}
$adminDocument = $authoritiesCollection->findOne(['_id' => $adminObjectId]);
if (isset($adminDocument['blockchainUserID'])) {
    $adminWalletID = $adminDocument['blockchainUserID'];
} else {
    header("Location: admin_user_management.php");
    exit;
}

// Build API URL with the admin's blockchain wallet ID and the driver's blockchain client ID
$apiUrl = "http://localhost:3000/api/deleteAllViolationsForDriver?adminID=" . urlencode($adminWalletID) . "&driverID=" . urlencode($driverBlockchainID);


// Debugging: log the API URL
// error_log("Calling API: " . $apiUrl);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
$apiResponse = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    error_log("cURL error while calling API: " . $error_msg);
} else {
    error_log("API response (HTTP code $httpStatus): " . $apiResponse);
}
curl_close($ch);

// Build API URL for deleting appeals
$appealsApiUrl = "http://localhost:3000/api/deleteAllAppealsForDriver?adminID=" . urlencode($adminWalletID) . "&driverID=" . urlencode($driverBlockchainID);

// Call API to delete all appeals
$ch = curl_init($appealsApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
$appealsApiResponse = curl_exec($ch);
$appealsHttpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    error_log("cURL error while deleting appeals: " . $error_msg);
} else {
    error_log("Appeals API response (HTTP code $appealsHttpStatus): " . $appealsApiResponse);
}
curl_close($ch);

// Redirect back to admin dashboard
header("Location: admin_user_management.php");
exit;
?>
