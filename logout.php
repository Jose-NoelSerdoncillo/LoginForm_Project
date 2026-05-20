<?php
// logout.php
session_start();

// Unset all session keys
$_SESSION = [];

// Destroy the tracking cookie session ID mapping
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear internal session registration completely
session_destroy();

// Redirect clean state users instantly back into the gateway interface
header("Location: loginform.php");
exit();