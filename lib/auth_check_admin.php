<?php

/**
 * Auth Guard — Requires admin access (level 2+)
 * Include at the top of any admin-only page.
 *
 * Usage: <?php require_once __DIR__ . '/../lib/auth_check_admin.php'; ?>
 */
require_once __DIR__ . '/RedfiveAuth.php';
RedfiveAuth::requireLogin(2);
