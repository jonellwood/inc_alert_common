<?php

/**
 * RedFive Relay — Login
 * Handles both form display (GET) and authentication (POST).
 */
require_once __DIR__ . '/../lib/RedfiveAuth.php';
RedfiveAuth::initSession();

// Already logged in → go to dashboard
if (RedfiveAuth::isLoggedIn()) {
    $config = require __DIR__ . '/../config/auth_config.php';
    header('Location: ' . $config['app_base'] . '/');
    exit;
}

$error = null;
$redirect = '';

// ── Handle POST (login attempt) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['rf_csrf_token'] ?? '', $csrfToken)) {
        $error = 'Invalid request — please try again';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $result = RedfiveAuth::authenticate($username, $password);

        if ($result['success']) {
            // Validate redirect URL to prevent open redirects
            $redirect = $_POST['redirect'] ?? '';
            $config = require __DIR__ . '/../config/auth_config.php';

            if (
                empty($redirect)
                || $redirect[0] !== '/'
                || strpos($redirect, '//') !== false
                || strpos($redirect, "\n") !== false
                || strpos($redirect, "\r") !== false
            ) {
                $redirect = $config['app_base'] . '/';
            }

            header('Location: ' . $redirect);
            exit;
        }

        $error = $result['error'];
    }
}

// ── Generate CSRF token ────────────────────────────────────────
$_SESSION['rf_csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['rf_csrf_token'];
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

// Map query-string error codes (used by auth_check redirects)
if (!$error && isset($_GET['error'])) {
    $errorMap = [
        'session' => 'Session expired — please re-authenticate',
    ];
    $error = $errorMap[$_GET['error']] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RED FIVE RELAY — Access Control</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            height: 100%;
            background: radial-gradient(circle at center, #0a0a0a 0%, #000 100%);
            color: #e0e0e0;
            font-family: 'Orbitron', sans-serif;
            overflow: hidden;
        }

        /* Scanline overlay */
        .overlay {
            position: fixed;
            inset: 0;
            background: repeating-linear-gradient(to bottom,
                    rgba(255, 255, 255, 0.02) 0px,
                    rgba(255, 255, 255, 0.02) 1px,
                    transparent 2px,
                    transparent 3px);
            animation: scanline 2s linear infinite;
            pointer-events: none;
            z-index: 1;
        }

        @keyframes scanline {
            0% {
                background-position: 0 0;
            }

            100% {
                background-position: 0 100%;
            }
        }

        /* Flicker animation */
        @keyframes flicker {

            0%,
            19%,
            21%,
            23%,
            25%,
            54%,
            56%,
            100% {
                opacity: 1;
            }

            20%,
            24%,
            55% {
                opacity: 0.3;
            }
        }

        /* Pulse animation */
        @keyframes pulse {
            from {
                transform: scale(0.8);
                opacity: 0.6;
            }

            to {
                transform: scale(1.4);
                opacity: 1;
            }
        }

        /* Fade in for form */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            width: 90%;
            max-width: 420px;
            z-index: 2;
        }

        .title {
            font-size: 2.5rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #ff2a2a;
            text-shadow:
                0 0 10px #ff2a2a,
                0 0 20px #ff2a2a,
                0 0 30px #ff2a2a;
            animation: flicker 2.5s infinite;
            margin: 0 0 0.5rem 0;
        }

        .subtitle {
            color: #555;
            letter-spacing: 0.2em;
            margin: 0 0 2rem 0;
            font-size: 0.75rem;
        }

        .login-form {
            background: rgba(20, 20, 20, 0.9);
            border: 1px solid #222;
            padding: 2rem;
            border-radius: 4px;
            text-align: left;
            animation: fadeIn 0.6s ease-out;
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        .input-group label {
            display: block;
            font-size: 0.65rem;
            letter-spacing: 0.15em;
            color: #666;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }

        .input-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: #0d0d0d;
            border: 1px solid #333;
            color: #e0e0e0;
            font-family: 'Courier New', monospace;
            font-size: 1rem;
            border-radius: 2px;
            outline: none;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .input-group input:focus {
            border-color: #ff2a2a;
            box-shadow: 0 0 8px rgba(255, 42, 42, 0.25);
        }

        .input-group input::placeholder {
            color: #333;
            font-size: 0.85rem;
        }

        .error-msg {
            background: rgba(255, 42, 42, 0.08);
            border: 1px solid rgba(255, 42, 42, 0.3);
            color: #ff2a2a;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.7rem;
            letter-spacing: 0.08em;
            border-radius: 2px;
            font-family: 'Courier New', monospace;
        }

        .submit-btn {
            width: 100%;
            padding: 0.85rem;
            background: transparent;
            border: 1px solid #ff2a2a;
            color: #ff2a2a;
            font-family: 'Orbitron', sans-serif;
            font-size: 0.85rem;
            letter-spacing: 0.15em;
            cursor: pointer;
            border-radius: 2px;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            background: rgba(255, 42, 42, 0.1);
            box-shadow: 0 0 20px rgba(255, 42, 42, 0.3);
        }

        .submit-btn:active {
            background: rgba(255, 42, 42, 0.2);
        }

        .submit-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .footer-text {
            margin-top: 2rem;
            color: #333;
            font-size: 0.6rem;
            letter-spacing: 0.15em;
        }

        .pulse-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #ff2a2a;
            border-radius: 50%;
            margin-left: 6px;
            box-shadow: 0 0 8px #ff2a2a;
            animation: pulse 1.5s infinite alternate;
            vertical-align: middle;
        }

        @media (max-width: 480px) {
            .title {
                font-size: 1.8rem;
            }

            .login-form {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="overlay"></div>

    <div class="center">
        <h1 class="title">RED FIVE RELAY</h1>
        <p class="subtitle">// authentication required //<span class="pulse-dot"></span></p>

        <form class="login-form" method="POST" action="" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

            <?php if ($error): ?>
                <div class="error-msg">&gt; <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="input-group">
                <label for="username">Callsign</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="username"
                    required
                    autocomplete="username"
                    autofocus
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>

            <div class="input-group">
                <label for="password">Access Code</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password">
            </div>

            <button type="submit" class="submit-btn" id="submitBtn">Authenticate</button>
        </form>

        <p class="footer-text">// berkeley county emergency services //</p>
    </div>

    <script>
        // Brief loading state on submit
        document.querySelector('.login-form').addEventListener('submit', function() {
            var btn = document.getElementById('submitBtn');
            btn.textContent = 'Establishing uplink...';
            btn.disabled = true;
        });
    </script>
</body>

</html>