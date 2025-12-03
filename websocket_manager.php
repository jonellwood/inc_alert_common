<?php
// WebSocket Client Management Interface
require_once __DIR__ . '/websocket_client.php';
require_once __DIR__ . '/config/rapidsos_config.php';

$action = $_POST['action'] ?? '';
$status = null;
$logs = [];

// Handle actions
if ($action === 'start') {
    // Start WebSocket client in background
    $command = "php " . __DIR__ . "/websocket_client.php > /dev/null 2>&1 &";
    exec($command);
    $status = "WebSocket client started in background";
} elseif ($action === 'stop') {
    // Stop WebSocket client
    exec("pkill -f 'websocket_client.php'");
    $status = "WebSocket client stopped";
} elseif ($action === 'status') {
    // Get status
    $client = new RapidSOSWebSocketClient();
    $clientStatus = $client->getStatus();
}

// Read recent logs
$logFile = __DIR__ . '/logs/websocket_client.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $logs = array_slice(explode("\n", $logContent), -50); // Last 50 lines
    $logs = array_filter($logs); // Remove empty lines
    $logs = array_reverse($logs); // Most recent first
}

// Check if process is running
$isRunning = false;
$output = [];
exec("pgrep -f 'websocket_client.php'", $output);
$isRunning = !empty($output);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebSocket Client Manager - Berkeley County Emergency Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            max-height: 400px;
        }

        .log-line {
            border-bottom: 1px solid #374151;
            padding: 0.25rem 0;
        }

        .log-info {
            color: #60a5fa;
        }

        .log-warning {
            color: #fbbf24;
        }

        .log-error {
            color: #f87171;
        }
    </style>
    <script>
        // Auto-refresh every 10 seconds
        setTimeout(function() {
            location.reload();
        }, 10000);
    </script>
</head>

