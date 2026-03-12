<?php
// RapidSOS Webhook Endpoint
// This script receives webhook notifications from RapidSOS and processes emergency alerts

require_once __DIR__ . '/../config/rapidsos_config.php';
require_once __DIR__ . '/../lib/rapidsos_auth.php';
require_once __DIR__ . '/../lib/rapidsos_data_mapper.php';
require_once __DIR__ . '/../lib/rapidsos_websocket_mapper.php';
require_once __DIR__ . '/../lib/southern_software_cad.php';
require_once __DIR__ . '/../secrets/db.php';

// Set up configuration and authentication
$config = require __DIR__ . '/../config/rapidsos_config.php';
$auth = new RapidSOSAuth($config);
$legacyMapper = new RapidSOSDataMapper();
$websocketMapper = new RapidSOSWebSocketMapper();
$cadClient = new SouthernSoftwareCAD();

// Database connection for CFS lookups on update events
$dbConn = null;
try {
    $dbConfig = new acoConfig();
    $dbConn = new PDO(
        "sqlsrv:Server={$dbConfig->serverName};Database={$dbConfig->database};ConnectionPooling=0;TrustServerCertificate=1;Encrypt=0",
        $dbConfig->uid,
        $dbConfig->pwd
    );
    $dbConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    // DB failures should NOT block alert processing
    error_log('Webhook DB connection failed (non-fatal): ' . $e->getMessage());
}

// Set response header
header('Content-Type: application/json');

function logWebhookActivity($action, $data = [])
{
    $logFile = __DIR__ . '/../logs/webhook_debug.log';
    $logDir = dirname($logFile);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'data' => $data,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];

    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND);
}

