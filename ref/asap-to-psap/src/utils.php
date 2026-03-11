<?php

declare(strict_types=1);

/**
 * Utility functions for the ASAP ECC endpoint.
 */

/**
 * Fetch a specific HTTP header ignoring case.
 */
function get_header_value(string $name): ?string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (isset($_SERVER[$key])) {
        return $_SERVER[$key];
    }
    // Fallback for Authorization which can be provided differently.
    if (strtolower($name) === 'authorization') {
        if (isset($_SERVER['Authorization'])) {
            return $_SERVER['Authorization'];
        }
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $hKey => $hVal) {
                if (strcasecmp($hKey, $name) === 0) {
                    return $hVal;
                }
            }
        }
    }
    return null;
}

/**
 * Generate a correlation ID.
 */
function make_correlation_id(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * Ensure the given log directory exists.
 */
function ensure_log_dir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

/**
 * Append a JSON log line to today's log file.
 */
function write_log(array $config, array $payload): void
{
    ensure_log_dir($config['log_dir']);
    $path = rtrim($config['log_dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR .
        'asap-ecc-' . date('Y-m-d') . '.log';
    $line = json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Send a JSON response with status code and exit.
 */
function respond_json(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}
