<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');

require 'conn.php';
require 'config.php';
require __DIR__ . '/vendor/autoload.php';

/** @var mysqli $conn */

$conn->query("SET time_zone = '+08:00'");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Always receive RAW plain-text email from the URL
$rawEmail = strtolower(trim($_GET['email'] ?? ''));

if (empty($rawEmail) || !filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
    exit("Invalid email address.");
}

// Encrypt for DB lookup (email is stored encrypted)
$encryptedEmail = encryptData($rawEmail);

$stmt = $conn->prepare("
    SELECT reg_id, name, email_verified_at, verification_expires, attempts
    FROM users
    WHERE email = ?
");
$stmt->bind_param("s", $encryptedEmail);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    exit("No account found.");
}

$user = $result->fetch_assoc();
$stmt->close();

if ($user['email_verified_at']) {
    exit("This email is already verified. <a href='loginform.php'>Login here</a>");
}

// Cooldown: block resend if code was issued less than 5 minutes ago
if (!empty($user['verification_expires'])) {
    $secondsUntilExpires = strtotime($user['verification_expires']) - time();
    if ($secondsUntilExpires > 300) {
        $remainingSecs = $secondsUntilExpires - 300;
        $remainingMins = ceil($remainingSecs / 60);
        exit("A verification code was already sent recently. Please wait $remainingMins minute(s) before requesting again.");
    }
}

$code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date("Y-m-d H:i:s", strtotime("+10 minutes"));

$stmt = $conn->prepare("
    UPDATE users
    SET verification_code    = ?,
        verification_expires = ?,
        attempts             = 0
    WHERE reg_id = ?
");
$stmt->bind_param("ssi", $code, $expires, $user['reg_id']);
$stmt->execute();
$stmt->close();

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = trim(SMTP_PASS);
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;

    $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
    $mail->addAddress($rawEmail); // Send to the real email address
    $mail->isHTML(true);
    $mail->Subject = "Your new verification code";
    $mail->Body    = "
        <p>Hi <b>" . htmlspecialchars($user['name']) . "</b>,</p>
        <p>Your new verification code is: <b>$code</b></p>
        <p>This code expires in <b>10 minutes</b>.</p>
        <p>If you did not request this, please ignore this email.</p>
    ";

    $mail->send();

    // Redirect back with raw email in URL
    header("Location: verify.php?email=" . urlencode($rawEmail) . "&resent=1");
    exit();

} catch (Exception $e) {
    error_log("Resend code mail error for $rawEmail: " . $mail->ErrorInfo);
    exit("Failed to send verification email. Please try again later.");
}
?>