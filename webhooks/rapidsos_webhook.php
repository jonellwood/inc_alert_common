<?php
// RapidSOS Webhook Endpoint
// This script receives webhook notifications from RapidSOS and processes emergency alerts

require_once __DIR__ . '/../config/rapidsos_config.php';
require_once __DIR__ . '/../lib/rapidsos_auth.php';
require_once __DIR__ . '/../lib/rapidsos_data_mapper.php';
require_once __DIR__ . '/../lib/rapidsos_websocket_mapper.php';

// Set up configuration and authentication
$config = require __DIR__ . '/../config/rapidsos_config.php';
$auth = new RapidSOSAuth($config);
$legacyMapper = new RapidSOSDataMapper();
$websocketMapper = new RapidSOSWebSocketMapper();

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
        // Determine if this is a status update
        $eventType = $payload['event_type'] ?? $payload['event'] ?? 'unknown';
        $isStatusUpdate = ($eventType === 'alert.status_update');

        $result = forwardAlertToWriteToDB($alert, $extractedData['format'], $isStatusUpdate);
        $results[] = $result;
        logWebhookActivity('alert_processed', array_merge($result, ['is_update' => $isStatusUpdate]));
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

function forwardAlertToWriteToDB($alertData, $eventType, $isStatusUpdate = false)
{
    global $config;

    // For status updates, only update the database - don't create new CAD entry
    if ($isStatusUpdate) {
        logWebhookActivity('status_update_received', [
            'alert_id' => $alertData['alert_id'] ?? $alertData['body']['alert_id'] ?? 'unknown',
            'status' => $alertData['body']['status'] ?? 'unknown'
        ]);

        // TODO: Update existing database record with new status
        // For now, just log and skip CAD creation
        return [
            'success' => true,
            'action' => 'status_update_logged',
            'message' => 'Status update received but not yet implemented'
        ];
    }

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
        'FIRE' => '52 ALARMS - FIRE',           // Fire alarm
        'MEDICAL' => '32 UNKNOWN PROBLEM',      // Medical emergency
        'BURGLARY' => '104 ALARMS - LAW',       // Burglary alarm
        'PANIC' => '104 ALARMS - LAW'           // Panic alarm (treat as law enforcement)
    ];

    // Normalize input (uppercase, trim)
    $type = strtoupper(trim($rapidSOSEmergencyType));

    // Return mapped value or default
    return $mapping[$type] ?? '32 UNKNOWN PROBLEM'; // Default to MEDICAL/UNKNOWN
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
