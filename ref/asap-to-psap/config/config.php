<?php

// Basic configuration with environment overrides.
return [
    'mock_bearer_token' => getenv('MOCK_BEARER_TOKEN') ?: 'change-me-token',
    // Default log dir is relative to project root; override via env to use /var/log/asap-ecc in prod.
    'log_dir' => getenv('LOG_DIR') ?: dirname(__DIR__) . '/logs',
    // Where to store per-event JSON output files.
    'output_dir' => getenv('OUTPUT_DIR') ?: dirname(__DIR__) . '/logs/events',
    // Max request body size in bytes.
    'max_body_bytes' => getenv('MAX_BODY_BYTES') ? (int) getenv('MAX_BODY_BYTES') : 1048576,
    'timezone' => getenv('TIMEZONE') ?: 'UTC',
];
