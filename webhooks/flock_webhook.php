<?php

/**
 * Flock Safety LPR Alerts Webhook Receiver
 * 
 * Receives license plate reader (LPR) alerts from Flock Safety
 * when a vehicle matching a BOLO/hotlist is detected by a camera.
 * Transforms data to CAD format and posts to writeToDB.php.
 * 
 * Authentication: Flock sends our API key in the X-API-Key header.
 * All alerts get CallTypeAlias 'FLOCK BOLO'.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load config
$flockConfig = require __DIR__ . '/../config/flock_config.php';

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

    $logFile = __DIR__ . '/../logs/flock_webhook_debug.log';
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

// Verify API key authentication
$headers = getallheaders();
// Header keys may be case-insensitive; normalize
$apiKey = null;
foreach ($headers as $key => $value) {
    if (strtolower($key) === 'x-api-key') {
        $apiKey = $value;
        break;
    }
}

if ($apiKey !== $flockConfig['webhook_api_key']) {
    logWebhookActivity('auth_failed', [
        'received_key' => $apiKey ? substr($apiKey, 0, 8) . '...' : 'none',
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    sendResponse(401, 'Unauthorized');
}

// Get raw POST data
$rawInput = file_get_contents('php://input');
logWebhookActivity('raw_request', [
    'raw_input_length' => strlen($rawInput),
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);

// Parse JSON payload
$payload = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    logWebhookActivity('json_error', ['error' => json_last_error_msg()]);
    sendResponse(400, 'Invalid JSON payload');
}

logWebhookActivity('payload_received', [
    'objectId' => $payload['objectId'] ?? 'unknown',
    'plate' => $payload['ocr']['label'] ?? 'unknown',
    'deviceName' => $payload['deviceName'] ?? 'unknown',
    'eventTime' => $payload['eventTime'] ?? 'unknown',
    'sources_count' => count($payload['sources'] ?? [])
]);

/**
 * Map Flock's full state name (e.g. "south_carolina") to 2-letter abbreviation
 */
function mapStateAbbreviation($stateName)
{
    $stateMap = [
        'alabama' => 'AL',
        'alaska' => 'AK',
        'arizona' => 'AZ',
        'arkansas' => 'AR',
        'california' => 'CA',
        'colorado' => 'CO',
        'connecticut' => 'CT',
        'delaware' => 'DE',
        'florida' => 'FL',
        'georgia' => 'GA',
        'hawaii' => 'HI',
        'idaho' => 'ID',
        'illinois' => 'IL',
        'indiana' => 'IN',
        'iowa' => 'IA',
        'kansas' => 'KS',
        'kentucky' => 'KY',
        'louisiana' => 'LA',
        'maine' => 'ME',
        'maryland' => 'MD',
        'massachusetts' => 'MA',
        'michigan' => 'MI',
        'minnesota' => 'MN',
        'mississippi' => 'MS',
        'missouri' => 'MO',
        'montana' => 'MT',
        'nebraska' => 'NE',
        'nevada' => 'NV',
        'new_hampshire' => 'NH',
        'new_jersey' => 'NJ',
        'new_mexico' => 'NM',
        'new_york' => 'NY',
        'north_carolina' => 'NC',
        'north_dakota' => 'ND',
        'ohio' => 'OH',
        'oklahoma' => 'OK',
        'oregon' => 'OR',
        'pennsylvania' => 'PA',
        'rhode_island' => 'RI',
        'south_carolina' => 'SC',
        'south_dakota' => 'SD',
        'tennessee' => 'TN',
        'texas' => 'TX',
        'utah' => 'UT',
        'vermont' => 'VT',
        'virginia' => 'VA',
        'washington' => 'WA',
        'west_virginia' => 'WV',
        'wisconsin' => 'WI',
        'wyoming' => 'WY',
        'district_of_columbia' => 'DC',
    ];

    $key = strtolower(str_replace(' ', '_', trim($stateName)));
    return $stateMap[$key] ?? strtoupper(substr($stateName, 0, 2));
}

/**
 * Transform Flock LPR alert payload to format expected by writeToDB.php
 */
