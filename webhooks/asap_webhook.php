<?php

/**
 * ASAP-to-PSAP Webhook Receiver
 *
 * Receives APCO/TMA ASAP alarm XML payloads from alarm monitoring companies,
 * transforms the XML to match the CAD system's expected format, and forwards
 * to writeToDB.php for database insertion and CAD posting.
 *
 * Endpoint: POST /webhooks/asap_webhook.php
 * Content-Type: application/xml
 * Authorization: Bearer <token>
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../lib/asap_transformer.php';

header('Content-Type: application/json');

// ─── Configuration ──────────────────────────────────────────────────────────
// TODO: Update bearer token after ASAP-to-PSAP meeting
$BEARER_TOKEN = 'change-me-token';
$MAX_BODY_BYTES = 1048576; // 1MB
$TARGET_URL = 'https://my.berkeleycountysc.gov/redfive/api/writeToDB.php';
$LOG_DIR = __DIR__ . '/../logs';

// ─── Helpers ────────────────────────────────────────────────────────────────

function sendResponse(int $statusCode, string $message, $data = null): void
{
    http_response_code($statusCode);
    $response = ['message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

function logWebhookActivity(string $action, array $data = []): void
{
    global $LOG_DIR;
    $logFile = $LOG_DIR . '/asap_webhook_debug.log';
    if (!is_dir($LOG_DIR)) {
        mkdir($LOG_DIR, 0755, true);
    }
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'data' => $data,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    file_put_contents($logFile, json_encode($logEntry, JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND);
    @chmod($logFile, 0666);
}

/**
 * Log CAD API response for every processed alarm.
 * This is our interim "callback" log until we know the ASAP acknowledgment format.
 */
function logCadResponse(string $alertId, int $httpCode, ?string $response, ?string $curlError = null): void
{
    global $LOG_DIR;
    $logFile = $LOG_DIR . '/asap_cad_responses.log';
    if (!is_dir($LOG_DIR)) {
        mkdir($LOG_DIR, 0755, true);
    }
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'alert_id' => $alertId,
        'http_code' => $httpCode,
        'response' => $response,
        'curl_error' => $curlError
    ];
    file_put_contents($logFile, json_encode($logEntry, JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND);
    @chmod($logFile, 0666);
}

function getBearerToken(): ?string
{
    $authHeader = null;
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['Authorization'])) {
        $authHeader = $_SERVER['Authorization'];
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $key => $val) {
            if (strcasecmp($key, 'Authorization') === 0) {
                $authHeader = $val;
                break;
            }
        }
    }
    if ($authHeader && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
        return trim($m[1]);
    }
    return null;
}

/**
 * Maps ASAP alarm event types to Southern Software CAD CallTypeAlias values.
 * Must match the exact codes configured in the CAD system.
 */
function mapAsapAlarmTypeToCallType(string $alarmType): string
{
    $mapping = [
        'BURGLARY'           => '104 ALARMS - LAW',
        'INTRUSION'          => '104 ALARMS - LAW',
        'PANIC'              => '104 ALARMS - LAW',
        'HOLDUP'             => '104 ALARMS - LAW',
        'DURESS'             => '104 ALARMS - LAW',
        'ROBBERY'            => '104 ALARMS - LAW',
        'ALARM'              => '104 ALARMS - LAW',
        'FIRE'               => '52 ALARMS - FIRE',
        'SMOKE'              => '52 ALARMS - FIRE',
        'CARBON MONOXIDE'    => '52 ALARMS - FIRE',
        'GAS'                => '52 ALARMS - FIRE',
        'WATER'              => '52 ALARMS - FIRE',
        'ENVIRONMENTAL'      => '52 ALARMS - FIRE',
        'HEAT'               => '52 ALARMS - FIRE',
        'MEDICAL'            => '32 UNKNOWN PROBLEM',
        'PERSONAL EMERGENCY' => '32 UNKNOWN PROBLEM',
    ];

    $type = strtoupper(trim($alarmType));
    return $mapping[$type] ?? '104 ALARMS - LAW';
}

/**
 * Transform ASAP structured data into the format expected by writeToDB.php.
 *
 * Follows the same pattern as helpme911_webhook.php:
 * - Wraps data in {"alerts": [...]} format for extractRapidSOSData()
 * - Adds top-level fields for ASAP-specific data the extractor doesn't handle
 *
 * @param array $asap Output of transform_apco()
 * @param string $rawXml The original XML payload for storage
 * @return array Ready to JSON-encode and POST to writeToDB.php
 */