function sendResponse($status, $message, $data = null)
{
    http_response_code($status);
    $response = ['message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

try {
    // Log incoming request
    $rawInput = file_get_contents('php://input');
    $headers = getallheaders();

    logWebhookActivity('incoming_request', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'content_length' => strlen($rawInput),
        'headers' => $headers,
        'raw_body_preview' => substr($rawInput, 0, 200) . (strlen($rawInput) > 200 ? '...' : '')
    ]);

    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(405, 'Method not allowed');
    }

    // Check for required headers (TEMPORARILY DISABLED FOR TESTING)
    $rapidSOSSignature = $headers['X-RapidSOS-Signature'] ?? $headers['x-rapidsos-signature'] ?? null;

    // TODO: Re-enable signature verification once we get the webhook_secret from RapidSOS
    /*
    if (!$rapidSOSSignature) {
        logWebhookActivity('missing_signature', ['headers' => array_keys($headers)]);
        sendResponse(400, 'Missing X-RapidSOS-Signature header');
    }

    // Verify webhook signature
    try {
        $isValidSignature = $auth->verifyWebhookSignature($rawInput, $rapidSOSSignature);
        if (!$isValidSignature) {
            logWebhookActivity('invalid_signature', [
                'signature' => $rapidSOSSignature,
                'body_length' => strlen($rawInput)
            ]);
            sendResponse(401, 'Invalid webhook signature');
        }

        logWebhookActivity('signature_verified', ['signature_valid' => true]);
    } catch (Exception $e) {
        logWebhookActivity('signature_error', ['error' => $e->getMessage()]);
        sendResponse(500, 'Signature verification failed: ' . $e->getMessage());
    }
    */

    // For now, just log if signature is present
    if ($rapidSOSSignature) {
        logWebhookActivity('signature_present', ['signature' => substr($rapidSOSSignature, 0, 20) . '...']);
    } else {
        logWebhookActivity('signature_missing', ['note' => 'Accepting request anyway for testing']);
    }

    // Parse JSON payload
    $payload = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logWebhookActivity('json_error', ['error' => json_last_error_msg()]);
        sendResponse(400, 'Invalid JSON payload');
    }

    logWebhookActivity('payload_received', [
        'event_type' => $payload['event_type'] ?? $payload['event'] ?? 'unknown',
        'has_body' => isset($payload['body']),
        'has_data' => isset($payload['data']),
        'has_event_type' => isset($payload['event_type']),
        'timestamp' => $payload['timestamp'] ?? 'unknown'
    ]);

    // Determine payload format and process accordingly
    $extractedData = null;

    if (isset($payload['event_type']) && isset($payload['body'])) {
        // Official Webhook format (event_type + body)
        logWebhookActivity('format_detected', ['format' => 'webhook_events_api']);
        $extractedData = [
            'format' => 'webhook',
            'alerts' => [$payload] // Wrap in array for consistent processing
        ];
    } elseif (isset($payload['event']) && isset($payload['body'])) {
        // Official WebSocket Events API v1.1.1 format
        logWebhookActivity('format_detected', ['format' => 'websocket_events_api']);
        $extractedData = $websocketMapper->extractWebSocketEvent($payload);
    } else {
        // Legacy format - use existing mapper
        logWebhookActivity('format_detected', ['format' => 'legacy']);
        $extractedData = $legacyMapper->extractAlertData($payload);
    }

    logWebhookActivity('data_mapped', [
        'format_detected' => $extractedData['format'],
        'alerts_count' => count($extractedData['alerts'] ?? [])
    ]);

    // Process each alert
    $results = [];
    foreach ($extractedData['alerts'] ?? [] as $alert) {
        // Determine event type from the webhook envelope
        $eventType = $payload['event_type'] ?? $payload['event'] ?? 'unknown';

        // Extract alert_id from wherever it lives in this payload
        $alertId = $alert['alert_id']
            ?? $alert['body']['alert_id']
            ?? $payload['body']['alert_id']
            ?? 'unknown';

        // Route based on event type:
        // - alert.new → Create new CFS in CAD
        // - All others → Log fully, update existing CFS (TODO: wire to SS PATCH/POST endpoints)
        $updateEventTypes = [
            'alert.status_update',
            'alert.disposition_update',
            'alert.location_update',
            'alert.chat',
            'alert.milestone',
            'alert.multi_trip_signal',
        ];

        $isUpdateEvent = in_array($eventType, $updateEventTypes);

        if ($isUpdateEvent) {
            $result = handleUpdateEvent($eventType, $alertId, $alert, $payload);
        } else {
            // alert.new or unknown → create new CFS
            $result = forwardAlertToWriteToDB($alert, $eventType, false);
        }

        $results[] = $result;
        logWebhookActivity('alert_processed', array_merge($result, [
            'event_type' => $eventType,
            'alert_id' => $alertId,
            'is_update' => $isUpdateEvent
        ]));
    }

    // Send success response
    sendResponse(200, 'Webhook processed successfully', [
        'format' => $extractedData['format'],
        'alerts_processed' => count($results),
        'results' => $results
    ]);
} catch (Exception $e) {
    logWebhookActivity('error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    sendResponse(500, 'Internal server error: ' . $e->getMessage());
}

/**
 * Handle update events for existing alerts.
 * Looks up the existing CFS by alert_id, then routes to the appropriate
 * Southern Software PATCH/POST endpoint to update the call in real time.
 */
function handleUpdateEvent($eventType, $alertId, $alertData, $rawPayload)
{
    global $cadClient, $dbConn;

    // Extract the body data (webhook format wraps in 'body')
    $body = $alertData['body'] ?? $alertData;

    // Log the full update event
    $updateLogFile = __DIR__ . '/../logs/rapidsos_update_events.log';
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event_type' => $eventType,
        'alert_id' => $alertId,
        'body' => $body,
        'raw_payload' => $rawPayload
    ];
    file_put_contents($updateLogFile, json_encode($logEntry, JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND);
    @chmod($updateLogFile, 0666);

    logWebhookActivity('update_event_received', [
        'event_type' => $eventType,
        'alert_id' => $alertId,
        'body_keys' => array_keys($body)
    ]);

    // --- Skip our own echoes ---
    // When entity_id is our PSAP, this is our own callback being echoed back
    $entityId = $body['entity_id'] ?? null;
    if ($entityId === 'ID_Berkeley' && in_array($eventType, ['alert.status_update', 'alert.disposition_update'])) {
        logWebhookActivity('own_echo_skipped', [
            'event_type' => $eventType,
            'alert_id' => $alertId,
            'entity_id' => $entityId
        ]);
        return [
            'success' => true,
            'action' => 'own_echo_skipped',
            'event_type' => $eventType,
            'alert_id' => $alertId,
            'message' => 'Skipped — this is our own callback echoed back'
        ];
    }

    // --- Look up the CFS number for this alert (with retry for race conditions) ---
    $cfsNumber = null;
    $maxRetries = 2;
    for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
        $cfsNumber = lookupCfsNumber($alertId);
        if ($cfsNumber) break;
        if ($attempt < $maxRetries) {
            logWebhookActivity('cfs_lookup_retry', [
                'alert_id' => $alertId,
                'attempt' => $attempt + 1,
                'event_type' => $eventType
            ]);
            sleep(3);
        }
    }
    if (!$cfsNumber) {
        logWebhookActivity('cfs_lookup_failed', [
            'alert_id' => $alertId,
            'event_type' => $eventType,
            'retries_attempted' => $maxRetries
        ]);
        return [
            'success' => false,
            'action' => 'cfs_lookup_failed',
            'event_type' => $eventType,
            'alert_id' => $alertId,
            'message' => 'No CFS found for this alert_id after ' . ($maxRetries + 1) . ' attempts'
        ];
    }

    logWebhookActivity('cfs_found', [
        'alert_id' => $alertId,
        'cfs_number' => $cfsNumber
    ]);

    // --- Update dtUpdatedDateTime in the database ---
    try {
        if ($dbConn) {
            $updateStmt = $dbConn->prepare(
                "UPDATE IncomingAlertData SET dtUpdatedDateTime = GETUTCDATE() 
                 WHERE sSourceId = :alert_id AND sCfsNumber IS NOT NULL AND sCadStatus = 'POSTED'"
            );
            $updateStmt->execute([':alert_id' => $alertId]);
            logWebhookActivity('db_timestamp_updated', [
                'alert_id' => $alertId,
                'rows_affected' => $updateStmt->rowCount()
            ]);
        }
    } catch (Exception $e) {
        // DB update failure is non-fatal
        error_log('Failed to update dtUpdatedDateTime: ' . $e->getMessage());
    }

    // --- Route to the appropriate SS endpoint ---
    $cadResult = null;

    switch ($eventType) {
        case 'alert.location_update':
            // Add a note with the updated location info (don't PATCH the CFS location mid-call)
            $lat = $body['geodetic']['latitude'] ?? '?';
            $lon = $body['geodetic']['longitude'] ?? '?';
            $addr = $body['civic']['street_1'] ?? 'unknown';
            $city = $body['civic']['city'] ?? '';
            $state = $body['civic']['state'] ?? '';
            $note = "LOCATION UPDATE: {$addr}, {$city} {$state} | GPS: {$lat}, {$lon}";
            $cadResult = $cadClient->addNote($cfsNumber, $note);
            break;

        case 'alert.status_update':
            // POST /CFSNote logging the external status change
            $statusName = $body['status']['name'] ?? 'unknown';
            $statusDisplay = $body['status']['display_name'] ?? $statusName;
            $sender = $body['sender'] ?? 'System';
            $entity = $body['entity_display_name'] ?? 'Unknown';
            $note = "STATUS UPDATE ({$entity}): {$statusDisplay} [by {$sender}]";
            $cadResult = $cadClient->addNote($cfsNumber, $note);
            break;

        case 'alert.disposition_update':
            // POST /CFSNote logging the external disposition change
            $dispName = $body['disposition']['name'] ?? 'unknown';
            $dispDisplay = $body['disposition']['display_name'] ?? $dispName;
            $entity = $body['entity_display_name'] ?? 'Unknown';
            $note = "DISPOSITION UPDATE ({$entity}): {$dispDisplay}";
            $cadResult = $cadClient->addNote($cfsNumber, $note);
            break;

        case 'alert.chat':
            // POST /CFSNote with the chat message
            $sender = $body['sender'] ?? 'Unknown';
            $message = $body['message'] ?? '';
            $entity = $body['entity_display_name'] ?? '';
            $note = "CHAT ({$entity} - {$sender}): {$message}";
            $cadResult = $cadClient->addNote($cfsNumber, $note);
            break;

        case 'alert.milestone':
            // POST /CFSNote with the milestone
            $message = $body['message'] ?? '';
            $note = "MILESTONE: {$message}";
            $cadResult = $cadClient->addNote($cfsNumber, $note);
            break;

        case 'alert.multi_trip_signal':
            $message = $body['message'] ?? '';
            $note = "MULTI-TRIP SIGNAL: {$message}";
            $cadResult = $cadClient->addNote($cfsNumber, $note);
            break;
    }

    logWebhookActivity('cad_update_result', [
        'event_type' => $eventType,
        'alert_id' => $alertId,
        'cfs_number' => $cfsNumber,
        'cad_result' => $cadResult
    ]);

    return [
        'success' => $cadResult['success'] ?? false,
        'action' => 'cad_updated',
        'event_type' => $eventType,
        'alert_id' => $alertId,
        'cfs_number' => $cfsNumber,
        'cad_result' => $cadResult
    ];
}

