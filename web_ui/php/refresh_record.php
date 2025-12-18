<?php
session_start();

// Check if the user is logged in and is an admin
include('../php/auth_admin.php');

require_once __DIR__ . '/../vendor/autoload.php';

try {
    $mongoClient = new MongoDB\Client("mongodb://localhost:27017");
    $violationsCollection = $mongoClient->chainguard->violations;

    // Find all violations with non-empty driverID and fabricPushed false.
    $pendingViolations = $violationsCollection->find([
        'driverID'     => ['$exists' => true, '$ne' => ''],
        'fabricPushed' => false
    ]);

    foreach ($pendingViolations as $violation) {
        $data = array(
            'adminID'            => $violation['adminID'],
            'violationID'        => (string)$violation['_id'],
            'driverID'           => $violation['driverID'],
            'violationType'      => $violation['violationType'],
            'location'           => $violation['location'],
            'penaltyAmount'      => $violation['penaltyAmount'],
            'timestamp'          => $violation['timestamp'],
            'licensePlateNumber' => $violation['licensePlateNumber'],
            'image'              => $violation['image'],
            'remark'             => $violation['remark'],
            'paymentStatus'      => $violation['paymentStatus'],
            'violationStatus'    => $violation['violationStatus']
        );

        $ch = curl_init('http://localhost:3000/api/createViolation');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $apiResponse = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log("cURL error: " . curl_error($ch));
        } else {
            $responseData = json_decode($apiResponse, true);
            if (isset($responseData['message'])) {
                $violationsCollection->updateOne(
                    ['_id' => $violation['_id']],
                    ['$set' => ['fabricPushed' => true]]
                );
            } else {
                error_log("API error: " . $apiResponse);
            }
        }
        curl_close($ch);
    }
    // After processing, redirect back with a status message.
        // header("Location: ../php/admin_logging.php?update=success");
    header("Location: ../php/admin_logging.php");
    $_SESSION['success'] = "Violation refreshed successfully.";
    exit;

} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    // header("Location: ../php/admin_logging.php?update=fail");
    header("Location: ../php/admin_logging.php");
    $_SESSION['error'] = "Fail to refresh violation.";
    exit;
}
