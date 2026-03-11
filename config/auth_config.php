<?php

/**
 * RedFive Relay — Authentication Configuration
 * 
 * Access Levels:
 *   0 = Disabled (account exists but cannot log in)
 *   1 = Viewer (view dashboard, alerts)
 *   2 = Admin (full access: manage webhooks, subscriptions, logs, users)
 */
return [
    // LDAP Configuration (Berkeley County Active Directory)
    'ldap_server' => 'ldaps://berkeleycounty.int:636',
    'ldap_domain' => '@berkeleycounty.int',

    // Session settings
    'session_name' => 'REDFIVE_SESSION',
    'session_lifetime' => 28800, // 8 hours

    // Application base path (as seen in the URL)
    // redfive.berkeleycountysc.gov  → app is at document root, no prefix
    // my.berkeleycountysc.gov       → app lives under /redfive
    // Local dev (php -S)            → auto-detects as ''
    'app_base' => (php_sapi_name() === 'cli-server'
        || ($_SERVER['HTTP_HOST'] ?? '') === 'redfive.berkeleycountysc.gov')
        ? ''
        : '/redfive',

    // Access level definitions (for reference / UI display)
    'access_levels' => [
        0 => 'Disabled',
        1 => 'Viewer',
        2 => 'Admin',
    ],
];
