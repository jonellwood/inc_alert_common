<?php

/**
 * API Auth Guard — Requires authenticated user for API endpoints.
 * Returns JSON 401 instead of redirecting to login page.
 *
 * Usage: <?php require_once __DIR__ . '/../lib/api_auth_check.php'; ?>
 */
require_once __DIR__ . '/RedfiveAuth.php';
RedfiveAuth::requireApiLogin(1);