function transformAsapForWriteToDB(array $asap, string $rawXml): array
{
    $loc = $asap['service_location'] ?? [];
    $street = $loc['street'] ?? [];
    $event = $asap['event'] ?? [];
    $subscriber = $asap['subscriber'] ?? [];
    $contacts = $asap['contacts'] ?? [];
    $vehicle = $asap['vehicle'] ?? [];
    $monitoringStation = $asap['monitoring_station'] ?? [];
    $serviceOrg = $asap['service_organization'] ?? [];

    // ── Caller info from subscriber ──
    $callerName = trim(implode(' ', array_filter([
        $subscriber['given'] ?? null,
        $subscriber['middle'] ?? null,
        $subscriber['surname'] ?? null,
    ]))) ?: null;

    $callerPhone = $contacts['subscriber'][0] ?? null;

    // ── Street address string (number + predirectional + name + type) ──
    $streetAddress = trim(implode(' ', array_filter([
        $street['number'] ?? null,
        $street['predirectional'] ?? null,
        $street['name'] ?? null,
        $street['category'] ?? null,
    ]))) ?: null;

    // ── Unit/apartment ──
    $unit = $loc['unit'] ?? null;
    $unitStr = $unit ? ('SUITE ' . $unit) : null;

    // ── Alarm type mapping ──
    $alarmType = $asap['alarm_type'] ?? $event['type'] ?? 'Alarm';
    $callTypeAlias = mapAsapAlarmTypeToCallType($alarmType);

    // ── Coordinates ──
    $latitude = isset($loc['map']['lat_text']) ? (float) $loc['map']['lat_text'] : null;
    $longitude = isset($loc['map']['lon_text']) ? (float) $loc['map']['lon_text'] : null;

    // ── Short comment for CAD Comment field (255 char limit) ──
    $shortParts = [$alarmType];
    if ($event['details'] ?? null) {
        $shortParts[] = $event['details'];
    }
    if ($event['audible_description'] ?? null) {
        $shortParts[] = '[' . $event['audible_description'] . ']';
    }
    $shortComment = implode(' - ', $shortParts);
    if (strlen($shortComment) > 250) {
        $shortComment = substr($shortComment, 0, 247) . '...';
    }

    // ── Build the alert in RapidSOS-compatible format for extractRapidSOSData() ──
    $alert = [
        'alert_id' => 'asap-' . ($asap['event_id'] ?? uniqid()),
        'source_id' => $asap['event_id'] ?? null,

        'location' => [
            'geodetic' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'uncertainty_radius' => null,
            ],
            'civic' => [
                'name' => $loc['name'] ?? null,
                'street_1' => $streetAddress,
                'street_2' => $unitStr,
                'city' => $loc['city'] ?? null,
                'state' => $loc['state'] ?? null,
                'zip_code' => $loc['postal_code'] ?? null,
                'country' => 'United States',
            ],
        ],

        'emergency_type' => [
            'name' => $alarmType,
            'display_name' => $alarmType . ($event['location_category'] ? ' (' . $event['location_category'] . ')' : ''),
        ],
        'call_type_alias' => $callTypeAlias,

        'service_provider_name' => $monitoringStation['name'] ?? $serviceOrg['name'] ?? null,
        'description' => $event['details'] ?? null,
        'status' => ['name' => 'NEW'],

        'incident_time' => $event['datetime'] ?? null,
        'last_updated_time' => $event['datetime'] ?? null,

        'site_type' => [
            'name' => $event['location_category'] ?? null,
            'display_name' => $event['location_category'] ?? null,
        ],

        'covering_psap' => [
            'name' => $event['dispatch_agency']['name'] ?? null,
        ],

        'authorized_entity' => [
            'phone' => $contacts['operator'][0] ?? null,
        ],
    ];

    // ── Build top-level fields for data not in the RapidSOS alerts format ──
    return [
        'source_system' => 'ASAP-to-PSAP',
        'alerts' => [$alert],

        // Contact info (extractRapidSOSData doesn't pull these from alerts format)
        'sContactFullName' => $callerName,
        'sContactFirstName' => $subscriber['given'] ?? null,
        'sContactLastName' => $subscriber['surname'] ?? null,
        'sContactPhone' => $callerPhone,

        // ASAP-specific alarm fields
        'sPermitNumber' => $event['permit']['id'] ?? null,
        'sAlarmPermitNumber' => $event['permit']['id'] ?? null,
        'sIsAudible' => $event['audible_description'] ?? null,
        'sCrossStreet' => $loc['cross_street'] ?? null,
        'sLocationName' => $loc['name'] ?? null,
        'sBuildingName' => $loc['building'] ?? null,
        'sAccessInstructions' => $loc['directions'] ?? null,
        'sAlarmDescription' => $event['details'] ?? null,
        'sComments' => $shortComment,
        'sRemarks' => $event['confirmation_text'] ?? null,
        'sInstructions' => $loc['info'] ?? null,

        // Service provider / monitoring station
        'sServiceProviderPhone' => $serviceOrg['phone'] ?? null,
        'sCentralStationPhone' => $contacts['operator'][0] ?? null,
        'sSourceEventCode' => $event['category'] ?? null,
        'sTransmitterId' => $monitoringStation['source_id'] ?? null,

        // Vehicle info
        'sVehicleMake' => $vehicle['make_code'] ?? null,
        'sVehicleModel' => $vehicle['model_code'] ?? null,
        'sVehicleColor' => $vehicle['color'] ?? null,
        'sVehiclePlateNumber' => $vehicle['plate_id'] ?? null,
        'sVehiclePlateState' => $vehicle['plate_source'] ?? null,

        // Client IP from webhook request
        'sClientIp' => $_SERVER['REMOTE_ADDR'] ?? null,

        // Store full ASAP payload as flow data (JSON-encoded by writeToDB.php)
        'sFlowData' => $asap,
    ];
}

