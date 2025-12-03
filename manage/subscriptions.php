<?php
// RapidSOS Subscription Management

require_once __DIR__ . '/../config/rapidsos_config.php';
require_once __DIR__ . '/../lib/rapidsos_auth.php';

class RapidSOSSubscriptions
{
    private $auth;
    private $config;
    private $baseUrl;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/rapidsos_config.php';
        $this->auth = new RapidSOSAuth($this->config);
        // Use the webhook-specific base URL (api-sandbox.rapidsos.com)
        $this->baseUrl = $this->config['webhook_base_urls'][$this->config['environment']];
    }

    public function getConnectionStatus()
    {
        $connectionTest = $this->testConnection();

        return [
            'connected' => $connectionTest['success'],
            'client_id' => $this->config['client_id'],
            'environment' => $this->config['environment'],
            'webhook_secret_configured' => !empty($this->config['webhook_secret']) && $this->config['webhook_secret'] !== 'YOUR_WEBHOOK_SECRET_HERE',
            'token_info' => $connectionTest['token_info'] ?? null,
            'error' => $connectionTest['error'] ?? null
        ];
    }

    public function createSubscription($webhookUrl = null, $events = null)
    {
        $webhookUrl = $webhookUrl ?: $this->config['webhook_endpoint'];
        $events = $events ?: $this->config['default_events'];

        // Format according to the RapidSOS Webhooks Subscription API docs
        $subscriptionData = [
            'event_types' => $events,
            'url' => $webhookUrl
        ];

        $this->logActivity("Creating webhook subscription", $subscriptionData);

        // Use the correct endpoint from RapidSOS docs: POST /v1/webhooks/subscriptions
        return $this->makeApiCall('POST', '/v1/webhooks/subscriptions', $subscriptionData);
    }

    public function listSubscriptions()
    {
        $this->logActivity("Listing webhook subscriptions");
        // Use the correct endpoint: GET /v1/webhooks/subscriptions
        return $this->makeApiCall('GET', '/v1/webhooks/subscriptions');
    }

    public function deleteSubscription($subscriptionId)
    {
        $this->logActivity("Deleting webhook subscription", ['subscription_id' => $subscriptionId]);
        // Use the correct endpoint: DELETE /v1/webhooks/subscriptions/{id}
        return $this->makeApiCall('DELETE', "/v1/webhooks/subscriptions/{$subscriptionId}");
    }

    public function updateSubscription($subscriptionId, $data)
    {
        $this->logActivity("Updating webhook subscription", ['subscription_id' => $subscriptionId, 'data' => $data]);
        // Use the correct endpoint: PATCH /v1/webhooks/subscriptions/{id}
        return $this->makeApiCall('PATCH', "/v1/webhooks/subscriptions/{$subscriptionId}", $data);
    }

    public function testConnection()
    {
        // Test the connection by getting the token
        try {
            $token = $this->auth->getAccessToken();
            $tokenInfo = $this->auth->getTokenInfo();

            return [
                'success' => true,
                'message' => 'Successfully authenticated with RapidSOS',
                'token_info' => $tokenInfo
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function makeApiCall($method, $endpoint, $data = null)
    {
        try {
            $accessToken = $this->auth->getAccessToken();
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Authentication failed: ' . $e->getMessage()
            ];
        }

        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json'
        ];

        $this->logActivity("API Call", [
            'method' => $method,
            'url' => $url,
            'has_data' => $data !== null
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $data ? json_encode($data) : null,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $this->logActivity("API Response", [
            'http_code' => $httpCode,
            'curl_error' => $error ?: 'None',
            'response_length' => strlen($response)
        ]);

        if ($error) {
            return [
                'success' => false,
                'error' => "cURL error: $error"
            ];
        }

        $decoded = json_decode($response, true);

        return [
            'http_code' => $httpCode,
            'success' => $httpCode >= 200 && $httpCode < 300,
            'data' => $decoded,
            'raw_response' => $response,
            'error' => $httpCode >= 400 ? ($decoded['message'] ?? $decoded['error'] ?? $response) : null
        ];
    }

    private function logActivity($action, $data = [])
    {
        $logFile = __DIR__ . '/../logs/rapidsos_subscriptions.log';
        $logDir = dirname($logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'data' => $data
        ];

        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND);
    }
}

// Web interface for managing subscriptions
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
?>
    <!DOCTYPE html>
    <html>

    <head>
        <title>RapidSOS Webhook Management</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>

    <body class="bg-gray-50">
        <div class="max-w-6xl mx-auto py-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">RapidSOS Webhook Management</h1>
                <p class="text-gray-600 mt-2">Manage webhook subscriptions for emergency alert notifications</p>
            </div>

            <!-- Connection Test -->
            <div class="bg-white p-6 rounded-lg shadow mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold">Connection Status</h2>
                    <button onclick="testConnection()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        <i class="fas fa-sync-alt mr-2"></i>Test Connection
                    </button>
                </div>
                <div id="connectionStatus" class="text-gray-600">Click "Test Connection" to verify RapidSOS API access</div>
            </div>

            <!-- Current Configuration -->
            <div class="bg-blue-50 p-6 rounded-lg shadow mb-8">
                <h2 class="text-xl font-semibold text-blue-900 mb-4">Current Configuration</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <strong>Environment:</strong> <?= $GLOBALS['config']['environment'] ?? 'sandbox' ?>
                    </div>
                    <div>
                        <strong>Client ID:</strong> <?= substr($GLOBALS['config']['client_id'] ?? '', 0, 8) ?>...
                    </div>
                    <div>
                        <strong>Webhook Endpoint:</strong><br>
                        <code class="text-xs bg-white px-2 py-1 rounded"><?= $GLOBALS['config']['webhook_endpoint'] ?? 'Not configured' ?></code>
                    </div>
                    <div>
                        <strong>Target Endpoint:</strong><br>
                        <code class="text-xs bg-white px-2 py-1 rounded"><?= $GLOBALS['config']['target_endpoint'] ?? 'Not configured' ?></code>
                    </div>
                </div>
            </div>

            <!-- Create Subscription Form -->
            <div class="bg-white p-6 rounded-lg shadow mb-8">
                <h2 class="text-xl font-semibold mb-4">Create New Subscription</h2>
                <form id="createForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Webhook URL</label>
                        <input type="url" name="webhook_url"
                            value="<?= $GLOBALS['config']['webhook_endpoint'] ?? '' ?>"
                            class="w-full border rounded px-3 py-2" required>
                        <p class="text-xs text-gray-500 mt-1">This is where RapidSOS will send webhook notifications</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Events to Subscribe To</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="events[]" value="alert.new" checked class="mr-2">
                                <span>Alert New</span>
                                <span class="text-xs text-gray-500 ml-2">(New emergency alerts)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="events[]" value="alert.status_update" checked class="mr-2">
                                <span>Alert Status Update</span>
                                <span class="text-xs text-gray-500 ml-2">(Status changes to existing alerts)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="events[]" value="alert.location_update" class="mr-2">
                                <span>Alert Location Update</span>
                                <span class="text-xs text-gray-500 ml-2">(Location updates)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="events[]" value="alert.disposition_update" class="mr-2">
                                <span>Alert Disposition Update</span>
                                <span class="text-xs text-gray-500 ml-2">(Disposition changes)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="events[]" value="alert.chat" class="mr-2">
                                <span>Alert Chat</span>
                                <span class="text-xs text-gray-500 ml-2">(Chat messages)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="events[]" value="alert.milestone" class="mr-2">
                                <span>Alert Milestone</span>
                                <span class="text-xs text-gray-500 ml-2">(Milestone events)</span>
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600">
                        <i class="fas fa-plus mr-2"></i>Create Subscription
                    </button>
                </form>
            </div>

            <!-- Current Subscriptions -->
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Current Subscriptions</h2>
                    <button onclick="loadSubscriptions()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-refresh mr-2"></i>Refresh
                    </button>
                </div>
                <div id="subscriptionsList">
                    <div class="text-center text-gray-500">
                        <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                        <p>Loading subscriptions...</p>
                    </div>
                </div>
            </div>

            <!-- Logs Section -->
            <div class="bg-gray-50 p-6 rounded-lg shadow mt-8">
                <h2 class="text-xl font-semibold mb-4">Recent Activity</h2>
                <div class="text-sm text-gray-600">
                    <p>Check the server logs for detailed activity:</p>
                    <ul class="list-disc ml-6 mt-2">
                        <li><code>/logs/rapidsos_auth.log</code> - Authentication activity</li>
                        <li><code>/logs/rapidsos_subscriptions.log</code> - Subscription management</li>
                        <li><code>/webhooks/webhook_debug.log</code> - Incoming webhook events</li>
                    </ul>
                </div>
            </div>
        </div>

        <script>
            // Store config globally
            const config = <?= json_encode($GLOBALS['config'] ?? []) ?>;

            // Load subscriptions on page load
            document.addEventListener('DOMContentLoaded', function() {
                testConnection();
                loadSubscriptions();
            });

            // Test connection to RapidSOS
            async function testConnection() {
                const statusEl = document.getElementById('connectionStatus');
                statusEl.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Testing connection...';

                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'test'
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        statusEl.innerHTML = `
                        <div class="flex items-center text-green-600">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span>Connected successfully</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Token expires in ${Math.round(result.token_info.expires_in_seconds / 60)} minutes
                        </div>
                    `;
                    } else {
                        statusEl.innerHTML = `
                        <div class="flex items-center text-red-600">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span>Connection failed: ${result.error}</span>
                        </div>
                    `;
                    }
                } catch (error) {
                    statusEl.innerHTML = `
                    <div class="flex items-center text-red-600">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <span>Error: ${error.message}</span>
                    </div>
                `;
                }
            }

            // Handle form submission
            document.getElementById('createForm').addEventListener('submit', async function(e) {
                e.preventDefault();

                const formData = new FormData(e.target);
                const events = formData.getAll('events[]');

                if (events.length === 0) {
                    alert('Please select at least one event type to subscribe to.');
                    return;
                }

                const submitBtn = e.target.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
                submitBtn.disabled = true;

                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'create',
                            webhook_url: formData.get('webhook_url'),
                            events: events
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert('Subscription created successfully!');
                        loadSubscriptions();
                        e.target.reset();
                        // Reset checkboxes to default
                        document.querySelector('input[value="alert.new"]').checked = true;
                        document.querySelector('input[value="alert.status_update"]').checked = true;
                    } else {
                        alert('Error: ' + (result.error || 'Unknown error'));
                    }
                } catch (error) {
                    alert('Error: ' + error.message);
                } finally {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            });

            async function loadSubscriptions() {
                const listEl = document.getElementById('subscriptionsList');
                listEl.innerHTML = '<div class="text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</div>';

                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'list'
                        })
                    });

                    const result = await response.json();

                    if (result.success && result.data) {
                        displaySubscriptions(result.data);
                    } else {
                        listEl.innerHTML = `
                        <div class="text-red-600 text-center">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Error loading subscriptions: ${result.error || 'Unknown error'}
                        </div>
                    `;
                    }
                } catch (error) {
                    listEl.innerHTML = `
                    <div class="text-red-600 text-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Error: ${error.message}
                    </div>
                `;
                }
            }

            function displaySubscriptions(subscriptions) {
                const listEl = document.getElementById('subscriptionsList');

                if (!subscriptions || subscriptions.length === 0) {
                    listEl.innerHTML = `
                    <div class="text-center text-gray-500 py-8">
                        <i class="fas fa-inbox text-4xl mb-4"></i>
                        <p>No subscriptions found.</p>
                        <p class="text-sm">Create your first subscription above to start receiving webhooks.</p>
                    </div>
                `;
                    return;
                }

                const html = subscriptions.map(sub => {
                    // Convert timestamps to readable dates
                    const createdDate = sub.created_time ? new Date(sub.created_time).toLocaleString() : 'Unknown';
                    const updatedDate = sub.last_updated_time ? new Date(sub.last_updated_time).toLocaleString() : 'Unknown';
                    const events = sub.event_types || sub.events || [];

                    return `
                    <div class="border border-gray-200 p-4 rounded-lg mb-4">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <strong class="text-lg">Subscription ${sub.id || 'Unknown'}</strong>
                                    <span class="ml-3 px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                        Active
                                    </span>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <strong>Webhook URL:</strong><br>
                                        <code class="text-xs bg-gray-100 px-2 py-1 rounded break-all">${sub.url || 'Unknown'}</code>
                                    </div>
                                    <div>
                                        <strong>Events:</strong><br>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            ${events.map(event => 
                                                `<span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">${event}</span>`
                                            ).join('')}
                                        </div>
                                    </div>
                                    <div>
                                        <strong>Created:</strong> ${createdDate}
                                    </div>
                                    <div>
                                        <strong>Last Updated:</strong> ${updatedDate}
                                    </div>
                                </div>
                            </div>
                            <div class="ml-4">
                                <button onclick="deleteSubscription('${sub.id}')" 
                                        class="bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600 text-sm">
                                    <i class="fas fa-trash mr-1"></i>Delete
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                }).join('');

                listEl.innerHTML = html;
            }

            async function deleteSubscription(id) {
                if (!confirm('Are you sure you want to delete this subscription?\n\nThis will stop webhook notifications for this endpoint.')) {
                    return;
                }

                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'delete',
                            subscription_id: id
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert('Subscription deleted successfully!');
                        loadSubscriptions();
                    } else {
                        alert('Error: ' + (result.error || 'Unknown error'));
                    }
                } catch (error) {
                    alert('Error: ' + error.message);
                }
            }
        </script>
    </body>

    </html>
<?php
    $GLOBALS['config'] = require __DIR__ . '/../config/rapidsos_config.php';
    exit;
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        $manager = new RapidSOSSubscriptions();

        switch ($action) {
            case 'connection_status':
                $result = $manager->getConnectionStatus();
                break;

            case 'test':
                $result = $manager->testConnection();
                break;

            case 'create':
                $webhookUrl = $input['webhook_url'];
                $events = $input['events'] ?? ['alert.new', 'alert.status_update'];
                $result = $manager->createSubscription($webhookUrl, $events);
                break;

            case 'list':
                $result = $manager->listSubscriptions();
                break;

            case 'delete':
                $subscriptionId = $input['subscription_id'];
                $result = $manager->deleteSubscription($subscriptionId);
                break;

            default:
                throw new Exception('Invalid action: ' . $action);
        }

        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>