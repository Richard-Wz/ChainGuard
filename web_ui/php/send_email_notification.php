<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendWelcomeEmail($toEmail, $toName) {
    $mail = new PHPMailer(true);
    try {
        // SMTP settings for Gmail
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your_email_address';
        $mail->Password   = 'your_application_password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('your_email_address', 'ChainGuard');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Welcome to ChainGuard';
        $mail->Body    = "
            <h3>Dear $toName,</h3>
            <p>Welcome to ChainGuard! Your driver account has been successfully registered.</p>
            <p>You can now log in to access your driver dashboard and manage your traffic records.</p>
            <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
            <p>Best regards,<br>ChainGuard Team</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer error: " . $mail->ErrorInfo);
        error_log("Full exception: " . $e->getMessage());
        return false;
    }
}

function sendViolationEmail($toEmail, $toName, $violationDetails) {
    $mail = new PHPMailer(true);
    try {
        // SMTP settings for Gmail
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your_email_address';
        $mail->Password   = 'your_application_password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('your_email_address', 'ChainGuard');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Traffic Violation Notice';
        $mail->Body    = "
            <h3>Dear $toName,</h3>
            <p>You have a new traffic violation recorded in the ChainGuard system.</p>
            <ul>
                <li><strong>Violation:</strong> {$violationDetails['type']}</li>
                <li><strong>Location:</strong> {$violationDetails['location']}</li>
                <li><strong>Time:</strong> {$violationDetails['timestamp']}</li>
                <li><strong>Penalty:</strong> RM {$violationDetails['penalty']}</li>
                <li><strong>Remark:</strong> {$violationDetails['remark']}</li>
            </ul>
            <p>Please log in to your dashboard to review and take appropriate action.</p>
            <p>Best regards,<br>ChainGuard Team</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer error: " . $mail->ErrorInfo);
        error_log("Full exception: " . $e->getMessage());
        return false;
    }
}

function sendPaymentSuccessEmail($toEmail, $toName, $paymentDetails) {
    $mail = new PHPMailer(true);
    try {
        // SMTP settings for Gmail
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your_email_address';
        $mail->Password   = 'your_application_password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('your_email_address', 'ChainGuard');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Payment Confirmation - ChainGuard';
        $mail->Body    = "
            <h3>Dear $toName,</h3>
            <p>Thank you for your payment. We're confirming that your payment for the following traffic violation has been successfully processed:</p>
            <ul>
                <li><strong>Violation Type:</strong> {$paymentDetails['violationType']}</li>
                <li><strong>Amount Paid:</strong> RM {$paymentDetails['amount']}</li>
                <li><strong>Transaction Date:</strong> {$paymentDetails['date']}</li>
                <li><strong>Violation ID:</strong> {$paymentDetails['violationId']}</li>
            </ul>
            <p>Your violation record has been updated in our system and marked as resolved.</p>
            <p>If you have any questions about this payment, please contact our support team.</p>
            <p>Best regards,<br>ChainGuard Team</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer error: " . $mail->ErrorInfo);
        error_log("Full exception: " . $e->getMessage());
        return false;
    }
}
?>
