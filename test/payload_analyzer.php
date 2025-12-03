<?php
// Payload Analysis and Testing Interface
require_once __DIR__ . '/../lib/payload_analyzer.php';
require_once __DIR__ . '/../lib/rapidsos_data_mapper.php';

$analyzer = new PayloadAnalyzer();
$mapper = new RapidSOSDataMapper();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        switch ($action) {
            case 'analyze_payload':
                $payload = $input['payload'];
                $name = $input['name'] ?? 'test_payload';

                $analysis = $analyzer->analyzeStructure($payload, $name);
                $extraction = $mapper->extractAlertData($payload);

                echo json_encode([
                    'success' => true,
                    'analysis' => $analysis,
                    'extraction' => $extraction,
                    'report' => $analyzer->generateReport($analysis)
                ]);
                break;

            case 'batch_analyze':
                $payloads = $input['payloads'];
                $batchResults = $analyzer->batchAnalyze($payloads);

                echo json_encode([
                    'success' => true,
                    'batch_results' => $batchResults
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
<!DOCTYPE html>
<html>

<head>
    <title>RapidSOS Payload Analyzer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-50">
    <div class="max-w-6xl mx-auto py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">RapidSOS Payload Analyzer</h1>
            <p class="text-gray-600 mt-2">Analyze different payload formats and test data extraction</p>
        </div>

        <!-- Single Payload Analysis -->
        <div class="bg-white p-6 rounded-lg shadow mb-8">
            <h2 class="text-xl font-semibold mb-4">Single Payload Analysis</h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium mb-2">Payload Name</label>
                    <input type="text" id="payloadName" value="test_payload" class="w-full border rounded px-3 py-2 mb-4">

                    <label class="block text-sm font-medium mb-2">Payload JSON</label>
                    <textarea id="singlePayload" rows="15" class="w-full border rounded px-3 py-2 font-mono text-sm" placeholder="Paste your RapidSOS payload here..."></textarea>

                    <button onclick="analyzeSinglePayload()" class="mt-4 bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">
                        <i class="fas fa-search mr-2"></i>Analyze Payload
                    </button>
                </div>

                <div>
                    <h3 class="text-lg font-semibold mb-2">Analysis Results</h3>
                    <div id="singleResults" class="bg-gray-50 p-4 rounded border min-h-96 max-h-96 overflow-auto">
                        <p class="text-gray-500">Paste a payload and click "Analyze Payload" to see results...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Multiple Payload Analysis -->
        <div class="bg-white p-6 rounded-lg shadow mb-8">
            <h2 class="text-xl font-semibold mb-4">Batch Analysis (Compare Multiple Formats)</h2>
            <div class="space-y-4">
                <div id="payloadInputs">
                    <div class="payload-input border p-4 rounded">
                        <div class="flex justify-between items-center mb-2">
                            <label class="font-medium">Payload 1</label>
                            <button onclick="removePayloadInput(this)" class="text-red-500 hover:text-red-700">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <input type="text" class="payload-name w-full border rounded px-3 py-2 mb-2" placeholder="Payload name" value="payload_1">
                        <textarea class="payload-data w-full border rounded px-3 py-2 font-mono text-sm" rows="8" placeholder="Paste payload JSON here..."></textarea>
                    </div>
                </div>

                <div class="flex space-x-4">
                    <button onclick="addPayloadInput()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        <i class="fas fa-plus mr-2"></i>Add Another Payload
                    </button>
                    <button onclick="loadSamplePayloads()" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                        <i class="fas fa-magic mr-2"></i>Load Sample Payloads
                    </button>
                    <button onclick="analyzeBatch()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        <i class="fas fa-chart-bar mr-2"></i>Analyze Batch
                    </button>
                </div>
            </div>

            <div id="batchResults" class="mt-6"></div>
        </div>

        <!-- Extraction Testing -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold mb-4">Data Extraction Testing</h2>
            <div id="extractionResults" class="space-y-4">
                <p class="text-gray-500">Run a payload analysis to see extracted data...</p>
            </div>
        </div>
    </div>

    <script>
        // Sample payloads for testing
        const samplePayloads = {
            'alerts_api_format': {
                "alerts_until": 1581110270622,
                "alerts": [{
                    "source_id": "abc123",
                    "incident_time": 1581110270622,
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
                    "description": "Fire alarm activation",
                    "emergency_type": {
                        "display_name": "Fire"
                    },
                    "alert_id": "alert-12345"
                }]
            },
            'webhook_callflow': {
                "callflow": "TestBCG_alerts",
                "variables": {
                    "permit_number": "93547",
                    "service_provider": "Berkeley County 911",
                    "alarm_description": "MEDICAL EMERGENCY",
                    "buildings": {
                        "address": {
                            "address1": "223 N LIVE OAK DR",
                            "city": "Moncks Corner",
                            "state": "SC",
                            "zip": "29456"
                        }
                    },
                    "event": {
                        "emergency_type": "MEDICAL",
                        "description": "Medical emergency"
                    }
                }
            },
            'direct_webhook_event': {
                "event": "alert.created",
                "timestamp": "2025-10-01T15:00:00Z",
                "data": {
                    "id": "test-alert-123",
                    "location": {
                        "latitude": 32.7767,
                        "longitude": -79.9311,
                        "address": {
                            "formatted": "100 MAIN ST, GOOSE CREEK, SC 29445"
                        }
                    },
                    "emergency": {
                        "type": "fire",
                        "description": "Structure fire reported"
                    }
                }
            }
        };

        async function analyzeSinglePayload() {
            const payloadText = document.getElementById('singlePayload').value.trim();
            const payloadName = document.getElementById('payloadName').value.trim();

            if (!payloadText) {
                alert('Please enter a payload to analyze');
                return;
            }

            let payload;
            try {
                payload = JSON.parse(payloadText);
            } catch (e) {
                alert('Invalid JSON: ' + e.message);
                return;
            }

            const resultsEl = document.getElementById('singleResults');
            resultsEl.innerHTML = '<div class="text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Analyzing...</div>';

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'analyze_payload',
                        payload: payload,
                        name: payloadName
                    })
                });

                const result = await response.json();

                if (result.success) {
                    displaySingleResults(result);
                } else {
                    resultsEl.innerHTML = `<div class="text-red-600">Error: ${result.error}</div>`;
                }

            } catch (error) {
                resultsEl.innerHTML = `<div class="text-red-600">Error: ${error.message}</div>`;
            }
        }

        function displaySingleResults(result) {
            const resultsEl = document.getElementById('singleResults');
            const analysis = result.analysis;
            const extraction = result.extraction;

            resultsEl.innerHTML = `
            <div class="space-y-4">
                <div class="bg-blue-50 p-3 rounded">
                    <h4 class="font-semibold text-blue-900">Format Detected</h4>
                    <p class="text-blue-800">${analysis.format_detected}</p>
                </div>
                
                <div class="bg-green-50 p-3 rounded">
                    <h4 class="font-semibold text-green-900">Data Completeness: ${analysis.data_completeness.score}%</h4>
                    <p class="text-green-800 text-sm">Present: ${analysis.data_completeness.present_fields.join(', ')}</p>
                    ${analysis.data_completeness.missing_fields.length > 0 ? 
                        `<p class="text-red-600 text-sm">Missing: ${analysis.data_completeness.missing_fields.join(', ')}</p>` : ''}
                </div>
                
                <div class="bg-yellow-50 p-3 rounded">
                    <h4 class="font-semibold text-yellow-900">Location Data</h4>
                    <div class="text-sm text-yellow-800">
                        <p>Coordinates: ${analysis.structure_analysis.has_coordinates ? 'Yes' : 'No'}</p>
                        <p>Civic Address: ${analysis.structure_analysis.has_civic_address ? 'Yes' : 'No'}</p>
                        ${Object.keys(analysis.structure_analysis.location_formats).length > 0 ? 
                            `<p>Formats: ${Object.keys(analysis.structure_analysis.location_formats).join(', ')}</p>` : ''}
                    </div>
                </div>
                
                <div class="bg-gray-50 p-3 rounded">
                    <h4 class="font-semibold">Recommendations</h4>
                    <ul class="text-sm list-disc ml-4">
                        ${analysis.recommendations.map(rec => `<li>${rec}</li>`).join('')}
                    </ul>
                </div>
            </div>
        `;

            // Update extraction results
            displayExtractionResults(extraction);
        }

        function displayExtractionResults(extraction) {
            const extractionEl = document.getElementById('extractionResults');

            if (extraction.alerts && extraction.alerts.length > 0) {
                const alert = extraction.alerts[0];

                extractionEl.innerHTML = `
                <div class="bg-white border rounded p-4">
                    <h3 class="font-semibold mb-3">Extracted Alert Data</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div><strong>Format:</strong> ${extraction.format}</div>
                        <div><strong>Source ID:</strong> ${alert.source_id || 'N/A'}</div>
                        <div><strong>Alert ID:</strong> ${alert.alert_id || 'N/A'}</div>
                        <div><strong>Incident Time:</strong> ${alert.incident_time || 'N/A'}</div>
                        <div><strong>Emergency Type:</strong> ${alert.emergency_type || 'N/A'}</div>
                        <div><strong>Description:</strong> ${alert.description || 'N/A'}</div>
                        <div><strong>Service Provider:</strong> ${alert.service_provider || 'N/A'}</div>
                        <div><strong>Site Type:</strong> ${alert.site_type || 'N/A'}</div>
                    </div>
                    
                    ${alert.location ? `
                        <div class="mt-4 bg-gray-50 p-3 rounded">
                            <h4 class="font-semibold mb-2">Location Data</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                                ${alert.location.latitude ? `<div><strong>Latitude:</strong> ${alert.location.latitude}</div>` : ''}
                                ${alert.location.longitude ? `<div><strong>Longitude:</strong> ${alert.location.longitude}</div>` : ''}
                                ${alert.location.civic ? `
                                    <div class="md:col-span-2">
                                        <strong>Address:</strong> 
                                        ${alert.location.civic.street_1 || ''} ${alert.location.civic.street_2 || ''}, 
                                        ${alert.location.civic.city || ''}, ${alert.location.civic.state || ''} ${alert.location.civic.zip_code || ''}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    ` : ''}
                    
                    ${alert.contact_info ? `
                        <div class="mt-4 bg-gray-50 p-3 rounded">
                            <h4 class="font-semibold mb-2">Contact Information</h4>
                            <div class="text-sm">
                                ${alert.contact_info.name ? `<div><strong>Name:</strong> ${alert.contact_info.name}</div>` : ''}
                                ${alert.contact_info.phone ? `<div><strong>Phone:</strong> ${alert.contact_info.phone}</div>` : ''}
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
            } else {
                extractionEl.innerHTML = '<div class="text-gray-500">No alert data extracted</div>';
            }
        }

        function addPayloadInput() {
            const container = document.getElementById('payloadInputs');
            const count = container.children.length + 1;

            const newInput = document.createElement('div');
            newInput.className = 'payload-input border p-4 rounded';
            newInput.innerHTML = `
            <div class="flex justify-between items-center mb-2">
                <label class="font-medium">Payload ${count}</label>
                <button onclick="removePayloadInput(this)" class="text-red-500 hover:text-red-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <input type="text" class="payload-name w-full border rounded px-3 py-2 mb-2" placeholder="Payload name" value="payload_${count}">
            <textarea class="payload-data w-full border rounded px-3 py-2 font-mono text-sm" rows="8" placeholder="Paste payload JSON here..."></textarea>
        `;

            container.appendChild(newInput);
        }

        function removePayloadInput(button) {
            const container = document.getElementById('payloadInputs');
            if (container.children.length > 1) {
                button.closest('.payload-input').remove();
            }
        }

        function loadSamplePayloads() {
            const container = document.getElementById('payloadInputs');
            container.innerHTML = '';

            Object.entries(samplePayloads).forEach(([name, payload], index) => {
                const newInput = document.createElement('div');
                newInput.className = 'payload-input border p-4 rounded';
                newInput.innerHTML = `
                <div class="flex justify-between items-center mb-2">
                    <label class="font-medium">${name}</label>
                    <button onclick="removePayloadInput(this)" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <input type="text" class="payload-name w-full border rounded px-3 py-2 mb-2" value="${name}">
                <textarea class="payload-data w-full border rounded px-3 py-2 font-mono text-sm" rows="8">${JSON.stringify(payload, null, 2)}</textarea>
            `;
                container.appendChild(newInput);
            });
        }

        async function analyzeBatch() {
            const payloadInputs = document.querySelectorAll('.payload-input');
            const payloads = {};

            for (let input of payloadInputs) {
                const name = input.querySelector('.payload-name').value.trim();
                const data = input.querySelector('.payload-data').value.trim();

                if (name && data) {
                    try {
                        payloads[name] = JSON.parse(data);
                    } catch (e) {
                        alert(`Invalid JSON in ${name}: ${e.message}`);
                        return;
                    }
                }
            }

            if (Object.keys(payloads).length === 0) {
                alert('Please add at least one payload to analyze');
                return;
            }

            const resultsEl = document.getElementById('batchResults');
            resultsEl.innerHTML = '<div class="text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Analyzing batch...</div>';

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'batch_analyze',
                        payloads: payloads
                    })
                });

                const result = await response.json();

                if (result.success) {
                    displayBatchResults(result.batch_results);
                } else {
                    resultsEl.innerHTML = `<div class="text-red-600">Error: ${result.error}</div>`;
                }

            } catch (error) {
                resultsEl.innerHTML = `<div class="text-red-600">Error: ${error.message}</div>`;
            }
        }

        function displayBatchResults(results) {
            const resultsEl = document.getElementById('batchResults');
            const summary = results.summary;

            resultsEl.innerHTML = `
            <div class="space-y-6">
                <div class="bg-blue-50 p-4 rounded">
                    <h3 class="font-semibold text-blue-900 mb-3">Batch Analysis Summary</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div><strong>Total Payloads:</strong> ${summary.total_payloads}</div>
                        <div><strong>Unique Formats:</strong> ${summary.unique_formats.join(', ')}</div>
                        <div><strong>Common Fields:</strong> ${summary.common_fields.length}</div>
                    </div>
                </div>
                
                <div class="bg-green-50 p-4 rounded">
                    <h3 class="font-semibold text-green-900 mb-3">Format Distribution</h3>
                    ${Object.entries(summary.format_distribution).map(([format, count]) => 
                        `<div class="text-sm text-green-800">${format}: ${count} payload(s)</div>`
                    ).join('')}
                </div>
                
                ${summary.common_fields.length > 0 ? `
                    <div class="bg-yellow-50 p-4 rounded">
                        <h3 class="font-semibold text-yellow-900 mb-3">Universal Fields (Present in all payloads)</h3>
                        <div class="text-sm text-yellow-800">${summary.common_fields.join(', ')}</div>
                    </div>
                ` : ''}
                
                <div class="bg-purple-50 p-4 rounded">
                    <h3 class="font-semibold text-purple-900 mb-3">Extraction Strategy</h3>
                    <div class="space-y-2 text-sm text-purple-800">
                        ${Object.entries(results.extraction_strategy.format_specific_extractors).map(([format, strategy]) => 
                            `<div><strong>${format}:</strong> ${strategy.completeness_score}% data completeness</div>`
                        ).join('')}
                    </div>
                </div>
            </div>
        `;
        }

        // Load first sample payload on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('singlePayload').value = JSON.stringify(samplePayloads.alerts_api_format, null, 2);
        });
    </script>
</body>

</html>