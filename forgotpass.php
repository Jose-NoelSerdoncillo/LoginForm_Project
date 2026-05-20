<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');

require 'conn.php';
/** @var mysqli $conn */

require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot'])) {

    $email = strtolower(trim($_POST['email']));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: forgotpass.php?error=invalid_email");
        exit();
    }

    $stmt = $conn->prepare("
        SELECT reg_id, name, status, email_verified_at, reset_requested_at
        FROM users WHERE email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: forgotpass.php?status=sent");
        exit();
    }

    $user = $result->fetch_assoc();

    if ($user['status'] !== 'active' || !$user['email_verified_at']) {
        header("Location: forgotpass.php?status=sent");
        exit();
    }

    if (!empty($user['reset_requested_at'])) {
        $cooldownSecs  = 5 * 60;
        $secondsPassed = time() - strtotime($user['reset_requested_at']);
        if ($secondsPassed >= 0 && $secondsPassed < $cooldownSecs) {
            $remainingMins = ceil(($cooldownSecs - $secondsPassed) / 60);
            header("Location: forgotpass.php?error=cooldown&mins=" . $remainingMins);
            exit();
        }
    }

    $token       = bin2hex(random_bytes(32));
    $expires     = date("Y-m-d H:i:s", strtotime("+1 hour"));
    $requestedAt = date("Y-m-d H:i:s");

    $stmt = $conn->prepare("
        UPDATE users
        SET reset_token = ?, reset_expires = ?, reset_requested_at = ?
        WHERE reg_id = ?
    ");
    $stmt->bind_param("sssi", $token, $expires, $requestedAt, $user['reg_id']);
    $stmt->execute();

    $resetLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
        . '://' . $_SERVER['HTTP_HOST']
        . dirname($_SERVER['PHP_SELF'])
        . '/resetpass.php?token=' . urlencode($token);

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'syncdeskus@gmail.com';
        $mail->Password = trim(SMTP_PASS);
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('syncdeskus@gmail.com', 'Login Form Project');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = "Password Reset Request";
        $mail->Body    = "
            <p>Hi <b>" . htmlspecialchars($user['name']) . "</b>,</p>
            <p>We received a request to reset your password.</p>
            <p>Click the link below to reset it. This link expires in <b>1 hour</b>.</p>
            <p><a href='$resetLink'>$resetLink</a></p>
            <p>If you did not request this, please ignore this email.</p>
        ";

        $mail->send();
        header("Location: forgotpass.php?status=sent");
        exit();

    } catch (Exception $e) {
        header("Location: forgotpass.php?error=mail_failed");
        exit();
    }
}

