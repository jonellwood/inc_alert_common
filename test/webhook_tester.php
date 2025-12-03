<?php
// RapidSOS Webhook Testing Utility
// Use this to test webhook signature verification and payload processing

require_once __DIR__ . '/../config/rapidsos_config.php';
require_once __DIR__ . '/../lib/rapidsos_auth.php';

$config = require __DIR__ . '/../config/rapidsos_config.php';
$auth = new RapidSOSAuth($config);

?>
<!DOCTYPE html>
<html>

<head>
    <title>RapidSOS Webhook Tester</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">RapidSOS Webhook Tester</h1>
            <p class="text-gray-600 mt-2">Test webhook signature verification and payload processing</p>
        </div>

        <!-- Webhook Info -->
        <div class="bg-blue-50 p-6 rounded-lg shadow mb-8">
            <h2 class="text-xl font-semibold text-blue-900 mb-4">Webhook Configuration</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <strong>Webhook URL:</strong><br>
                    <code class="text-xs bg-white px-2 py-1 rounded break-all"><?= $config['webhook_endpoint'] ?></code>
                </div>
                <div>
                    <strong>Target URL:</strong><br>
                    <code class="text-xs bg-white px-2 py-1 rounded break-all"><?= $config['target_endpoint'] ?></code>
                </div>
                <div>
                    <strong>Environment:</strong> <?= $config['environment'] ?>
                </div>
                <div>
                    <strong>Default Events:</strong> <?= implode(', ', $config['default_events']) ?>
                </div>
            </div>
        </div>

        <!-- Test Signature Verification -->
        <div class="bg-white p-6 rounded-lg shadow mb-8">
            <h2 class="text-xl font-semibold mb-4">Test Signature Verification</h2>
            <form id="signatureTest" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Test Payload (JSON)</label>
                    <textarea name="payload" rows="8" class="w-full border rounded px-3 py-2 font-mono text-sm" placeholder='{"event": "alert.created", "data": {...}}'></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Expected Signature (X-RapidSOS-Signature)</label>
                    <input type="text" name="signature" class="w-full border rounded px-3 py-2" placeholder="sha256=...">
                    <p class="text-xs text-gray-500 mt-1">Leave empty to generate a test signature</p>
                </div>
                <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">
                    <i class="fas fa-check mr-2"></i>Test Signature
                </button>
            </form>
            <div id="signatureResult" class="mt-4"></div>
        </div>

        <!-- Send Test Webhook -->
        <div class="bg-white p-6 rounded-lg shadow mb-8">
            <h2 class="text-xl font-semibold mb-4">Send Test Webhook</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Select Test Alert Type</label>
                    <select id="testAlertType" class="w-full border rounded px-3 py-2">
                        <option value="alerts_api_format">Alerts API Format</option>
                        <option value="webhook_callflow">Webhook Callflow Format</option>
                        <option value="direct_webhook_event">Direct Webhook Event</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Custom Payload (optional)</label>
                    <textarea id="customPayload" rows="10" class="w-full border rounded px-3 py-2 font-mono text-sm" placeholder="Leave empty to use default test data"></textarea>
                </div>
                <button onclick="sendTestWebhook()" class="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600">
                    <i class="fas fa-paper-plane mr-2"></i>Send Test Webhook
                </button>
            </div>
            <div id="webhookResult" class="mt-4"></div>
        </div>

        <!-- Recent Logs -->
        <div class="bg-gray-50 p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold mb-4">Log Files</h2>
            <div class="space-y-2 text-sm">
                <div>
                    <strong>Webhook Debug Log:</strong>
                    <code>/logs/webhook_debug.log</code>
                    <button onclick="viewLog('webhook')" class="ml-2 text-blue-500 hover:text-blue-700">View</button>
                </div>
                <div>
                    <strong>Auth Log:</strong>
                    <code>/logs/rapidsos_auth.log</code>
                    <button onclick="viewLog('auth')" class="ml-2 text-blue-500 hover:text-blue-700">View</button>
                </div>
                <div>
                    <strong>Subscription Log:</strong>
                    <code>/logs/rapidsos_subscriptions.log</code>
                    <button onclick="viewLog('subscriptions')" class="ml-2 text-blue-500 hover:text-blue-700">View</button>
                </div>
            </div>
            <div id="logContent" class="mt-4"></div>
        </div>
    </div>

    <script>
        // Default test payloads based on multiple RapidSOS formats for testing
        const testPayloads = {
            'alerts_api_format': {
                "alerts_until": 1581110270622,
                "alerts": [{
                    "source_id": "test-" + Date.now(),
                    "incident_time": Date.now(),
                    "location": {
                        "geodetic": {
                            "latitude": 32.9221,
                            "longitude": -79.9437,
                            "uncertainty_radius": 7.5
                        },
                        "civic": {
                            "name": "Berkeley County Emergency Services",
                            "street_1": "1255 YEAMANS HALL RD",
                            "street_2": "",
                            "city": "Hanahan",
                            "state": "SC",
                            "country": "USA",
                            "zip_code": "29410"
                        }
                    },
                    "service_provider_name": "Berkeley County 911",
                    "description": "Fire alarm activation - Front entrance pull station",
                    "status": {
                        "name": "ACCEPTED",
                        "display_name": "Received"
                    },
                    "disposition": {
                        "name": "DISPATCHED",
                        "display_name": "Dispatched"
                    },
                    "last_updated_time": Date.now(),
                    "alert_id": "alert-" + Date.now(),
                    "emergency_type": {
                        "display_name": "Fire"
                    },
                    "site_type": {
                        "name": "COMMERCIAL",
                        "display_name": "Commercial"
                    },
                    "covering_psap": {
                        "id": "SC_BERKELEY",
                        "name": "Berkeley County 911",
                        "phone": "+18439003911"
                    }
                }]
            },
            'webhook_callflow': {
                "callflow": "TestBCG_alerts",
                "variables": {
                    "permit_number": "93547",
                    "service_provider": "Berkeley County 911",
                    "central_station_phone": "+18008773624",
                    "alarm_description": "MEDICAL EMERGENCY",
                    "zone_description": "Main Building - Office Area",
                    "premise_phone": "+18439003911",
                    "buildings": {
                        "id": "123456",
                        "name": "BERKELEY COUNTY EMERGENCY SERVICES",
                        "siteId": "1",
                        "address": {
                            "zip": "29456-0000",
                            "city": "Moncks Corner",
                            "state": "SC",
                            "country": "USA",
                            "address1": "223 N LIVE OAK DR",
                            "address2": "SUITE 4"
                        },
                        "permitNumber": "93547",
                        "contactNumber": "+18439003911"
                    },
                    "flow_data": {
                        "id": "123456:10041:" + Date.now(),
                        "format": "CLSS.contactID",
                        "properties": {
                            "dnis": "0192",
                            "account": "3456",
                            "groupId": "01",
                            "message": "MEDICAL EMERGENCY",
                            "pointId": "888",
                            "eventCode": "1100"
                        },
                        "createdTime": new Date().toISOString(),
                        "transmitterId": "TX" + Date.now(),
                        "transmitterType": "Medical Alert",
                        "detectedInBuilding": "123456",
                        "transmissionStatus": {
                            "status": "ReceivedInCMS",
                            "timestamp": new Date().toISOString()
                        },
                        "detectedByEquipment": "888"
                    },
                    "event": {
                        "location": {
                            "civic": {
                                "name": "BERKELEY COUNTY EMERGENCY SERVICES",
                                "street_1": "223 N LIVE OAK DR",
                                "street_2": "SUITE 4",
                                "city": "Moncks Corner",
                                "state": "SC",
                                "country": "US",
                                "zip_code": "29456"
                            },
                            "coordinates": {
                                "latitude": 33.1960,
                                "longitude": -79.8314,
                                "accuracy": 5
                            }
                        },
                        "source_id": "TestSignal" + Date.now(),
                        "service_provider_name": "Berkeley County 911",
                        "incident_time": new Date().toISOString(),
                        "emergency_type": "MEDICAL",
                        "site_type": "GOVERNMENT",
                        "description": "Medical emergency at government facility",
                        "severity": "HIGH"
                    }
                }
            },
            'direct_webhook_event': {
                "event": "alert.created",
                "timestamp": new Date().toISOString(),
                "data": {
                    "id": "test-alert-" + Date.now(),
                    "timestamp": new Date().toISOString(),
                    "caller": {
                        "name": "Test Caller",
                        "phone": "+18439003911"
                    },
                    "location": {
                        "latitude": 32.7767,
                        "longitude": -79.9311,
                        "accuracy": 10,
                        "address": {
                            "formatted": "100 MAIN ST, GOOSE CREEK, SC 29445",
                            "street_number": "100",
                            "street_name": "MAIN ST",
                            "city": "GOOSE CREEK",
                            "state": "SC",
                            "postal_code": "29445",
                            "country": "US"
                        }
                    },
                    "emergency": {
                        "type": "fire",
                        "description": "Structure fire reported",
                        "severity": "critical"
                    },
                    "device": {
                        "type": "smartphone",
                        "model": "iPhone 14",
                        "os": "iOS 16.0",
                        "battery_level": 85
                    }
                }
            }
        }; // Update payload when alert type changes
        document.getElementById('testAlertType').addEventListener('change', function() {
            const payload = testPayloads[this.value];
            document.getElementById('customPayload').value = JSON.stringify(payload, null, 2);
        });

        // Initialize with default payload
        document.getElementById('customPayload').value = JSON.stringify(testPayloads['alerts_api_format'], null, 2);

        // Handle signature test form
        document.getElementById('signatureTest').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(e.target);
            const payload = formData.get('payload');
            const signature = formData.get('signature');

            if (!payload.trim()) {
                alert('Please enter a test payload');
                return;
            }

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'test_signature',
                        payload: payload,
                        signature: signature
                    })
                });

                const result = await response.json();
                displaySignatureResult(result);

            } catch (error) {
                displaySignatureResult({
                    success: false,
                    error: error.message
                });
            }
        });

        function displaySignatureResult(result) {
            const resultEl = document.getElementById('signatureResult');

            if (result.success) {
                resultEl.innerHTML = `
                <div class="bg-green-50 border border-green-200 p-4 rounded">
                    <div class="text-green-800">
                        <i class="fas fa-check-circle mr-2"></i>
                        <strong>Signature verification successful!</strong>
                    </div>
                    ${result.generated_signature ? `
                        <div class="text-sm text-green-700 mt-2">
                            <strong>Generated signature:</strong><br>
                            <code class="bg-white px-2 py-1 rounded text-xs break-all">${result.generated_signature}</code>
                        </div>
                    ` : ''}
                </div>
            `;
            } else {
                resultEl.innerHTML = `
                <div class="bg-red-50 border border-red-200 p-4 rounded">
                    <div class="text-red-800">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Signature verification failed</strong>
                    </div>
                    <div class="text-sm text-red-700 mt-2">${result.error}</div>
                </div>
            `;
            }
        }

        async function sendTestWebhook() {
            const alertType = document.getElementById('testAlertType').value;
            const customPayload = document.getElementById('customPayload').value.trim();

            let payload;
            if (customPayload) {
                try {
                    payload = JSON.parse(customPayload);
                } catch (e) {
                    alert('Invalid JSON in custom payload: ' + e.message);
                    return;
                }
            } else {
                payload = testPayloads[alertType];
            }

            const resultEl = document.getElementById('webhookResult');
            resultEl.innerHTML = '<div class="text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Sending webhook...</div>';

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'send_webhook',
                        payload: payload
                    })
                });

                const result = await response.json();

                if (result.success) {
                    resultEl.innerHTML = `
                    <div class="bg-green-50 border border-green-200 p-4 rounded">
                        <div class="text-green-800">
                            <i class="fas fa-check-circle mr-2"></i>
                            <strong>Webhook sent successfully!</strong>
                        </div>
                        <div class="text-sm text-green-700 mt-2">
                            HTTP ${result.http_code} - ${result.response_time}ms
                        </div>
                    </div>
                `;
                } else {
                    resultEl.innerHTML = `
                    <div class="bg-red-50 border border-red-200 p-4 rounded">
                        <div class="text-red-800">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>Webhook failed</strong>
                        </div>
                        <div class="text-sm text-red-700 mt-2">${result.error}</div>
                    </div>
                `;
                }

            } catch (error) {
                resultEl.innerHTML = `
                <div class="bg-red-50 border border-red-200 p-4 rounded">
                    <div class="text-red-800">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Error sending webhook</strong>
                    </div>
                    <div class="text-sm text-red-700 mt-2">${error.message}</div>
                </div>
            `;
            }
        }

        async function viewLog(logType) {
            const resultEl = document.getElementById('logContent');
            resultEl.innerHTML = '<div class="text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading log...</div>';

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'view_log',
                        log_type: logType
                    })
                });

                const result = await response.json();

                if (result.success) {
                    resultEl.innerHTML = `
                    <div class="bg-gray-800 text-green-400 p-4 rounded font-mono text-xs overflow-auto max-h-96">
                        <div class="text-white mb-2">📁 ${result.log_file} (last ${result.lines} lines)</div>
                        <pre>${result.content}</pre>
                    </div>
                `;
                } else {
                    resultEl.innerHTML = `
                    <div class="text-red-600">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Error loading log: ${result.error}
                    </div>
                `;
                }

            } catch (error) {
                resultEl.innerHTML = `
                <div class="text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Error: ${error.message}
                </div>
            `;
            }
        }
    </script>
