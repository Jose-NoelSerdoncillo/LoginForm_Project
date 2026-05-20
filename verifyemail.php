<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'conn.php';

//  Force PHP to use Manila Time
date_default_timezone_set('Asia/Manila');

//  Force MariaDB to use Manila Time for this database session
$conn->query("SET time_zone = '+08:00'");

if (isset($_POST['verify'])) {

    $email = strtolower(trim($_POST['email']));
    $code  = preg_replace('/\D/', '', trim($_POST['code']));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: verify.php?email=" . urlencode($email) . "&error=invalid_email");
        exit();
    }

    if (strlen($code) !== 6) {
        header("Location: verify.php?email=" . urlencode($email) . "&error=invalid_code");
        exit();
    }

    $stmt = $conn->prepare("
        SELECT reg_id, verification_code, verification_expires, email_verified_at, attempts
        FROM users
        WHERE email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: verify.php?email=" . urlencode($email) . "&error=no_account");
        exit();
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user['email_verified_at']) {
        header("Location: loginform.php?verified=1");
        exit();
    }

    if ($user['attempts'] >= 5) {
        header("Location: verify.php?email=" . urlencode($email) . "&error=too_many_attempts");
        exit();
    }

    // Fixed Expiration Check 

    $expirationTime = strtotime($user['verification_expires']);
    $currentTime    = time(); 

    if ($currentTime > $expirationTime) {
        header("Location: verify.php?email=" . urlencode($email) . "&error=expired");
        exit();
    }

    if ($user['email_verified_at']) {
        header("Location: loginform.php?verified=1");
        exit();
    }

    if ($user['attempts'] >= 5) {
        header("Location: verify.php?email=" . urlencode($email) . "&error=too_many_attempts");
        exit();
    }

    if (hash_equals($user['verification_code'], $code)) {

        $stmt = $conn->prepare("
            UPDATE users
            SET email_verified_at    = NOW(),
                verification_code    = NULL,
                verification_expires = NULL,
                attempts             = 0,
                status               = 'active'
            WHERE reg_id = ?
        ");
        $stmt->bind_param("i", $user['reg_id']);
        $stmt->execute();
        $stmt->close();

        header("Location: loginform.php?verified=1");
        exit();

    } else {

        $stmt = $conn->prepare("
            UPDATE users
            SET attempts = attempts + 1
            WHERE reg_id = ?
        ");
        $stmt->bind_param("i", $user['reg_id']);
        $stmt->execute();
        $stmt->close();

        $attemptsLeft = 4 - $user['attempts'];
        header("Location: verify.php?email=" . urlencode($email) . "&error=wrong_code&attempts_left=" . max(0, $attemptsLeft));
        exit();
    }
}
?>