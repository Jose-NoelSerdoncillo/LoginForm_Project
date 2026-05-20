<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');
/** @var mysqli $conn */

session_start();
require 'conn.php';
require 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {

    $name     = trim($_POST['name']);
    $rawEmail = strtolower(trim($_POST['email']));
    $password = trim($_POST['password']);

    // Encrypt email for storage
    $encryptedEmail = encryptData($rawEmail);

    // 1. Check for duplicate email using the encrypted version
    $stmt = $conn->prepare("SELECT reg_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $encryptedEmail);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        header("Location: registerform.php?error=email_exists");
        exit();
    }
    $stmt->close();

    // 2. Generate verification code + expiry
    $code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date("Y-m-d H:i:s", strtotime("+10 minutes"));
    $hashed  = password_hash($password, PASSWORD_DEFAULT);

    // 3. Start transaction
    $conn->begin_transaction();

    try {
        // 4. Insert using encrypted email for storage
        $stmt = $conn->prepare("
            INSERT INTO users (name, email, password, verification_code, verification_expires, attempts, status)
            VALUES (?, ?, ?, ?, ?, 0, 'pending')
        ");
        $stmt->bind_param("sssss", $name, $encryptedEmail, $hashed, $code, $expires);
        $stmt->execute();
        $stmt->close();

        // 5. Send verification email to the REAL plain-text email address
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = trim(SMTP_PASS);
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($rawEmail); // Always use raw email for sending

        $mail->isHTML(true);
        $mail->Subject = "Verify your email";
        $mail->Body    = "
            <p>Hi <b>" . htmlspecialchars($name) . "</b>,</p>
            <p>Your verification code is: <b>$code</b></p>
            <p>This code expires in <b>10 minutes</b>.</p>
        ";

        $mail->send();

        $conn->commit();

        // 6. Redirect with RAW (plain-text) email in URL — never the encrypted hash
        header("Location: verify.php?email=" . urlencode($rawEmail));
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Register mail error for $rawEmail: " . $e->getMessage());
        header("Location: registerform.php?error=mail_failed");
        exit();
    }
}
?>