<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';
date_default_timezone_set($config['timezone'] ?? 'UTC');

require dirname(__DIR__) . '/src/utils.php';
require dirname(__DIR__) . '/src/Transformer.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($uri === '/health') {
    respond_json(200, ['status' => 'ok']);
}

if ($uri === '/asap/ecc') {
    handle_asap_ecc($config, $method);
}

respond_json(404, ['error' => 'Not found']);

function handle_asap_ecc(array $config, string $method): void
{
    if (strtoupper($method) !== 'POST') {
        header('Allow: POST');
        respond_json(405, ['error' => 'Method not allowed']);
    }

    $correlationId = make_correlation_id();
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : null;
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/xml') === false) {
        write_log($config, [
            'ts' => date('c'),
            'id' => $correlationId,
            'remote_ip' => $remoteIp,
            'status' => 415,
            'reason' => 'unsupported_media_type',
            'content_type' => $contentType,
        ]);
        respond_json(415, ['error' => 'Content-Type must be application/xml', 'id' => $correlationId]);
    }

    $authHeader = get_header_value('Authorization');
    $expected = 'Bearer ' . $config['mock_bearer_token'];
    if ($authHeader === null || trim($authHeader) !== $expected) {
        write_log($config, [
            'ts' => date('c'),
            'id' => $correlationId,
            'remote_ip' => $remoteIp,
            'status' => 401,
            'reason' => 'auth_failed',
            'auth_header' => $authHeader,
        ]);
        respond_json(401, ['error' => 'Unauthorized', 'id' => $correlationId]);
    }

    $raw = file_get_contents('php://input', false, null, 0, $config['max_body_bytes'] + 1);
    if ($raw === false || $raw === '') {
        write_log($config, [
            'ts' => date('c'),
            'id' => $correlationId,
            'remote_ip' => $remoteIp,
            'status' => 400,
            'reason' => 'empty_body',
        ]);
        respond_json(400, ['error' => 'Empty body', 'id' => $correlationId]);
    }

    if (strlen($raw) > $config['max_body_bytes']) {
        write_log($config, [
            'ts' => date('c'),
            'id' => $correlationId,
            'remote_ip' => $remoteIp,
            'status' => 413,
            'reason' => 'body_too_large',
            'content_length' => $contentLength,
        ]);
        respond_json(413, ['error' => 'Request entity too large', 'id' => $correlationId]);
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($raw);
    if ($xml === false) {
        $errors = array_map(static fn($e) => trim($e->message), libxml_get_errors());
        libxml_clear_errors();
        write_log($config, [
            'ts' => date('c'),
            'id' => $correlationId,
            'remote_ip' => $remoteIp,
            'status' => 400,
            'reason' => 'invalid_xml',
            'errors' => $errors,
        ]);
        respond_json(400, ['error' => 'Invalid XML', 'id' => $correlationId]);
    }

    $requiredNamespaces = ['apco-alarm', 'em', 'j', 's', 'nc'];
    $docNamespaces = $xml->getDocNamespaces(true);
    $missing = array_values(array_filter($requiredNamespaces, static function ($ns) use ($docNamespaces) {
        return !array_key_exists($ns, $docNamespaces);
    }));

    if ($missing) {
        write_log($config, [
            'ts' => date('c'),
            'id' => $correlationId,
            'remote_ip' => $remoteIp,
            'status' => 400,
            'reason' => 'missing_namespaces',
            'missing' => $missing,
            'namespaces' => $docNamespaces,
        ]);
        respond_json(400, ['error' => 'Missing required namespaces', 'missing' => $missing, 'id' => $correlationId]);
    }

    $transformed = transform_apco($xml);

    // Persist a per-event JSON file for downstream processing.
    ensure_log_dir($config['output_dir']);
    $timestamp = date('Ymd_His');
    $jsonFilename = sprintf('%s-%s.json', $correlationId, $timestamp);
    $jsonPath = rtrim($config['output_dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $jsonFilename;
    file_put_contents($jsonPath, json_encode($transformed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    write_log($config, [
        'ts' => date('c'),
        'id' => $correlationId,
        'remote_ip' => $remoteIp,
        'status' => 200,
        'content_length' => $contentLength,
        'namespaces' => $docNamespaces,
        'payload' => $raw,
        'extracted' => $transformed,
        'output_path' => $jsonPath,
    ]);

    respond_json(200, [
        'status' => 'ok',
        'id' => $correlationId,
        'output_file' => $jsonFilename,
        'extracted' => $transformed,
    ]);
}
