<?php
// Test script for RapidSOS WebSocket Events API v1.1.1 format
require_once __DIR__ . '/../lib/rapidsos_websocket_mapper.php';
require_once __DIR__ . '/../lib/payload_analyzer.php';

// Test payload in official WebSocket Events API format
$testPayload = [
    "event" => "alert.new",
    "timestamp" => 1703768400000, // milliseconds
    "body" => [
        "alert_id" => "12345678-1234-5678-9012-123456789012",
        "source_id" => "rapidsos-mobile-app",
        "incident_time" => 1703768400000,
        "created_time" => 1703768410000,
        "last_updated_time" => 1703768420000,
        "location" => [
            "provided_location" => "BOTH",
            "geodetic" => [
                "latitude" => 32.7767,
                "longitude" => -79.9311,
                "uncertainty_radius" => 25
            ],
            "civic" => [
                "name" => "Charleston County Public Safety Complex",
                "street_1" => "4045 Bridge View Dr",
                "street_2" => "",
                "city" => "Charleston",
                "state" => "SC",
                "country" => "US",
                "zip_code" => "29405"
            ]
        ],
        "emergency_type" => [
            "name" => "MEDICAL",
            "display_name" => "Medical Emergency"
        ],
        "description" => "Automated mobile medical emergency alert",
        "site_type" => [
            "name" => "INDOOR",
            "display_name" => "Indoor Location"
        ],
        "service_provider_name" => "RapidSOS Mobile",
        "status" => [
            "name" => "NEW",
            "display_name" => "New Alert",
            "sla_expiration_time" => 1703768700000
        ],
        "covering_psap" => [
            "id" => "charleston-county-911",
            "name" => "Charleston County 911",
            "phone" => "+1-843-202-6050"
        ],
        "authorized_entity" => [
            "id" => "charleston-county-ems",
            "name" => "Charleston County EMS",
            "phone" => "+1-843-202-6100"
        ],
        "dispatch_requested_time" => 1703768430000,
        "sla_expiration_time" => 1703768700000,
        "covering_psap_first_viewed_time" => 1703768440000,
        "is_chat_enabled" => true,
        "is_media_enabled" => true,
        "supplemental_only" => false,
        "followers" => [
            "charleston-county-police",
            "charleston-county-fire"
        ],
        "location_history" => [
            [
                "provided_location" => "GEODETIC",
                "geodetic" => [
                    "latitude" => 32.7760,
                    "longitude" => -79.9305,
                    "uncertainty_radius" => 50
                ]
            ]
        ]
    ]
];

// Test additional event types
$statusUpdatePayload = [
    "event" => "alert.status_update",
    "timestamp" => 1703768500000,
    "body" => [
        "alert_id" => "12345678-1234-5678-9012-123456789012",
        "status" => [
            "name" => "DISPATCH_REQUESTED",
            "display_name" => "Dispatch Requested"
        ],
        "sender" => "John Doe",
        "sender_id" => "operator-123",
        "entity_display_name" => "Charleston County 911",
        "entity_id" => "charleston-county-911",
        "message_time" => 1703768500000,
        "created_at" => 1703768500000
    ]
];

$locationUpdatePayload = [
    "event" => "alert.location_update",
    "timestamp" => 1703768600000,
    "body" => [
        "alert_id" => "12345678-1234-5678-9012-123456789012",
        "provided_location" => "GEODETIC",
        "latitude" => 32.7770,
        "longitude" => -79.9315,
        "uncertainty_radius" => 20,
        "created_at" => 1703768600000
    ]
];

$chatPayload = [
    "event" => "alert.chat",
    "timestamp" => 1703768700000,
    "body" => [
        "alert_id" => "12345678-1234-5678-9012-123456789012",
        "message" => "Patient is conscious and breathing",
        "sender" => "Field Responder",
        "sender_id" => "responder-456",
        "entity_display_name" => "Charleston County EMS",
        "entity_id" => "charleston-county-ems",
        "message_time" => 1703768700000,
        "created_at" => 1703768700000
    ]
];

$milestonePayload = [
    "event" => "alert.milestone",
    "timestamp" => 1703768800000,
    "body" => [
        "alert_id" => "12345678-1234-5678-9012-123456789012",
        "message" => "Unit dispatched",
        "message_type" => "DISPATCH",
        "sender" => "Dispatch System",
        "sender_id" => "dispatch-auto",
        "entity_display_name" => "Charleston County 911",
        "entity_id" => "charleston-county-911",
        "message_time" => 1703768800000,
        "created_at" => 1703768800000
    ]
];

echo "=== Testing RapidSOS WebSocket Events API v1.1.1 Mapper ===\n\n";

