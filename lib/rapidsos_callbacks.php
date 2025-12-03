<?php

/**
 * RapidSOS Alert Callbacks
 * 
 * Sends status updates and dispositions back to RapidSOS after processing alerts
 * 
 * API Documentation: Alert Management API v1.1.0
 * Endpoint: PATCH https://edx-sandbox.rapidsos.com/v1/alerts/{alert_id}
 * 
 * Status Permissions:
 * - PSAP integrations can set: ACCEPTED, DECLINED
 * - PSAP integrations can set: disposition, decline_reason
 * - Central Station can set: DISPATCH_REQUESTED, IGNORED, CANCELED
 */

require_once __DIR__ . '/../config/rapidsos_config.php';
require_once __DIR__ . '/rapidsos_auth.php';

class RapidSOSCallbacks
{
    private $config;
    private $auth;
    private $baseUrl;

    public function __construct($config = null)
    {
        $this->config = $config ?? require __DIR__ . '/../config/rapidsos_config.php';
        $this->auth = new RapidSOSAuth($this->config);
        $this->baseUrl = $this->config['alert_management_base_url'];
    }

    /**
     * Accept an alert - indicates PSAP has received and accepted the alert
     * 
     * @param string $alertId RapidSOS alert ID (e.g., "alert-xxx-xxx-xxx")
     * @param string|null $cfsNumber Optional CFS/Call number from your CAD system
     * @return array Response with success status and data
     */
    public function acceptAlert($alertId, $cfsNumber = null)
    {
        $payload = [
            'status' => 'ACCEPTED'
        ];

        $note = "Alert accepted by Berkeley County 911";
        if ($cfsNumber) {
            $note .= " - CFS Number: {$cfsNumber}";
        }

        return $this->updateAlertStatus($alertId, $payload, $note);
    }

    /**
     * Set alert disposition - provides more detail about alert handling
     * 
     * Available dispositions:
     * - DISPATCHED: Units dispatched to location
     * - ENROUTE: Units en route to location
     * - ON_SCENE: Units arrived at location
     * - CLEARED_NO_REPORT: Cleared without report
     * - CLEARED_WITH_REPORT: Cleared with report filed
     * - CLOSED: Incident closed
     * - CANCELED: Alert canceled
     * - PREEMPTED: Alert preempted by another agency
     * 
     * @param string $alertId RapidSOS alert ID
     * @param string $disposition Disposition value (see list above)
     * @param string|null $cfsNumber Optional CFS/Call number
     * @return array Response with success status and data
     */
    public function setDisposition($alertId, $disposition, $cfsNumber = null)
    {
        $validDispositions = [
            'DISPATCHED',
            'ENROUTE',
            'ON_SCENE',
            'PREEMPTED',
            'CLEARED_NO_REPORT',
            'CLEARED_WITH_REPORT',
            'CLOSED',
            'CANCELED'
        ];

        $disposition = strtoupper($disposition);
        if (!in_array($disposition, $validDispositions)) {
            return [
                'success' => false,
                'error' => "Invalid disposition: {$disposition}. Must be one of: " . implode(', ', $validDispositions)
            ];
        }

        $payload = [
            'disposition' => $disposition
        ];

        $note = "Disposition updated to {$disposition}";
        if ($cfsNumber) {
            $note .= " - CFS Number: {$cfsNumber}";
        }

        return $this->updateAlertStatus($alertId, $payload, $note);
    }

    /**
     * Decline an alert - indicates PSAP cannot handle this alert
     * 
     * @param string $alertId RapidSOS alert ID
     * @param string $reason Reason for declining
     * @return array Response with success status and data
     */
    public function declineAlert($alertId, $reason)
    {
        $payload = [
            'status' => 'DECLINED',
            'decline_reason' => $reason
        ];

        return $this->updateAlertStatus($alertId, $payload, "Alert declined: {$reason}");
    }

    /**
     * Send a combined status and disposition update
     * Useful for when CAD entry is created and units dispatched in one action
     * 
     * @param string $alertId RapidSOS alert ID
     * @param string $cfsNumber CFS/Call number from CAD
     * @return array Response with success status and data
     */
    public function acceptAndDispatch($alertId, $cfsNumber)
    {
        // First accept the alert
        $acceptResponse = $this->acceptAlert($alertId, $cfsNumber);

        if (!$acceptResponse['success']) {
            return $acceptResponse;
        }

        // Then set disposition to DISPATCHED
        // Small delay to ensure status change is processed
        usleep(500000); // 0.5 seconds

        return $this->setDisposition($alertId, 'DISPATCHED', $cfsNumber);
    }

    /**
     * Core method to update alert status via PATCH request
     * 
     * @param string $alertId RapidSOS alert ID
     * @param array $payload Status/disposition data
     * @param string|null $logNote Optional note for logging
     * @return array Response with success status and data
     */
    private function updateAlertStatus($alertId, $payload, $logNote = null)
    {
        try {
            // Get OAuth token
            $token = $this->auth->getAccessToken();

            if (!$token) {
                throw new Exception('Failed to obtain OAuth token');
            }

            // Build URL
            $url = rtrim($this->baseUrl, '/') . '/v1/alerts/' . $alertId;

            // Make PATCH request
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'PATCH',
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 30
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Log the callback
            $this->logCallback([
                'timestamp' => date('Y-m-d H:i:s'),
                'alert_id' => $alertId,
                'action' => $logNote ?? 'Status update',
                'payload' => $payload,
                'http_code' => $httpCode,
                'response' => $response,
                'curl_error' => $curlError ?: null
            ]);

            $success = $httpCode >= 200 && $httpCode < 300;

            return [
                'success' => $success,
                'http_code' => $httpCode,
                'response' => $response ? json_decode($response, true) : null,
                'error' => $curlError ?: ($success ? null : "HTTP {$httpCode}")
            ];
        } catch (Exception $e) {
            $this->logCallback([
                'timestamp' => date('Y-m-d H:i:s'),
                'alert_id' => $alertId,
                'action' => 'ERROR',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Log callback activity for debugging
     * 
     * @param array $data Log entry data
     */
    private function logCallback($data)
    {
        $logFile = __DIR__ . '/../logs/rapidsos_callbacks.log';
        $logDir = dirname($logFile);

        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents(
            $logFile,
            json_encode($data, JSON_PRETTY_PRINT) . "\n---\n",
            FILE_APPEND
        );

        @chmod($logFile, 0666);
    }

    /**
     * Check if an alert_id looks valid
     * 
     * @param string $alertId Alert ID to validate
     * @return bool
     */
    public static function isValidAlertId($alertId)
    {
        // RapidSOS alert IDs follow pattern: alert-{uuid}
        return preg_match('/^alert-[a-f0-9\-]{36}$/i', $alertId) === 1;
    }
}
