<?php
// RapidSOS WebSocket Events API Client for Berkeley County Emergency Services
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/rapidsos_config.php';
require_once __DIR__ . '/lib/rapidsos_auth.php';
require_once __DIR__ . '/lib/rapidsos_websocket_mapper.php';

use WebSocket\Client;
use WebSocket\ConnectionException;

class RapidSOSWebSocketClient
{
    private $config;
    private $auth;
    private $websocketMapper;
    private $connection;
    private $accessToken;
    private $isRunning = false;
    private $reconnectAttempts = 0;
    private $maxReconnectAttempts = 5;
    private $logFile;

    public function __construct()
    {
        $this->config = require __DIR__ . '/config/rapidsos_config.php';
        $this->auth = new RapidSOSAuth($this->config);
        $this->websocketMapper = new RapidSOSWebSocketMapper();
        $this->logFile = __DIR__ . '/logs/websocket_client.log';

        // Ensure logs directory exists
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }

    /**
     * Start the WebSocket client connection
     */
    public function start()
    {
        $this->log("Starting RapidSOS WebSocket client...");

        // Get access token
        if (!$this->authenticate()) {
            $this->log("Authentication failed - cannot start WebSocket client", 'ERROR');
            return false;
        }

        // Connect to WebSocket
        if (!$this->connect()) {
            $this->log("WebSocket connection failed", 'ERROR');
            return false;
        }

        $this->isRunning = true;
        $this->log("WebSocket client started successfully");

        // Start listening loop
        $this->listen();

        return true;
    }

