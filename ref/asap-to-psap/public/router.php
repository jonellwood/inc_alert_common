<?php

// Development router for PHP's built-in web server.
// Serves static files if they exist; otherwise routes everything to index.php.
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $fullPath = __DIR__ . $path;

    if (is_file($fullPath)) {
        return false; // let the built-in server handle the static file
    }
}

require __DIR__ . '/index.php';