$status = $_GET['status'] ?? '';
$error  = $_GET['error']  ?? '';
$mins   = (int)($_GET['mins'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — Login Form Project</title>
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

        /* DYNAMIC GEOMETRIC BACKGROUND (FULL SCREEN) */
        .auth-visual-bg {
            position: absolute;
            inset: 0;
            z-index: 1;
            overflow: hidden;
        }

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
        .hex-4 { width: 200px; height: 200px; background: rgba(255,255,255,0.01); bottom: 10%;  right: 10%; animation: floatHex1 12s ease-in-out infinite reverse; }

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

        /* POPUP CONTAINER CENTERED */
        .auth-form-popup {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            background: var(--white);
            padding: 3rem 2.5rem;
            border-radius: 6px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.05);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1.75rem;
        }

        .brand-mark {
            width: 34px;
            height: 34px;
            background: var(--text1);
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
        }

        .brand-name {
            font-family: 'Rajdhani', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--text1);
        }

        .form-heading {
            font-family: 'Rajdhani', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--text1);
            margin-bottom: 0.35rem;
            line-height: 1;
        }

        .subtitle {
            font-size: 0.8125rem;
            color: var(--text3);
            margin-bottom: 1.75rem;
            letter-spacing: 0.01em;
            line-height: 1.4;
        }

        /* ALERTS */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 10px 12px;
            font-size: 0.78rem;
            line-height: 1.5;
            margin-bottom: 1.25rem;
            border-left: 2px solid transparent;
        }
        .alert-success { border-color: var(--success); color: var(--success); background: rgba(26,122,74,0.06); }
        .alert-error   { border-color: var(--error);   color: var(--error);   background: rgba(192,57,43,0.06); }
        .alert-warning { border-color: var(--warning); color: var(--warning); background: rgba(212,134,10,0.06); }
        .alert svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: 2px; }

        /* FIELDS */
        .field { margin-bottom: 1.25rem; }

        .field label {
            display: block;
            font-size: 0.68rem;
            font-weight: 500;
            color: var(--text3);
            margin-bottom: 5px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .field input {
            width: 100%;
            padding: 10px 12px;
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 3px;
            color: var(--text1);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.875rem;
            transition: border-color 0.15s, box-shadow 0.15s;
            outline: none;
        }

        .field input:hover  { border-color: var(--border-hover); }
        .field input:focus  { border-color: var(--text1); box-shadow: 0 0 0 2px rgba(26,25,23,0.08); }
        .field input::placeholder { color: var(--text3); }

        .field-error { font-size: 0.7rem; color: var(--error); margin-top: 4px; display: none; }
        .field.has-error input  { border-color: var(--error); }
        .field.has-error .field-error { display: block; }

        .btn-primary {
            width: 100%;
            padding: 11px;
            background: var(--text1);
            color: #fff;
            border: none;
            border-radius: 3px;
            font-family: 'Rajdhani', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
            margin-top: 0.5rem;
        }
        .btn-primary:hover  { background: #2e2c28; }
        .btn-primary:active { transform: scale(0.99); }

        .links {
            margin-top: 1.25rem;
            font-size: 0.8125rem;
            color: var(--text3);
            text-align: left;
        }
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

    <h1 class="form-heading">Forgot Password</h1>
    <p class="subtitle">Enter your registered email and we'll send you a reset link.</p>

    <?php if ($status === 'sent'): ?>
        <div class="alert alert-success">
            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            If that email is registered, a reset link has been sent. Check your inbox.
        </div>
        <div class="links"><a href="loginform.php">← Back to Login</a></div>

    <?php elseif ($error === 'cooldown'): ?>
        <div class="alert alert-warning">
            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            A reset link was already sent recently. Please wait <?php echo $mins; ?> minute(s) before requesting again.
        </div>
        <div class="links">
            <a href="forgotpass.php">Try again</a> &nbsp;·&nbsp;
            <a href="loginform.php">Back to Login</a>
        </div>

    <?php elseif ($error === 'mail_failed'): ?>
        <div class="alert alert-error">
            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5M12 16v.5"/></svg>
            Failed to send the reset email. Please try again later.
        </div>
        <div class="links">
            <a href="forgotpass.php">Try again</a> &nbsp;·&nbsp;
            <a href="loginform.php">Back to Login</a>
        </div>

    <?php elseif ($error === 'invalid_token'): ?>
        <div class="alert alert-error">
            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5M12 16v.5"/></svg>
            That reset link is invalid or has already been used.
        </div>
        <div class="links">
            <a href="forgotpass.php">Request a new link</a> &nbsp;·&nbsp;
            <a href="loginform.php">Back to Login</a>
        </div>

    <?php elseif ($error === 'token_expired'): ?>
        <div class="alert alert-warning">
            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            That reset link has expired. Please request a new one.
        </div>
        <div class="links">
            <a href="forgotpass.php">Request a new link</a> &nbsp;·&nbsp;
            <a href="loginform.php">Back to Login</a>
        </div>

    <?php else: ?>
        <form action="forgotpass.php" method="POST" id="forgotForm" novalidate>
            <div class="field">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email">
                <div class="field-error">Please enter a valid email address</div>
            </div>
            <button type="submit" name="forgot" class="btn-primary">Send Reset Link →</button>
        </form>
        <div class="links"><a href="loginform.php">← Back to Login</a></div>

    <?php endif; ?>

    <?php if ($error === 'invalid_email'): ?>
        <div class="alert alert-error" style="margin-top:1rem;">
            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5M12 16v.5"/></svg>
            Please enter a valid email address.
        </div>
    <?php endif; ?>

</div>

<script>
    const form = document.getElementById('forgotForm');
    if (form) {
        form.addEventListener('submit', e => {
            const emailEl = form.querySelector('#email');
            const bad = !emailEl || emailEl.value.trim() === '' ||
                        !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value.trim());
            emailEl.closest('.field').classList.toggle('has-error', bad);
            if (bad) e.preventDefault();
        });
        form.querySelectorAll('input').forEach(el =>
            el.addEventListener('input', () => el.closest('.field').classList.remove('has-error'))
        );
    }
</script>

</body>
</html>