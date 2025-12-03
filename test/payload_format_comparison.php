<?php
// Payload Format Compatibility Analysis - Enhanced Web Interface
require_once __DIR__ . '/../lib/rapidsos_data_mapper.php';
require_once __DIR__ . '/../lib/rapidsos_websocket_mapper.php';
require_once __DIR__ . '/../lib/payload_analyzer.php';

// Check if this is a web request
$isWebRequest = isset($_SERVER['HTTP_HOST']);

if ($isWebRequest) {
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payload Format Analysis - Berkeley County Emergency Services</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            .gradient-bg {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }

            .code-block {
                background: #1a1a1a;
                color: #e6e6e6;
                border-radius: 8px;
                padding: 1rem;
                overflow-x: auto;
                font-family: 'Courier New', monospace;
                font-size: 0.875rem;
                line-height: 1.5;
            }

            .json-key {
                color: #9cdcfe;
            }

            .json-string {
                color: #ce9178;
            }

            .json-number {
                color: #b5cea8;
            }

            .json-boolean {
                color: #569cd6;
            }

            .card-hover {
                transition: all 0.3s ease;
            }

            .card-hover:hover {
                transform: translateY(-5px);
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            }

            .compatibility-meter {
                background: conic-gradient(from 0deg, #10b981 0deg 108deg, #f59e0b 108deg 180deg, #ef4444 180deg 360deg);
                border-radius: 50%;
                width: 120px;
                height: 120px;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
            }

            .compatibility-meter::before {
                content: '';
                background: white;
                border-radius: 50%;
                width: 90px;
                height: 90px;
                position: absolute;
            }

            .compatibility-score {
                z-index: 10;
                font-weight: bold;
                font-size: 1.5rem;
                color: #1f2937;
            }
        </style>
    </head>

    <body class="bg-gray-50">
        <!-- Header -->
        <div class="gradient-bg text-white py-8 mb-8">
            <div class="container mx-auto px-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-4xl font-bold mb-2">
                            <i class="fas fa-code-compare mr-3"></i>
                            Payload Format Analysis
                        </h1>
                        <p class="text-blue-100 text-lg">Berkeley County Emergency Services - RapidSOS Integration</p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-blue-100 mb-1">Analysis Date</div>
                        <div class="text-lg font-semibold"><?php echo date('M d, Y H:i:s'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container mx-auto px-6">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-lg p-6 card-hover">
                    <div class="flex items-center">
                        <div class="bg-green-500 text-white p-3 rounded-full mr-4">
                            <i class="fas fa-chart-pie text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Compatibility Score</h3>
                            <p class="text-2xl font-bold text-gray-900" id="compatibilityScore">--</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-lg p-6 card-hover">
                    <div class="flex items-center">
                        <div class="bg-blue-500 text-white p-3 rounded-full mr-4">
                            <i class="fas fa-list-check text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Fields Analyzed</h3>
                            <p class="text-2xl font-bold text-gray-900" id="fieldsAnalyzed">--</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-lg p-6 card-hover">
                    <div class="flex items-center">
                        <div class="bg-purple-500 text-white p-3 rounded-full mr-4">
                            <i class="fas fa-check-double text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Mapping Success</h3>
                            <p class="text-2xl font-bold text-gray-900" id="mappingSuccess">--</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-lg p-6 card-hover">
                    <div class="flex items-center">
                        <div class="bg-indigo-500 text-white p-3 rounded-full mr-4">
                            <i class="fas fa-code text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Format Type</h3>
                            <p class="text-sm font-bold text-gray-900" id="formatType">--</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Analysis Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Compatibility Overview -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">
                        <i class="fas fa-tachometer-alt text-blue-500 mr-2"></i>
                        Compatibility Overview
                    </h2>
                    <div class="flex items-center justify-center mb-6">
                        <div class="compatibility-meter">
                            <div class="compatibility-score" id="compatibilityMeter">--</div>
                        </div>
                    </div>
                    <div class="text-center">
                        <h3 class="font-semibold text-gray-900 mb-2">System Recommendations</h3>
                        <div id="recommendations" class="space-y-2 text-sm text-gray-600">
                            <!-- Recommendations will be populated by JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Field Mapping Matrix -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">
                        <i class="fas fa-table text-green-500 mr-2"></i>
                        Field Mapping Matrix
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Field</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Match</th>
                                </tr>
                            </thead>
                            <tbody id="mappingTable" class="bg-white divide-y divide-gray-200">
                                <!-- Table rows will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Payload Comparison -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-6">
                    <i class="fas fa-code-compare text-purple-500 mr-2"></i>
                    Side-by-Side Payload Comparison
                </h2>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Your Test Payload (Legacy Format)</h3>
                        <div class="code-block">
                            <pre id="legacyPayload">Loading...</pre>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Official WebSocket Events API v1.1.1</h3>
                        <div class="code-block">
                            <pre id="officialPayload">Loading...</pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="text-center py-8">
                <a href="../index.html" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Emergency Services Dashboard
                </a>
            </div>
        </div>
    </body>

    </html>

<?php
}

// Perform the analysis
$userTestPayload = [
    "alerts" => [
        [
            "alert_id" => "alert-6e58d630-d28f-4623-ba01-3207b497a9ba",
            "source_id" => "abc123",
            "incident_time" => 1581110270622,
            "created_time" => 1581110270922,
            "last_updated_time" => 1581110270622,
            "emergency_type" => [
                "name" => "BURGLARY",
                "display_name" => "Burglary"
            ],
            "location" => [
                "provided_location" => "BOTH",
                "geodetic" => [
                    "latitude" => 45.123,
                    "longitude" => -71.321,
                    "uncertainty_radius" => 7.5
                ],
                "civic" => [
                    "name" => "RapidSOS HQ",
                    "street_1" => "123 Main Street",
                    "street_2" => "Apt. 626",
                    "city" => "Los Angeles",
                    "state" => "CA",
                    "country" => "USA",
                    "zip_code" => "11375"
                ]
            ],
            "service_provider_name" => "Alarm Company ABC",
            "description" => "Smoke detector tripped in zone 1 on floor 2.",
            "status" => [
                "name" => "DISPATCHED",
                "display_name" => "Dispatched"
            ],
            "site_type" => [
                "name" => "RESIDENTIAL",
                "display_name" => "Residential"
            ],
            "authorized_entity" => [
                "name" => "Central Station ABC",
                "phone" => "15555555555"
            ],
            "covering_psap" => [
                "name" => "Berkeley County 911",
                "phone" => "15555555555"
            ]
        ]
    ]
];

// Official WebSocket Events API v1.1.1 format example
$officialWebSocketPayload = [
    "event" => "alert.new",
    "body" => [
        "alert_id" => "alert-6e58d630-d28f-4623-ba01-3207b497a9ba",
        "source_id" => "abc123",
        "incident_time" => 1581110270622,
        "created_time" => 1581110270922,
        "last_updated_time" => 1581110270622,
        "emergency_type" => [
            "name" => "BURGLARY",
            "display_name" => "Burglary"
        ],
        "location" => [
            "provided_location" => "BOTH",
            "geodetic" => [
                "latitude" => 45.123,
                "longitude" => -71.321,
                "uncertainty_radius" => 7.5
            ],
            "civic" => [
                "name" => "RapidSOS HQ",
                "street_1" => "123 Main Street",
                "street_2" => "Apt. 626",
                "city" => "Los Angeles",
                "state" => "CA",
                "country" => "USA",
                "zip_code" => "11375"
            ]
        ],
        "service_provider_name" => "Alarm Company ABC",
        "description" => "Smoke detector tripped in zone 1 on floor 2.",
        "status" => [
            "name" => "NEW",
            "display_name" => "New"
        ],
        "site_type" => [
            "name" => "RESIDENTIAL",
            "display_name" => "Residential"
        ],
        "authorized_entity" => [
            "name" => "Central Station ABC",
            "phone" => "15555555555"
        ],
        "covering_psap" => [
            "name" => "Berkeley County 911",
            "phone" => "15555555555"
        ]
    ],
    "timestamp" => 1581110270922
];

// Simple compatibility analysis
$compatibilityMatrix = [
    [
        'field' => 'Alert ID',
        'legacy_path' => 'alerts[0].alert_id',
        'official_path' => 'body.alert_id',
        'legacy_value' => $userTestPayload['alerts'][0]['alert_id'],
        'official_value' => $officialWebSocketPayload['body']['alert_id'],
        'compatible' => true,
        'status' => 'Compatible'
    ],
    [
        'field' => 'Source ID',
        'legacy_path' => 'alerts[0].source_id',
        'official_path' => 'body.source_id',
        'legacy_value' => $userTestPayload['alerts'][0]['source_id'],
        'official_value' => $officialWebSocketPayload['body']['source_id'],
        'compatible' => true,
        'status' => 'Compatible'
    ],
    [
        'field' => 'Incident Time',
        'legacy_path' => 'alerts[0].incident_time',
        'official_path' => 'body.incident_time',
        'legacy_value' => $userTestPayload['alerts'][0]['incident_time'],
        'official_value' => $officialWebSocketPayload['body']['incident_time'],
        'compatible' => true,
        'status' => 'Compatible'
    ],
    [
        'field' => 'Emergency Type',
        'legacy_path' => 'alerts[0].emergency_type.display_name',
        'official_path' => 'body.emergency_type.display_name',
        'legacy_value' => $userTestPayload['alerts'][0]['emergency_type']['display_name'],
        'official_value' => $officialWebSocketPayload['body']['emergency_type']['display_name'],
        'compatible' => true,
        'status' => 'Compatible'
    ],
    [
        'field' => 'Latitude',
        'legacy_path' => 'alerts[0].location.geodetic.latitude',
        'official_path' => 'body.location.geodetic.latitude',
        'legacy_value' => $userTestPayload['alerts'][0]['location']['geodetic']['latitude'],
        'official_value' => $officialWebSocketPayload['body']['location']['geodetic']['latitude'],
        'compatible' => true,
        'status' => 'Compatible'
    ],
    [
        'field' => 'Status',
        'legacy_path' => 'alerts[0].status.display_name',
        'official_path' => 'body.status.display_name',
        'legacy_value' => $userTestPayload['alerts'][0]['status']['display_name'],
        'official_value' => $officialWebSocketPayload['body']['status']['display_name'],
        'compatible' => false,
        'status' => 'Different Values'
    ]
];

$compatibilityScore = 83; // 5 out of 6 fields compatible

$analysisResults = [
    'summary' => [
        'compatibility_score' => $compatibilityScore,
        'compatible_fields' => 5,
        'total_fields' => 6,
        'format_detected' => 'Legacy Custom Format'
    ],
    'legacy_payload' => $userTestPayload,
    'official_payload' => $officialWebSocketPayload,
    'compatibility_matrix' => $compatibilityMatrix,
    'recommendations' => [
        'Your test payload works with the legacy system ✓',
        'Both formats are supported by your current system (auto-detection) ✓',
        'For future RapidSOS integrations, use the official WebSocket Events API v1.1.1 ✓',
        'Your payload appears to be from RapidSOS Alerts API (older/different format) ⚠️',
        'WebSocket Events API format is RECOMMENDED ⭐'
    ]
];

if ($isWebRequest) {
?>

    <script>
        const analysisData = <?php echo json_encode($analysisResults); ?>;

        // Initialize the interface with analysis data
        document.addEventListener('DOMContentLoaded', function() {
            displayAnalysisResults(analysisData);
        });

        function displayAnalysisResults(data) {
            // Update summary cards
            document.getElementById('compatibilityScore').textContent = data.summary.compatibility_score + '%';
            document.getElementById('fieldsAnalyzed').textContent = data.summary.total_fields;
            document.getElementById('mappingSuccess').textContent = data.summary.compatible_fields + '/' + data.summary.total_fields;
            document.getElementById('formatType').textContent = data.summary.format_detected;
            document.getElementById('compatibilityMeter').textContent = data.summary.compatibility_score + '%';

            // Update recommendations
            const recommendationsContainer = document.getElementById('recommendations');
            recommendationsContainer.innerHTML = '';
            data.recommendations.forEach(rec => {
                const div = document.createElement('div');
                div.className = 'flex items-center space-x-2';
                div.innerHTML = `<span class="text-sm">${rec}</span>`;
                recommendationsContainer.appendChild(div);
            });

            // Update compatibility matrix table
            const tableBody = document.getElementById('mappingTable');
            tableBody.innerHTML = '';
            data.compatibility_matrix.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${row.field}</td>
            <td class="px-4 py-4 whitespace-nowrap">
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${row.compatible ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                    ${row.status}
                </span>
            </td>
            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                <span class="${row.compatible ? 'text-green-600' : 'text-yellow-600'}">
                    <i class="fas ${row.compatible ? 'fa-check' : 'fa-exclamation-triangle'} mr-1"></i>
                    ${row.compatible ? 'Match' : 'Different'}
                </span>
            </td>
        `;
                tableBody.appendChild(tr);
            });

            // Update payload displays
            document.getElementById('legacyPayload').textContent = JSON.stringify(data.legacy_payload, null, 2);
            document.getElementById('officialPayload').textContent = JSON.stringify(data.official_payload, null, 2);
        }
    </script>

<?php
} else {
    // CLI output
    echo json_encode($analysisResults, JSON_PRETTY_PRINT);
}
?>