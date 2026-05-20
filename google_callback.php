<?php
/**
 * google_callback.php
 * Handles the redirect back from Google after the user grants permission.
 * Requires: league/oauth2-google  (composer require league/oauth2-google)
 */

// Start session securely before any logic processing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');

require 'conn.php';
require 'config.php';
require __DIR__ . '/vendor/autoload.php';

use League\OAuth2\Client\Provider\Google;

$provider = new Google([
    'clientId'     => GOOGLE_CLIENT_ID,
    'clientSecret' => GOOGLE_CLIENT_SECRET,
    'redirectUri'  => GOOGLE_REDIRECT_URI,
]);

// ── Step 1: No code yet — redirect user to Google ────────────────────────────
if (!isset($_GET['code'])) {

    // Added 'prompt' => 'select_account' to force Google to ask which account to use every time
    $authUrl = $provider->getAuthorizationUrl([
        'scope'  => ['openid', 'profile', 'email'],
        'prompt' => 'select_account' 
    ]);

    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: ' . $authUrl);
    exit();
}

// ── Step 2: CSRF check ───────────────────────────────────────────────────────
// FIX: Added !isset() check to gracefully prevent undefined array key warnings 
if (empty($_GET['state']) || !isset($_SESSION['oauth2state']) || $_GET['state'] !== $_SESSION['oauth2state']) {
    unset($_SESSION['oauth2state']);
    exit('Invalid OAuth state. Possible CSRF attack.');
}
unset($_SESSION['oauth2state']);

// ── Step 3: Exchange code for token ─────────────────────────────────────────
try {
    $token        = $provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
    
    /** @var \League\OAuth2\Client\Provider\GoogleUser $googleUser */
    $googleUser   = $provider->getResourceOwner($token);
    
    $googleId     = $googleUser->getId();
    $googleEmail  = strtolower(trim($googleUser->getEmail()));
    $googleName   = $googleUser->getName();

} catch (Exception $e) {
    error_log('Google OAuth error: ' . $e->getMessage());
    header('Location: loginform.php?error=google_failed');
    exit();
}

// ── Step 4: Find or create the user ─────────────────────────────────────────
$conn->query("SET time_zone = '+08:00'");

// Try to find by google_id first
$stmt = $conn->prepare("SELECT reg_id, status FROM users WHERE google_id = ? LIMIT 1");
$stmt->bind_param("s", $googleId);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

if ($user) {
    // Existing Google user — just log in
    if ($user['status'] !== 'active') {
        header('Location: loginform.php?error=google_inactive');
        exit();
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['reg_id'];
    header('Location: dashboard_ui.php');
    exit();
}

// No google_id match — check if email already exists (password user)
$stmt = $conn->prepare("SELECT reg_id, status, google_id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $googleEmail);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

if ($user) {
    // Email exists — link Google to that account
    $stmt = $conn->prepare("
        UPDATE users
        SET google_id         = ?,
            email_verified_at = COALESCE(email_verified_at, NOW()),
            status            = 'active'
        WHERE reg_id = ?
    ");
    $stmt->bind_param("si", $googleId, $user['reg_id']);
    $stmt->execute();
    $stmt->close();

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['reg_id'];
    header('Location: dashboard_ui.php');
    exit();
}

// Brand-new user — register them automatically
$stmt = $conn->prepare("
    INSERT INTO users (name, email, google_id, email_verified_at, status, attempts)
    VALUES (?, ?, ?, NOW(), 'active', 0)
");
$stmt->bind_param("sss", $googleName, $googleEmail, $googleId);
$stmt->execute();
$newId = $conn->insert_id;
$stmt->close();

session_regenerate_id(true);
$_SESSION['user_id'] = $newId;
header('Location: dashboard_ui.php');
exit();