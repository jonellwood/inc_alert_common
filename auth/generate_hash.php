#!/usr/bin/env php
<?php
/**
 * RedFive Relay — Password Hash Generator (CLI only)
 *
 * Generates a bcrypt hash for local (non-LDAP) user accounts.
 * Use the output in your INSERT statement for the sHashedPass column.
 *
 * Usage:
 *   php auth/generate_hash.php
 *   php auth/generate_hash.php "MySecurePassword"
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script must be run from the command line.');
}

echo "====================================\n";
echo " RedFive — Password Hash Generator\n";
echo "====================================\n\n";

if (isset($argv[1])) {
    $password = $argv[1];
} else {
    echo "Enter password: ";
    // Try to hide input on supported systems
    if (strncasecmp(PHP_OS, 'WIN', 3) !== 0 && function_exists('readline')) {
        system('stty -echo');
        $password = trim(fgets(STDIN));
        system('stty echo');
        echo "\n";
    } else {
        $password = trim(fgets(STDIN));
    }
}

if (empty($password)) {
    echo "ERROR: Password cannot be empty.\n";
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Generated hash:\n";
echo $hash . "\n\n";
echo "SQL INSERT example:\n";
echo "INSERT INTO redfive_users (sUserName, sDisplayName, sHashedPass, bIsLDAP, iAccess)\n";
echo "VALUES ('username_here', 'Display Name', '{$hash}', 0, 2);\n";