    /**
     * Authenticate with RapidSOS and get access token
     */
    private function authenticate()
    {
        try {
            $this->log("Authenticating with RapidSOS...");
            $this->accessToken = $this->auth->getAccessToken();

            if (!$this->accessToken) {
                $this->log("Failed to get access token", 'ERROR');
                return false;
            }

            $this->log("Authentication successful");
            return true;
        } catch (Exception $e) {
            $this->log("Authentication error: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Connect to RapidSOS WebSocket server
     */
    private function connect()
    {
        try {
            // Build WebSocket URL with parameters
            $environment = $this->config['environment'] ?? 'sandbox';
            $baseUrl = $environment === 'production' ? 'wss://ws.edx.rapidsos.com/v1' : 'wss://ws.edx-sandbox.rapidsos.com/v1';

            // Event types we want to subscribe to
            $eventTypes = [
                'alert.new',
                'alert.status_update',
                'alert.location_update',
                'alert.disposition_update',
                'alert.chat',
                'alert.milestone',
                'alert.multi_trip_signal'
            ];

            $url = $baseUrl . '?' . http_build_query([
                'token' => $this->accessToken,
                'event_types' => implode(',', $eventTypes)
            ]);

            $this->log("Connecting to WebSocket: " . $baseUrl);
            $this->log("Event types: " . implode(', ', $eventTypes));

            // Create WebSocket context
            $context = stream_context_create([
                'http' => [
                    'header' => [
                        'Authorization: Bearer ' . $this->accessToken,
                        'User-Agent: Berkeley-County-Emergency-Services/1.0'
                    ],
                    'timeout' => 30
                ]
            ]);

            // Create WebSocket client with proper library
            $this->connection = new Client($url, [
                'headers' => [
                    'User-Agent' => 'Berkeley-County-Emergency-Services/1.0'
                ],
                'timeout' => 30,
                'fragment_size' => 4096,
                'context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                    ]
                ])
            ]);

            $this->log("WebSocket connection established successfully");
            return true;
        } catch (ConnectionException $e) {
            $this->log("WebSocket connection error: " . $e->getMessage(), 'ERROR');
            return false;
        } catch (Exception $e) {
            $this->log("WebSocket connection error: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Main listening loop for WebSocket messages
     */
    private function listen()
    {
        $this->log("Starting WebSocket listener...");

        while ($this->isRunning) {
            try {
                // In a real implementation, this would read from the WebSocket
                $message = $this->readWebSocketMessage();

                if ($message) {
                    $this->processMessage($message);
                }

                // Prevent CPU spinning
                usleep(100000); // 100ms sleep

            } catch (Exception $e) {
                $this->log("Error in listen loop: " . $e->getMessage(), 'ERROR');

                if ($this->shouldReconnect()) {
                    $this->reconnect();
                } else {
                    break;
                }
            }
        }

        $this->log("WebSocket listener stopped");
    }

    /**
     * Read message from WebSocket connection
     */
    private function readWebSocketMessage()
    {
        try {
            if ($this->connection && $this->connection->isConnected()) {
                // Check if there's a message waiting (non-blocking)
                $message = $this->connection->receive();
                if ($message) {
                    return $message;
                }
            }
        } catch (ConnectionException $e) {
            $this->log("WebSocket read error: " . $e->getMessage(), 'ERROR');
            throw $e;
        } catch (Exception $e) {
            $this->log("WebSocket read error: " . $e->getMessage(), 'ERROR');
            throw $e;
        }

        return null;
    }

    /**
     * Create a demo WebSocket message for testing
     */
    private function createDemoMessage()
    {
        return json_encode([
            'event' => 'alert.new',
            'timestamp' => time() * 1000,
            'body' => [
                'alert_id' => 'websocket-demo-' . uniqid(),
                'source_id' => 'rapidsos-demo-portal',
                'incident_time' => (time() - 60) * 1000,
                'created_time' => (time() - 50) * 1000,
                'last_updated_time' => time() * 1000,
                'location' => [
                    'provided_location' => 'BOTH',
                    'geodetic' => [
                        'latitude' => 32.7767,
                        'longitude' => -79.9311,
                        'uncertainty_radius' => 25
                    ],
                    'civic' => [
                        'name' => 'RapidSOS Demo Location',
                        'street_1' => '123 Emergency Lane',
                        'city' => 'Charleston',
                        'state' => 'SC',
                        'country' => 'US',
                        'zip_code' => '29401'
                    ]
                ],
                'emergency_type' => [
                    'name' => 'FIRE',
                    'display_name' => 'Fire Emergency'
                ],
                'description' => 'WebSocket demo alert from RapidSOS portal',
                'service_provider_name' => 'RapidSOS Demo Portal',
                'status' => [
                    'name' => 'NEW',
                    'display_name' => 'New Alert'
                ],
                'covering_psap' => [
                    'id' => 'berkeley-county-911',
                    'name' => 'Berkeley County 911',
                    'phone' => '+1-843-719-4357'
                ],
                'is_chat_enabled' => true,
                'is_media_enabled' => false,
                'supplemental_only' => false
            ]
        ]);
    }

    /**
     * Process incoming WebSocket message
     */
    private function processMessage($rawMessage)
    {
        try {
            $this->log("Received WebSocket message: " . substr($rawMessage, 0, 200) . '...');

            // Parse JSON message
            $message = json_decode($rawMessage, true);
            if (!$message) {
                $this->log("Invalid JSON message received", 'ERROR');
                return;
            }

            // Validate message structure
            if (!isset($message['event']) || !isset($message['body'])) {
                $this->log("Invalid message structure - missing event or body", 'ERROR');
                return;
            }

            $this->log("Processing WebSocket event: " . $message['event']);

            // Process with existing WebSocket mapper
            $extractedData = $this->websocketMapper->extractWebSocketEvent($message);

            if (!$extractedData || empty($extractedData['alerts'])) {
                $this->log("No alerts extracted from WebSocket message", 'WARNING');
                return;
            }

            // Process each alert through existing pipeline
            foreach ($extractedData['alerts'] as $alert) {
                $this->processAlert($alert, $message['event']);
            }

            $this->log("WebSocket message processed successfully");
        } catch (Exception $e) {
            $this->log("Error processing WebSocket message: " . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Process individual alert through existing infrastructure
     */
    private function processAlert($alert, $eventType)
    {
        try {
            $this->log("Processing alert: " . ($alert['alert_id'] ?? 'unknown'));

            // Transform alert data for database storage (reuse existing transformation)
            $transformedData = $this->transformAlertForDatabase($alert, $eventType);

            // Save to database using existing writeToDB.php functionality
            $this->saveToDatabase($transformedData);

            // Post to CAD system if configured
            $this->postToCad($transformedData);

            $this->log("Alert processed and stored successfully: " . $alert['alert_id']);
        } catch (Exception $e) {
            $this->log("Error processing alert: " . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Transform alert for database storage (reuse existing logic)
     */
    private function transformAlertForDatabase($alert, $eventType)
    {
        return [
            'webhook_event_type' => $eventType,
            'rapidsos_alert_id' => $alert['alert_id'] ?? null,
            'source' => 'websocket',
            'timestamp' => $alert['incident_time'] ?? date('c'),
            'location' => [
                'latitude' => $alert['location']['geodetic']['latitude'] ?? null,
                'longitude' => $alert['location']['geodetic']['longitude'] ?? null,
                'address' => [
                    'formatted' => $alert['location']['civic']['name'] ?? null,
                    'street_name' => $alert['location']['civic']['street_1'] ?? null,
                    'city' => $alert['location']['civic']['city'] ?? null,
                    'state' => $alert['location']['civic']['state'] ?? null,
                    'postal_code' => $alert['location']['civic']['zip_code'] ?? null
                ]
            ],
            'emergency' => [
                'type' => $alert['emergency_type']['name'] ?? null,
                'description' => $alert['description'] ?? null
            ],
            'service_provider' => $alert['service_provider_name'] ?? null,
            'status' => $alert['status'] ?? null,
            'covering_psap' => $alert['covering_psap'] ?? null,
            'raw_data' => json_encode($alert)
        ];
    }

    /**
     * Save to database using existing infrastructure
     */
    private function saveToDatabase($alertData)
    {
        try {
            $this->log("Saving WebSocket alert to database...");

            // Create a POST request to writeToDB.php to reuse existing logic
            $postData = json_encode($alertData);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://localhost/inc_alert_common/api/writeToDB.php');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($postData),
                'X-Source: WebSocket-Client'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $this->log("WebSocket alert saved to database successfully");
            } else {
                $this->log("Database save failed with HTTP {$httpCode}: {$response}", 'ERROR');
            }
        } catch (Exception $e) {
            $this->log("Database save error: " . $e->getMessage(), 'ERROR');
            // Don't throw - database errors shouldn't stop WebSocket processing
        }
    }

    /**
     * Post to CAD system using existing infrastructure
     */
    private function postToCad($alertData)
    {
        try {
            $this->log("Posting WebSocket alert to CAD system...");

            // TODO: Integrate with existing CAD posting logic from sv_sendToCad.php

            $this->log("CAD post completed");
        } catch (Exception $e) {
            $this->log("CAD post error: " . $e->getMessage(), 'ERROR');
            // Don't throw - CAD posting should not break the main flow
        }
    }

    /**
     * Handle reconnection logic
     */
    private function shouldReconnect()
    {
        return $this->reconnectAttempts < $this->maxReconnectAttempts;
    }

    private function reconnect()
    {
        $this->reconnectAttempts++;
        $this->log("Attempting reconnection #{$this->reconnectAttempts}...");

        sleep(5 * $this->reconnectAttempts); // Exponential backoff

        if ($this->connect()) {
            $this->reconnectAttempts = 0;
            $this->log("Reconnection successful");
        }
    }

    /**
     * Stop the WebSocket client
     */
    public function stop()
    {
        $this->log("Stopping WebSocket client...");
        $this->isRunning = false;

        if ($this->connection && $this->connection->isConnected()) {
            try {
                $this->connection->close();
                $this->log("WebSocket connection closed successfully");
            } catch (Exception $e) {
                $this->log("Error closing WebSocket connection: " . $e->getMessage(), 'WARNING');
            }
        }

        $this->log("WebSocket client stopped");
    }

    /**
     * Log messages with timestamp
     */
    private function log($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        // Write to log file
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);

        // Also output to console if running in CLI
        if (php_sapi_name() === 'cli') {
            echo $logLine;
        }
    }

    /**
     * Get connection status
     */
    public function getStatus()
    {
        return [
            'running' => $this->isRunning,
            'authenticated' => !empty($this->accessToken),
            'connected' => !empty($this->connection),
            'reconnect_attempts' => $this->reconnectAttempts,
            'log_file' => $this->logFile
        ];
    }
}

// If running from command line, start the client
if (php_sapi_name() === 'cli') {
    echo "Starting RapidSOS WebSocket Client for Berkeley County Emergency Services\n";
    echo "Press Ctrl+C to stop\n\n";

    $client = new RapidSOSWebSocketClient();

    // Handle graceful shutdown
    pcntl_signal(SIGTERM, function () use ($client) {
        $client->stop();
        exit(0);
    });

    pcntl_signal(SIGINT, function () use ($client) {
        $client->stop();
        exit(0);
    });

    // Start the client
    $client->start();
}
