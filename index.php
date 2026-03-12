<?php require_once __DIR__ . '/lib/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Alerts System - Berkeley County</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        /* Splash Screen Styles */
        #splashScreen {
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at center, #0a0a0a 0%, #000 100%);
            color: #e0e0e0;
            font-family: 'Orbitron', sans-serif;
            z-index: 9999;
            transition: opacity 0.8s ease-out;
        }

        #splashScreen.fade-out {
            opacity: 0;
            pointer-events: none;
        }

        .splash-overlay {
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(to bottom,
                    rgba(255, 255, 255, 0.02) 0px,
                    rgba(255, 255, 255, 0.02) 1px,
                    transparent 2px,
                    transparent 3px);
            animation: scanline 2s linear infinite;
            pointer-events: none;
        }

        @keyframes scanline {
            0% {
                background-position: 0 0;
            }

            100% {
                background-position: 0 100%;
            }
        }

        .splash-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }

        .splash-title {
            font-size: 3rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #ff2a2a;
            text-shadow:
                0 0 10px #ff2a2a,
                0 0 20px #ff2a2a,
                0 0 30px #ff2a2a;
            animation: flicker 2.5s infinite;
            margin: 0;
        }

        .splash-text {
            margin-top: 1rem;
            font-size: 1rem;
            letter-spacing: 0.15em;
            color: #888;
        }

        .splash-pulse {
            display: inline-block;
            width: 10px;
            height: 10px;
            background: #ff2a2a;
            border-radius: 50%;
            margin-left: 8px;
            box-shadow: 0 0 10px #ff2a2a;
            animation: pulse 1.5s infinite alternate;
        }

        @keyframes flicker {

            0%,
            19%,
            21%,
            23%,
            25%,
            54%,
            56%,
            100% {
                opacity: 1;
            }

            20%,
            24%,
            55% {
                opacity: 0.3;
            }
        }

        @keyframes pulse {
            from {
                transform: scale(0.8);
                opacity: 0.6;
            }

            to {
                transform: scale(1.4);
                opacity: 1;
            }
        }

        #mainContent {
            opacity: 0;
            transition: opacity 0.5s ease-in;
        }

        #mainContent.show {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .splash-title {
                font-size: 2rem;
            }
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <!-- Splash Screen -->
    <div id="splashScreen">
        <div class="splash-overlay"></div>
        <div class="splash-center">
            <h1 class="splash-title">RED FIVE RELAY</h1>
            <p class="splash-text">Signal uplink initializing<span class="splash-pulse"></span></p>
            <p class="splash-text" style="margin-top:2rem;color:#444;">// transmission pending // standby for deployment
                //</p>
        </div>
    </div>

    <!-- Main Content -->
    <div id="mainContent">
        <!-- Header -->
        <header class="gradient-bg text-white shadow-lg">
            <div class="container mx-auto px-6 py-8">
                <div class="text-center">
                    <div class="flex items-center justify-center mb-4">
                        <i class="fas fa-shield-alt text-4xl mr-4"></i>
                        <h1 class="text-4xl font-bold">Emergency Alerts System</h1>
                    </div>
                    <p class="text-xl opacity-90">Berkeley County Event Ingestion Layer</p>
                    <p class="text-lg opacity-75 mt-2">RapidSOS • Alastar • CAD Integration • AsapToPsap • Real-time
                        Dashboard</p>
                </div>
            </div>
        </header>

        <!-- User Bar -->
        <div class="bg-gray-900 text-gray-400 text-xs py-2 px-6">
            <div class="container mx-auto flex justify-between items-center">
                <span><i class="fas fa-user-circle mr-1"></i> <?php echo htmlspecialchars(RedfiveAuth::getDisplayName()); ?></span>
                <a href="auth/logout.php" class="text-red-400 hover:text-red-300 transition-colors">
                    <i class="fas fa-sign-out-alt mr-1"></i>Logout
                </a>
            </div>
        </div>

        <!-- System Status Banner -->
        <div class="bg-green-50 border-l-4 border-green-400 p-4">
            <div class="container mx-auto px-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-400 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700">
                            <strong>System Status:</strong>
                            <span id="systemStatus">Checking...</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="container mx-auto px-6 py-12">
            <!-- Tools Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
                <!-- Dashboard -->
                <div class="bg-white p-6 rounded-lg shadow-md card-hover">
                    <div class="text-center">
                        <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-tachometer-alt text-2xl text-blue-600"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Emergency Dashboard</h3>
                        <p class="text-gray-600 mb-4">Real-time view of emergency alerts with timezone conversion and
                            interactive filtering</p>
                        <a href="view/"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg inline-flex items-center transition-colors">
                            <i class="fas fa-external-link-alt mr-2"></i>
                            Open Dashboard
                        </a>
                    </div>
                </div>

                <!-- Webhook Management -->
                <div class="bg-white p-6 rounded-lg shadow-md card-hover">
                    <div class="text-center">
                        <div class="bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-link text-2xl text-green-600"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Webhook Management</h3>
                        <p class="text-gray-600 mb-4">Manage RapidSOS webhook subscriptions and connection status</p>
                        <a href="manage/subscriptions.php"
                            class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg inline-flex items-center transition-colors">
                            <i class="fas fa-cogs mr-2"></i>
                            Manage Webhooks
                        </a>
                    </div>
                </div>
                <!-- Flock Webhook Management -->
                <div class="bg-white p-6 rounded-lg shadow-md card-hover">
                    <div class="text-center">
                        <div class="bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-link text-2xl text-green-600"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Webhook Management</h3>
                        <p class="text-gray-600 mb-4">Manage Flock ALPR webhook subscriptions and connection status</p>
                        <a href="manage/flock_subscriptions.php"
                            class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg inline-flex items-center transition-colors">
                            <i class="fas fa-cogs mr-2"></i>
                            Manage Webhooks
                        </a>
                    </div>
                </div>

                <!-- WebSocket Client -->
                <div class="bg-white p-6 rounded-lg shadow-md card-hover">
                    <div class="text-center">
                        <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-satellite-dish text-2xl text-blue-600"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">WebSocket Client</h3>
                        <p class="text-gray-600 mb-4">Real-time event streaming from RapidSOS WebSocket Events API</p>
                        <a href="websocket_manager.php"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg inline-flex items-center transition-colors">
                            <i class="fas fa-satellite-dish mr-2"></i>
                            Manage WebSocket
                        </a>
                    </div>
                </div>

                <!-- System Logs -->
                <div class="bg-white p-6 rounded-lg shadow-md card-hover">
                    <div class="text-center">
                        <div class="bg-purple-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-file-alt text-2xl text-purple-600"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">System Logs</h3>
                        <p class="text-gray-600 mb-4">Monitor webhook activity, authentication, and payload processing
                        </p>
                        <button onclick="showLogsModal()"
                            class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg inline-flex items-center transition-colors">
                            <i class="fas fa-list mr-2"></i>
                            View Logs
                        </button>
                    </div>
                </div>

                <!-- Testing Tools -->
                <div class="bg-white p-6 rounded-lg shadow-md card-hover">
                    <div class="text-center">
                        <div class="bg-orange-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-flask text-2xl text-orange-600"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Testing Tools</h3>
                        <p class="text-gray-600 mb-4">Test webhook endpoints, payload simulation, and format analysis
                        </p>
                        <button onclick="showTestingModal()"
                            class="bg-orange-600 hover:bg-orange-700 text-white px-6 py-2 rounded-lg inline-flex items-center transition-colors">
                            <i class="fas fa-tools mr-2"></i>
                            Testing Suite
                        </button>
                    </div>
                </div>

                <!-- API Documentation -->
                <div class="bg-white p-6 rounded-lg shadow-md card-hover">
                    <div class="text-center">
                        <div class="bg-indigo-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-book text-2xl text-indigo-600"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">API Documentation</h3>
                        <p class="text-gray-600 mb-4">Integration guides, API references, and technical documentation
                        </p>
                        <button onclick="showDocsModal()"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg inline-flex items-center transition-colors">
                            <i class="fas fa-book-open mr-2"></i>
                            View Docs
                        </button>
                    </div>
                </div>

                <!-- System Status -->
                <div class="bg-white p-6 rounded-lg shadow-md card-hover">
                    <div class="text-center">
                        <div class="bg-red-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-heartbeat text-2xl text-red-600"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">System Health</h3>
                        <p class="text-gray-600 mb-4">Monitor service health, connectivity, and performance metrics</p>
                        <button onclick="checkSystemHealth()"
                            class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg inline-flex items-center transition-colors">
                            <i class="fas fa-stethoscope mr-2"></i>
                            Health Check
                        </button>
                    </div>
                </div>
            </div>

            <!-- System Overview -->
            <div class="bg-white rounded-lg shadow-md p-8 mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">System Overview</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <i class="fas fa-satellite-dish text-3xl text-blue-600 mb-2"></i>
                            <h4 class="font-semibold text-gray-900">RapidSOS Integration</h4>
                            <p class="text-sm text-gray-600 mt-2">Real-time emergency alert ingestion via webhook
                                subscriptions</p>
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="bg-green-50 p-4 rounded-lg">
                            <i class="fas fa-database text-3xl text-green-600 mb-2"></i>
                            <h4 class="font-semibold text-gray-900">Data Processing</h4>
                            <p class="text-sm text-gray-600 mt-2">Universal payload mapping with format detection and
                                validation</p>
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <i class="fas fa-share-alt text-3xl text-purple-600 mb-2"></i>
                            <h4 class="font-semibold text-gray-900">CAD Integration</h4>
                            <p class="text-sm text-gray-600 mt-2">Automated posting to Southern Software CIM system</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white p-4 rounded-lg shadow text-center">
                    <div class="text-2xl font-bold text-blue-600" id="alertCount">--</div>
                    <div class="text-sm text-gray-600">Total Alerts</div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow text-center">
                    <div class="text-2xl font-bold text-green-600" id="webhookStatus">--</div>
                    <div class="text-sm text-gray-600">Webhook Status</div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow text-center">
                    <div class="text-2xl font-bold text-purple-600" id="lastAlert">--</div>
                    <div class="text-sm text-gray-600">Last Alert</div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow text-center">
                    <div class="text-2xl font-bold text-orange-600" id="cadStatus">--</div>
                    <div class="text-sm text-gray-600">CAD Integration</div>
                </div>
            </div>
        </main>

        <!-- Logs Modal -->
        <div id="logsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
            <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">System Logs</h3>
                        <button onclick="closeModal('logsModal')" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <a href="logs/webhook_debug.log" target="_blank"
                            class="block p-4 border rounded hover:bg-gray-50">
                            <i class="fas fa-file-alt text-blue-600 mr-2"></i>
                            Webhook Debug Log
                        </a>
                        <a href="logs/rapidsos_auth.log" target="_blank"
                            class="block p-4 border rounded hover:bg-gray-50">
                            <i class="fas fa-key text-green-600 mr-2"></i>
                            Authentication Log
                        </a>
                        <a href="logs/payload_analysis.log" target="_blank"
                            class="block p-4 border rounded hover:bg-gray-50">
                            <i class="fas fa-search text-purple-600 mr-2"></i>
                            Payload Analysis Log
                        </a>
                        <a href="api/cad_debug.log" target="_blank" class="block p-4 border rounded hover:bg-gray-50">
                            <i class="fas fa-share-alt text-orange-600 mr-2"></i>
                            CAD Integration Log
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Testing Modal -->
        <div id="testingModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
            <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Testing Tools</h3>
                        <button onclick="closeModal('testingModal')" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <a href="test/webhook_simulation.php" target="_blank"
                            class="block p-4 border rounded hover:bg-gray-50">
                            <i class="fas fa-satellite-dish text-blue-600 mr-2"></i>
                            Webhook Simulation
                        </a>
                        <a href="test/payload_format_comparison.php" target="_blank"
                            class="block p-4 border rounded hover:bg-gray-50">
                            <i class="fas fa-balance-scale text-green-600 mr-2"></i>
                            Format Comparison
                        </a>
                        <a href="test_log_permissions.php" target="_blank"
                            class="block p-4 border rounded hover:bg-gray-50">
                            <i class="fas fa-shield-alt text-purple-600 mr-2"></i>
                            Permission Test
                        </a>
                        <a href="test_webhook_endpoint.php" target="_blank"
                            class="block p-4 border rounded hover:bg-gray-50">
                            <i class="fas fa-globe text-orange-600 mr-2"></i>
                            Webhook Test
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documentation Modal -->
        <div id="docsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
            <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">API Documentation</h3>
                        <button onclick="closeModal('docsModal')" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="grid grid-cols-1 gap-4">
                        <a href="ref/Alert Management API v. 1.1.0.json" target="_blank"
                            class="block p-4 border rounded hover:bg-gray-50">
                            <i class="fas fa-file-code text-blue-600 mr-2"></i>
                            RapidSOS Alert Management API v1.1.0
                        </a>
                        <a href="ref/Websocket Events API v. 1.1.1.json" target="_blank"
                            class="block p-4 border rounded hover:bg-gray-50">
                            <i class="fas fa-plug text-green-600 mr-2"></i>
                            RapidSOS WebSocket Events API v1.1.1
                        </a>
                        <a href="WEBHOOK_README.md" target="_blank" class="block p-4 border rounded hover:bg-gray-50">
                            <i class="fas fa-book text-purple-600 mr-2"></i>
                            Webhook Integration Guide
                        </a>
                        <a href="WEBSOCKET_API_README.md" target="_blank"
                            class="block p-4 border rounded hover:bg-gray-50">
                            <i class="fas fa-network-wired text-orange-600 mr-2"></i>
                            WebSocket API Documentation
                        </a>
                        <a href="ref/api-docs/" target="_blank" class="block p-4 border rounded hover:bg-gray-50">
                            <i class="fas fa-crosshairs text-orange-600 mr-2"></i>
                            CFS Listener API Documentation
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="bg-gray-800 text-white py-8 mt-12">
            <div class="container mx-auto px-6 text-center">
                <p>&copy; 2026 Berkeley County IT Department. All rights reserved.</p>
                <p class="text-gray-400 mt-2">Red Five Signal Gateway - Public Safety Event Ingestion Layer</p>
            </div>
        </footer>
    </div>
    <!-- End Main Content -->

    <script>
        // Splash screen transition
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('splashScreen').classList.add('fade-out');
                setTimeout(function() {
                    document.getElementById('mainContent').classList.add('show');
                    document.getElementById('splashScreen').style.display = 'none';
                }, 800); // Wait for fade-out to complete
            }, 2500); // Show splash for 2.5 seconds
        });

        // Load system status on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadSystemStatus();
            loadQuickStats();
        });

        async function loadSystemStatus() {
            try {
                const response = await fetch('manage/subscriptions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'connection_status'
                    })
                });

                const result = await response.json();

                const statusEl = document.getElementById('systemStatus');
                if (result.connected) {
                    statusEl.innerHTML = '<span class="text-green-700">🟢 Operational - Connected to RapidSOS</span>';
                } else {
                    statusEl.innerHTML = '<span class="text-red-700">🔴 Connection Issues - ' + (result.error || 'Unknown error') + '</span>';
                }
            } catch (error) {
                document.getElementById('systemStatus').innerHTML = '<span class="text-yellow-700">⚠️ Status Check Failed</span>';
            }
        }

        async function loadQuickStats() {
            // This would connect to your actual APIs to get real stats
            // For now, showing placeholder values
            document.getElementById('alertCount').textContent = '..';
            document.getElementById('webhookStatus').textContent = 'Active';
            document.getElementById('lastAlert').textContent = '..';
            document.getElementById('cadStatus').textContent = 'Online';
        }

        function showLogsModal() {
            document.getElementById('logsModal').classList.remove('hidden');
        }

        function showTestingModal() {
            document.getElementById('testingModal').classList.remove('hidden');
        }

        function showDocsModal() {
            document.getElementById('docsModal').classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        async function checkSystemHealth() {
            // Perform comprehensive system health check
            alert('System health check initiated. Check the logs for detailed results.');

            // You could make multiple API calls here to check:
            // - RapidSOS connection
            // - Database connectivity  
            // - CAD system status
            // - Log file permissions
            // - Webhook endpoint accessibility
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = ['logsModal', 'testingModal', 'docsModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.classList.add('hidden');
                }
            });
        }
    </script>
</body>

</html>