/**
 * Look up the CFS number for a given RapidSOS alert_id.
 * Queries the database for the most recent POSTED record with this alert_id.
 */
function lookupCfsNumber($alertId)
{
    global $dbConn;

    if (!$dbConn || !$alertId) {
        return null;
    }

    try {
        $stmt = $dbConn->prepare(
            "SELECT TOP 1 sCfsNumber FROM IncomingAlertData 
             WHERE sSourceId = :alert_id AND sCfsNumber IS NOT NULL AND sCadStatus = 'POSTED'
             ORDER BY dtCreatedDateTime DESC"
        );
        $stmt->execute([':alert_id' => $alertId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['sCfsNumber'] : null;
    } catch (Exception $e) {
        error_log('CFS lookup failed: ' . $e->getMessage());
        return null;
    }
}

function forwardAlertToWriteToDB($alertData, $eventType, $isStatusUpdate = false)
{
    global $config;

    // Transform RapidSOS webhook alert data to match the format expected by writeToDB.php
    $transformedData = transformWebhookAlert($alertData, $eventType);

    // CRITICAL: writeToDB.php expects data wrapped in 'alerts' array
    // Wrap the transformed alert in the expected format
    $payload = [
        'alerts' => [$transformedData]
    ];

    // Log the payload being sent for debugging
    $debugLogFile = __DIR__ . '/../api/webhook_to_cad_payload.log';
    $debugEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event_type' => $eventType,
        'alert_id' => $transformedData['rapidsos_alert_id'] ?? 'unknown',
        'payload' => $payload,
        'payload_size' => strlen(json_encode($payload))
    ];
    file_put_contents($debugLogFile, json_encode($debugEntry, JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND);

    // Call writeToDB.php endpoint
    $writeToDBUrl = $config['target_endpoint'];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $writeToDBUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Webhook-Source: rapidsos',
            'X-Event-Type: ' . $eventType
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Log the response
    $responseLogFile = __DIR__ . '/../api/cad_response_debug.log';
    $responseEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'http_code' => $httpCode,
        'curl_error' => $error ?: null,
        'response' => $response,
        'alert_id' => $transformedData['rapidsos_alert_id'] ?? 'unknown'
    ];
    file_put_contents($responseLogFile, json_encode($responseEntry, JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND);

    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'curl_error' => $error ?: null,
        'response' => $response,
        'transformed_data_size' => strlen(json_encode($payload))
    ];
}

/**
 * Maps RapidSOS emergency types to Southern Software CallTypeAlias values
 * Based on mappings provided by Berkeley County
 */
function mapEmergencyTypeToCallType($rapidSOSEmergencyType)
{
    // Map RapidSOS emergency types to exact Southern Software CAD call type codes
    // CRITICAL: These strings must EXACTLY match what's in the CAD dropdown
    $mapping = [
        // Fire-related
        'FIRE' => '52 ALARMS - FIRE',
        'FIRE_ALARM' => '52 ALARMS - FIRE',
        'CO' => '52 ALARMS - FIRE',              // Carbon monoxide → fire response
        'CARBON_MONOXIDE' => '52 ALARMS - FIRE',
        'SMOKE' => '52 ALARMS - FIRE',

        // Medical
        'MEDICAL' => '32 UNKNOWN PROBLEM',
        'MEDICAL_ALERT' => '32 UNKNOWN PROBLEM',

        // Law enforcement / alarms
        'BURGLARY' => '104 ALARMS - LAW',
        'PANIC' => '104 ALARMS - LAW',
        'MOBILE_PANIC' => '104 ALARMS - LAW',
        'HOLDUP' => '104 ALARMS - LAW',
        'DURESS' => '104 ALARMS - LAW',
        'ACTIVE_ASSAILANT' => '104 ALARMS - LAW',
        'INTRUSION' => '104 ALARMS - LAW',
        'PERIMETER' => '104 ALARMS - LAW',
        'GLASS_BREAK' => '104 ALARMS - LAW',
        'DOOR' => '104 ALARMS - LAW',

        // General / other
        'OTHER' => '32 UNKNOWN PROBLEM',
        'UNKNOWN' => '32 UNKNOWN PROBLEM',
        'TEST' => '32 UNKNOWN PROBLEM',
    ];

    // Normalize input (uppercase, trim, underscores for spaces)
    $type = strtoupper(trim($rapidSOSEmergencyType));
    $type = str_replace(' ', '_', $type);

    // Return mapped value or default
    return $mapping[$type] ?? '32 UNKNOWN PROBLEM';
}

function transformWebhookAlert($alertData, $eventType)
{
    // Transform the webhook alert data to match the format expected by writeToDB.php
    // Handles both WebSocket Events API v1.1.1 (with 'body' wrapper) and legacy formats

    // Extract data from 'body' if present (official webhook format)
    $data = isset($alertData['body']) ? $alertData['body'] : $alertData;

    // CRITICAL: writeToDB.php's extractRapidSOSData expects specific field structure
    // We need to preserve the original RapidSOS structure with our enhancements

    // Start with the original data structure from RapidSOS
    $transformed = $data;

    // Add computed fields and enhancements
    $transformed['webhook_event_type'] = $eventType;
    $transformed['alert_id'] = $data['alert_id'] ?? $data['id'] ?? null;

    // Add emergency type mapping if available
    if (isset($data['emergency_type'])) {
        $emergencyType = $data['emergency_type'];
        $rapidSOSType = is_array($emergencyType)
            ? ($emergencyType['name'] ?? $emergencyType['display_name'] ?? null)
            : $emergencyType;

        if ($rapidSOSType) {
            $transformed['call_type_alias'] = mapEmergencyTypeToCallType($rapidSOSType);
        }
    }

    // Ensure location structure exists
    if (!isset($transformed['location'])) {
        $transformed['location'] = [];
    }

    // Ensure we have location data in the expected format
    if (isset($data['location'])) {
        $location = $data['location'];

        // Preserve geodetic data
        if (isset($location['geodetic'])) {
            $transformed['location']['geodetic'] = $location['geodetic'];
        }

        // Preserve civic address data
        if (isset($location['civic'])) {
            $transformed['location']['civic'] = $location['civic'];
        }
    }

    // Add timestamp conversions for logging/reference
    if (isset($data['created_time'])) {
        $transformed['created_time_formatted'] = date('Y-m-d H:i:s', $data['created_time'] / 1000);
    }
    if (isset($data['last_updated_time'])) {
        $transformed['last_updated_time_formatted'] = date('Y-m-d H:i:s', $data['last_updated_time'] / 1000);
    }
    if (isset($data['incident_time'])) {
        $transformed['incident_time_formatted'] = date('Y-m-d H:i:s', $data['incident_time'] / 1000);
    }

    return $transformed;
}

function handleClosedAlert($alertData)
{
    // Optional: Handle closed alerts by updating database status
    // You might want to mark alerts as "closed" in your database

    $alertId = $alertData['id'] ?? null;

    if (!$alertId) {
        return ['success' => false, 'error' => 'No alert ID provided'];
    }

    // You could add database logic here to update alert status
    // For now, just log the closure

    return [
        'success' => true,
        'action' => 'alert_closed',
        'alert_id' => $alertId,
        'closed_at' => $alertData['timestamp'] ?? date('c')
    ];
}
