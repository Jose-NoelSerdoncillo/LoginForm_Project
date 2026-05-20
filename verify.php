<?php
// verify.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');

session_start();
require 'conn.php';
/** @var mysqli $conn */

// Get RAW email from URL (always plain text, never encrypted)
$email = strtolower(trim($_GET['email'] ?? ''));

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $email        = strtolower(trim($_POST['email']));
    $entered_code = trim($_POST['code']);

    if (empty($entered_code)) {
        $errors[] = "Please enter the 6-digit verification code.";
    } else {
        // Encrypt email for DB lookup (email is stored encrypted)
        $encryptedEmail = encryptData($email);

        $stmt = $conn->prepare("SELECT reg_id, verification_code, verification_expires, status FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $encryptedEmail);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $errors[] = "Account record not found.";
        } else {
            $user = $result->fetch_assoc();
            $now  = date("Y-m-d H:i:s");

            if ($user['status'] === 'active') {
                header("Location: loginform.php?verified=1");
                exit();
            } elseif ($user['verification_code'] !== $entered_code) {
                $errors[] = "Invalid verification code. Please try again.";
            } elseif ($user['verification_expires'] < $now) {
                $errors[] = "This verification code has expired. Please request a new one.";
            } else {
                $stmt_update = $conn->prepare("UPDATE users SET status = 'active', email_verified_at = ? WHERE reg_id = ?");
                $stmt_update->bind_param("si", $now, $user['reg_id']);
                $stmt_update->execute();
                $stmt_update->close();

                header("Location: loginform.php?verified=1");
                exit();
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email — Login Form Project</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --white: #ffffff;
            --border: #e8e6e0;
            --border-hover: #c8c4bc;
            --accent: #d4382a;
            --text1: #1a1917;
            --text2: #5c5a56;
            --text3: #9e9c98;
            --input-bg: #faf9f7;
            --success: #1a7a4a;
            --error: #c0392b;
            --warning: #d4860a;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1a1917;
            overflow-x: hidden;
            position: relative;
        }

        .auth-visual-bg { position: absolute; inset: 0; z-index: 1; overflow: hidden; }

        .visual-bg {
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 50% 50%, rgba(212,56,42,0.15) 0%, transparent 60%),
                radial-gradient(ellipse 50% 70% at 80% 20%, rgba(180,40,30,0.1) 0%, transparent 55%),
                #1a1917;
        }

        .geo-container { position: absolute; inset: 0; overflow: hidden; }
        .geo-lines { position: absolute; bottom: 0; right: 0; width: 100%; height: 100%; opacity: 0.07; }

        .hex-shape { position: absolute; clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%); }
        .hex-1 { width: 280px; height: 280px; background: rgba(212,56,42,0.05); top: 10%; left: 8%;  animation: floatHex1 8s  ease-in-out infinite; }
        .hex-2 { width: 140px; height: 140px; background: rgba(212,56,42,0.08); bottom: 15%; left: 25%; animation: floatHex2 10s ease-in-out infinite; }
        .hex-3 { width: 60px;  height: 60px;  background: rgba(255,255,255,0.03); top: 25%; right: 15%; animation: floatHex3 7s  ease-in-out infinite; }
        .hex-4 { width: 200px; height: 200px; background: rgba(255,255,255,0.01); bottom: 10%; right: 10%; animation: floatHex1 12s ease-in-out infinite reverse; }

        @keyframes floatHex1 { 0%,100%{transform:translateY(0) rotate(0deg)} 50%{transform:translateY(-18px) rotate(3deg)} }
        @keyframes floatHex2 { 0%,100%{transform:translateY(0) rotate(0deg)} 50%{transform:translateY(14px) rotate(-5deg)} }
        @keyframes floatHex3 { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }

        .dot-grid {
            position: absolute; inset: 0;
            background-image: radial-gradient(circle, rgba(255,255,255,0.06) 1px, transparent 1px);
            background-size: 32px 32px;
            opacity: 0.5;
        }

        .corner-accent { position: absolute; top: 2.5rem; right: 2.5rem; display: flex; align-items: center; gap: 6px; z-index: 3; }
        .corner-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--accent); opacity: 0.6; }
        .corner-dot.pulse { animation: pulseDot 2s ease-in-out infinite; }
        @keyframes pulseDot { 0%,100%{opacity:0.6;transform:scale(1)} 50%{opacity:1;transform:scale(1.3)} }
        .corner-text { font-size: 0.65rem; color: rgba(255,255,255,0.25); letter-spacing: 0.12em; text-transform: uppercase; font-family: 'Rajdhani', sans-serif; }

        .auth-form-popup {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            background: var(--white);
            padding: 3rem 2.5rem;
            border-radius: 6px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.05);
        }

        .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 1.75rem; }
        .brand-mark { width: 34px; height: 34px; background: var(--text1); clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%); }
        .brand-name { font-family: 'Rajdhani', sans-serif; font-size: 1.05rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text1); }

        .form-heading { font-family: 'Rajdhani', sans-serif; font-size: 2rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: var(--text1); margin-bottom: 0.35rem; line-height: 1; }

        .subtitle { font-size: 0.8125rem; color: var(--text3); margin-bottom: 1.75rem; letter-spacing: 0.01em; line-height: 1.6; }
        .subtitle strong { color: var(--text2); font-weight: 500; }

        .alert { display: flex; align-items: flex-start; gap: 8px; padding: 10px 12px; font-size: 0.78rem; line-height: 1.5; margin-bottom: 1.25rem; border-left: 2px solid transparent; }
        .alert-error   { border-color: var(--error);   color: var(--error);   background: rgba(192,57,43,0.06); }
        .alert-success { border-color: var(--success); color: var(--success); background: rgba(26,122,74,0.06); }
        .alert svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: 2px; }

        .field { margin-bottom: 1.25rem; }
        .field label { display: block; font-size: 0.68rem; font-weight: 500; color: var(--text3); margin-bottom: 5px; letter-spacing: 0.1em; text-transform: uppercase; }
        .field input { width: 100%; padding: 12px; background: var(--input-bg); border: 1px solid var(--border); border-radius: 3px; color: var(--text1); font-family: 'DM Sans', sans-serif; font-size: 1.1rem; letter-spacing: 0.3em; text-align: center; transition: border-color 0.15s, box-shadow 0.15s; outline: none; }
        .field input:hover  { border-color: var(--border-hover); }
        .field input:focus  { border-color: var(--text1); box-shadow: 0 0 0 2px rgba(26,25,23,0.08); }
        .field input::placeholder { color: var(--text3); letter-spacing: normal; }

        .field-error { font-size: 0.7rem; color: var(--error); margin-top: 4px; display: none; text-align: center; }
        .field.has-error input  { border-color: var(--error); }
        .field.has-error .field-error { display: block; }

        .btn-primary { width: 100%; padding: 11px; background: var(--text1); color: #fff; border: none; border-radius: 3px; font-family: 'Rajdhani', sans-serif; font-size: 0.95rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; cursor: pointer; transition: background 0.15s, transform 0.1s; margin-top: 0.5rem; }
        .btn-primary:hover  { background: #2e2c28; }
        .btn-primary:active { transform: scale(0.99); }

        .links { margin-top: 1.25rem; font-size: 0.8125rem; color: var(--text3); display: flex; justify-content: space-between; }
        .links a { color: var(--accent); text-decoration: none; font-weight: 500; }
        .links a:hover { text-decoration: underline; }

        @media (max-width: 480px) {
            body { padding: 1rem; }
            .auth-form-popup { padding: 2rem 1.5rem; }
            .corner-accent { display: none; }
        }
    </style>
</head>
<body>

<div class="auth-visual-bg">
    <div class="visual-bg"></div>
    <div class="dot-grid"></div>
    <div class="geo-container">
        <svg class="geo-lines" viewBox="0 0 800 900" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice">
            <line x1="0"    y1="900" x2="800" y2="0"   stroke="white" stroke-width="0.5"/>
            <line x1="100"  y1="900" x2="900" y2="0"   stroke="white" stroke-width="0.5"/>
            <line x1="-100" y1="900" x2="700" y2="0"   stroke="white" stroke-width="0.5"/>
            <line x1="200"  y1="900" x2="200" y2="0"   stroke="white" stroke-width="0.3"/>
            <line x1="400"  y1="900" x2="400" y2="0"   stroke="white" stroke-width="0.3"/>
            <line x1="600"  y1="900" x2="600" y2="0"   stroke="white" stroke-width="0.3"/>
            <line x1="0"    y1="200" x2="800" y2="200" stroke="white" stroke-width="0.3"/>
            <line x1="0"    y1="500" x2="800" y2="500" stroke="white" stroke-width="0.3"/>
            <line x1="0"    y1="700" x2="800" y2="700" stroke="white" stroke-width="0.3"/>
        </svg>
        <div class="hex-shape hex-1"></div>
        <div class="hex-shape hex-2"></div>
        <div class="hex-shape hex-3"></div>
        <div class="hex-shape hex-4"></div>
    </div>
    <div class="corner-accent">
        <div class="corner-dot pulse"></div>
        <div class="corner-dot"></div>
        <span class="corner-text">Secure</span>
    </div>
</div>

<div class="auth-form-popup">

    <div class="brand">
        <div class="brand-mark"></div>
        <span class="brand-name">Login Form Project</span>
    </div>

    <h1 class="form-heading">Verify Account</h1>
    <!-- Display the raw plain-text email, NOT the encrypted hash -->
    <p class="subtitle">
        We sent a 6-digit code to:<br>
        <strong><?= htmlspecialchars($email ?: 'your email address') ?></strong>
    </p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5M12 16v.5"/></svg>
            <div><?php foreach ($errors as $e): ?><?= htmlspecialchars($e) ?><br><?php endforeach; ?></div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['resent'])): ?>
        <div class="alert alert-success">
            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            A new code was sent to your inbox.
        </div>
    <?php endif; ?>

    <form action="verify.php" method="POST" id="verifyForm" novalidate>
        <!-- Always pass RAW email, never the encrypted version -->
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

        <div class="field">
            <label for="code">Verification Code</label>
            <input type="text" id="code" name="code" placeholder="000000"
                   maxlength="6" required autocomplete="one-time-code" pattern="\d{6}"
                   inputmode="numeric">
            <div class="field-error">Please enter the 6-digit code sent to your email.</div>
        </div>

        <button type="submit" name="verify_code" class="btn-primary">Verify Account →</button>
    </form>

    <div class="links">
        <a href="resend_code.php?email=<?= urlencode($email) ?>">Resend Code</a>
        <a href="registerform.php">Back to Register</a>
    </div>

</div>

<script>
    const form = document.getElementById('verifyForm');
    if (form) {
        form.addEventListener('submit', e => {
            const codeInp = document.getElementById('code');
            const bad = !codeInp || codeInp.value.trim() === '' || !/^\d{6}$/.test(codeInp.value.trim());
            codeInp.closest('.field').classList.toggle('has-error', bad);
            if (bad) e.preventDefault();
        });

        const codeInp = document.getElementById('code');
        codeInp.addEventListener('input', () => {
            codeInp.value = codeInp.value.replace(/\D/g, '');
            codeInp.closest('.field').classList.remove('has-error');
        });
    }
</script>

</body>
</html>