<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');

require 'conn.php';
/** @var mysqli $conn */

// Force MySQL session timezone to match PHP (fixes Asia/Manila vs UTC mismatch)
$conn->query("SET time_zone = '+08:00'");

// Get token from GET or POST
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

// Validate token format
if (empty($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    header("Location: forgotpass.php?error=invalid_token");
    exit();
}

// Look up token in database
$stmt = $conn->prepare("
    SELECT reg_id, reset_expires
    FROM users
    WHERE reset_token = ?
    LIMIT 1
");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: forgotpass.php?error=invalid_token");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Compare using PHP time only — avoids MySQL timezone mismatch
$now = date("Y-m-d H:i:s");

if ($user['reset_expires'] < $now) {
    header("Location: forgotpass.php?error=token_expired");
    exit();
}

// Handle POST submission
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {

    $password = trim($_POST['password']);
    $confirm  = trim($_POST['confirm']);

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    } elseif (!preg_match('/[\W_]/', $password)) {
        $errors[] = "Password must contain at least one special character.";
    }

    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // AND reset_token = ? prevents race condition — token used twice
        $stmt = $conn->prepare("
            UPDATE users
            SET password           = ?,
                reset_token        = NULL,
                reset_expires      = NULL,
                reset_requested_at = NULL,
                login_attempts     = 0
            WHERE reg_id = ?
              AND reset_token = ?
        ");
        $stmt->bind_param("sis", $hashed, $user['reg_id'], $token);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            header("Location: forgotpass.php?error=invalid_token");
            exit();
        }

        $stmt->close();

        header("Location: loginform.php?reset=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — Login Form Project</title>
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
                radial-gradient(ellipse 50% 70% at 20% 80%, rgba(180,40,30,0.1) 0%, transparent 55%),
                #1a1917;
        }

        .geo-container { position: absolute; inset: 0; overflow: hidden; }
        .geo-lines { position: absolute; bottom: 0; right: 0; width: 100%; height: 100%; opacity: 0.07; }

        .hex-shape { position: absolute; clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%); }
        .hex-1 { width: 280px; height: 280px; background: rgba(212,56,42,0.05); bottom: 10%; right: 8%;  animation: floatHex1 8s  ease-in-out infinite; }
        .hex-2 { width: 140px; height: 140px; background: rgba(212,56,42,0.08); top: 15%; left: 15%; animation: floatHex2 10s ease-in-out infinite; }
        .hex-3 { width: 60px;  height: 60px;  background: rgba(255,255,255,0.03); bottom: 25%; left: 40%; animation: floatHex3 7s  ease-in-out infinite; }
        .hex-4 { width: 200px; height: 200px; background: rgba(255,255,255,0.01); top: 10%;  right: 25%; animation: floatHex1 12s ease-in-out infinite reverse; }

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

        /* POPUP MODAL CONTAINER */
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
        .alert-error { border-color: var(--error); color: var(--error); background: rgba(192,57,43,0.06); }
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

        .password-hint {
            font-size: 0.68rem;
            color: var(--text3);
            margin-top: 4px;
            letter-spacing: 0.01em;
            line-height: 1.3;
        }

        .input-icon-wrap { position: relative; }
        .input-icon-wrap input { padding-right: 38px; }
        .eye-toggle {
            position: absolute;
            right: 10px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            cursor: pointer; padding: 0;
            color: var(--text3); font-size: 15px; line-height: 1;
        }
        .eye-toggle:hover { color: var(--text2); }

        .field-error { font-size: 0.7rem; color: var(--error); margin-top: 4px; display: none; }
        .field.has-error input { border-color: var(--error); }
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

    <h1 class="form-heading">Reset Password</h1>
    <p class="subtitle">Choose a strong new password for your account.</p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5M12 16v.5"/></svg>
            <div>
                <?php foreach ($errors as $e): ?>
                    <?php echo htmlspecialchars($e); ?><br>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <form action="resetpass.php" method="POST" id="resetForm" novalidate>
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

        <div class="field">
            <label for="password">New Password</label>
            <div class="input-icon-wrap">
                <input type="password" id="password" name="password" placeholder="Enter new password" required autocomplete="new-password">
                <button type="button" class="eye-toggle" id="togglePassword" aria-label="Show password" title="Show password">&#128065;</button>
            </div>
            <p class="password-hint">Min. 8 chars · one uppercase · one number · one special character</p>
            <div class="field-error">Password does not meet validation complexity requirements.</div>
        </div>

        <div class="field">
            <label for="confirm">Confirm Password</label>
            <div class="input-icon-wrap">
                <input type="password" id="confirm" name="confirm" placeholder="Confirm new password" required autocomplete="new-password">
                <button type="button" class="eye-toggle" id="toggleConfirm" aria-label="Show password" title="Show password">&#128065;</button>
            </div>
            <div class="field-error">Passwords fields must match identically.</div>
        </div>

        <button type="submit" name="reset" class="btn-primary">Reset Password</button>
    </form>

    <div class="links"><a href="loginform.php">← Back to Login</a></div>

</div>

<script>
    /* Dual custom independent eye toggles */
    function attachVisibilityToggle(buttonId, inputId) {
        const btn = document.getElementById(buttonId);
        const inp = document.getElementById(inputId);
        if (btn && inp) {
            btn.addEventListener('click', () => {
                const show = inp.type === 'password';
                inp.type = show ? 'text' : 'password';
                btn.title = show ? 'Hide password' : 'Show password';
            });
        }
    }
    attachVisibilityToggle('togglePassword', 'password');
    attachVisibilityToggle('toggleConfirm', 'confirm');

    /* Client side matching complexity validation layer */
    const form = document.getElementById('resetForm');
    const pwdPattern = /^(?=.*[A-Z])(?=.*[0-9])(?=.*[\W_]).{8,}$/;

    form.addEventListener('submit', e => {
        let valid = true;
        const pwdEl = document.getElementById('password');
        const cfmEl = document.getElementById('confirm');

        // Check password complexity requirements
        const badPwd = pwdEl.value.trim() === '' || !pwdPattern.test(pwdEl.value);
        pwdEl.closest('.field').classList.toggle('has-error', badPwd);
        if (badPwd) valid = false;

        // Check if values match
        const badCfm = cfmEl.value.trim() === '' || cfmEl.value !== pwdEl.value;
        cfmEl.closest('.field').classList.toggle('has-error', badCfm);
        if (badCfm) valid = false;

        if (!valid) e.preventDefault();
    });

    form.querySelectorAll('input').forEach(el => {
        el.addEventListener('input', () => el.closest('.field').classList.remove('has-error'));
    });
</script>

</body>
</html>