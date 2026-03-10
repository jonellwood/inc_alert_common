<?php

/**
 * HelpMe911 Webhook Receiver
 * 
 * Receives emergency call data from HelpMe911 provider
 * Transforms data to match CAD format and posts to writeToDB.php
 * 
 * Expected payload format:
 * {
 *   "id": "uuid",
 *   "callType": "Animal Control|Medical|Fire|EMS|Other",
 *   "agency": "Animal Control",
 *   "contactFirstName": "...",
 *   "contactLastName": "...",
 *   "contactPhone": "...",
 *   "streetAddress": "...",
 *   "city": "...",
 *   "state": "...",
 *   "latitude": 33.110504,
 *   "longitude": -80.105917,
 *   "remarks": "...",
 *   "status": "ACTIVE",
 *   ...
 * }
 */

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Response helper
function sendResponse($statusCode, $message, $data = null)
{
    http_response_code($statusCode);
    $response = ['message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// Logging helper
function logWebhookActivity($action, $data = [])
{
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'data' => $data
    ];

    $logFile = __DIR__ . '/../logs/helpme911_webhook_debug.log';
    file_put_contents(
        $logFile,
        json_encode($logEntry, JSON_PRETTY_PRINT) . "\n---\n",
        FILE_APPEND
    );
    @chmod($logFile, 0666);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logWebhookActivity('method_not_allowed', ['method' => $_SERVER['REQUEST_METHOD']]);
    sendResponse(405, 'Method not allowed. Only POST requests are accepted.');
}

// Get raw POST data
$rawInput = file_get_contents('php://input');
logWebhookActivity('raw_request', [
    'raw_input' => $rawInput,
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'headers' => getallheaders()
]);

// Parse JSON payload
$payload = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    logWebhookActivity('json_error', ['error' => json_last_error_msg()]);
    sendResponse(400, 'Invalid JSON payload');
}

logWebhookActivity('payload_received', [
    'id' => $payload['id'] ?? 'unknown',
    'callType' => $payload['callType'] ?? 'unknown',
    'agency' => $payload['agency'] ?? 'unknown',
    'status' => $payload['status'] ?? 'unknown'
]);

/**
 * Map HelpMe911 call types to CAD call type codes
 */
function mapHelpMe911CallType($callType, $agency)
{
    // TODO: Get complete list of call types from HelpMe911
    // For now, using placeholders

    $mapping = [
        'Animal Control' => '104 ALARMS - LAW',  // Placeholder - need animal-specific codes
        'Medical' => '32 UNKNOWN PROBLEM',       // Medical emergency
        'Fire' => '52 ALARMS - FIRE',            // Fire alarm
        'EMS' => '32 UNKNOWN PROBLEM',           // EMS emergency
        'Other' => '104 ALARMS - LAW'            // Default
    ];

    // Try to match by callType first
    $type = trim($callType);
    if (isset($mapping[$type])) {
        return $mapping[$type];
    }

    // Fall back to agency if callType doesn't match
    $agencyType = trim($agency);
    if (isset($mapping[$agencyType])) {
        return $mapping[$agencyType];
    }

    // Default fallback
    return '104 ALARMS - LAW';
}

/**
 * Transform HelpMe911 payload to format expected by writeToDB.php
 */