try {
    $mapper = new RapidSOSWebSocketMapper();

    // Test alert.new event
    echo "1. Testing alert.new event:\n";
    echo "----------------------------\n";
    $result1 = $mapper->extractWebSocketEvent($testPayload);
    echo "Format: " . $result1['format'] . "\n";
    echo "Event Type: " . $result1['event_type'] . "\n";
    echo "Alert ID: " . $result1['alerts'][0]['alert_id'] . "\n";
    echo "Emergency Type: " . ($result1['alerts'][0]['emergency_type']['name'] ?? 'N/A') . "\n";
    echo "Location: " . $result1['alerts'][0]['location']['geodetic']['latitude'] . ", " .
        $result1['alerts'][0]['location']['geodetic']['longitude'] . "\n";
    echo "Address: " . ($result1['alerts'][0]['location']['civic']['name'] ?? 'N/A') . "\n";
    echo "Status: " . ($result1['alerts'][0]['status']['name'] ?? 'N/A') . "\n";
    echo "PSAP: " . ($result1['alerts'][0]['covering_psap']['name'] ?? 'N/A') . "\n\n";

    // Test alert.status_update event
    echo "2. Testing alert.status_update event:\n";
    echo "--------------------------------------\n";
    $result2 = $mapper->extractWebSocketEvent($statusUpdatePayload);
    echo "Format: " . $result2['format'] . "\n";
    echo "Event Type: " . $result2['event_type'] . "\n";
    echo "Alert ID: " . $result2['alert_id'] . "\n";
    echo "Status: " . $result2['status']['name'] . "\n";
    echo "Operator: " . $result2['operator_info']['sender'] . "\n\n";

    // Test alert.location_update event
    echo "3. Testing alert.location_update event:\n";
    echo "----------------------------------------\n";
    $result3 = $mapper->extractWebSocketEvent($locationUpdatePayload);
    echo "Format: " . $result3['format'] . "\n";
    echo "Event Type: " . $result3['event_type'] . "\n";
    echo "Alert ID: " . $result3['alert_id'] . "\n";
    echo "New Location: " . $result3['location']['geodetic']['latitude'] . ", " .
        $result3['location']['geodetic']['longitude'] . "\n";
    echo "Accuracy: " . $result3['location']['geodetic']['uncertainty_radius'] . "m\n\n";

    // Test alert.chat event
    echo "4. Testing alert.chat event:\n";
    echo "-----------------------------\n";
    $result4 = $mapper->extractWebSocketEvent($chatPayload);
    echo "Format: " . $result4['format'] . "\n";
    echo "Event Type: " . $result4['event_type'] . "\n";
    echo "Alert ID: " . $result4['alert_id'] . "\n";
    echo "Message: " . $result4['message'] . "\n";
    echo "Sender: " . $result4['operator_info']['sender'] . "\n\n";

    // Test alert.milestone event
    echo "5. Testing alert.milestone event:\n";
    echo "----------------------------------\n";
    $result5 = $mapper->extractWebSocketEvent($milestonePayload);
    echo "Format: " . $result5['format'] . "\n";
    echo "Event Type: " . $result5['event_type'] . "\n";
    echo "Alert ID: " . $result5['alert_id'] . "\n";
    echo "Milestone: " . $result5['message'] . "\n";
    echo "Type: " . $result5['message_type'] . "\n\n";

    // Test emergency type constants
    echo "6. Testing Emergency Type Constants:\n";
    echo "------------------------------------\n";
    echo "MEDICAL: " . RapidSOSEmergencyTypes::MEDICAL . "\n";
    echo "FIRE: " . RapidSOSEmergencyTypes::FIRE . "\n";
    echo "CRASH: " . RapidSOSEmergencyTypes::CRASH . "\n";
    echo "BURGLARY: " . RapidSOSEmergencyTypes::BURGLARY . "\n\n";

    // Test status constants
    echo "7. Testing Status Constants:\n";
    echo "----------------------------\n";
    echo "NEW: " . RapidSOSStatusTypes::NEW . "\n";
    echo "DISPATCH_REQUESTED: " . RapidSOSStatusTypes::DISPATCH_REQUESTED . "\n";
    echo "ACCEPTED: " . RapidSOSStatusTypes::ACCEPTED . "\n";
    echo "DECLINED: " . RapidSOSStatusTypes::DECLINED . "\n\n";

    echo "=== All tests completed successfully! ===\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

// Test payload analysis
echo "\n=== Payload Analysis ===\n";
try {
    $analyzer = new PayloadAnalyzer();

    echo "Analyzing alert.new payload structure:\n";
    $analysis = $analyzer->analyzeStructure($testPayload);
    echo "Field count: " . $analysis['field_count'] . "\n";
    echo "Depth: " . $analysis['depth'] . "\n";
    echo "Has location: " . ($analysis['has_location'] ? 'Yes' : 'No') . "\n";
    echo "Has emergency type: " . ($analysis['has_emergency_type'] ? 'Yes' : 'No') . "\n";

    // Test batch analysis with multiple event types
    $payloads = [
        'alert.new' => $testPayload,
        'alert.status_update' => $statusUpdatePayload,
        'alert.location_update' => $locationUpdatePayload,
        'alert.chat' => $chatPayload,
        'alert.milestone' => $milestonePayload
    ];

    echo "\nBatch analysis of all event types:\n";
    $batchResults = $analyzer->batchAnalyze($payloads);
    foreach ($batchResults as $type => $result) {
        echo "$type: " . $result['field_count'] . " fields, depth " . $result['depth'] . "\n";
    }
} catch (Exception $e) {
    echo "Analysis ERROR: " . $e->getMessage() . "\n";
}