// ─── Main Request Handler ───────────────────────────────────────────────────

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logWebhookActivity('method_not_allowed', ['method' => $_SERVER['REQUEST_METHOD']]);
    sendResponse(405, 'Method not allowed. Only POST requests are accepted.');
}

$rawInput = file_get_contents('php://input', false, null, 0, $MAX_BODY_BYTES + 1);
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

logWebhookActivity('incoming_request', [
    'content_type' => $contentType,
    'content_length' => strlen($rawInput ?: ''),
    'headers' => function_exists('getallheaders') ? getallheaders() : [],
    'body_preview' => substr($rawInput ?: '', 0, 300)
]);

// Validate content type (accept application/xml or text/xml)
if (stripos($contentType, 'xml') === false) {
    logWebhookActivity('unsupported_media_type', ['content_type' => $contentType]);
    sendResponse(415, 'Content-Type must be application/xml or text/xml');
}

// Validate bearer token
// TODO: Auth mechanism TBD after ASAP-to-PSAP meeting
$token = getBearerToken();
if ($token === null || $token !== $BEARER_TOKEN) {
    logWebhookActivity('auth_failed', ['token_present' => $token !== null]);
    sendResponse(401, 'Unauthorized');
}

// Validate body
if ($rawInput === false || $rawInput === '') {
    logWebhookActivity('empty_body');
    sendResponse(400, 'Empty request body');
}

if (strlen($rawInput) > $MAX_BODY_BYTES) {
    logWebhookActivity('body_too_large', ['size' => strlen($rawInput)]);
    sendResponse(413, 'Request entity too large');
}

// Parse XML
libxml_use_internal_errors(true);
$xml = simplexml_load_string($rawInput);
if ($xml === false) {
    $errors = array_map(fn($e) => trim($e->message), libxml_get_errors());
    libxml_clear_errors();
    logWebhookActivity('invalid_xml', ['errors' => $errors]);
    sendResponse(400, 'Invalid XML', ['errors' => $errors]);
}

// Validate required APCO namespaces
$requiredNamespaces = ['apco-alarm', 'em', 'j', 's', 'nc'];
$docNamespaces = $xml->getDocNamespaces(true);
$missing = array_values(array_filter($requiredNamespaces, fn($ns) => !array_key_exists($ns, $docNamespaces)));

if ($missing) {
    logWebhookActivity('missing_namespaces', [
        'missing' => $missing,
        'found' => array_keys($docNamespaces)
    ]);
    sendResponse(400, 'Missing required APCO namespaces', ['missing' => $missing]);
}

// ─── Transform and Forward ──────────────────────────────────────────────────

// Step 1: Parse XML into structured array
$transformed = transform_apco($xml);

logWebhookActivity('xml_transformed', [
    'event_id' => $transformed['event_id'] ?? 'unknown',
    'alarm_type' => $transformed['alarm_type'] ?? 'unknown',
    'city' => $transformed['service_location']['city'] ?? 'unknown'
]);

// Step 2: Build writeToDB.php-compatible payload
$postData = transformAsapForWriteToDB($transformed, $rawInput);
$alertId = $postData['alerts'][0]['alert_id'] ?? 'unknown';

logWebhookActivity('posting_to_writetodb', [
    'alert_id' => $alertId,
    'call_type_alias' => $postData['alerts'][0]['call_type_alias'] ?? 'unknown',
    'target_url' => $TARGET_URL
]);

// Step 3: POST to writeToDB.php via curl
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $TARGET_URL);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Webhook-Source: asap-to-psap'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Step 4: Log CAD response (interim "callback" until ASAP ack format is known)
logCadResponse($alertId, $httpCode, $response, $curlError ?: null);

logWebhookActivity('writetodb_response', [
    'alert_id' => $alertId,
    'http_code' => $httpCode,
    'response' => $response,
    'curl_error' => $curlError ?: null
]);

if ($httpCode >= 200 && $httpCode < 300) {
    $responseData = json_decode($response, true);
    sendResponse(200, 'ASAP alarm processed successfully', [
        'alert_id' => $alertId,
        'cfs_number' => $responseData['cad_response']['cfs_number'] ?? null,
        'record_id' => $responseData['record_id'] ?? null
    ]);
} else {
    logWebhookActivity('writetodb_error', [
        'http_code' => $httpCode,
        'response' => $response,
        'curl_error' => $curlError
    ]);
    sendResponse(502, 'Failed to process ASAP alarm', [
        'alert_id' => $alertId,
        'http_code' => $httpCode,
        'error' => $response
    ]);
}
