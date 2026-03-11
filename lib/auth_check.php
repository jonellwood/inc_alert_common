<?php

/**
 * Auth Guard — Requires authenticated user (any access level 1+)
 * Include at the top of any page that requires login.
 *
 * Usage: <?php require_once __DIR__ . '/lib/auth_check.php'; ?>
 *   or:  <?php require_once __DIR__ . '/../lib/auth_check.php'; ?>
 */
require_once __DIR__ . '/RedfiveAuth.php';
RedfiveAuth::requireLogin(1);
