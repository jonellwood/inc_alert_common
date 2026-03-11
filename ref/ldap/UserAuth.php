<?php
include_once(dirname(__FILE__) . '/../classes/User.php');
include_once(dirname(__FILE__) . '/../classes/Department.php');
include_once(dirname(__FILE__) . '/../classes/Logger.php');
include_once(dirname(__FILE__) . '/UserAuthFailure.php');
include_once(dirname(__FILE__) . '/AuthSecurity.php');

class UserAuth
{
    use UserAuthFailure;

    private $ldapServer;
    private $ldapDomain;
    private $db;

    public function __construct()
    {
        // Load environment configuration first
        require_once dirname(__FILE__) . '/../config.php';

        // Try to include local auth config, but don't fail if it doesn't exist
        $authConfigPath = dirname(__FILE__) . '/config.php';
        if (file_exists($authConfigPath)) {
            include_once $authConfigPath;
        }

        // Use environment configuration with fallbacks - Updated to working LDAP configuration
        $this->ldapServer = Environment::get('ldap.server', $GLOBALS['ldapServer'] ?? 'ldaps://berkeleycounty.int:636');
        $this->ldapDomain = Environment::get('ldap.domain', $GLOBALS['ldapDomain'] ?? '@berkeleycounty.int');

        // Load database config - try multiple locations
        $dbConfigPaths = [
            dirname(__FILE__) . "/dbconfig.php",
            dirname(__FILE__) . "/../vueauth/dbconfig.php",
            dirname(__FILE__) . "/../data/appConfig.php"
        ];

        $dbConfigLoaded = false;
        foreach ($dbConfigPaths as $dbConfigPath) {
            if (file_exists($dbConfigPath)) {
                include_once $dbConfigPath;

                // Check which config class is available
                if (class_exists('bcdashConfig')) {
                    $this->db = new bcdashConfig();
                    $dbConfigLoaded = true;
                    break;
                } elseif (class_exists('appConfig')) {
                    $this->db = new appConfig();
                    $dbConfigLoaded = true;
                    break;
                }
            }
        }

        if (!$dbConfigLoaded && Environment::isLocal()) {
            error_log("WARNING: No database configuration found for UserAuth in local development");
            // For local development, we could fall back to the main app database config
            if (file_exists(dirname(__FILE__) . "/../data/appConfig.php")) {
                include_once dirname(__FILE__) . "/../data/appConfig.php";
                $this->db = new appConfig();
            }
        }
    }

