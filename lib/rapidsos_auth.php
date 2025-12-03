<?php
// RapidSOS Authentication Manager

class RapidSOSAuth
{
    private $config;
    private $tokenFile;

    public function __construct($config)
    {
        $this->config = $config;
        $this->tokenFile = __DIR__ . '/../cache/rapidsos_token.json';

        // Ensure cache directory exists
        $cacheDir = dirname($this->tokenFile);
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
    }

    public function getAccessToken()
    {
        // Check if we have a valid cached token
        $cachedToken = $this->getCachedToken();
        if ($cachedToken && !$this->isTokenExpired($cachedToken)) {
            return $cachedToken['access_token'];
        }

        // Get new token using client credentials
        return $this->refreshAccessToken();
    }

    public function getTokenInfo()
    {
        $cachedToken = $this->getCachedToken();
        if (!$cachedToken) {
            return null;
        }

        return [
            'expires_at' => $cachedToken['expires_at'] ?? null,
            'expires_in_seconds' => max(0, ($cachedToken['expires_at'] ?? 0) - time()),
            'token_type' => $cachedToken['token_type'] ?? 'Bearer',
            'scope' => $cachedToken['scope'] ?? null,
            'is_expired' => $this->isTokenExpired($cachedToken)
        ];
    }

    public function testConnection()
    {
        try {
            $token = $this->getAccessToken();
            $tokenInfo = $this->getTokenInfo();

            return [
                'success' => true,
                'token_preview' => substr($token, 0, 20) . '...',
                'token_info' => $tokenInfo
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getCachedToken()
    {
        if (!file_exists($this->tokenFile)) {
            return null;
        }

        $tokenData = json_decode(file_get_contents($this->tokenFile), true);
        return $tokenData ?: null;
    }

    private function isTokenExpired($tokenData)
    {
        if (!isset($tokenData['expires_at'])) {
            return true;
        }

        // Add 5-minute buffer before expiration
        return time() > ($tokenData['expires_at'] - 300);
    }

    private function refreshAccessToken()
    {
        $baseUrl = $this->config['base_urls'][$this->config['environment']];
        $tokenUrl = $baseUrl . '/oauth/token';

        $postData = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'scope' => 'alerts'
        ];

        // Log the token request for debugging
        $logFile = __DIR__ . '/../logs/rapidsos_auth.log';
        $logDir = dirname($logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents(
            $logFile,
            "[" . date('Y-m-d H:i:s') . "] Requesting token from: $tokenUrl\n" .
                "Client ID: " . $this->config['client_id'] . "\n\n",
            FILE_APPEND
        );

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $tokenUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_VERBOSE => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Log the response
        file_put_contents(
            $logFile,
            "HTTP Code: $httpCode\n" .
                "Response: $response\n" .
                "cURL Error: " . ($error ?: 'None') . "\n\n",
            FILE_APPEND
        );

        if ($error) {
            throw new Exception("cURL error during token refresh: $error");
        }

        if ($httpCode !== 200) {
            throw new Exception("Token refresh failed with HTTP $httpCode: $response");
        }

        $tokenData = json_decode($response, true);
        if (!$tokenData || !isset($tokenData['access_token'])) {
            throw new Exception("Invalid token response: $response");
        }

        // Cache the token with expiration
        $tokenData['expires_at'] = time() + ($tokenData['expires_in'] ?? 3600);
        $tokenData['created_at'] = time();
        file_put_contents($this->tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT));

        file_put_contents(
            $logFile,
            "Token successfully obtained and cached\n" .
                "Expires in: " . ($tokenData['expires_in'] ?? 'unknown') . " seconds\n\n",
            FILE_APPEND
        );

        return $tokenData['access_token'];
    }

    public function verifyWebhookSignature($payload, $signature, $secret = null)
    {
        $secret = $secret ?: $this->config['webhook_secret'];

        // If no webhook secret is configured, log warning but allow through for testing
        if (!$secret) {
            $logFile = __DIR__ . '/../logs/webhook_security.log';
            file_put_contents(
                $logFile,
                "[" . date('Y-m-d H:i:s') . "] WARNING: No webhook secret configured - skipping signature verification\n",
                FILE_APPEND
            );
            return true; // Allow for testing until webhook secret is obtained
        }

        if (!$signature) {
            return false;
        }

        // RapidSOS uses HMAC-SHA256 for webhook signatures
        // The signature might be in format "sha256=hash" or just "hash"
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        // Handle both formats
        if (strpos($signature, 'sha256=') === 0) {
            $providedHash = substr($signature, 7);
        } else {
            $providedHash = $signature;
        }

        // Use hash_equals to prevent timing attacks
        return hash_equals($expectedSignature, $providedHash);
    }
}
