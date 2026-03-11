<?php

/**
 * RedFive Relay — Authentication
 *
 * Handles LDAP and local password authentication against the redfive_users table.
 * Designed to coexist with other applications sharing the same database.
 *
 * Usage:
 *   RedfiveAuth::requireLogin();        // Redirect to login if not authenticated
 *   RedfiveAuth::requireLogin(2);       // Require admin access
 *   RedfiveAuth::requireApiLogin();     // Return 401 JSON for API endpoints
 *   RedfiveAuth::isLoggedIn();          // Boolean check
 *   RedfiveAuth::getAccessLevel();      // Returns int access level
 *   RedfiveAuth::authenticate($u, $p);  // Perform authentication
 *   RedfiveAuth::logout();              // Destroy session
 */
class RedfiveAuth
{
    private static $config = null;
    private static $db = null;

    // ── Configuration ──────────────────────────────────────────

    private static function getConfig(): array
    {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/auth_config.php';
        }
        return self::$config;
    }

    private static function getDb(): PDO
    {
        if (self::$db === null) {
            require_once __DIR__ . '/../secrets/db.php';
            $cfg = new acoConfig();
            self::$db = new PDO(
                "sqlsrv:Server={$cfg->serverName};Database={$cfg->database};ConnectionPooling=0;TrustServerCertificate=1;Encrypt=0",
                $cfg->uid,
                $cfg->pwd
            );
            self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        return self::$db;
    }

    // ── Session Management ─────────────────────────────────────

    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $config = self::getConfig();
            session_name($config['session_name']);
            session_set_cookie_params([
                'lifetime' => $config['session_lifetime'],
                'path'     => '/',
                'httponly'  => true,
                'samesite'  => 'Lax',
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            ]);
            session_start();
        }
    }

    public static function isLoggedIn(): bool
    {
        self::initSession();
        return isset($_SESSION['rf_authenticated']) && $_SESSION['rf_authenticated'] === true;
    }

    public static function getAccessLevel(): int
    {
        self::initSession();
        return (int)($_SESSION['rf_access'] ?? 0);
    }

    public static function getUsername(): ?string
    {
        self::initSession();
        return $_SESSION['rf_username'] ?? null;
    }

    public static function getDisplayName(): ?string
    {
        self::initSession();
        return $_SESSION['rf_display_name'] ?? null;
    }

    // ── Access Guards ──────────────────────────────────────────

    /**
     * Require authentication for page requests. Redirects to login if not authenticated.
     * @param int $minAccess Minimum access level required (default 1 = viewer)
     */
    public static function requireLogin(int $minAccess = 1): void
    {
        self::initSession();
        $config = self::getConfig();
        $loginUrl = $config['app_base'] . '/auth/login.php';

        if (!self::isLoggedIn()) {
            $redirect = $_SERVER['REQUEST_URI'] ?? '';
            header('Location: ' . $loginUrl . '?redirect=' . urlencode($redirect));
            exit;
        }

        if (self::getAccessLevel() < $minAccess) {
            http_response_code(403);
            $base = $config['app_base'];
            echo '<!DOCTYPE html><html><head><title>Access Denied</title>';
            echo '<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">';
            echo '<style>body{margin:0;background:#0a0a0a;color:#ff2a2a;font-family:"Orbitron",monospace;display:flex;align-items:center;justify-content:center;height:100vh;text-align:center}';
            echo 'h1{font-size:2rem;text-shadow:0 0 10px #ff2a2a}p{color:#666;margin-top:1rem}a{color:#ff2a2a;text-decoration:none}</style></head>';
            echo '<body><div><h1>ACCESS DENIED</h1><p>Insufficient clearance level</p>';
            echo '<p style="margin-top:2rem"><a href="' . htmlspecialchars($base) . '/">&laquo; Return to base</a></p></div></body></html>';
            exit;
        }
    }

    /**
     * Require authentication for API endpoints. Returns JSON 401/403 instead of redirect.
     * @param int $minAccess Minimum access level required (default 1 = viewer)
     */
    public static function requireApiLogin(int $minAccess = 1): void
    {
        self::initSession();

        if (!self::isLoggedIn()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentication required', 'code' => 'AUTH_REQUIRED']);
            exit;
        }

        if (self::getAccessLevel() < $minAccess) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Insufficient access level', 'code' => 'ACCESS_DENIED']);
            exit;
        }
    }

    // ── Authentication ─────────────────────────────────────────

    /**
     * Authenticate a user via LDAP or local password.
     * @return array{success: bool, error: ?string, error_code: ?string}
     */
    public static function authenticate(string $username, string $password): array
    {
        $username = self::cleanUsername($username);

        if ($username === '' || trim($password) === '') {
            return ['success' => false, 'error' => 'Callsign and access code are required', 'error_code' => 'empty_fields'];
        }

        // Look up user in redfive_users
        $user = self::getUserRecord($username);

        if ($user === null) {
            self::logAuth("Login attempt for unknown user: {$username}");
            return ['success' => false, 'error' => 'Invalid callsign or access code', 'error_code' => 'invalid'];
        }

        if (!$user['bIsActive'] || (int)$user['iAccess'] === 0) {
            self::logAuth("Login attempt for disabled account: {$username}");
            return ['success' => false, 'error' => 'Account disabled — contact administrator', 'error_code' => 'disabled'];
        }

        // Authenticate based on user type
        if ($user['bIsLDAP']) {
            if (!self::ldapAuth($username, $password)) {
                self::logAuth("LDAP auth failed for: {$username}");
                return ['success' => false, 'error' => 'Invalid callsign or access code', 'error_code' => 'invalid'];
            }
        } else {
            if (!self::localAuth($password, $user['sHashedPass'])) {
                self::logAuth("Local auth failed for: {$username}");
                return ['success' => false, 'error' => 'Invalid callsign or access code', 'error_code' => 'invalid'];
            }
        }

        // Success — set session
        self::initSession();
        session_regenerate_id(true);
        self::setSession($user);
        self::updateLastLogin($username);
        self::logAuth("Login successful: {$username} (access={$user['iAccess']}, ldap=" . ($user['bIsLDAP'] ? 'yes' : 'no') . ")");

        return ['success' => true, 'error' => null, 'error_code' => null];
    }

    public static function logout(): void
    {
        self::initSession();
        $username = $_SESSION['rf_username'] ?? 'unknown';
        self::logAuth("Logout: {$username}");

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    // ── Internal Helpers ───────────────────────────────────────

    private static function cleanUsername(string $username): string
    {
        $username = trim($username);
        // Strip domain suffix if user typed user@domain
        $pos = strpos($username, '@');
        if ($pos !== false) {
            $username = substr($username, 0, $pos);
        }
        // LDAP enforces 20-char max
        return substr($username, 0, 20);
    }

    private static function getUserRecord(string $username): ?array
    {
        try {
            $db = self::getDb();
            $stmt = $db->prepare("SELECT * FROM redfive_users WHERE sUserName = ?");
            $stmt->execute([$username]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            self::logAuth("DB error in getUserRecord: " . $e->getMessage());
            return null;
        }
    }

    private static function ldapAuth(string $username, string $password): bool
    {
        // Graceful fallback if LDAP extension is not installed (e.g. local dev)
        if (!function_exists('ldap_connect')) {
            self::logAuth("LDAP extension not available — cannot authenticate LDAP users");
            return false;
        }

        $config = self::getConfig();
        putenv('LDAPTLS_REQCERT=never');

        $ldapConn = @ldap_connect($config['ldap_server']);
        if (!$ldapConn) {
            self::logAuth("Could not connect to LDAP server: {$config['ldap_server']}");
            return false;
        }

        ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapConn, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);

        $bindDn = $username . $config['ldap_domain'];
        $result = @ldap_bind($ldapConn, $bindDn, $password);
        @ldap_unbind($ldapConn);

        return $result;
    }

    private static function localAuth(string $password, ?string $hashedPass): bool
    {
        if (empty($hashedPass)) {
            return false;
        }
        return password_verify($password, $hashedPass);
    }

    private static function setSession(array $user): void
    {
        $_SESSION['rf_authenticated'] = true;
        $_SESSION['rf_user_id']       = (int)$user['id'];
        $_SESSION['rf_username']      = $user['sUserName'];
        $_SESSION['rf_display_name']  = $user['sDisplayName'] ?? $user['sUserName'];
        $_SESSION['rf_access']        = (int)$user['iAccess'];
        $_SESSION['rf_is_ldap']       = (bool)$user['bIsLDAP'];
        $_SESSION['rf_login_time']    = time();
    }

    private static function updateLastLogin(string $username): void
    {
        try {
            $db = self::getDb();
            $stmt = $db->prepare("UPDATE redfive_users SET dtLastLogin = GETUTCDATE() WHERE sUserName = ?");
            $stmt->execute([$username]);
        } catch (PDOException $e) {
            // Non-critical — don't block login
            self::logAuth("Failed to update last login for {$username}: " . $e->getMessage());
        }
    }

    private static function logAuth(string $message): void
    {
        $logFile = __DIR__ . '/../logs/redfive_auth.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $entry = date('Y-m-d H:i:s') . " | {$ip} | {$message}\n";
        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
