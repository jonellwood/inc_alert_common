<?php
require_once __DIR__ . '/../lib/auth_check_admin.php';
// RapidSOS EDX Integration Management
// Based on official RapidSOS Postman collections

require_once __DIR__ . '/../lib/rapidsos_auth.php';

class RapidSOSIntegrationManager
{
    private $config;
    private $auth;
    private $baseUrl;
    private $logFile;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/rapidsos_config.php';
        $this->auth = new RapidSOSAuth($this->config);

        // EDX Integration API uses edx-sandbox (not api-sandbox)
        $this->baseUrl = $this->config['base_urls'][$this->config['environment']];
        $this->logFile = __DIR__ . '/../logs/rapidsos_integrations.log';

        // Ensure logs directory exists
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }

    /**
     * Create a new webhook integration
     * This will return EDX client credentials to use for WebSocket auth
     */
    public function createIntegration($rsosAgencyId)
    {
        $integrationData = [
            'rsosClientId' => $this->config['client_id'],
            'rsosClientSecret' => $this->config['client_secret'],
            'rsosAgencyId' => $rsosAgencyId
        ];

        $this->log("Creating webhook integration", $integrationData);

        return $this->makeApiCall('POST', '/v1/integrations', $integrationData);
    }

    /**
     * List all webhook integrations
     */
    public function listIntegrations($limit = 10, $nextToken = '')
    {
        $queryParams = http_build_query([
            'limit' => $limit,
            'nextToken' => $nextToken
        ]);

        $this->log("Listing webhook integrations", ['limit' => $limit, 'nextToken' => $nextToken]);

        return $this->makeApiCall('GET', '/v1/integrations?' . $queryParams);
    }

    /**
     * Get a specific integration (includes EDX client secret)
     */
    public function getIntegration($webhookId)
    {
        $this->log("Getting webhook integration", ['webhookId' => $webhookId]);

        return $this->makeApiCall('GET', "/v1/integrations/{$webhookId}");
    }

    /**
     * Update integration event types
     */
    public function updateIntegration($webhookId, $eventTypes)
    {
        $updateData = [
            'eventTypes' => $eventTypes
        ];

        $this->log("Updating webhook integration", ['webhookId' => $webhookId, 'eventTypes' => $eventTypes]);

        return $this->makeApiCall('PATCH', "/v1/integrations/{$webhookId}", $updateData);
    }

    /**
     * Delete a webhook integration
     */
    public function deleteIntegration($webhookId)
    {
        $this->log("Deleting webhook integration", ['webhookId' => $webhookId]);

        return $this->makeApiCall('DELETE', "/v1/integrations/{$webhookId}");
    }

    /**
     * Make an API call to RapidSOS
     */
    private function makeApiCall($method, $endpoint, $data = null)
    {
        $accessToken = $this->auth->getAccessToken();
        if (!$accessToken) {
            return [
                'success' => false,
                'error' => 'Failed to obtain access token'
            ];
        }

        $url = $this->baseUrl . $endpoint;

        $this->log("API Call", [
            'method' => $method,
            'url' => $url,
            'has_data' => !is_null($data)
        ]);

        $ch = curl_init();

        $curlOpts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ];

        if ($data && in_array($method, ['POST', 'PATCH', 'PUT'])) {
            $curlOpts[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($ch, $curlOpts);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $this->log("API Response", [
            'http_code' => $httpCode,
            'curl_error' => $curlError ?: 'None',
            'response_length' => strlen($response)
        ]);

        if ($curlError) {
            return [
                'success' => false,
                'error' => $curlError,
                'http_code' => $httpCode
            ];
        }

        $responseData = json_decode($response, true);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'data' => $responseData,
            'raw_response' => $response
        ];
    }

    /**
     * Log activity
     */
    private function log($action, $data = [])
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'data' => $data
        ];

        file_put_contents(
            $this->logFile,
            json_encode($logEntry) . "\n",
            FILE_APPEND
        );
    }
}

// If accessed directly, provide a simple interface
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Content-Type: application/json');

    $manager = new RapidSOSIntegrationManager();
    $action = $_GET['action'] ?? 'list';

    switch ($action) {
        case 'list':
            $result = $manager->listIntegrations();
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;

        case 'create':
            // You'll need to provide your agency ID
            $agencyId = $_GET['agency_id'] ?? null;
            if (!$agencyId) {
                echo json_encode(['error' => 'agency_id parameter required']);
                exit;
            }
            $result = $manager->createIntegration($agencyId);
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;

        case 'get':
            $webhookId = $_GET['webhook_id'] ?? null;
            if (!$webhookId) {
                echo json_encode(['error' => 'webhook_id parameter required']);
                exit;
            }
            $result = $manager->getIntegration($webhookId);
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;

        case 'update':
            $webhookId = $_GET['webhook_id'] ?? null;
            if (!$webhookId) {
                echo json_encode(['error' => 'webhook_id parameter required']);
                exit;
            }
            $eventTypes = [
                'alert.new',
                'alert.status_update',
                'alert.disposition_update',
                'alert.location_update',
                'alert.chat',
                'alert.milestone',
                'alert.multi_trip_signal'
            ];
            $result = $manager->updateIntegration($webhookId, $eventTypes);
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;

        case 'delete':
            $webhookId = $_GET['webhook_id'] ?? null;
            if (!$webhookId) {
                echo json_encode(['error' => 'webhook_id parameter required']);
                exit;
            }
            $result = $manager->deleteIntegration($webhookId);
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
    }
}