function transformFlockAlert($payload, $config)
{
    // Extract OCR (plate) data
    $plate = $payload['ocr']['label'] ?? null;
    $plateState = $payload['ocr']['state'] ?? null;
    $plateConfidence = $payload['ocr']['labelConfidence'] ?? null;
    $stateConfidence = $payload['ocr']['stateConfidence'] ?? null;

    // Extract source/hotlist info (first source is primary)
    $sources = $payload['sources'] ?? [];
    $primarySource = $sources[0] ?? [];
    $reason = $primarySource['reason'] ?? 'Unknown';
    $hotlistName = $primarySource['name'] ?? 'Unknown';
    $caseNumber = $primarySource['caseNumber'] ?? null;

    // Build unique alert ID
    $objectId = $payload['objectId'] ?? uniqid('flock-');
    $alertId = 'flock-' . $objectId;

    // Map Flock's full state name to 2-letter abbreviation
    $plateStateAbbr = $plateState ? mapStateAbbreviation($plateState) : null;
    $plateStateDisplay = $plateStateAbbr ?: ($plateState ? strtoupper($plateState) : null);

    // Build description line for CAD Comment field
    $descParts = ["BOLO: $plate"];
    if ($plateStateDisplay) {
        $descParts[0] .= " ($plateStateDisplay)";
    }
    $descParts[] = $reason;
    if ($caseNumber) {
        $descParts[] = "Case# $caseNumber";
    }
    $description = implode(' - ', $descParts);

    // Build detailed comments for CFSNote
    $commentLines = [];
    $commentLines[] = "Flock Safety LPR Alert";
    $commentLines[] = "Plate: $plate" . ($plateStateDisplay ? " ($plateStateDisplay)" : "");
    if ($plateConfidence !== null) {
        $commentLines[] = "Plate Confidence: " . round($plateConfidence * 100, 1) . "%";
    }
    $commentLines[] = "Reason: $reason";
    $commentLines[] = "Hotlist: $hotlistName";
    if ($caseNumber) {
        $commentLines[] = "Case Number: $caseNumber";
    }
    // Include all sources if multiple
    if (count($sources) > 1) {
        for ($i = 1; $i < count($sources); $i++) {
            $s = $sources[$i];
            $commentLines[] = "Additional Match: " . ($s['name'] ?? '') . " - " . ($s['reason'] ?? '') . ($s['caseNumber'] ? " (Case# {$s['caseNumber']})" : "");
        }
    }
    $commentLines[] = "Camera: " . ($payload['deviceName'] ?? 'Unknown');
    if (!empty($payload['networkName'])) {
        $commentLines[] = "Network: " . $payload['networkName'];
    }
    if (!empty($payload['detailsUrl'])) {
        $commentLines[] = "Details: " . $payload['detailsUrl'];
    }
    if (!empty($payload['imageUrl'])) {
        $commentLines[] = "Image: " . $payload['imageUrl'];
    }
    $comments = implode("\n", $commentLines);

    // Parse event time (ISO 8601 string → Unix ms for writeToDB compatibility)
    $eventTimeMs = null;
    if (!empty($payload['eventTime'])) {
        $ts = strtotime($payload['eventTime']);
        if ($ts !== false) {
            $eventTimeMs = $ts * 1000;
        }
    }

    // Transform to format that writeToDB.php / extractRapidSOSData() expects
    $transformed = [
        'alert_id' => $alertId,
        'source_id' => $objectId,
        'reference_number' => $objectId,

        'source_system' => 'FlockSafety',
        'service_provider_name' => 'Flock Safety',

        'emergency_type' => [
            'name' => 'LPR_ALERT',
            'display_name' => 'LPR Alert - ' . $reason
        ],
        'call_type_alias' => $config['cad_call_type'],

        // Camera location (no civic address — reverse geocoding will handle it)
        'location' => [
            'geodetic' => [
                'latitude' => $payload['deviceLat'] ?? null,
                'longitude' => $payload['deviceLong'] ?? null,
                'uncertainty_radius' => null
            ],
            'civic' => [
                'street_1' => null,
                'street_2' => null,
                'city' => null,
                'state' => null,
                'zip_code' => null,
                'country' => 'United States',
                'name' => $payload['deviceName'] ?? null
            ]
        ],

        // No caller for LPR alerts
        'contact' => [
            'full_name' => 'Flock Safety LPR',
            'first_name' => null,
            'last_name' => null,
            'phone' => null,
            'email' => null
        ],

        'user' => [
            'full_name' => 'Flock Safety LPR',
            'phone_number' => null,
            'email' => null,
            'first_name' => null,
            'last_name' => null
        ],

        // Alert details
        'description' => $description,
        'comments' => $comments,
        'status' => [
            'name' => 'ACTIVE'
        ],

        // Timestamps
        'created_time' => $eventTimeMs ?? (time() * 1000),
        'submitted_time' => time() * 1000,
        'last_updated_time' => time() * 1000,
        'incident_time' => $eventTimeMs ?? (time() * 1000),

        // Vehicle data from OCR
        'vehicle' => [
            'plate_number' => $plate,
            'plate_state' => $plateStateAbbr,
            'make' => null,
            'model' => null,
            'color' => null
        ],

        // Store full Flock payload for reference
        'additional_data' => [
            'device_external_id' => $payload['deviceExternalId'] ?? null,
            'network_name' => $payload['networkName'] ?? null,
            'network_external_id' => $payload['networkExternalId'] ?? null,
            'image_url' => $payload['imageUrl'] ?? null,
            'image_expiration' => $payload['imageExpiration'] ?? null,
            'details_url' => $payload['detailsUrl'] ?? null,
            'plate_confidence' => $plateConfidence,
            'state_confidence' => $stateConfidence,
            'sources' => $sources
        ]
    ];

    return $transformed;
}

// Transform the Flock payload
try {
    $transformedAlert = transformFlockAlert($payload, $flockConfig);

    logWebhookActivity('alert_transformed', [
        'alert_id' => $transformedAlert['alert_id'],
        'call_type_alias' => $transformedAlert['call_type_alias'],
        'plate' => $transformedAlert['vehicle']['plate_number'] ?? 'unknown',
        'description' => $transformedAlert['description']
    ]);

    // Wrap in 'alerts' array to match writeToDB.php expectations
    $postData = [
        'source' => 'flocksafety',
        'alerts' => [$transformedAlert]
    ];

    // Post to writeToDB.php
    $targetUrl = $flockConfig['target_endpoint'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $targetUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Webhook-Source: flocksafety'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    logWebhookActivity('posting_to_cad', [
        'url' => $targetUrl,
        'alert_id' => $transformedAlert['alert_id']
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
        sendResponse(200, 'Alert processed', [
            'cfs_number' => $responseData['cad_response']['cfs_number'] ?? null,
            'record_id' => $responseData['record_id'] ?? null
        ]);
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
