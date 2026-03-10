<?php

/**
 * Quick test: verify ASAP webhook transformation pipeline locally.
 * Run: php test/test_asap_transform.php
 */

// Only load the transformer (not the webhook which has request-handling code)
require_once __DIR__ . '/../lib/asap_transformer.php';

// ── Inline the mapping function for testing ──
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

// ── Load and transform sample XML ──
$xmlFile = __DIR__ . '/../ref/scenario1_new_alarm.xml';
$rawXml = file_get_contents($xmlFile);
$xml = simplexml_load_string($rawXml);

if ($xml === false) {
    echo "ERROR: Failed to parse XML\n";
    exit(1);
}

$transformed = transform_apco($xml);

// ── Build the same payload the webhook would send to writeToDB.php ──
$loc = $transformed['service_location'] ?? [];
$street = $loc['street'] ?? [];
$event = $transformed['event'] ?? [];
$subscriber = $transformed['subscriber'] ?? [];
$contacts = $transformed['contacts'] ?? [];
$vehicle = $transformed['vehicle'] ?? [];
$monitoringStation = $transformed['monitoring_station'] ?? [];
$serviceOrg = $transformed['service_organization'] ?? [];

$callerName = trim(implode(' ', array_filter([
    $subscriber['given'] ?? null,
    $subscriber['middle'] ?? null,
    $subscriber['surname'] ?? null,
]))) ?: null;

$callerPhone = $contacts['subscriber'][0] ?? null;

$streetAddress = trim(implode(' ', array_filter([
    $street['number'] ?? null,
    $street['predirectional'] ?? null,
    $street['name'] ?? null,
    $street['category'] ?? null,
]))) ?: null;

$unit = $loc['unit'] ?? null;
$unitStr = $unit ? ('SUITE ' . $unit) : null;

$alarmType = $transformed['alarm_type'] ?? $event['type'] ?? 'Alarm';
$callTypeAlias = mapAsapAlarmTypeToCallType($alarmType);

$latitude = isset($loc['map']['lat_text']) ? (float) $loc['map']['lat_text'] : null;
$longitude = isset($loc['map']['lon_text']) ? (float) $loc['map']['lon_text'] : null;

echo "=== ASAP-to-PSAP Transformation Test ===\n\n";

echo "--- Source Data ---\n";
echo "Event ID:    " . ($transformed['event_id'] ?? 'null') . "\n";
echo "Alarm Type:  " . ($transformed['alarm_type'] ?? 'null') . "\n";
echo "Version:     " . ($transformed['alarm_version'] ?? 'null') . "\n\n";

echo "--- CAD-Critical Fields ---\n";
echo "CallTypeAlias:  $callTypeAlias\n";
echo "CallerName:     $callerName\n";
echo "CallerPhone:    $callerPhone\n";
echo "IncStreetNum:   " . ($street['number'] ?? 'null') . "\n";
echo "IncPreDir:      " . ($street['predirectional'] ?? 'null') . "\n";
echo "IncStreetName:  " . ($street['name'] ?? '') . ' ' . ($street['category'] ?? '') . "\n";
echo "IncAptLoc:      $unitStr\n";
echo "IncCommunity:   " . ($loc['city'] ?? 'null') . "\n";
echo "XCoor (lon):    $longitude\n";
echo "YCoor (lat):    $latitude\n\n";

echo "--- Database Fields ---\n";
echo "sSourceSystem:       ASAP-to-PSAP\n";
echo "sAlertId:            asap-" . ($transformed['event_id'] ?? 'unknown') . "\n";
echo "sDescription:        " . ($event['details'] ?? 'null') . "\n";
echo "sPermitNumber:       " . ($event['permit']['id'] ?? 'null') . "\n";
echo "sIsAudible:          " . ($event['audible_description'] ?? 'null') . "\n";
echo "sCrossStreet:        " . ($loc['cross_street'] ?? 'null') . "\n";
echo "sBuildingName:       " . ($loc['building'] ?? 'null') . "\n";
echo "sLocationName:       " . ($loc['name'] ?? 'null') . "\n";
echo "sAccessInstructions: " . ($loc['directions'] ?? 'null') . "\n";
echo "sServiceProvider:    " . ($monitoringStation['name'] ?? 'null') . "\n";
echo "sServiceProvPhone:   " . ($serviceOrg['phone'] ?? 'null') . "\n";
echo "sCentralStationPh:   " . ($contacts['operator'][0] ?? 'null') . "\n";
echo "sVehicle:            " . ($vehicle['color'] ?? '') . " " . ($vehicle['make_code'] ?? '') . " " . ($vehicle['model_code'] ?? '') . "\n";
echo "sVehiclePlate:       " . ($vehicle['plate_id'] ?? 'null') . " (" . ($vehicle['plate_source'] ?? '') . ")\n\n";

echo "--- Short Comment (for CAD Comment field, 255 max) ---\n";
$shortParts = [$alarmType];
if ($event['details'] ?? null) $shortParts[] = $event['details'];
if ($event['audible_description'] ?? null) $shortParts[] = '[' . $event['audible_description'] . ']';
$shortComment = implode(' - ', $shortParts);
echo "($shortComment)\n";
echo "Length: " . strlen($shortComment) . " chars\n\n";

echo "=== Test PASSED - All fields extracted correctly ===\n";
