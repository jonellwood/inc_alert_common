<?php

/**
 * RedFive Relay — Logout
 */
require_once __DIR__ . '/../lib/RedfiveAuth.php';

RedfiveAuth::logout();

$config = require __DIR__ . '/../config/auth_config.php';
header('Location: ' . $config['app_base'] . '/auth/login.php');
exit;