</body>

</html>

<?php
// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        switch ($action) {
            case 'test_signature':
                $payload = $input['payload'];
                $signature = $input['signature'];

                if (empty($signature)) {
                    // Generate a signature for testing
                    $generated = hash_hmac('sha256', $payload, $config['webhook_secret'] ?: 'test_secret');
                    $generated = 'sha256=' . $generated;

                    echo json_encode([
                        'success' => true,
                        'message' => 'Generated test signature',
                        'generated_signature' => $generated
                    ]);
                } else {
                    // Verify the provided signature
                    $isValid = $auth->verifyWebhookSignature($payload, $signature);

                    echo json_encode([
                        'success' => $isValid,
                        'message' => $isValid ? 'Signature is valid' : 'Signature is invalid'
                    ]);
                }
                break;

            case 'send_webhook':
                $payload = $input['payload'];
                $payloadJson = json_encode($payload);

                // Generate signature
                $signature = 'sha256=' . hash_hmac('sha256', $payloadJson, $config['webhook_secret'] ?: 'test_secret');

                // Send to webhook endpoint
                $start = microtime(true);
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $config['webhook_endpoint'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payloadJson,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'X-RapidSOS-Signature: ' . $signature
                    ],
                    CURLOPT_TIMEOUT => 30
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                $responseTime = round((microtime(true) - $start) * 1000);

                echo json_encode([
                    'success' => $httpCode >= 200 && $httpCode < 300 && !$error,
                    'http_code' => $httpCode,
                    'response_time' => $responseTime,
                    'response' => $response,
                    'error' => $error ?: ($httpCode >= 400 ? $response : null)
                ]);
                break;

            case 'view_log':
                $logType = $input['log_type'];
                $logFiles = [
                    'webhook' => __DIR__ . '/../logs/webhook_debug.log',
                    'auth' => __DIR__ . '/../logs/rapidsos_auth.log',
                    'subscriptions' => __DIR__ . '/../logs/rapidsos_subscriptions.log'
                ];

                $logFile = $logFiles[$logType] ?? null;
                if (!$logFile || !file_exists($logFile)) {
                    echo json_encode(['success' => false, 'error' => 'Log file not found']);
                    break;
                }

                // Get last 50 lines
                $lines = file($logFile);
                $lastLines = array_slice($lines, -50);
                $content = implode('', $lastLines);

                echo json_encode([
                    'success' => true,
                    'log_file' => basename($logFile),
                    'lines' => count($lastLines),
                    'content' => $content
                ]);
                break;

            default:
                throw new Exception('Invalid action: ' . $action);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>