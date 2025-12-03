<?php
// Test RapidSOS Emergency Data Session API
require_once __DIR__ . '/config/rapidsos_config.php';
require_once __DIR__ . '/lib/rapidsos_auth.php';

class RapidSOSSessionTest
{
    private $config;
    private $auth;

    public function __construct()
    {
        $this->config = require __DIR__ . '/config/rapidsos_config.php';
        $this->auth = new RapidSOSAuth($this->config);
    }

    public function testSessionEndpoint()
    {
        $accessToken = $this->auth->getAccessToken();
        if (!$accessToken) {
            echo "Failed to get access token\n";
            return;
        }

        // Test the session endpoint mentioned by the dev
        $sessionUrl = 'https://api-sandbox.rapidsos.com/v2/emergency-data/session';

        echo "Testing session endpoint: $sessionUrl\n";
        echo "Access token: " . substr($accessToken, 0, 20) . "...\n\n";

        // Test GET request to see available sessions
        $this->testGetSessions($sessionUrl, $accessToken);

        // Test POST request to create a session
        $this->testCreateSession($sessionUrl, $accessToken);
    }

    private function testGetSessions($url, $token)
    {
        echo "=== Testing GET sessions with query params ===\n";

        // Try with query parameter as the error suggests
        $urlWithQuery = $url . '?query=active';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $urlWithQuery,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'User-Agent: Berkeley-County-Emergency-Services/1.0'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        echo "URL: $urlWithQuery\n";
        echo "HTTP Code: $httpCode\n";
        if ($error) {
            echo "CURL Error: $error\n";
        }
        echo "Response: " . ($response ?: 'No response') . "\n\n";
    }

    private function testCreateSession($url, $token)
    {
        echo "=== Testing POST with minimal required fields ===\n";

        // Try with just the query parameter that seems required
        $sessionData = [
            'query' => 'create'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($sessionData),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'User-Agent: Berkeley-County-Emergency-Services/1.0'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        echo "HTTP Code: $httpCode\n";
        if ($error) {
            echo "CURL Error: $error\n";
        }
        echo "Response: " . ($response ?: 'No response') . "\n\n";

        // Also try as query parameter instead of body
        echo "=== Testing POST with query parameter ===\n";
        $urlWithQuery = $url . '?query=create';

        $ch2 = curl_init();
        curl_setopt_array($ch2, [
            CURLOPT_URL => $urlWithQuery,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'User-Agent: Berkeley-County-Emergency-Services/1.0'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response2 = curl_exec($ch2);
        $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        $error2 = curl_error($ch2);
        curl_close($ch2);

        echo "URL: $urlWithQuery\n";
        echo "HTTP Code: $httpCode2\n";
        if ($error2) {
            echo "CURL Error: $error2\n";
        }
        echo "Response: " . ($response2 ?: 'No response') . "\n\n";
    }
}

// Run the test
echo "RapidSOS Emergency Data Session API Test\n";
echo "========================================\n\n";

$test = new RapidSOSSessionTest();
$test->testSessionEndpoint();