function transformHelpMe911Alert($payload)
{
    // Extract contact name
    $firstName = $payload['contactFirstName'] ?? '';
    $lastName = $payload['contactLastName'] ?? '';
    $fullName = trim($firstName . ' ' . $lastName) ?: null;

    // Map call type
    $callTypeAlias = mapHelpMe911CallType(
        $payload['callType'] ?? 'Other',
        $payload['agency'] ?? ''
    );

    // Build full address string
    $fullAddress = $payload['fullAddress'] ?? implode(' ', array_filter([
        $payload['streetAddress'] ?? '',
        $payload['city'] ?? '',
        $payload['state'] ?? ''
    ]));

    // Transform to format that writeToDB.php expects
    // CRITICAL: Field names must match what extractRapidSOSData() looks for
    $transformed = [
        // Alert identification
        'alert_id' => 'helpme911-' . ($payload['id'] ?? uniqid()),
        'reference_number' => $payload['referenceNumber'] ?? null,
        'source_id' => $payload['id'] ?? null,

        // Source system identification
        'source_system' => 'HelpMe911',
        'service_provider_name' => 'HelpMe911',

        // Emergency type and call classification
        'emergency_type' => [
            'name' => $payload['callType'] ?? 'Other',
            'display_name' => $payload['agency'] ?? 'Unknown'
        ],
        'call_type_alias' => $callTypeAlias,

        // Location data - Match RapidSOS structure exactly
        'location' => [
            'geodetic' => [
                'latitude' => $payload['latitude'] ?? null,
                'longitude' => $payload['longitude'] ?? null,
                'uncertainty_radius' => null
            ],
            'civic' => [
                'street_1' => $payload['streetAddress'] ?? null,      // writeToDB looks for street_1
                'street_2' => $payload['apartmentNumber'] ?? null,    // writeToDB looks for street_2
                'city' => $payload['city'] ?? null,
                'state' => $payload['state'] ?? null,
                'zip_code' => null,
                'country' => 'United States',
                'name' => null
            ]
        ],

        // Contact information - Add both nested AND top-level fields
        'contact' => [
            'full_name' => $fullName,
            'first_name' => $firstName ?: null,
            'last_name' => $lastName ?: null,
            'phone' => $payload['contactPhone'] ?? null,
            'email' => $payload['contactEmail'] ?? null
        ],

        // Also add contact info at top level for database extraction
        'user' => [
            'full_name' => $fullName,
            'phone_number' => $payload['contactPhone'] ?? null,
            'email' => $payload['contactEmail'] ?? null,
            'first_name' => $firstName ?: null,
            'last_name' => $lastName ?: null
        ],

        // Alert details
        'description' => $payload['remarks'] ?? '',
        'comments' => $payload['comments'] ?? '',
        'status' => [
            'name' => $payload['status'] ?? 'ACTIVE'
        ],

        // Timestamps - writeToDB looks for last_updated_time for sSubmittedTimeRaw
        'created_time' => $payload['created'] ?? (time() * 1000),
        'submitted_time' => $payload['submitted'] ?? (time() * 1000),
        'last_updated_time' => $payload['submitted'] ?? (time() * 1000),
        'incident_time' => $payload['created'] ?? (time() * 1000),

        // Additional HelpMe911-specific data
        'additional_data' => [
            'speak_with_responder' => $payload['speakWithFirstResponder'] ?? null,
            'contact_permission' => $payload['contactPermission'] ?? false,
            'text_message' => $payload['textMessage'] ?? false,
            'has_attachment' => $payload['hasAttachment'] ?? false,
            'client_ip' => $payload['clientIp'] ?? null,
            'cleared_time' => $payload['clearedTime'] ?? null
        ]
    ];

    return $transformed;
}

// Transform the HelpMe911 payload
try {
    $transformedAlert = transformHelpMe911Alert($payload);

    logWebhookActivity('alert_transformed', [
        'alert_id' => $transformedAlert['alert_id'],
        'call_type_alias' => $transformedAlert['call_type_alias'],
        'location' => $transformedAlert['location']['civic']['street_address'] ?? 'unknown'
    ]);

    // Wrap in 'alerts' array to match writeToDB.php expectations
    $postData = [
        'source' => 'helpme911',
        'alerts' => [$transformedAlert]
    ];

    // Post to writeToDB.php
    $targetUrl = 'https://my.berkeleycountysc.gov/redfive/api/writeToDB.php';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $targetUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Webhook-Source: helpme911'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    // Log the payload being sent
    logWebhookActivity('posting_to_cad', [
        'url' => $targetUrl,
        'payload' => $postData
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    logWebhookActivity('cad_response', [
        'http_code' => $httpCode,
        'response' => $response,
        'curl_error' => $curlError ?: null
    ]);

    if ($httpCode >= 200 && $httpCode < 300) {
        $responseData = json_decode($response, true);
        http_response_code(200);
        echo $responseData['cfs_number'] ?? '';
        exit;
    } else {
        logWebhookActivity('cad_error', [
            'http_code' => $httpCode,
            'response' => $response
        ]);
        sendResponse(500, 'Failed to post to CAD system', [
            'http_code' => $httpCode,
            'error' => $response
        ]);
    }
} catch (Exception $e) {
    logWebhookActivity('exception', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    sendResponse(500, 'Internal server error: ' . $e->getMessage());
}
