<?php
// Must be first — before ANY output
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$error = $_GET['error'] ?? '';
$left  = (int)($_GET['left'] ?? 0);
$mins  = (int)($_GET['mins'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Login Form Project</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #f5f4f2;
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
            background: var(--bg);
            color: var(--text1);
            min-height: 100vh;
            display: flex;
            align-items: stretch;
        }

        .auth-form {
            flex: 0 0 440px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 2.5rem 3rem;
            background: var(--white);
            position: relative;
            z-index: 2;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 2rem;
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
            margin-bottom: 0.25rem;
            line-height: 1;
        }

        .form-sub {
            font-size: 0.8125rem;
            color: var(--text3);
            margin-bottom: 1.75rem;
            letter-spacing: 0.01em;
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

        /* FIELDS */
        .field { margin-bottom: 1rem; }

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

        .field-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .field-row label { font-size: 0.68rem; color: var(--text3); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0; }
        .field-row a { font-size: 0.75rem; color: var(--accent); text-decoration: none; letter-spacing: 0.02em; }
        .field-row a:hover { text-decoration: underline; }

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
            margin-top: 0.75rem;
        }
        .btn-primary:hover  { background: #2e2c28; }
        .btn-primary:active { transform: scale(0.99); }

        .divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 1.25rem 0;
        }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }
        .divider span { font-size: 0.7rem; color: var(--text3); text-transform: uppercase; letter-spacing: 0.1em; }

        .btn-google {
            width: 100%;
            padding: 10px;
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 3px;
            color: var(--text2);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.875rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: border-color 0.15s, color 0.15s, background 0.15s;
            text-decoration: none;
        }
        .btn-google:hover { border-color: var(--border-hover); color: var(--text1); background: var(--input-bg); }
        .btn-google svg { width: 16px; height: 16px; }

        .auth-footer {
            margin-top: 1.25rem;
            font-size: 0.8125rem;
            color: var(--text3);
        }
        .auth-footer a { color: var(--accent); text-decoration: none; font-weight: 500; }
        .auth-footer a:hover { text-decoration: underline; }

        /* RIGHT PANEL */
        .auth-visual {
            flex: 1;
            position: relative;
            overflow: hidden;
            background: #1a1917;
        }

        .visual-bg {
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 60% 40%, rgba(212,56,42,0.18) 0%, transparent 60%),
                radial-gradient(ellipse 50% 70% at 80% 80%, rgba(180,40,30,0.12) 0%, transparent 55%),
                #1a1917;
        }

        .geo-container { position: absolute; inset: 0; overflow: hidden; }

        .geo-lines { position: absolute; bottom: 0; right: 0; width: 100%; height: 100%; opacity: 0.07; }

        .accent-stripe   { position: absolute; top: 0; bottom: 0; left: -60px; width: 3px; background: var(--accent); transform: skewX(-8deg); opacity: 0.6; }
        .accent-stripe-2 { position: absolute; top: 0; bottom: 0; left: -48px; width: 1px; background: var(--accent); transform: skewX(-8deg); opacity: 0.3; }

        .hex-shape { position: absolute; clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%); }
        .hex-1 { width: 280px; height: 280px; background: rgba(212,56,42,0.07); top: 10%; right: 8%;  animation: floatHex1 8s  ease-in-out infinite; }
        .hex-2 { width: 140px; height: 140px; background: rgba(212,56,42,0.12); top: 35%; right: 30%; animation: floatHex2 10s ease-in-out infinite; }
        .hex-3 { width: 60px;  height: 60px;  background: rgba(255,255,255,0.04); bottom: 25%; right: 12%; animation: floatHex3 7s  ease-in-out infinite; }
        .hex-4 { width: 200px; height: 200px; background: rgba(255,255,255,0.02); bottom: 5%;  right: 40%; animation: floatHex1 12s ease-in-out infinite reverse; }

        @keyframes floatHex1 { 0%,100%{transform:translateY(0) rotate(0deg)} 50%{transform:translateY(-18px) rotate(3deg)} }
        @keyframes floatHex2 { 0%,100%{transform:translateY(0) rotate(0deg)} 50%{transform:translateY(14px) rotate(-5deg)} }
        @keyframes floatHex3 { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }

        .dot-grid {
            position: absolute; inset: 0;
            background-image: radial-gradient(circle, rgba(255,255,255,0.08) 1px, transparent 1px);
            background-size: 32px 32px;
            opacity: 0.5;
        }

        .corner-accent { position: absolute; top: 2.5rem; right: 2.5rem; display: flex; align-items: center; gap: 6px; z-index: 3; }
        .corner-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--accent); opacity: 0.6; }
        .corner-dot.pulse { animation: pulseDot 2s ease-in-out infinite; }
        @keyframes pulseDot { 0%,100%{opacity:0.6;transform:scale(1)} 50%{opacity:1;transform:scale(1.3)} }
        .corner-text { font-size: 0.65rem; color: rgba(255,255,255,0.25); letter-spacing: 0.12em; text-transform: uppercase; font-family: 'Rajdhani', sans-serif; }

        @media (max-width: 768px) {
            .auth-visual { display: none; }
            .auth-form { flex: 1; }
        }
    </style>