    public static function confirmLoggedIn(): bool
    {
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] != 1) {
            Logger::logAuth('This guy is not logged in');
            return false;
        } else {
            Logger::logAuth('This guy IS logged in');
            return true;
        }
    }

    public function checkUserName($username)
    {
        //logError("Check User Name Func Received: " . $username);
        Logger::logAuth("Check User Name Func Received: " . $username);
        $position = strpos($username, '@');

        if ($position !== false) {
            //logError("function made " . substr($username, 0, $position));
            $cleanUsername = substr($username, 0, $position);
            Logger::logAuth("function made " . $cleanUsername);
            // Truncate to 20 characters to match LDAP username limit
            // (LDAP enforces 20-char max, app_users now stores truncated usernames)
            return substr($cleanUsername, 0, 20);
        }

        // Also truncate standalone usernames to 20 chars
        return substr($username, 0, 20);
    }

    public function validateCredentials($username, $password)
    {
        if (trim($password) == "") return false;

        putenv('LDAPTLS_REQCERT=never');

        $ldapConn = ldap_connect($this->ldapServer) or die("Could not connect to LDAP");
        ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapConn, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);

        // Clean up username
        $username = $this->checkUserName($username);

        // Debugging: Check final username before binding
        // logError("Attempting to bind with: " . $username . $this->ldapDomain);
        Logger::logAuth("Attempting to bind with: " . $username . $this->ldapDomain);

        // Try binding
        if (ldap_bind($ldapConn, $username . $this->ldapDomain, $password)) {
            Logger::logAuth("LDAP authentication successful for: " . $username);
            return true;
        } else {
            // logError('LDAP Response: ' . ldap_error($ldapConn)); // Debug LDAP error
            $ldapError = ldap_error($ldapConn);
            Logger::logAuth('LDAP Response: ' . $ldapError . 'for ' . $username . ' - ' . $password); // Debug LDAP error

            // Log the LDAP authentication failure
            $this->logAuthFailure([
                'username' => $username,
                'failureReason' => 'LDAP: ' . $ldapError,
                'authMethod' => 'LDAP',
                'expectedLDAP' => true
            ]);
            Logger::logAuth("Attempting database authentication fallback for: " . $username);

            $dbAuthResult = $this->checkIsUserLDAP($username, $password);

            switch ($dbAuthResult['status']) {
                case 'PASSWORD_CORRECT':
                    Logger::logAuth("Database authentication successful for non-LDAP user: " . $username);

                    // Check if user needs to update password based on last login
                    if ($this->shouldForcePasswordUpdate($username)) {
                        Logger::logAuth("Redirecting user " . $username . " to password update - first login or >90 days since last login");
                        // Store username in session for password update page
                        session_start();
                        $_SESSION['password_update_username'] = $username;
                        header("Location: /auth/password-update.php");
                        exit;
                    }

                    return true;

                case 'IS_LDAP':
                    Logger::logAuth("User " . $username . " is marked as LDAP but LDAP auth failed - denying access");
                    return false;

                case 'USER_NOT_FOUND':
                    Logger::logAuth("User " . $username . " not found in database");
                    return false;

                case 'PASSWORD_INCORRECT':
                    Logger::logAuth("Database password incorrect for user: " . $username);
                    return false;

                default:
                    Logger::logAuth("Unknown authentication result for user: " . $username);
                    return false;
            }
        }
    }


    public function checkUser($username)
    {
        // Log entry to checkUser function
        Logger::logAuth("[CHECKUSER ENTRY] Function called with username: $username", 3, dirname(__FILE__) . '/../logs/redirect_debug.log');
        Logger::logAuth("checkUser() called with username: '" . $username . "'");

        // logError("Check User Func Received: " . $username);
        $username = $this->checkUserName($username);
        //logError("Username after checkUserName: " . $username);
        $serverName = $this->db->serverName;
        $database = $this->db->database;
        $uid = $this->db->uid;
        $pwd = $this->db->pwd;


        // Log database connection attempt
        Logger::logAuth("Attempting database connection to: Server=$serverName; Database=$database; UID=$uid");

        try {
            $conn = new PDO("sqlsrv:Server=$serverName;Database=$database;ConnectionPooling=0;TrustServerCertificate=true", $uid, $pwd);
            Logger::logAuth("Database connection established successfully");
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $sql = "SELECT * FROM app_users WHERE sUserName = :username AND bIsActive = 1";

            // Log the SQL query and parameters for debugging to both logs
            $logMessage = "[CHECKUSER SQL] Query: " . $sql . " | Parameter: :username = '" . $username . "'";
            error_log($logMessage, 3, dirname(__FILE__) . '/../logs/redirect_debug.log');
            Logger::logAuth("SQL Query: " . $sql . " with username parameter: '" . $username . "'");

            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Log the result of the query to both logs
            $resultMessage = "[CHECKUSER RESULT] Query returned " . ($row ? "1 row" : "0 rows") . ($row ? " | User found: " . json_encode($row) : " | No user found");
            error_log($resultMessage, 3, dirname(__FILE__) . '/../logs/redirect_debug.log');
            Logger::logAuth("Database query result: " . ($row ? "User found" : "No user found") . " for username: '" . $username . "'");

            if ($row) {
                $_SESSION["loggedin"] = true;
                $_SESSION["username"] = $username;

                // Log that we're setting session data
                error_log("[CHECKUSER SUCCESS] Setting session data for user: $username", 3, dirname(__FILE__) . '/../logs/redirect_debug.log');
                $_SESSION['employeeID'] = $row['sEmployeeNumber'] ? $row['sEmployeeNumber'] : '007';
                $_SESSION['userID'] = $row['sUserId'] ? $row['sUserId'] : 0;
                $_SESSION['FirstName'] = $row['sFirstName'] ? $row['sFirstName'] : 'First Name Confidential';
                $_SESSION['LastName'] = $row['sLastName'] ? $row['sLastName'] : 'Last Name Redacted';
                $_SESSION['PreferredName'] = $row['sPreferredName'] ? $row['sPreferredName'] : $row['sFirstName'];
                $_SESSION['DepartmentNumber'] = $row['iDepartmentNumber'] ? $row['iDepartmentNumber'] : 'Department is TOP SECRET';
                $_SESSION['isAdmin'] = $row['bIsAdmin'] ? $row['bIsAdmin'] : 'No Info';
                $_SESSION['isLDAP'] = $row['bIsLDAP'] ? $row['bIsLDAP'] : 'No info';
                $_SESSION['iAppRoleId'] = $row['iAppRoleId'] ? $row['iAppRoleId'] : 105;
                $_SESSION['userEmail'] = $row['sEmail'] ? $row['sEmail'] : 'no-email@berkeleycountysc.gov';
                $_SESSION['dtLastLogin'] = $row['dtLastLogin'] ? $row['dtLastLogin'] : '';
                // 3CX Phone System Integration
                $_SESSION['threecxExtension'] = $row['sThreecxExtension'] ?? null;
                $_SESSION['threecxDID'] = $row['sThreecxDID'] ?? null;
                // logError("User data set in session: " . json_encode($_SESSION));
                $user_id = json_encode([
                    'userID' => $_SESSION['userID']
                ]);
                $this->setSecureCookie('bcdash_user', $user_id, time() + (30 * 24 * 60 * 60));
                if (isset($_POST['rememberme'])) {
                    $cookie_data = json_encode([
                        'username' => $username
                    ]);
                    $this->setSecureCookie('rememberme', $cookie_data, time() + (30 * 24 * 60 * 60)); // 30 days
                }
                $this->logLogIn();
                $this->checkEntryCount();
                $user = new User();
                $roles = $user->getLoggedInUsersRoles();

                // CRITICAL FIX: Always ensure base role is included
                // If user has no explicit role assignments in app_users_roles, 
                // their base iAppRoleId won't be in the roles array
                if (!in_array($_SESSION['iAppRoleId'], $roles)) {
                    array_unshift($roles, $_SESSION['iAppRoleId']); // Add base role at the beginning
                    Logger::logAuth("Added base role " . $_SESSION['iAppRoleId'] . " to roles array for user " . $_SESSION['username']);
                }

                $_SESSION['roles'] = $roles;

                // Store session start time for role change detection
                // This timestamp is used to detect if roles were modified after login
                $_SESSION['sessionStartTime'] = date('Y-m-d H:i:s');

                // logError('Putting roles in session: for user: ' . $_SESSION['username']);

                if (isset($_SESSION['iAppRoleId']) && $_SESSION['iAppRoleId'] == 105) {
                    Logger::logAuth("User " . $_SESSION['username'] . " has role 105 - redirecting to facilities request view");
                    header("Location: /facilitiesrequests/");
                    exit;
                }

                $department = new Department();
                $departments = $department->getJointDepartmentData($_SESSION['DepartmentNumber']);
                $jointDepNumbers = [];
                foreach ($departments as $department) {
                    $jointDepNumbers[] = $department['iDepartmentNumber'];
                }
                $_SESSION['jointDepartments'] = $jointDepNumbers;
                //logAuth($_SESSION['username'] . " logged in");
                Logger::logAuth($_SESSION['username'] . " logged in");
                // Deprecated: getCurrentAppVersion() call removed - version now fetched via currentVersion() helper
                return $row; // Return user data instead of true
            } else {
                $loginfailure = true;
                return false;
            }
        } catch (PDOException $e) {
            // error_log("Error in checkUser function: " . $e->getMessage());
            Logger::logAuth("Error in checkUser function: " . $e->getMessage());
            // Clear any session data before redirecting
            session_unset();
            // Redirect to auth with error parameter
            header("Location: /auth/index.php?error=database_error");
            exit;
        } finally {
            if ($conn) {
                $conn = null;
            }
        }
    }

    public function logLogIn()
    {
        $serverName = $this->db->serverName;
        $database = $this->db->database;
        $uid = $this->db->uid;
        $pwd = $this->db->pwd;

        $conn = null;
        $stmt = null;

        try {
            $conn = new PDO("sqlsrv:Server=$serverName;Database=$database;ConnectionPooling=0;TrustServerCertificate=true", $uid, $pwd);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $UserId = $_SESSION['userID'];
            $loginTime = date('Y-m-d H:i:s');
            $sql = "UPDATE app_users SET dtLastLogin = ?, dtLastActivity = ? WHERE sUserId = ?";
            $stmt = $conn->prepare($sql);

            if ($stmt->execute([$loginTime, $loginTime, $UserId])) {
                Logger::logAuth("Login time and last activity updated successfully for user ID: $UserId");
                return true;
            } else {
                Logger::logAuth("Failed to update login time and last activity for user ID: $UserId");
                return false;
            }
        } catch (PDOException $e) {
            Logger::logAuth("Error in logLogIn function: " . $e->getMessage());
            return false;
        } finally {
            // Clean up resources safely
            if ($stmt !== null) {
                $stmt = null;
            }
            if ($conn !== null) {
                $conn = null;
            }
        }
    }

    public function checkEntryCount()
    {
        $serverName = $this->db->serverName;
        $database = $this->db->database;
        $uid = $this->db->uid;
        $pwd = $this->db->pwd;

        $conn = null;
        $stmt = null;

        try {
            $conn = new PDO("sqlsrv:Server=$serverName;Database=$database;ConnectionPooling=0;TrustServerCertificate=true", $uid, $pwd);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $UserId = $_SESSION['userID'];
            $sql = "SELECT count(*) FROM bcg_intranet.dbo.app_user_component_order WHERE sUserId = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$UserId]);
            $count = $stmt->fetchColumn();

            if ($count == 0) {
                $sql = "SELECT sCardId FROM data_cards";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $displayOrder = 0;
                foreach ($cards as $card) {
                    $sql = "INSERT INTO app_user_component_order (sUserId, sComponentId, iDisplayOrder) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt->execute([$UserId, $card['sCardId'], $displayOrder])) {
                        Logger::logAuth("Inserted component order for user ID: $UserId, Component ID: " . $card['sCardId']);
                    } else {
                        Logger::logAuth("Failed to insert component order for user ID: $UserId, Component ID: " . $card['sCardId']);
                    }
                    $displayOrder++;
                }
            }
            return true;
        } catch (PDOException $e) {
            Logger::logAuth("Error in checkEntryCount function: " . $e->getMessage());
            return false;
        } finally {
            // Clean up resources safely
            if ($stmt !== null) {
                $stmt = null;
            }
            if ($conn !== null) {
                $conn = null;
            }
        }
    }

    public function checkCardAccessCount()
    {
        $serverName = $this->db->serverName;
        $database = $this->db->database;
        $uid = $this->db->uid;
        $pwd = $this->db->pwd;

        $conn = null;
        $stmt = null;

        try {
            $conn = new PDO("sqlsrv:Server=$serverName;Database=$database;ConnectionPooling=0;TrustServerCertificate=true", $uid, $pwd);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $UserId = $_SESSION['userID'];
            $sql = "SELECT count(*) FROM bcg_intranet.dbo.data_cards_users WHERE fkUserId = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$UserId]);
            $count = $stmt->fetchColumn();

            if ($count == 0) {
                $sql = "SELECT sCardId from data_cards where bForAll = 1";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cards as $card) {
                    $sql = "INSERT INTO data_cards_users (fkUserId, fkCardId) VALUES (?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt->execute([$UserId, $card['sCardId']])) {
                        Logger::logAuth("Inserted card access record for User ID: $UserId, Card ID:" . $card['sCardId']);
                    } else {
                        Logger::logAuth("Failed to insert Card Access Record for user ID: $UserId, Card ID:" . $card['sCardId']);
                    }
                }
            }
            return true;
        } catch (PDOException $e) {
            Logger::logAuth("Error in checkCardAccessCount function Connection: " . $e->getMessage());
            return false;
        } finally {
            // Clean up resources safely
            if ($stmt !== null) {
                $stmt = null;
            }
            if ($conn !== null) {
                $conn = null;
            }
        }
    }

    /**
     * Check sidenav item count - DEPRECATED FUNCTIONALITY
     * 
     * This method previously auto-inserted all role-based sidenav items into data_sidenav_users
     * on first login. This behavior has been removed as part of the navigation system optimization.
     * 
     * The data_sidenav_users table should now only contain specific user assignments for edge cases,
     * not bulk role-based assignments which are handled through app_sidenav_roles.
     * 
     * @deprecated This auto-insertion behavior is no longer needed
     * @return bool Always returns true for backward compatibility
     */
    public function checkSidenavItemCount()
    {
        // Log that this method was called but no longer performs bulk insertions
        Logger::logAuth("checkSidenavItemCount called - no longer auto-inserting role-based items");

        // Return true for backward compatibility with existing login flow
        return true;
    }

    public function checkIsUserLDAP($username, $password)
    {
        $serverName = $this->db->serverName;
        $database = $this->db->database;
        $uid = $this->db->uid;
        $pwd = $this->db->pwd;

        $conn = null;
        $stmt = null;

        try {
            $conn = new PDO("sqlsrv:Server=$serverName;Database=$database;ConnectionPooling=0;TrustServerCertificate=true", $uid, $pwd);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $sql = "SELECT id, bIsLDAP, sHashedPass FROM app_users WHERE sUserName = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result === false) {
                Logger::logAuth("User " . $username . " was not found");

                // Log the user not found failure
                $this->logAuthFailure([
                    'username' => $username,
                    'failureReason' => 'User not found in database',
                    'authMethod' => 'Local',
                    'expectedLDAP' => false
                ]);

                return ['status' => 'USER_NOT_FOUND', 'message' => 'User does not exist'];
            }

            $isLDAP = $result['bIsLDAP'];
            $hashedPass = $result['sHashedPass'];

            if ($isLDAP) {
                Logger::logAuth("User " . $username . " is LDAP");

                // Log the attempt to use database auth for LDAP user
                $this->logAuthFailure([
                    'username' => $username,
                    'failureReason' => 'User is marked as LDAP but attempted database authentication',
                    'authMethod' => 'Local',
                    'expectedLDAP' => true
                ]);

                return ['status' => 'IS_LDAP', 'message' => 'User should authenticate via LDAP'];
            }

            // $hashedInputPassword = password_hash($password, PASSWORD_DEFAULT);
            // I believe password_verify method will handle the hashing of the password for me....
            if (password_verify($password, $hashedPass)) {
                Logger::logAuth("Non LDAP User Password for " . $username . " is correct");
                return ['status' => 'PASSWORD_CORRECT', 'message' => 'Non LDAP Password for ' . $username . ' is correct'];
            } else {
                Logger::logAuth("Incorrect User Password for " . $username);

                // Log the incorrect password failure
                $this->logAuthFailure([
                    'username' => $username,
                    'failureReason' => 'Invalid password for local authentication',
                    'authMethod' => 'Local',
                    'expectedLDAP' => false
                ]);

                return ['status' => 'PASSWORD_INCORRECT', 'message' => 'Non LDDAP Password for ' . $username . ' is incorrect'];
            }
        } catch (PDOException $e) {
            Logger::logAuth("Error in checkIsUserLDAP function Connection: " . $e->getMessage());

            // Return appropriate error status for database connection failure
            return ['status' => 'DATABASE_ERROR', 'message' => 'Database connection failed'];
        } finally {
            // Clean up resources safely
            if ($stmt !== null) {
                $stmt = null;
            }
            if ($conn !== null) {
                $conn = null;
            }
        }
    }

    /**
     * Check if a non-LDAP user should be forced to update their password
     * Based on first login (NULL dtLastLogin) or login older than 90 days
     */
    private function shouldForcePasswordUpdate($username)
    {
        $serverName = $this->db->serverName;
        $database = $this->db->database;
        $uid = $this->db->uid;
        $pwd = $this->db->pwd;

        $conn = null;
        $stmt = null;

        try {
            $conn = new PDO("sqlsrv:Server=$serverName;Database=$database;ConnectionPooling=0;TrustServerCertificate=true", $uid, $pwd);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $sql = "SELECT dtLastLogin FROM app_users WHERE sUserName = ? AND bIsLDAP = 0";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result === false) {
                Logger::logAuth("User $username not found for password update check");
                return false;
            }

            $lastLogin = $result['dtLastLogin'];

            // If never logged in before (NULL)
            if ($lastLogin === null) {
                Logger::logAuth("User $username has never logged in - forcing password update");
                return true;
            }

            // Check if last login is older than 90 days
            $lastLoginDate = new DateTime($lastLogin);
            $currentDate = new DateTime();
            $daysDiff = $currentDate->diff($lastLoginDate)->days;

            if ($daysDiff > 90) {
                Logger::logAuth("User $username last login was $daysDiff days ago - forcing password update");
                return true;
            }

            Logger::logAuth("User $username last login was $daysDiff days ago - no password update required");
            return false;
        } catch (PDOException $e) {
            Logger::logAuth("Error in shouldForcePasswordUpdate for user $username: " . $e->getMessage());
            return false; // On error, don't force update
        } catch (Exception $e) {
            Logger::logAuth("Date calculation error in shouldForcePasswordUpdate for user $username: " . $e->getMessage());
            return false;
        } finally {
            // Clean up resources safely
            if ($stmt !== null) {
                $stmt = null;
            }
            if ($conn !== null) {
                $conn = null;
            }
        }
    }

    public function updatePassword($username, $password)
    {
        $serverName = $this->db->serverName;
        $database = $this->db->database;
        $uid = $this->db->uid;
        $pwd = $this->db->pwd;

        $conn = null;
        $stmt = null;

        try {
            $conn = new PDO("sqlsrv:Server=$serverName;Database=$database;ConnectionPooling=0;TrustServerCertificate=true", $uid, $pwd);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $hashedPass = password_hash($password, PASSWORD_DEFAULT);
            $loginTime = date('Y-m-d H:i:s');

            // Update both password and last login time to prevent immediate re-prompt
            $sql = "UPDATE app_users SET sHashedPass = ?, dtLastLogin = ?, dtLastActivity = ? WHERE sUserName = ?";
            $stmt = $conn->prepare($sql);

            if ($stmt->execute([$hashedPass, $loginTime, $loginTime, $username])) {
                Logger::logAuth("Password and login time updated successfully for " . $username);
                return true;
            } else {
                Logger::logAuth("Failed to update password and login time for " . $username);
                return false;
            }
        } catch (PDOException $e) {
            Logger::logAuth("Error in updatePassword function Connection: " . $e->getMessage());
            return false;
        } finally {
            // Clean up resources safely
            if ($stmt !== null) {
                $stmt = null;
            }
            if ($conn !== null) {
                $conn = null;
            }
        }
    }

    public function checkUserAccess($featureName): bool
    {
        Logger::logAuth("checkUserAccess called for feature: " . $featureName);

        // Check if user has valid session with roles
        if (!isset($_SESSION['roles']) || !is_array($_SESSION['roles'])) {
            Logger::logAuth($_SESSION['username'] . " attempted access to " . $featureName . " but has no roles in session");
            return false;
        }

        Logger::logAuth("Session roles for " . $_SESSION['username'] . ": " . json_encode($_SESSION['roles']));

        // Simple role-based access control
        // Check if the feature name (or role ID) is in the user's roles array
        if (in_array($featureName, $_SESSION['roles'])) {
            Logger::logAuth($_SESSION['username'] . " granted access to " . $featureName);
            return true;
        } else {
            Logger::logAuth($_SESSION['username'] . " denied access to " . $featureName);
            return false;
        }
    }

    /**
     * @deprecated This method is deprecated. Use currentVersion() helper function instead.
     * This method has been removed from the login process as of the $_SESSION['AppVersion'] deprecation cleanup.
     * Version information is now fetched on-demand via the currentVersion() helper function.
     */
    public function getCurrentAppVersion()
    {
        // Deprecated: This method no longer sets $_SESSION['AppVersion']
        // Use the currentVersion() helper function instead for version information
        Logger::logInfo("getCurrentAppVersion() called - this method is deprecated. Use currentVersion() helper instead.");

        // For backward compatibility, still return the version without setting session
        $serverName = $this->db->serverName;
        $database = $this->db->database;
        $uid = $this->db->uid;
        $pwd = $this->db->pwd;

        $conn = null;
        $stmt = null;

        try {
            $conn = new PDO("sqlsrv:Server=$serverName;Database=$database;ConnectionPooling=0;TrustServerCertificate=true", $uid, $pwd);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sql = "SELECT TOP (1) sVersion from app_version_control ORDER BY 
                     CAST(SUBSTRING(sVersion, 1, CHARINDEX('.', sVersion) - 1) AS INT), 
                     CAST(SUBSTRING(sVersion, CHARINDEX('.', sVersion) + 1, CHARINDEX('.', sVersion, CHARINDEX('.', sVersion) + 1) - CHARINDEX('.', sVersion) - 1) AS INT), 
                     CAST(SUBSTRING(sVersion, CHARINDEX('.', sVersion, CHARINDEX('.', sVersion) + 1) + 1, LEN(sVersion)) AS INT) DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $appVersion = $stmt->fetchColumn();
            Logger::logInfo("Retrieved App Version: " . ($appVersion ?: 'NULL'));
            return $appVersion ?: 'Unknown';
        } catch (PDOException $e) {
            Logger::logAuth('getCurrentAppVersion Error ' . $e->getMessage());
            return 'Unknown';
        } finally {
            // Clean up resources safely
            if ($stmt !== null) {
                $stmt = null;
            }
            if ($conn !== null) {
                $conn = null;
            }
        }
    }

    /**
     * Set CORS headers for cross-origin requests
     */
    public static function setCORSHeaders()
    {
        // Define allowed origins (customize these based on your Vue frontend URLs)
        $allowedOrigins = [
            'http://localhost:3000',  // Vue dev server default
            'http://localhost:8080',  // Vue dev server alternative
            'http://localhost:5173',  // Vite dev server default
            'http://localhost:4173',  // Vite preview default
            'http://127.0.0.1:3000',
            'http://127.0.0.1:8080',
            'http://127.0.0.1:5173',
            'http://127.0.0.1:4173',
            // Add your production domain here
            // 'https://yourdomain.com'
        ];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Check if the origin is in the allowed list
        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
        } else {
            // For development, you might want to allow all origins
            // Comment out the next line in production and only use the whitelist above
            if (!empty($origin)) {
                header("Access-Control-Allow-Origin: $origin");
            }
        }

        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
        header('Access-Control-Max-Age: 86400'); // 24 hours
    }

    /**
     * Handle preflight OPTIONS requests
     */
    public static function handlePreflightRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            self::setCORSHeaders();

            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
                header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            }
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
                header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
            }

            http_response_code(200);
            exit(0);
        }
    }

    /**
     * Configure secure session settings for cross-origin requests
     */
    public static function configureSecureSession()
    {
        // Only configure if session hasn't started yet
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session cookie parameters for cross-origin requests
            $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

            session_set_cookie_params([
                'lifetime' => 86400, // 24 hours
                'path' => '/',
                'domain' => '', // Leave empty to use current domain
                'secure' => $isHttps, // Only send over HTTPS in production
                'httponly' => true, // Prevent XSS attacks
                'samesite' => $isHttps ? 'None' : 'Lax' // None for cross-origin HTTPS, Lax for HTTP dev
            ]);

            session_start();
        }
    }

    /**
     * Set a secure cookie for cross-origin requests
     */
    public function setSecureCookie($name, $value, $expire = 0, $path = '/', $domain = '')
    {
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

        $options = [
            'expires' => $expire ?: time() + 86400, // Default 24 hours
            'path' => $path,
            'domain' => $domain,
            'secure' => $isHttps, // Only send over HTTPS in production
            'httponly' => true, // Prevent XSS attacks
            'samesite' => $isHttps ? 'None' : 'Lax' // None for cross-origin HTTPS, Lax for HTTP dev
        ];

        return setcookie($name, $value, $options);
    }

    /**
     * Set CORS headers and handle preflight in one call
     */
    public static function initializeCORS()
    {
        self::setCORSHeaders();
        self::handlePreflightRequest();
    }

    /**
     * @deprecated This method is deprecated. Use ADSync class directly instead.
     * 
     * This method has been replaced by the ADSync class which provides more robust Active Directory
     * synchronization functionality. Instead of using this method, instantiate an ADSync object
     * and call its getADUsers() method.
     * 
     * Example migration:
     * ```php
     * // Old way:
     * $userAuth = new UserAuth();
     * $users = $userAuth->getADUsers();
     * 
     * // New way:
     * $adSync = new ADSync();
     * $users = $adSync->getADUsers();
     * ```
     * 
     * @return array An array of AD user data, delegated to ADSync::getADUsers()
     */
    public function getADUsers()
    {
        Logger::logInfo("getADUsers: This method is deprecated. Use ADSync class instead.");
        require_once __DIR__ . '/../tools/ad_sync/ADSync.php';

        try {
            $adSync = new ADSync();
            return $adSync->getADUsers();
        } catch (Exception $e) {
            Logger::logError("Error in deprecated getADUsers method: " . $e->getMessage());
            return array();
        }
    }
}
