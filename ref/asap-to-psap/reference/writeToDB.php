<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
include_once __DIR__ . '/../secrets/db.php';
require_once __DIR__ . '/../lib/rapidsos_callbacks.php';

$config = new acoConfig();
$serverName = $config->serverName;
$database = $config->database;
$uid = $config->uid;
$pwd = $config->pwd;

try {
    $conn = new PDO("sqlsrv:Server=$serverName;Database=$database;ConnectionPooling=0;TrustServerCertificate=1;Encrypt=0", $uid, $pwd);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    $conn = null;
    exit;
}

// Get the raw POST data from the API
$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);
if ($data === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

// Detect and extract data based on payload structure
function sanitizePhoneNumber($phone)
{
    if ($phone === null || $phone === '') {
        return null;
    }
    // Remove all non-numeric characters
    $cleaned = preg_replace('/[^0-9]/', '', $phone);

    // Remove leading '1' if present and phone is 11 digits
    if (strlen($cleaned) === 11 && substr($cleaned, 0, 1) === '1') {
        $cleaned = substr($cleaned, 1);
    }

    return $cleaned === '' ? null : $cleaned;
}

function extractRapidSOSData($data)
{
    $extracted = [];

    // Handle RapidSOS Alerts API format (has "alerts" array)
    if (isset($data['alerts']) && is_array($data['alerts'])) {
        $alert = $data['alerts'][0] ?? []; // Process first alert

        $extracted['sSourceSystem'] = 'RapidSOS';
        $extracted['sSourceId'] = $alert['alert_id'] ?? $alert['source_id'] ?? null;
        $extracted['sSourceReferenceNumber'] = $alert['alert_id'] ?? null;

        // Location data
        if (isset($alert['location']['geodetic'])) {
            $extracted['iLatitude'] = $alert['location']['geodetic']['latitude'] ?? null;
            $extracted['iLongitude'] = $alert['location']['geodetic']['longitude'] ?? null;
            $extracted['sLocationUncertainty'] = $alert['location']['geodetic']['uncertainty_radius'] ?? null;
        }

        if (isset($alert['location']['civic'])) {
            $civic = $alert['location']['civic'];
            $extracted['sLocationName'] = $civic['name'] ?? null;
            $extracted['sStreetAddress'] = trim(($civic['street_1'] ?? '') . ' ' . ($civic['street_2'] ?? ''));
            $extracted['sApartmentNumber'] = $civic['street_2'] ?? null;
            $extracted['sCity'] = $civic['city'] ?? null;
            $extracted['sState'] = $civic['state'] ?? null;
            $extracted['sCountry'] = $civic['country'] ?? null;
            $extracted['iZipCode'] = $civic['zip_code'] ?? null;

            $fullAddress = implode(', ', array_filter([
                $extracted['sStreetAddress'],
                $civic['city'] ?? null,
                $civic['state'] ?? null,
                $civic['zip_code'] ?? null
            ]));
            $extracted['sFullAddress'] = $fullAddress ?: null;
        }

        // Emergency and service details
        $extracted['sEmergencyType'] = $alert['emergency_type']['display_name'] ?? $alert['emergency_type']['name'] ?? null;
        $extracted['sCallType'] = $alert['call_type_alias'] ?? null; // Mapped CallTypeAlias from webhook
        $extracted['sSiteType'] = $alert['site_type']['display_name'] ?? $alert['site_type']['name'] ?? null;
        $extracted['sServiceProviderName'] = $alert['service_provider_name'] ?? null;
        $extracted['sDescription'] = $alert['description'] ?? null;
        $extracted['sStatus'] = $alert['status']['name'] ?? null;

        // Timing data
        $extracted['sIncidentTimeRaw'] = $alert['incident_time'] ?? null;
        $extracted['sSubmittedTimeRaw'] = $alert['last_updated_time'] ?? null;

        // PSAP/Agency info
        if (isset($alert['covering_psap'])) {
            $extracted['sAgency'] = $alert['covering_psap']['name'] ?? null;
        }

        // Central station info
        if (isset($alert['authorized_entity'])) {
            $extracted['sCentralStationPhone'] = sanitizePhoneNumber($alert['authorized_entity']['phone'] ?? null);
        }
    }
    // Handle RapidSOS Variables format (has "variables" object)
    elseif (isset($data['variables'])) {
        $vars = $data['variables'];

        $extracted['sSourceSystem'] = 'RapidSOS';
        $extracted['sAlarmDescription'] = $vars['alarm_description'] ?? null;

        // User contact info
        if (isset($vars['user'])) {
            $extracted['sContactFullName'] = $vars['user']['full_name'] ?? null;
            $extracted['sContactPhone'] = sanitizePhoneNumber($vars['user']['phone_number'] ?? null);
        }

        // Vehicle info
        if (isset($vars['vehicle'])) {
            $vehicle = $vars['vehicle'];
            $extracted['sVehicleMake'] = $vehicle['make'] ?? null;
            $extracted['sVehicleModel'] = $vehicle['model'] ?? null;
            $extracted['sVehicleColor'] = $vehicle['color'] ?? null;
            $extracted['sVehiclePlateNumber'] = $vehicle['plate_number'] ?? null;
            $extracted['sVehiclePlateState'] = $vehicle['plate_state'] ?? null;
        }

        // Alert profile info
        if (isset($vars['alert_profile'])) {
            $profile = $vars['alert_profile'];
            $extracted['sServiceProviderName'] = $profile['service_provider_name'] ?? null;
            $extracted['sEmergencyType'] = $profile['emergency_type'] ?? null;
            $extracted['sSiteType'] = $profile['site_type'] ?? null;
            $extracted['sDescription'] = $profile['description'] ?? null;
        }

        // Event location data
        if (isset($vars['event']['location']['geodetic'])) {
            $geodetic = $vars['event']['location']['geodetic'];
            $extracted['iLatitude'] = $geodetic['latitude'] ?? null;
            $extracted['iLongitude'] = $geodetic['longitude'] ?? null;
            $extracted['sLocationUncertainty'] = $geodetic['uncertainty'] ?? null;
        }

        // Updated location data (if different)
        if (isset($vars['updated_location'])) {
            $updated = $vars['updated_location'];
            $extracted['iLatitude'] = $updated['latitude'] ?? $extracted['iLatitude'];
            $extracted['iLongitude'] = $updated['longitude'] ?? $extracted['iLongitude'];
            $extracted['sLocationUncertainty'] = $updated['uncertainty'] ?? $extracted['sLocationUncertainty'];
        }

        // Event details
        if (isset($vars['event'])) {
            $event = $vars['event'];
            $extracted['sSourceId'] = $event['source_id'] ?? null;
            $extracted['sIncidentTimeRaw'] = $event['incident_time'] ?? null;
        }

        // Store complex nested data as JSON
        $extracted['sFlowData'] = json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    return $extracted;
}

function reverseGeocode($latitude, $longitude)
{
    if (!$latitude || !$longitude) {
        return null;
    }

    $url = "https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/reverseGeocode";
    $params = [
        'location' => $longitude . ',' . $latitude,
        'f' => 'json'
    ];

    $queryString = http_build_query($params);
    $response = @file_get_contents($url . '?' . $queryString);

    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);

    if (isset($data['address'])) {
        $address = $data['address'];
        return [
            'street' => $address['Address'] ?? null,
            'city' => isset($address['City']) ? ucwords(strtolower($address['City'])) : null,
            'state' => isset($address['Region']) ? strtoupper($address['Region']) : null,
            'zip' => $address['Postal'] ?? null
        ];
    }

    return null;
}

function extractApartmentNumber($addressString)
{
    // Regular expression pattern to extract apartment number
    // Updated to handle periods and more variations
    $pattern = '/\b(APT\.?|APARTMENT|LOT\.?|UNIT\.?|SUITE\.?)\s*([A-Z0-9#\-\.]+)/i';

    // Match the pattern against the address string
    preg_match($pattern, $addressString, $matches);

    $aptType = $matches[1] ?? null;
    // Extract apartment number from the matched group
    $aptNumber = $matches[2] ?? null;

    if ($aptType && $aptNumber) {
        // Clean up the type (remove periods)
        $aptType = str_replace('.', '', strtoupper($aptType));
        return $aptType . " " . $aptNumber;
    }

    return null;
}

/**
 * Send callback to RapidSOS to acknowledge alert acceptance and provide CFS number
 * 
 * @param array $record Database record with alert data
 * @param string $cfsNumber CFS/Call number from CAD
 */
function sendRapidSOSCallback($record, $cfsNumber)
{
    try {
        // Only send callback if we have a RapidSOS alert ID
        $alertId = $record['sSourceReferenceNumber'] ?? $record['sSourceId'] ?? null;

        if (!$alertId || !RapidSOSCallbacks::isValidAlertId($alertId)) {
            error_log("sendRapidSOSCallback: Invalid or missing alert ID: " . var_export($alertId, true));
            return;
        }

        // Only send callback for RapidSOS alerts
        $sourceSystem = $record['sSourceSystem'] ?? '';
        if (strtolower($sourceSystem) !== 'rapidsos') {
            return;
        }

        $callbacks = new RapidSOSCallbacks();

        // Accept the alert and mark as dispatched
        $result = $callbacks->acceptAndDispatch($alertId, $cfsNumber);

        if ($result['success']) {
            error_log("Successfully sent RapidSOS callback for alert {$alertId} with CFS {$cfsNumber}");
        } else {
            error_log("Failed to send RapidSOS callback for alert {$alertId}: " . ($result['error'] ?? 'Unknown error'));
        }
    } catch (Exception $e) {
        error_log("Exception in sendRapidSOSCallback: " . $e->getMessage());
    }
}

function replaceStreetAbbreviations($streetName)
{
    if (!$streetName) {
        return null;
    }

    // USPS standard street suffix abbreviations
    // CAD expects these exact abbreviations
    $abbreviations = [
        'alley' => 'ALY',
        'avenue' => 'AV',
        'boulevard' => 'BLVD',
        'circle' => 'CIR',
        'court' => 'CT',
        'cove' => 'CV',
        'creek' => 'CRK',
        'drive' => 'DR',
        'extension' => 'EXT',
        'expressway' => 'EXPY',
        'highway' => 'HWY',
        'lane' => 'LN',
        'landing' => 'LNDG',
        'loop' => 'LOOP',
        'manor' => 'MNR',
        'parkway' => 'PKWY',
        'pass' => 'PASS',
        'path' => 'PATH',
        'place' => 'PL',
        'plaza' => 'PLZ',
        'point' => 'PT',
        'ridge' => 'RDG',
        'road' => 'RD',
        'row' => 'ROW',
        'run' => 'RUN',
        'square' => 'SQ',
        'street' => 'ST',
        'terrace' => 'TER',
        'trail' => 'TR',
        'turnpike' => 'TPKE',
        'way' => 'WAY',
        // Special cases
        'fifty two' => '52',
        'fifty too' => '52'
    ];

    $streetName = strtolower(trim($streetName));
    $parts = explode(' ', $streetName);

    // Only replace if there are multiple parts (don't replace single-word streets)
    if (count($parts) > 1) {
        $lastPart = array_pop($parts);

        // Check if last part matches an abbreviation
        if (isset($abbreviations[$lastPart])) {
            $lastPart = $abbreviations[$lastPart];
        }

        $parts[] = $lastPart;
    }

    return strtoupper(implode(' ', $parts));
}

function parseStreetAddress($streetAddress)
{
    if (!$streetAddress) {
        return ['number' => null, 'name' => null, 'pre_dir' => null, 'apt' => null];
    }

    // Extract street number
    $number = null;
    if (preg_match('/^(\d+)\s+/', $streetAddress, $matches)) {
        $number = (int)$matches[1];
    }

    // Extract apartment/suite using the robust function
    $apt = extractApartmentNumber($streetAddress);

    // Extract pre-direction
    $preDir = null;
    if (preg_match('/^\d+\s+([NSEW]|NORTH|SOUTH|EAST|WEST)\s+/i', $streetAddress, $matches)) {
        $direction = strtoupper($matches[1]);
        $preDir = substr($direction, 0, 1); // Convert to single letter
        if ($direction === 'NORTH') $preDir = 'N';
        if ($direction === 'SOUTH') $preDir = 'S';
        if ($direction === 'EAST') $preDir = 'E';
        if ($direction === 'WEST') $preDir = 'W';
    }

    // Extract street name (everything after number and pre-direction, before apt)
    $name = $streetAddress;
    $name = preg_replace('/^\d+\s+/', '', $name); // Remove number
    if ($preDir) {
        $name = preg_replace('/^[NSEW]\s+/i', '', $name); // Remove pre-direction
        $name = preg_replace('/^(NORTH|SOUTH|EAST|WEST)\s+/i', '', $name); // Remove spelled out direction
    }
    // Use the same pattern as extractApartmentNumber to remove apartment info
    $name = preg_replace('/\b(APT\.?|APARTMENT|LOT\.?|UNIT\.?|SUITE\.?)\s*([A-Z0-9#\-\.]+)/i', '', $name); // Remove apt
    $name = trim($name);

    // Apply street abbreviations to the street name
    $name = replaceStreetAbbreviations($name);

    return [
        'number' => $number,
        'name' => $name,
        'pre_dir' => $preDir,
        'apt' => $apt
    ];
}

function sendToCad($recordId, $conn)
{
    // Debug: Log function entry immediately
    error_log("sendToCad function called with recordId: " . var_export($recordId, true));

    try {
        // Validate the record ID first
        if (empty($recordId)) {
            error_log("sendToCad: Record ID is empty");
            throw new Exception("Invalid record ID: empty or null");
        }

        // Debug log
        file_put_contents(
            __DIR__ . '/cad_debug.log',
            "[" . date('Y-m-d H:i:s') . "] Starting sendToCad with Record ID: " . var_export($recordId, true) . "\n",
            FILE_APPEND
        );        // Fetch the record from database
        $stmt = $conn->prepare("SELECT * FROM IncomingAlertData WHERE id = :id");
        $stmt->execute([':id' => $recordId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            throw new Exception("Record not found: $recordId");
        }

        // Build CAD payload
        // Debug: Log the sCallType value to diagnose CallTypeAlias issues
        $debugCallType = $record['sCallType'] ?? 'NULL';
        error_log("CAD Payload - Record sCallType: '{$debugCallType}' | Emergency Type: '" . ($record['sEmergencyType'] ?? 'NULL') . "'");

        $cadData = [
            'InterfaceRecordID' => $record['id'],
            'CallTypeAlias' => $record['sCallType'] ?? '104 ALARMS - LAW', // Use mapped value from webhook, fallback to default
            'CallerName' => $record['sContactFullName'],
            'CallerPhone' => $record['sContactPhone'],
            'XCoor' => (float)$record['iLongitude'],
            'YCoor' => (float)$record['iLatitude'],
            'Comment' => $record['sComments'] ?: $record['sDescription'],
            'CFSNote' =>
            "Source System: " . ($record['sSourceSystem'] ?? 'Unknown') . "\n" .
                "Emergency Type: " . ($record['sEmergencyType'] ?? 'Unknown') . "\n" .
                "Service Provider: " . ($record['sServiceProviderName'] ?? 'Unknown') . "\n" .
                "Original Description: " . ($record['sDescription'] ?? 'No description') . "\n" .
                "GPS Coordinates: " . $record['iLatitude'] . ", " . $record['iLongitude'] . "\n" .
                "Map Link: https://www.openstreetmap.org/search?query=" . $record['iLatitude'] . "%2C" . $record['iLongitude'] . "#map=14/" . $record['iLatitude'] . "/" . $record['iLongitude']
        ];

        // Parse street address first (from civic data)
        $streetParts = parseStreetAddress($record['sStreetAddress']);

        // Use civic address data if available, otherwise try reverse geocoding
        $hasStreetAddress = !empty($record['sStreetAddress']) && !empty($record['sCity']);

        if ($hasStreetAddress) {
            // Use civic address data (preferred)
            $cadData['IncStreetNum'] = $streetParts['number'];
            $cadData['IncStreetName'] = $streetParts['name'];
            $cadData['IncPreDir'] = $streetParts['pre_dir'];

            // Try apartment from parsed street address first, then from dedicated field
            $aptLoc = $streetParts['apt'] ?: ($record['sApartmentNumber'] ?? null);
            $cadData['IncAptLoc'] = $aptLoc;

            $cadData['IncCommunity'] = $record['sCity'];
        } else {
            // Fallback to reverse geocoding only if no civic address
            $addressInfo = null;
            if ($record['iLatitude'] && $record['iLongitude']) {
                $addressInfo = reverseGeocode($record['iLatitude'], $record['iLongitude']);
            }

            if ($addressInfo && $addressInfo['street']) {
                // Use geocoded address
                $geocodedParts = parseStreetAddress($addressInfo['street']);
                $cadData['IncStreetNum'] = $geocodedParts['number'];
                $cadData['IncStreetName'] = $geocodedParts['name'];
                $cadData['IncPreDir'] = $geocodedParts['pre_dir'];
                $cadData['IncAptLoc'] = $geocodedParts['apt'];
                $cadData['IncCommunity'] = $addressInfo['city'];
            } else {
                // Last resort - use whatever we have
                $cadData['IncStreetNum'] = $streetParts['number'];
                $cadData['IncStreetName'] = $streetParts['name'];
                $cadData['IncPreDir'] = $streetParts['pre_dir'];
                $cadData['IncAptLoc'] = $streetParts['apt'];
                $cadData['IncCommunity'] = $record['sCity'];
            }
        }

        // Debug: write CAD Data out to a file for debugging - do this early to ensure it happens
        $debugFile = __DIR__ . '/cad_debug_' . str_replace('-', '_', $recordId) . '.json';
        $debugResult = file_put_contents($debugFile, json_encode($cadData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Set permissions so you can read the file
        if ($debugResult !== false) {
            @chmod($debugFile, 0666);
        }

        // Also write to a general debug log
        $logResult = file_put_contents(
            __DIR__ . '/cad_debug.log',
            "[" . date('Y-m-d H:i:s') . "] Record ID: $recordId\n" .
                "CallTypeAlias: " . ($cadData['CallTypeAlias'] ?? 'NULL') . "\n" .
                "CAD Data: " . json_encode($cadData, JSON_UNESCAPED_UNICODE) . "\n" .
                "Debug file result: " . ($debugResult ? "SUCCESS" : "FAILED") . "\n\n",
            FILE_APPEND
        );

        // Set permissions on log file too
        if ($logResult !== false) {
            @chmod(__DIR__ . '/cad_debug.log', 0666);
        }

        // Send to CAD API
        $cadUrl = "http://10.19.1.52:32001/CAD/CIM/AlarmCallTest/CFS/";
        $cadHeaders = [
            'Content-Type: application/json',
            'ApiKey: Y9YcKfeq+r+dRmluJyk0u+5ZeQOG53gDPYWowHLzYUE='
        ];

        // Debug: Log the exact JSON payload being sent to CAD
        $cadJsonPayload = json_encode($cadData);
        error_log("EXACT CAD JSON PAYLOAD: " . $cadJsonPayload);
        error_log("CallTypeAlias in payload: " . ($cadData['CallTypeAlias'] ?? 'MISSING'));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $cadUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $cadJsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $cadHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $cadResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Debug: Log the raw CAD response
        $cadDebugLog = __DIR__ . '/cad_response_debug.log';
        file_put_contents(
            $cadDebugLog,
            "[" . date('Y-m-d H:i:s') . "] HTTP Code: $httpCode\n" .
                "Raw Response: " . $cadResponse . "\n" .
                "Curl Error: " . ($curlError ?: 'None') . "\n\n",
            FILE_APPEND
        );
        @chmod($cadDebugLog, 0666);

        if ($curlError) {
            throw new Exception("CAD API connection failed: $curlError");
        }

        $responseData = json_decode($cadResponse, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            // Success - extract CFSNumber if available
            $cfsNumber = null;
            if (isset($responseData['RequestID'])) {
                $cfsNumber = $responseData['RequestID'];
            } elseif (isset($responseData['RecordID'])) {
                $cfsNumber = $responseData['RecordID'];
            } elseif (isset($responseData['CFSNumber'])) {
                $cfsNumber = $responseData['CFSNumber'];
            } elseif (isset($responseData['cfs_number'])) {
                $cfsNumber = $responseData['cfs_number'];
            }

            // Update database record
            // CRITICAL: Send callback to RapidSOS IMMEDIATELY (before database update)
            // Callbacks must happen within ~45 seconds or alert times out
            sendRapidSOSCallback($record, $cfsNumber);

            // Update database record with CFS number
            $updateStmt = $conn->prepare("
                UPDATE IncomingAlertData 
                SET sCfsNumber = :cfs_number, 
                    sCadStatus = 'POSTED', 
                    dtCadPostedDateTime = GETUTCDATE(),
                    iRetryCount = 0
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':cfs_number' => $cfsNumber,
                ':id' => $recordId
            ]);

            return [
                'status' => 'success',
                'cfs_number' => $cfsNumber,
                'message' => 'Successfully posted to CAD',
                'raw_response' => $cadResponse,
                'parsed_response' => $responseData,
                'http_code' => $httpCode
            ];
        } else {
            // Failure - update retry count and error message
            $errorMessage = "HTTP $httpCode: " . ($responseData['message'] ?? $cadResponse);

            $updateStmt = $conn->prepare("
                UPDATE IncomingAlertData 
                SET sCadStatus = 'FAILED', 
                    sCadErrorMessage = :error_message,
                    iRetryCount = iRetryCount + 1
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':error_message' => $errorMessage,
                ':id' => $recordId
            ]);

            return [
                'status' => 'failed',
                'error' => $errorMessage,
                'http_code' => $httpCode,
                'raw_response' => $cadResponse,
                'parsed_response' => $responseData
            ];
        }
    } catch (Exception $e) {
        // Update database with error
        try {
            $updateStmt = $conn->prepare("
                UPDATE IncomingAlertData 
                SET sCadStatus = 'FAILED', 
                    sCadErrorMessage = :error_message,
                    iRetryCount = iRetryCount + 1
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':error_message' => $e->getMessage(),
                ':id' => $recordId
            ]);
        } catch (Exception $dbError) {
            // Log database update error but don't fail the original request
            error_log("Failed to update database after CAD error: " . $dbError->getMessage());
        }

        return [
            'status' => 'error',
            'error' => $e->getMessage()
        ];
    }
}

// Extract data based on payload structure
$extractedData = [];
if (isset($data['alerts']) || isset($data['variables'])) {
    $extractedData = extractRapidSOSData($data);
}

// Merge extracted data with original, giving priority to extracted data
$normalizedData = array_merge($data, $extractedData);

// Legacy normalization for other sources
if (!isset($normalizedData['sSourceSystem']) && isset($normalizedData['sSource'])) {
    $normalizedData['sSourceSystem'] = $normalizedData['sSource'];
}
if (!isset($normalizedData['sStreetAddress']) && isset($normalizedData['sLocation'])) {
    $normalizedData['sStreetAddress'] = $normalizedData['sLocation'];
}
if (!isset($normalizedData['sFullAddress']) && isset($normalizedData['sStreetAddress'])) {
    $normalizedData['sFullAddress'] = $normalizedData['sStreetAddress'];
}
if (!isset($normalizedData['iZipCode']) && isset($normalizedData['sZip'])) {
    $normalizedData['iZipCode'] = $normalizedData['sZip'];
}
if (!isset($normalizedData['sContactFullName']) && isset($normalizedData['sContactName'])) {
    $normalizedData['sContactFullName'] = $normalizedData['sContactName'];
}
if (!isset($normalizedData['sComments']) && isset($normalizedData['sNotes'])) {
    $normalizedData['sComments'] = $normalizedData['sNotes'];
}

// Sanitize all phone number fields
if (isset($normalizedData['sContactPhone'])) {
    $normalizedData['sContactPhone'] = sanitizePhoneNumber($normalizedData['sContactPhone']);
}
if (isset($normalizedData['sServiceProviderPhone'])) {
    $normalizedData['sServiceProviderPhone'] = sanitizePhoneNumber($normalizedData['sServiceProviderPhone']);
}
if (isset($normalizedData['sCentralStationPhone'])) {
    $normalizedData['sCentralStationPhone'] = sanitizePhoneNumber($normalizedData['sCentralStationPhone']);
}
if (isset($normalizedData['sPremisePhone'])) {
    $normalizedData['sPremisePhone'] = sanitizePhoneNumber($normalizedData['sPremisePhone']);
}
if (isset($normalizedData['sSitePhone'])) {
    $normalizedData['sSitePhone'] = sanitizePhoneNumber($normalizedData['sSitePhone']);
}

if (
    (!isset($normalizedData['sContactFirstName']) || $normalizedData['sContactFirstName'] === '') &&
    (!isset($normalizedData['sContactLastName']) || $normalizedData['sContactLastName'] === '') &&
    !empty($normalizedData['sContactFullName'])
) {
    $nameParts = preg_split('/\s+/', trim((string) $normalizedData['sContactFullName']));
    if (!empty($nameParts)) {
        $normalizedData['sContactFirstName'] = array_shift($nameParts);
        $normalizedData['sContactLastName'] = empty($nameParts) ? null : implode(' ', $nameParts);
    }
}

$originalPayload = $rawData;
if ($originalPayload === false || $originalPayload === null || $originalPayload === '') {
    $fallbackPayload = json_encode($normalizedData, JSON_UNESCAPED_UNICODE);
    $originalPayload = $fallbackPayload !== false ? $fallbackPayload : '{}';
}

$columnMap = [
    'sSourceSystem' => ['key' => 'sSourceSystem', 'default' => 'Unknown'],
    'sSourceId' => 'sSourceId',
    'sSourceReferenceNumber' => 'sSourceReferenceNumber',
    'dtUpdatedDateTime' => 'dtUpdatedDateTime',
    'sContactFirstName' => 'sContactFirstName',
    'sContactLastName' => 'sContactLastName',
    'sContactFullName' => 'sContactFullName',
    'sContactPhone' => 'sContactPhone',
    'sContactEmail' => 'sContactEmail',
    'sContactRelationship' => 'sContactRelationship',
    'sContactLanguage' => 'sContactLanguage',
    'sStreetAddress' => 'sStreetAddress',
    'sApartmentNumber' => 'sApartmentNumber',
    'sCity' => 'sCity',
    'sState' => 'sState',
    'sCountry' => 'sCountry',
    'iZipCode' => 'iZipCode',
    'sFullAddress' => 'sFullAddress',
    'sCrossStreet' => 'sCrossStreet',
    'iLatitude' => ['key' => 'iLatitude', 'type' => 'float'],
    'iLongitude' => ['key' => 'iLongitude', 'type' => 'float'],
    'sLocationUncertainty' => 'sLocationUncertainty',
    'sLocationName' => 'sLocationName',
    'sEmergencyType' => 'sEmergencyType',
    'sCallType' => 'sCallType',
    'sSiteType' => 'sSiteType',
    'sAgency' => 'sAgency',
    'sStatus' => 'sStatus',
    'sAlarmDescription' => 'sAlarmDescription',
    'sZoneDescription' => 'sZoneDescription',
    'sDescription' => 'sDescription',
    'sComments' => 'sComments',
    'sRemarks' => 'sRemarks',
    'sInstructions' => 'sInstructions',
    'sServiceProviderName' => 'sServiceProviderName',
    'sServiceProviderPhone' => 'sServiceProviderPhone',
    'sCentralStationPhone' => 'sCentralStationPhone',
    'sPremisePhone' => 'sPremisePhone',
    'sSitePhone' => 'sSitePhone',
    'sIncidentTimeRaw' => 'sIncidentTimeRaw',
    'sSubmittedTimeRaw' => 'sSubmittedTimeRaw',
    'sClearedTimeRaw' => 'sClearedTimeRaw',
    'sPermitNumber' => 'sPermitNumber',
    'sAlarmPermitNumber' => 'sAlarmPermitNumber',
    'sLockboxCode' => 'sLockboxCode',
    'sGateCode' => 'sGateCode',
    'sHiddenKey' => 'sHiddenKey',
    'sAccessInstructions' => 'sAccessInstructions',
    'sIsAudible' => 'sIsAudible',
    'sVisuallyVerified' => 'sVisuallyVerified',
    'sVialOfLife' => 'sVialOfLife',
    'sAccountOwner' => 'sAccountOwner',
    'sBuildingId' => 'sBuildingId',
    'sBuildingName' => 'sBuildingName',
    'sSpeakWithFirstResponder' => 'sSpeakWithFirstResponder',
    'bContactPermission' => ['key' => 'bContactPermission', 'type' => 'bool'],
    'bTextMessage' => ['key' => 'bTextMessage', 'type' => 'bool'],
    'sVehicleMake' => 'sVehicleMake',
    'sVehicleModel' => 'sVehicleModel',
    'sVehicleColor' => 'sVehicleColor',
    'sVehiclePlateNumber' => 'sVehiclePlateNumber',
    'sVehiclePlateState' => 'sVehiclePlateState',
    'sClientIp' => 'sClientIp',
    'sSourceEventCode' => 'sSourceEventCode',
    'sTransmitterId' => 'sTransmitterId',
    'sTransmitterType' => 'sTransmitterType',
    'sFlowData' => ['key' => 'sFlowData', 'type' => 'json'],
    'bHasAttachment' => ['key' => 'bHasAttachment', 'type' => 'bool', 'default' => 0],
    'bIsDeleted' => ['key' => 'bIsDeleted', 'type' => 'bool', 'default' => 0],
    'dtDeletedDateTime' => 'dtDeletedDateTime',
    'sEmergencyContactsJson' => ['key' => 'sEmergencyContactsJson', 'type' => 'json'],
    'sOriginalPayloadJson' => ['value' => $originalPayload],
    'sCfsNumber' => 'sCfsNumber',
    'sCadStatus' => ['key' => 'sCadStatus', 'default' => 'PENDING'],
    'dtCadPostedDateTime' => 'dtCadPostedDateTime',
    'sCadErrorMessage' => 'sCadErrorMessage',
    'iRetryCount' => ['key' => 'iRetryCount', 'type' => 'int', 'default' => 0],
];

$columns = [];
$placeholders = [];
$params = [];

foreach ($columnMap as $column => $definition) {
    if (!is_array($definition)) {
        $definition = ['key' => $definition];
    }

    $placeholder = ':' . $column;
    $columns[] = "[$column]";
    $placeholders[] = $placeholder;

    if (array_key_exists('value', $definition)) {
        $value = $definition['value'];
    } else {
        $sourceKey = $definition['key'] ?? $column;
        $value = array_key_exists($sourceKey, $normalizedData)
            ? $normalizedData[$sourceKey]
            : ($definition['default'] ?? null);
    }

    if ($value === '' && array_key_exists('default', $definition)) {
        $value = $definition['default'];
    }

    if ($value === '') {
        $value = null;
    }

    switch ($definition['type'] ?? null) {
        case 'bool':
            if ($value !== null) {
                $filtered = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                $value = $filtered === null ? null : ($filtered ? 1 : 0);
            }
            break;
        case 'int':
            $value = $value === null ? null : (int) $value;
            break;
        case 'float':
            $value = $value === null ? null : (float) $value;
            break;
        case 'json':
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            break;
    }

    $params[$placeholder] = $value;
}

$sql = sprintf(
    'INSERT INTO IncomingAlertData (%s) VALUES (%s)',
    implode(', ', $columns),
    implode(', ', $placeholders)
);

$stmt = $conn->prepare($sql);

try {
    $stmt->execute($params);

    // Get the ID of the inserted record
    $lastInsertId = $conn->lastInsertId();

    // Debug: write the record ID to see what we got
    $debugIdFile = __DIR__ . '/debug_record_id.txt';
    file_put_contents($debugIdFile, "Record ID: " . var_export($lastInsertId, true) . "\n", FILE_APPEND);
    @chmod($debugIdFile, 0666);

    // For SQL Server with GUID primary keys, lastInsertId() might not work properly
    // Let's try to get the ID differently if it's empty
    if (empty($lastInsertId)) {
        // Try to get the most recent record for this session/connection
        $idStmt = $conn->query("SELECT TOP 1 id FROM IncomingAlertData ORDER BY dtCreatedDateTime DESC");
        $lastRecord = $idStmt->fetch(PDO::FETCH_ASSOC);
        $lastInsertId = $lastRecord ? $lastRecord['id'] : null;

        file_put_contents($debugIdFile, "Fallback ID: " . var_export($lastInsertId, true) . "\n", FILE_APPEND);
        @chmod($debugIdFile, 0666);
    }
    if ($lastInsertId) {
        // Debug: Log that we're about to call sendToCad
        error_log("About to call sendToCad with ID: " . $lastInsertId);

        // Send to CAD system
        $cadResponse = sendToCad($lastInsertId, $conn);

        // Debug: Log the response
        error_log("sendToCad returned: " . json_encode($cadResponse));
    } else {
        $cadResponse = [
            'status' => 'error',
            'error' => 'Could not retrieve inserted record ID'
        ];
    }

    http_response_code(200);
    echo json_encode([
        'message' => 'Data inserted successfully',
        'record_id' => $lastInsertId,
        'cad_response' => $cadResponse,
        'cad_payload' => $cadResponse['payload'] ?? null, // Include the payload that was sent
        'cad_headers' => $cadResponse['headers'] ?? null   // Include headers for debugging
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database insert failed: ' . $e->getMessage()]);
}
$conn = null;