</head>
<body>

<div class="auth-form">

    <div class="brand">
        <div class="brand-mark"></div>
        <span class="brand-name">Login Form Project</span>
    </div>

    <h1 class="form-heading">Sign In</h1>
    <p class="form-sub">Enter your credentials to continue</p>

    <?php if (isset($_GET['verified'])): ?>
        <div class="alert alert-success">Email verified. You can now sign in.</div>
    <?php elseif (isset($_GET['reset'])): ?>
        <div class="alert alert-success">Password reset successfully.</div>
    <?php elseif ($error === 'locked'): ?>
        <div class="alert alert-warning">Too many attempts. Please wait <?= $mins ?> minute(s).</div>
    <?php elseif ($error === 'invalid'): ?>
        <div class="alert alert-error">
            Invalid email or password.
            <?php if ($left > 0): ?><?= $left ?> attempt(s) remaining.<?php endif; ?>
        </div>
    <?php elseif ($error === 'google_failed'): ?>
        <div class="alert alert-error">Google sign-in failed. Please try again.</div>
    <?php endif; ?>

    <form action="login.php" method="POST" id="loginForm" novalidate>
        <!-- CSRF protection -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="field">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email"
                   placeholder="you@example.com" required autocomplete="email">
            <div class="field-error">Please enter a valid email address</div>
        </div>

        <div class="field">
            <div class="field-row">
                <label for="password">Password</label>
                <a href="forgotpass.php">Can't sign in?</a>
            </div>
            <div class="input-icon-wrap">
                <input type="password" id="password" name="password"
                       placeholder="••••••••••" required autocomplete="current-password"
                       minlength="8">
                <button type="button" class="eye-toggle" id="eyeToggle"
                        aria-label="Show password" title="Show password">&#128065;</button>
            </div>
            <div class="field-error">Minimum 8 characters required</div>
        </div>

        <button type="submit" name="login" class="btn-primary">Sign In →</button>
    </form>

    <div class="divider"><span>or continue with</span></div>

    <a href="google_callback.php" class="btn-google">
        <svg viewBox="0 0 24 24" fill="none">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
        </svg>
        Google
    </a>

    <div class="auth-footer">
        Create Account — <a href="registerform.php">Register here</a>
    </div>

</div>

<div class="auth-visual">
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

    <div class="accent-stripe"></div>
    <div class="accent-stripe-2"></div>

    <div class="corner-accent">
        <div class="corner-dot pulse"></div>
        <div class="corner-dot"></div>
        <span class="corner-text">Secure</span>
    </div>
</div>

<script>
    /* Eye toggle */
    const btn = document.getElementById('eyeToggle');
    const inp = document.getElementById('password');
    btn.addEventListener('click', () => {
        const show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        btn.title = show ? 'Hide password' : 'Show password';
    });

    /* Validation */
    const form = document.getElementById('loginForm');

    form.addEventListener('submit', e => {
        let valid = true;

        form.querySelectorAll('input[required]').forEach(el => {
            const empty = el.value.trim() === '';
            const minFail = el.minLength > 0 && el.value.length < el.minLength;
            const bad = empty || minFail;
            el.closest('.field').classList.toggle('has-error', bad);
            if (bad) valid = false;
        });

        if (!valid) e.preventDefault();
    });

    form.querySelectorAll('input').forEach(el => {
        el.addEventListener('input', () => el.closest('.field').classList.remove('has-error'));
    });
</script>

</body>
</html>