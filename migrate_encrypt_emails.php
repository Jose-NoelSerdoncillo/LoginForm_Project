<?php
/**
 * migrate_encrypt_emails.php
 * 
 * ONE-TIME SCRIPT — Run this ONCE to encrypt any plain-text emails
 * left in the database from before encryption was added.
 * 
 * HOW TO USE:
 *   1. Upload this file to your server (same folder as conn.php)
 *   2. Visit it in your browser: http://yoursite.com/migrate_encrypt_emails.php
 *   3. DELETE this file immediately after running it
 * 
 * SAFE TO RE-RUN — already-encrypted rows are detected and skipped.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'conn.php'; // Provides $conn and encryptData() / decryptData()

$result = $conn->query("SELECT reg_id, email FROM users");
if (!$result) {
    die("Query failed: " . $conn->error);
}

$updated = 0;
$skipped = 0;
$errors  = 0;
$log     = [];

while ($row = $result->fetch_assoc()) {
    $raw = $row['email'];

    // If it's already a valid email address, it's plain-text — needs encrypting
    if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
        $encrypted = encryptData($raw);

        $stmt = $conn->prepare("UPDATE users SET email = ? WHERE reg_id = ?");
        $stmt->bind_param("si", $encrypted, $row['reg_id']);

        if ($stmt->execute()) {
            $log[] = "✅ ID {$row['reg_id']}: encrypted {$raw}";
            $updated++;
        } else {
            $log[] = "❌ ID {$row['reg_id']}: UPDATE failed — " . $stmt->error;
            $errors++;
        }
        $stmt->close();

    } else {
        // Already encrypted — skip
        $log[] = "⏭ ID {$row['reg_id']}: already encrypted, skipped";
        $skipped++;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Encryption Migration</title>
    <style>
        body { font-family: monospace; background: #0d0d0d; color: #e8e6e0; padding: 2rem; }
        h1   { color: #d4382a; font-size: 1.4rem; margin-bottom: 1rem; }
        .summary { display: flex; gap: 2rem; margin-bottom: 1.5rem; }
        .stat { background: #141414; border: 1px solid #222; padding: 1rem 1.5rem; border-radius: 4px; }
        .stat .n { font-size: 2rem; font-weight: bold; }
        .stat .l { font-size: 0.75rem; color: #9e9c98; text-transform: uppercase; letter-spacing: 0.1em; }
        .green { color: #22c77a; }
        .yellow { color: #f5a623; }
        .red { color: #f25757; }
        .log-box { background: #060606; border: 1px solid #1a1917; border-radius: 4px; padding: 1.5rem; max-height: 400px; overflow-y: auto; line-height: 1.8; font-size: 0.85rem; }
        .warn { margin-top: 1.5rem; padding: 1rem; background: rgba(212,56,42,0.1); border-left: 3px solid #d4382a; color: #f5a623; border-radius: 3px; }
    </style>
</head>
<body>
<h1>Email Encryption Migration</h1>

<div class="summary">
    <div class="stat"><div class="n green"><?= $updated ?></div><div class="l">Encrypted</div></div>
    <div class="stat"><div class="n yellow"><?= $skipped ?></div><div class="l">Skipped</div></div>
    <div class="stat"><div class="n red"><?= $errors ?></div><div class="l">Errors</div></div>
</div>

<div class="log-box">
    <?php foreach ($log as $line): ?>
        <?= htmlspecialchars($line) ?><br>
    <?php endforeach; ?>
</div>

<div class="warn">
    ⚠️ <strong>DELETE this file now!</strong> Do not leave migrate_encrypt_emails.php on your server.
</div>
</body>
</html>