<body class="bg-gray-50">
    <!-- Header -->
    <div class="gradient-bg text-white py-8 mb-8">
        <div class="container mx-auto px-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-4xl font-bold mb-2">
                        <i class="fas fa-satellite-dish mr-3"></i>
                        WebSocket Client Manager
                    </h1>
                    <p class="text-blue-100 text-lg">RapidSOS Real-time Event Streaming</p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-blue-100 mb-1">Status Check</div>
                    <div class="text-lg font-semibold"><?php echo date('M d, Y H:i:s'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-6">
        <?php if ($status): ?>
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-blue-700"><?php echo htmlspecialchars($status); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Status Panel -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-6">
                <i class="fas fa-heartbeat mr-2 <?php echo $isRunning ? 'text-green-500' : 'text-red-500'; ?>"></i>
                WebSocket Client Status
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-<?php echo $isRunning ? 'green' : 'red'; ?>-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-<?php echo $isRunning ? 'green' : 'red'; ?>-600">
                        <?php echo $isRunning ? 'RUNNING' : 'STOPPED'; ?>
                    </div>
                    <div class="text-sm text-<?php echo $isRunning ? 'green' : 'red'; ?>-700">Connection Status</div>
                </div>

                <div class="bg-blue-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-blue-600">
                        <?php echo $isRunning ? 'ACTIVE' : 'INACTIVE'; ?>
                    </div>
                    <div class="text-sm text-blue-700">Event Listener</div>
                </div>

                <div class="bg-purple-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-purple-600">
                        <?php echo count($logs); ?>
                    </div>
                    <div class="text-sm text-purple-700">Recent Log Entries</div>
                </div>

                <div class="bg-orange-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-orange-600">
                        <?php echo $isRunning ? 'AUTO' : 'MANUAL'; ?>
                    </div>
                    <div class="text-sm text-orange-700">Refresh Mode</div>
                </div>
            </div>

            <!-- Controls -->
            <div class="flex space-x-4">
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="start">
                    <button type="submit" <?php echo $isRunning ? 'disabled' : ''; ?>
                        class="bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white font-bold py-2 px-6 rounded-lg transition-colors duration-200">
                        <i class="fas fa-play mr-2"></i>
                        Start WebSocket Client
                    </button>
                </form>

                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="stop">
                    <button type="submit" <?php echo !$isRunning ? 'disabled' : ''; ?>
                        class="bg-red-600 hover:bg-red-700 disabled:bg-gray-400 text-white font-bold py-2 px-6 rounded-lg transition-colors duration-200">
                        <i class="fas fa-stop mr-2"></i>
                        Stop WebSocket Client
                    </button>
                </form>

                <button onclick="location.reload()"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors duration-200">
                    <i class="fas fa-refresh mr-2"></i>
                    Refresh Status
                </button>
            </div>
        </div>

        <!-- Configuration Info -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-cogs text-blue-500 mr-2"></i>
                Configuration & Event Types
            </h2>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <h3 class="font-semibold text-gray-800 mb-3">WebSocket Connection</h3>
                    <div class="bg-gray-50 p-4 rounded text-sm">
                        <div><strong>Environment:</strong> <?php echo $config['environment'] ?? 'sandbox'; ?></div>
                        <div><strong>Endpoint:</strong> wss://ws.edx-sandbox.rapidsos.com/v1</div>
                        <div><strong>Authentication:</strong> OAuth 2.0 Bearer Token</div>
                        <div><strong>Auto-reconnect:</strong> Yes (5 attempts)</div>
                    </div>
                </div>

                <div>
                    <h3 class="font-semibold text-gray-800 mb-3">Subscribed Event Types</h3>
                    <div class="bg-gray-50 p-4 rounded text-sm">
                        <ul class="space-y-1">
                            <li>• <strong>alert.new</strong> - New emergency alerts</li>
                            <li>• <strong>alert.status_update</strong> - Status changes</li>
                            <li>• <strong>alert.location_update</strong> - Location updates</li>
                            <li>• <strong>alert.disposition_update</strong> - Disposition changes</li>
                            <li>• <strong>alert.chat</strong> - Chat messages</li>
                            <li>• <strong>alert.milestone</strong> - Milestone events</li>
                            <li>• <strong>alert.multi_trip_signal</strong> - Multi-trip signals</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Logs -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-terminal text-green-500 mr-2"></i>
                Live WebSocket Logs
                <span class="text-sm font-normal text-gray-500">(Auto-refreshes every 10 seconds)</span>
            </h2>

            <?php if (empty($logs)): ?>
                <div class="text-gray-500 text-center py-8">
                    <i class="fas fa-info-circle text-4xl mb-4"></i>
                    <p>No logs available yet. Start the WebSocket client to see activity.</p>
                </div>
            <?php else: ?>
                <div class="code-block">
                    <?php foreach ($logs as $log): ?>
                        <?php
                        $logClass = 'log-info';
                        if (strpos($log, '[ERROR]') !== false) $logClass = 'log-error';
                        elseif (strpos($log, '[WARNING]') !== false) $logClass = 'log-warning';
                        ?>
                        <div class="log-line <?php echo $logClass; ?>">
                            <?php echo htmlspecialchars($log); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Integration Status -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-link text-purple-500 mr-2"></i>
                Integration Pipeline Status
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h3 class="font-semibold text-green-800 mb-2">
                        <i class="fas fa-check-circle mr-2"></i>
                        WebSocket → Database
                    </h3>
                    <p class="text-green-700 text-sm">
                        Events received via WebSocket are processed through your existing data mapper and stored in the emergency alerts database.
                    </p>
                </div>

                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h3 class="font-semibold text-green-800 mb-2">
                        <i class="fas fa-check-circle mr-2"></i>
                        Database → CAD Integration
                    </h3>
                    <p class="text-green-700 text-sm">
                        Stored alerts are automatically forwarded to your CAD system using the existing Southern Software integration.
                    </p>
                </div>

                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h3 class="font-semibold text-green-800 mb-2">
                        <i class="fas fa-check-circle mr-2"></i>
                        Dashboard Updates
                    </h3>
                    <p class="text-green-700 text-sm">
                        Real-time alerts appear in your Vue.js dashboard alongside webhook-received alerts for complete visibility.
                    </p>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="text-center py-8">
            <a href="index.html" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors duration-200 mr-4">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Dashboard
            </a>
            <a href="view/index.html" class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200 mr-4">
                <i class="fas fa-chart-line mr-2"></i>
                View Live Alerts
            </a>
            <a href="manage/subscriptions.php" class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200">
                <i class="fas fa-webhook mr-2"></i>
                Manage Webhooks
            </a>
        </div>
    </div>
</body>

</html>