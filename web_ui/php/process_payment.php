<?php
session_start();
require_once '../vendor/autoload.php';

// Include authentication check
include('../php/auth_driver.php');

// Get violation details from POST
$violationId = $_POST['violation_id'] ?? '';
$amount = $_POST['amount'] ?? 0;
$violationType = $_POST['violation_type'] ?? '';

// Validate inputs
if (empty($violationId) || empty($amount) || empty($violationType)) {
    echo json_encode(['error' => 'Missing required payment information']);
    exit;
}

// Convert amount to cents (Stripe uses smallest currency unit)
$amountInCents = (float)$amount * 100;

// Stripe API key
\Stripe\Stripe::setApiKey('your_secret_key');

try {
    // Create a checkout session
    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'myr',
                'product_data' => [
                    'name' => "Traffic Violation: $violationType",
                    'description' => "Payment for violation ID: $violationId",
                ],
                'unit_amount' => $amountInCents,
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => 'http://<ip>/php/payment_success.php?session_id={CHECKOUT_SESSION_ID}&violation_id=' . $violationId,
        'cancel_url' => 'http://<ip>/php/pending_violation.php',
        'metadata' => [
            'violation_id' => $violationId,
            'driver_id' => $_SESSION['user_id']
        ],
    ]);

    echo json_encode(['id' => $checkout_session->id]);
} catch(Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
