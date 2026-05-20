<?php
/**
 * config.php — Application configuration
 *
 * IMPORTANT: This file contains sensitive credentials.
 *   - Do NOT commit this file to version control.
 *   - Add it to your .gitignore immediately.
 *   - Ideally move this file one level ABOVE your web root.
 */

// ── SMTP / Email settings ─────────────────────────────────────────────────────

define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_USER',      'syncdeskus@gmail.com');
define('SMTP_PASS',      'jtuvldjwdquniffz'); 
define('SMTP_PORT',      587);
define('SMTP_FROM_NAME', 'System');

// ── Google OAuth settings ─────────────────────────────────────────────────────
// 1. Go to https://console.cloud.google.com/
// 2. Create a project → APIs & Services → Credentials → Create OAuth 2.0 Client ID
// 3. Application type: Web application
// 4. Authorized redirect URI: http://localhost/your-folder/google_callback.php
//    (change to your real domain when deploying)

define('GOOGLE_CLIENT_ID',     '455231610795-vlvca162fmjdq81ld5c3jrosuvd29fn2.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI',  'http://localhost/INFOMAN_Security_Project-main/INFOMAN_Security_Project-main/google_callback.php');

// ── Database settings (optional — move out of conn.php if preferred) ──────────
// define('DB_HOST', '127.0.0.1:3307');
// define('DB_USER', 'root');
// define('DB_PASS', '');
// define('DB_NAME', 'register');

// ── Advanced Security Encryption Keys ─────────────────────────────────────────
// This cryptographically strong key secures sensitive database records at rest
define('ENCRYPTION_KEY', 'C3f9A2b8D1e7F0c4B6a5E8d29c3b4a5f'); // Secure, fixed 32-character hex key
define('CIPHER_METHOD',  'aes-256-cbc');