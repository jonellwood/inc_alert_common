<?php
// Flock Safety Authentication Manager
// OAuth 2.0 client_credentials flow — mirrors rapidsos_auth.php pattern

class FlockAuth
{
    private $config;
    private $tokenFile;

    public function __construct($config)
    {
        $this->config = $config;
        $this->tokenFile = __DIR__ . '/../cache/flock_token.json';

        $cacheDir = dirname($this->tokenFile);
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
    }

    public function getAccessToken()
    {
        $cachedToken = $this->getCachedToken();
        if ($cachedToken && !$this->isTokenExpired($cachedToken)) {
            return $cachedToken['access_token'];
        }

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

        // 5-minute buffer before expiration
        return time() > ($tokenData['expires_at'] - 300);
    }

    private function refreshAccessToken()
    {
        $baseUrl = $this->config['base_urls'][$this->config['environment']];
        $tokenUrl = $baseUrl . '/oauth/token';
        $audience = $this->config['audiences'][$this->config['environment']];

        $postData = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'audience' => $audience,
        ];

        $logFile = __DIR__ . '/../logs/flock_auth.log';
        $logDir = dirname($logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents(
            $logFile,
            "[" . date('Y-m-d H:i:s') . "] Requesting token from: $tokenUrl\n" .
                "Client ID: " . $this->config['client_id'] . "\n" .
                "Audience: $audience\n\n",
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
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        file_put_contents(
            $logFile,
            "HTTP Code: $httpCode\n" .
                "Response: $response\n" .
                "cURL Error: " . ($error ?: 'None') . "\n\n",
            FILE_APPEND
        );

        if ($error) {
            throw new Exception("cURL error during Flock token refresh: $error");
        }

        if ($httpCode !== 200) {
            throw new Exception("Flock token refresh failed with HTTP $httpCode: $response");
        }

        $tokenData = json_decode($response, true);
        if (!$tokenData || !isset($tokenData['access_token'])) {
            throw new Exception("Invalid Flock token response: $response");
        }

        // Cache token with expiration (Flock returns expires_in: 86400 = 24h)
        $tokenData['expires_at'] = time() + ($tokenData['expires_in'] ?? 86400);
        $tokenData['created_at'] = time();
        file_put_contents($this->tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT));
        @chmod($this->tokenFile, 0666);

        file_put_contents(
            $logFile,
            "Token successfully obtained and cached\n" .
                "Expires in: " . ($tokenData['expires_in'] ?? 'unknown') . " seconds\n\n",
            FILE_APPEND
        );

        return $tokenData['access_token'];
    }
}
