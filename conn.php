<?php
// Database connection settings
$servername = "127.0.0.1";  // Keep this as a pure IP address or "localhost"
$username   = "root";       // Your MySQL username
$password   = "";           // Your MySQL password (empty by default in XAMPP)
$database   = "register";   // Your exact database name from phpMyAdmin
$port       = 3307;         // Port goes here as a separate variable

// Create connection - Notice $port is added as the 5th argument
$conn = new mysqli($servername, $username, $password, $database, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ── SAFEGUARD: Dynamic Constant Definition ─────────────────────────────────────
// If config.php wasn't loaded first, define defaults here to prevent fatal errors
if (!defined('CIPHER_METHOD')) {
    define('CIPHER_METHOD', 'aes-256-cbc');
}
if (!defined('ENCRYPTION_KEY')) {
    define('ENCRYPTION_KEY', 'C3f9A2b8D1e7F0c4B6a5E8d29c3b4a5f'); // Matches your secure 32-char key
}

// ── Helper encryption functions ───────────────────────────────────────────────
function encryptData($data) {
    if (empty($data)) return '';
    $ivLength = openssl_cipher_iv_length(CIPHER_METHOD);
    $iv = openssl_random_pseudo_bytes($ivLength);
    $encrypted = openssl_encrypt($data, CIPHER_METHOD, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}

function decryptData($data) {
    if (empty($data)) return '';
    $mix = base64_decode($data);
    if (strpos($mix, '::') === false) return $data; // Graceful fallback if the string is plain text
    list($iv, $encrypted) = explode('::', $mix, 2);
    return openssl_decrypt($encrypted, CIPHER_METHOD, ENCRYPTION_KEY, 0, $iv);
}
?>