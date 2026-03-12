<?php
require_once __DIR__ . '/../lib/auth_check_admin.php';
// Flock Safety LPR Alerts — Subscription Management

require_once __DIR__ . '/../lib/flock_auth.php';

class FlockSubscriptions
{
    private $auth;
    private $config;
    private $baseUrl;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/flock_config.php';
        $this->auth = new FlockAuth($this->config);
        $this->baseUrl = $this->config['base_urls'][$this->config['environment']];
    }

    public function getConnectionStatus()
    {
        $connectionTest = $this->testConnection();

        return [
            'connected' => $connectionTest['success'],
            'client_id' => $this->config['client_id'],
            'environment' => $this->config['environment'],
            'token_info' => $connectionTest['token_info'] ?? null,
            'error' => $connectionTest['error'] ?? null
        ];
    }

    public function createSubscription($callbackUrl = null, $name = null)
    {
        $callbackUrl = $callbackUrl ?: $this->config['webhook_endpoint'];
        $name = $name ?: $this->config['subscription_name'];

        $subscriptionData = [
            'name' => $name,
            'callbackUrl' => $callbackUrl,
            'credentials' => [
                'scheme' => 'apiKey',
                'apiKey' => [
                    'key' => $this->config['webhook_api_key'],
                    'headerName' => 'X-API-Key',
                ],
            ],
            'contentType' => 'application/json',
            'customHotlistAudienceFilter' => $this->config['custom_hotlist_audience_filter'],
            'enabledFirstResponderJurisdictionAlerts' => $this->config['enable_frj_alerts'],
            'enabled' => true,
        ];

        $this->logActivity("Creating Flock subscription", $subscriptionData);

        return $this->makeApiCall('POST', '/api/v3/integrations/lpr/alerts/subscriptions', $subscriptionData);
    }

    public function listSubscriptions()
    {
        $this->logActivity("Listing Flock subscriptions");
        return $this->makeApiCall('GET', '/api/v3/integrations/lpr/alerts/subscriptions');
    }

    public function getSubscription($subscriptionId)
    {
        $this->logActivity("Getting Flock subscription", ['id' => $subscriptionId]);
        return $this->makeApiCall('GET', '/api/v3/integrations/lpr/alerts/subscriptions/' . urlencode($subscriptionId));
    }

    public function updateSubscription($subscriptionId, $data)
    {
        $this->logActivity("Updating Flock subscription", ['id' => $subscriptionId, 'data' => $data]);
        // Flock uses PUT (full replace), not PATCH
        return $this->makeApiCall('PUT', '/api/v3/integrations/lpr/alerts/subscriptions', $data);
    }

    public function deleteSubscription($subscriptionId)
    {
        $this->logActivity("Deleting Flock subscription", ['id' => $subscriptionId]);
        return $this->makeApiCall('DELETE', '/api/v3/integrations/lpr/alerts/subscriptions/' . urlencode($subscriptionId));
    }

    public function testConnection()
    {
        try {
            $token = $this->auth->getAccessToken();
            $tokenInfo = $this->auth->getTokenInfo();

            return [
                'success' => true,
                'message' => 'Successfully authenticated with Flock Safety',
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
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

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
        $logFile = __DIR__ . '/../logs/flock_subscriptions.log';
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

// ============================
// Web interface
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $config = require __DIR__ . '/../config/flock_config.php';
?>
    <!DOCTYPE html>
    <html>

    <head>
        <title>Flock Safety LPR — Subscription Management</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>

    <body class="bg-gray-50">
        <div class="max-w-6xl mx-auto py-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">
                    <i class="fas fa-camera text-indigo-600 mr-2"></i>Flock Safety LPR — Webhook Management
                </h1>
                <p class="text-gray-600 mt-2">Manage webhook subscriptions for LPR hotlist alert notifications</p>
            </div>

            <!-- Connection Status -->
            <div class="bg-white p-6 rounded-lg shadow mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold">Connection Status</h2>
                    <button onclick="testConnection()" class="bg-indigo-500 text-white px-4 py-2 rounded hover:bg-indigo-600">
                        <i class="fas fa-sync-alt mr-2"></i>Test Connection
                    </button>
                </div>
                <div id="connectionStatus" class="text-gray-600">Click "Test Connection" to verify Flock Safety API access</div>
            </div>

            <!-- Configuration -->
            <div class="bg-indigo-50 p-6 rounded-lg shadow mb-8">
                <h2 class="text-xl font-semibold text-indigo-900 mb-4">Current Configuration</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <strong>Environment:</strong> <?= htmlspecialchars($config['environment']) ?>
                    </div>
                    <div>
                        <strong>Client ID:</strong> <?= htmlspecialchars(substr($config['client_id'], 0, 8)) ?>...
                    </div>
                    <div>
                        <strong>Webhook Endpoint:</strong><br>
                        <code class="text-xs bg-white px-2 py-1 rounded"><?= htmlspecialchars($config['webhook_endpoint']) ?></code>
                    </div>
                    <div>
                        <strong>CAD Call Type:</strong>
                        <code class="text-xs bg-white px-2 py-1 rounded"><?= htmlspecialchars($config['cad_call_type']) ?></code>
                    </div>
                    <div>
                        <strong>Hotlist Filter:</strong> <?= htmlspecialchars($config['custom_hotlist_audience_filter']) ?>
                    </div>
                    <div>
                        <strong>FRJ Alerts:</strong> <?= $config['enable_frj_alerts'] ? 'Enabled' : 'Disabled' ?>
                    </div>
                </div>
            </div>

            <!-- Create Subscription -->
            <div class="bg-white p-6 rounded-lg shadow mb-8">
                <h2 class="text-xl font-semibold mb-4">Create New Subscription</h2>
                <p class="text-sm text-gray-500 mb-4">
                    <i class="fas fa-info-circle mr-1"></i>
                    Flock allows <strong>one active subscription per customer per vendor</strong>. If you already have one, delete it first.
                </p>
                <form id="createForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Subscription Name</label>
                        <input type="text" name="subscription_name"
                            value="<?= htmlspecialchars($config['subscription_name']) ?>"
                            class="w-full border rounded px-3 py-2" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Callback URL</label>
                        <input type="url" name="callback_url"
                            value="<?= htmlspecialchars($config['webhook_endpoint']) ?>"
                            class="w-full border rounded px-3 py-2" required>
                        <p class="text-xs text-gray-500 mt-1">This is where Flock will POST LPR alert events</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">Custom Hotlist Audience</label>
                            <select name="audience_filter" class="w-full border rounded px-3 py-2">
                                <option value="organization" <?= $config['custom_hotlist_audience_filter'] === 'organization' ? 'selected' : '' ?>>Organization only</option>
                                <option value="any" <?= $config['custom_hotlist_audience_filter'] === 'any' ? 'selected' : '' ?>>All (including restricted)</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center">
                                <input type="checkbox" name="enable_frj" <?= $config['enable_frj_alerts'] ? 'checked' : '' ?> class="mr-2">
                                <span class="text-sm">Enable First Responder Jurisdiction alerts</span>
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

            <!-- Logs -->
            <div class="bg-gray-50 p-6 rounded-lg shadow mt-8">
                <h2 class="text-xl font-semibold mb-4">Recent Activity</h2>
                <div class="text-sm text-gray-600">
                    <p>Check the server logs for detailed activity:</p>
                    <ul class="list-disc ml-6 mt-2">
                        <li><code>/logs/flock_auth.log</code> — Authentication activity</li>
                        <li><code>/logs/flock_subscriptions.log</code> — Subscription management</li>
                        <li><code>/logs/flock_webhook_debug.log</code> — Incoming LPR alerts</li>
                    </ul>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                testConnection();
                loadSubscriptions();
            });

            async function testConnection() {
                const el = document.getElementById('connectionStatus');
                el.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Testing connection...';

                try {
                    const res = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'test'
                        })
                    });
                    const result = await res.json();

                    if (result.success) {
                        const mins = result.token_info ? Math.round(result.token_info.expires_in_seconds / 60) : '?';
                        el.innerHTML = `
                            <div class="flex items-center text-green-600">
                                <i class="fas fa-check-circle mr-2"></i>
                                <span>Connected to Flock Safety API</span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                Token expires in ${mins} minutes
                            </div>`;
                    } else {
                        el.innerHTML = `
                            <div class="flex items-center text-red-600">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span>Connection failed: ${result.error}</span>
                            </div>`;
                    }
                } catch (err) {
                    el.innerHTML = `
                        <div class="flex items-center text-red-600">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span>Error: ${err.message}</span>
                        </div>`;
                }
            }

            document.getElementById('createForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const fd = new FormData(e.target);

                const btn = e.target.querySelector('button[type="submit"]');
                const orig = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
                btn.disabled = true;

                try {
                    const res = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'create',
                            subscription_name: fd.get('subscription_name'),
                            callback_url: fd.get('callback_url'),
                            audience_filter: fd.get('audience_filter'),
                            enable_frj: fd.has('enable_frj')
                        })
                    });
                    const result = await res.json();

                    if (result.success) {
                        alert('Subscription created successfully!');
                        loadSubscriptions();
                    } else {
                        alert('Error: ' + (result.error || 'Unknown error'));
                    }
                } catch (err) {
                    alert('Error: ' + err.message);
                } finally {
                    btn.innerHTML = orig;
                    btn.disabled = false;
                }
            });

            async function loadSubscriptions() {
                const el = document.getElementById('subscriptionsList');
                el.innerHTML = '<div class="text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</div>';

                try {
                    const res = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'list'
                        })
                    });
                    const result = await res.json();

                    if (result.success) {
                        displaySubscriptions(result.data);
                    } else {
                        el.innerHTML = `<div class="text-red-600 text-center">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Error: ${result.error || 'Unknown error'}</div>`;
                    }
                } catch (err) {
                    el.innerHTML = `<div class="text-red-600 text-center">Error: ${err.message}</div>`;
                }
            }

            function displaySubscriptions(data) {
                const el = document.getElementById('subscriptionsList');

                // Flock may return a single object or an array
                const subs = Array.isArray(data) ? data : (data ? [data] : []);

                if (subs.length === 0) {
                    el.innerHTML = `
                        <div class="text-center text-gray-500 py-8">
                            <i class="fas fa-inbox text-4xl mb-4"></i>
                            <p>No subscriptions found.</p>
                            <p class="text-sm">Create your first subscription above to start receiving LPR alerts.</p>
                        </div>`;
                    return;
                }

                el.innerHTML = subs.map(sub => {
                    const created = sub.createdAt ? new Date(sub.createdAt).toLocaleString() : 'Unknown';
                    const updated = sub.updatedAt ? new Date(sub.updatedAt).toLocaleString() : 'Unknown';
                    const enabled = sub.enabled !== false;
                    const frj = sub.enabledFirstResponderJurisdictionAlerts ? 'Yes' : 'No';
                    const filter = sub.customHotlistAudienceFilter || 'organization';

                    return `
                        <div class="border border-gray-200 p-4 rounded-lg mb-4">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <strong class="text-lg">${sub.name || 'Subscription'}</strong>
                                        <span class="ml-3 px-2 py-1 text-xs rounded-full ${enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                            ${enabled ? 'Active' : 'Disabled'}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <strong>ID:</strong><br>
                                            <code class="text-xs bg-gray-100 px-2 py-1 rounded">${sub.id || 'Unknown'}</code>
                                        </div>
                                        <div>
                                            <strong>Callback URL:</strong><br>
                                            <code class="text-xs bg-gray-100 px-2 py-1 rounded break-all">${sub.callbackUrl || 'Unknown'}</code>
                                        </div>
                                        <div><strong>Hotlist Filter:</strong> ${filter}</div>
                                        <div><strong>FRJ Alerts:</strong> ${frj}</div>
                                        <div><strong>Created:</strong> ${created}</div>
                                        <div><strong>Updated:</strong> ${updated}</div>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <button onclick="deleteSubscription('${sub.id}')"
                                            class="bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600 text-sm">
                                        <i class="fas fa-trash mr-1"></i>Delete
                                    </button>
                                </div>
                            </div>
                        </div>`;
                }).join('');
            }

            async function deleteSubscription(id) {
                if (!confirm('Delete this Flock subscription?\n\nThis will stop LPR alert webhook deliveries.')) return;

                try {
                    const res = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'delete',
                            subscription_id: id
                        })
                    });
                    const result = await res.json();

                    if (result.success) {
                        alert('Subscription deleted.');
                        loadSubscriptions();
                    } else {
                        alert('Error: ' + (result.error || 'Unknown error'));
                    }
                } catch (err) {
                    alert('Error: ' + err.message);
                }
            }
        </script>
    </body>

    </html>
<?php
    exit;
}

// ============================
// POST — JSON API actions
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        $manager = new FlockSubscriptions();

        switch ($action) {
            case 'test':
                $result = $manager->testConnection();
                break;

            case 'connection_status':
                $result = $manager->getConnectionStatus();
                break;

            case 'create':
                $callbackUrl = $input['callback_url'] ?? null;
                $name = $input['subscription_name'] ?? null;
                $result = $manager->createSubscription($callbackUrl, $name);
                break;

            case 'list':
                $result = $manager->listSubscriptions();
                break;

            case 'get':
                $result = $manager->getSubscription($input['subscription_id']);
                break;

            case 'delete':
                $result = $manager->deleteSubscription($input['subscription_id']);
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
