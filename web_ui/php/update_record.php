<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/send_email_notification.php';

/**
 * Checks for pending violations by license plate and pushes them to the Fabric network.
 *
 * @param MongoDB\Client $mongoClient       An instance of the MongoDB client.
 * @param string         $licensePlate      The license plate number to search for.
 * @param string         $blockchainClientID The registered blockchain client ID.
 * @param string         $email             The driver's email address.
 * @param string         $fullName          The driver's full name.
 */

function updatePendingViolations($mongoClient, $licensePlate, $blockchainClientID, $email, $fullName) {
    // Connect to the violations collection.
    $violationsCollection = $mongoClient->chainguard->violations;

    // Find all pending violations for the given license plate (fabricPushed is false).
    $pendingViolations = $violationsCollection->find([
        'licensePlateNumber' => $licensePlate,
        'fabricPushed'       => false
    ]);
    
    // Convert cursor to array to check if there are any violations
    $violationsArray = iterator_to_array($pendingViolations);
    
    // Only proceed if there are violations to process
    if (count($violationsArray) > 0) {
        foreach ($violationsArray as $violation) {
            // Prepare the data payload for the API call.
            $data = array(
                'adminID'            => $violation['adminID'],
                'violationID'        => (string)$violation['_id'],
                'driverID'           => $blockchainClientID,
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
            
            // Send the data payload to the Node.js API (Fabric network)
            $ch = curl_init('http://localhost:3000/api/createViolation');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $apiResponse = curl_exec($ch);

            if (curl_errno($ch)) {
                $err_msg = curl_error($ch);
                error_log("cURL error: " . $err_msg);
            } else {
                $responseData = json_decode($apiResponse, true);
                if (isset($responseData['message'])) {
                    // API call successful; update fabricPushed flag in MongoDB
                    $updateResult = $violationsCollection->updateOne(
                        ['_id' => $violation['_id']],
                        ['$set' => [
                            'driverID'    => $blockchainClientID,
                            'fabricPushed'=> true
                        ]]
                    );
                } else {
                    error_log("API error: " . $apiResponse);
                }
            }
            curl_close($ch);
        }

        // Send email notification to the driver only if there were violations processed
        if (!empty($email)) {
            $driverEmail = $email;
            $driverName = $fullName;

            // Get the last processed violation for the email
            // Or you could loop through all violations and include them all
            $lastViolation = end($violationsArray);
            
            // Prepare the violation details for the email
            $violationDetails = [
                'type' => $lastViolation['violationType'],
                'location' => $lastViolation['location'],
                'timestamp' => $lastViolation['timestamp'],
                'penalty' => $lastViolation['penaltyAmount'],
                'remark' => $lastViolation['remark']
            ];
            
            $emailSent = sendViolationEmail($driverEmail, $driverName, $violationDetails);
            if (!$emailSent) {
                error_log("Failed to send email notification to: $driverEmail");
            }
        }
    }
}
