<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');

session_start();
require 'conn.php'; // Contains your connection and encryptData() / decryptData() helper logic
require 'config.php';

// Force MySQL session timezone parameters to perfectly match local server timezone
$conn->query("SET time_zone = '+08:00'");

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    
    // 1. Grab inputs from the sign-in form using your correct markup name attribute
    // Change 'email' to 'username' below if your login form's input name attribute is name="username"
    $rawEmail = strtolower(trim($_POST['email'])); 
    $password = trim($_POST['password']);
    
    // Clean parameter validation checklist
    if (empty($rawEmail) || !filter_var($rawEmail, FILTER_VALIDATE_EMAIL) || empty($password)) {
        header("Location: loginform.php?error=invalid");
        exit();
    }

    // 2. CRITICAL SECURITY STEP: Scramble raw email input to match MariaDB's encrypted record format
    $secureEmail = encryptData($rawEmail); 

    // 3. Prepare optimized statement capturing full row information by secure encrypted hash string mapping
    $stmt = $conn->prepare("
        SELECT reg_id, password, email_verified_at, status, login_attempts, last_login_attempt 
        FROM users 
        WHERE email = ? 
        LIMIT 1
    ");
    $stmt->bind_param("s", $secureEmail);
    $stmt->execute();
    $result = $stmt->get_result();

    // No account found — still trigger standard generic message to mitigate risk of user enumeration vectors
    if ($result->num_rows === 0) {
        header("Location: loginform.php?error=invalid");
        exit();
    }

    $user = $result->fetch_assoc();

    // 4. Brute-Force Automated Attack Lockout Structural Tracking Check
    if ($user['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        $lockedUntil = strtotime($user['last_login_attempt']) + (LOCKOUT_MINUTES * 60);
        if (time() < $lockedUntil) {
            $minsLeft = ceil(($lockedUntil - time()) / 60);
            header("Location: loginform.php?error=locked&mins=" . $minsLeft);
            exit();
        } else {
            // Lockout period expired — refresh relational parameter attempts map to zero state
            $reset = $conn->prepare("UPDATE users SET login_attempts = 0 WHERE reg_id = ?");
            $reset->bind_param("i", $user['reg_id']);
            $reset->execute();
            $reset->close();
            $user['login_attempts'] = 0;
        }
    }

    // 5. Check password signature integrity BEFORE evaluation of validation state (Defends context leaks)
    $passwordOk = password_verify($password, $user['password']);

    if (!$passwordOk) {
        // Increment structural counter for authentication failures tracking metrics
        $inc = $conn->prepare("
            UPDATE users
            SET login_attempts     = login_attempts + 1,
                last_login_attempt = NOW()
            WHERE reg_id = ?
        ");
        $inc->bind_param("i", $user['reg_id']);
        $inc->execute();
        $inc->close();

        $attemptsLeft = MAX_LOGIN_ATTEMPTS - ($user['login_attempts'] + 1);

        if ($attemptsLeft <= 0) {
            header("Location: loginform.php?error=locked&mins=" . LOCKOUT_MINUTES);
        } else {
            header("Location: loginform.php?error=invalid&left=" . max(0, $attemptsLeft));
        }
        exit();
    }

    // 6. Security verification validation gates (Account states map checkpoint execution)
    if ($user['status'] !== 'active' || $user['email_verified_at'] === null) {
        // Maintain same error response parameters to deceive active network probing tools
        header("Location: loginform.php?error=invalid");
        exit();
    }

    // 7. SUCCESS: Reset brute tracking parameters back to baseline clear indices
    $reset = $conn->prepare("UPDATE users SET login_attempts = 0, last_login_attempt = NULL WHERE reg_id = ?");
    $reset->bind_param("i", $user['reg_id']);
    $reset->execute();
    $reset->close();

    // Prevent Session Fixation attacks by swapping active identifier signatures
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['reg_id'];
    header("Location: dashboard_ui.php");
    exit();
} else {
    // Drop back cleanly if script was invoked without valid POST payload elements
    header("Location: loginform.php");
    exit();
}
?>