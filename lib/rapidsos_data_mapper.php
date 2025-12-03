<?php
// Universal RapidSOS Data Mapper
// Handles multiple payload formats and extracts consistent data structure

class RapidSOSDataMapper
{

    private $logger;

    public function __construct()
    {
        $this->logger = new PayloadLogger();
    }

    /**
     * Main entry point - converts any RapidSOS payload to standard format
     */
    public function extractAlertData($payload)
    {
        $this->logger->logPayload('incoming', $payload);

        // Identify payload format
        $format = $this->identifyFormat($payload);
        $this->logger->log("Identified format: $format");

        // Extract data based on format
        switch ($format) {
            case 'alerts_api':
                $result = $this->extractFromAlertsAPI($payload);
                break;
            case 'webhook_callflow':
                $result = $this->extractFromCallflow($payload);
                break;
            case 'direct_alert':
                $result = $this->extractFromDirectAlert($payload);
                break;
            case 'webhook_event':
                $result = $this->extractFromWebhookEvent($payload);
                break;
            default:
                $result = $this->extractGeneric($payload);
        }

        $this->logger->logPayload('extracted', $result);
        return $result;
    }

    /**
     * Identify payload format by structure
     */
    private function identifyFormat($payload)
    {
        // Alerts API format (has alerts array)
        if (isset($payload['alerts']) && is_array($payload['alerts'])) {
            return 'alerts_api';
        }

        // Webhook Callflow format (has callflow and variables)
        if (isset($payload['callflow']) && isset($payload['variables'])) {
            return 'webhook_callflow';
        }

        // Direct webhook event format (has event and data)
        if (isset($payload['event']) && isset($payload['data']) && is_string($payload['event'])) {
            return 'webhook_event';
        }

        // Direct alert format (has alert_id or source_id at root)
        if (isset($payload['alert_id']) || isset($payload['source_id'])) {
            return 'direct_alert';
        }

        return 'unknown';
    }

    /**
     * Extract from Alerts API format (alerts array)
     */
    private function extractFromAlertsAPI($payload)
    {
        $alerts = [];

        foreach ($payload['alerts'] as $alert) {
            $alerts[] = [
                'source_id' => $alert['source_id'] ?? null,
                'alert_id' => $alert['alert_id'] ?? null,
                'incident_time' => $this->parseTimestamp($alert['incident_time'] ?? null),
                'location' => $this->extractLocation($alert),
                'emergency_type' => $this->extractEmergencyType($alert),
                'description' => $this->extractDescription($alert),
                'status' => $this->extractStatus($alert),
                'contact_info' => $this->extractContactInfo($alert),
                'service_provider' => $alert['service_provider_name'] ?? null,
                'site_type' => $this->extractSiteType($alert),
                'original_payload' => $alert
            ];
        }

        return [
            'format' => 'alerts_api',
            'alerts_until' => $this->parseTimestamp($payload['alerts_until'] ?? null),
            'alerts' => $alerts
        ];
    }

    /**
     * Extract from Webhook Callflow format
     */
    private function extractFromCallflow($payload)
    {
        $variables = $payload['variables'] ?? [];

        return [
            'format' => 'webhook_callflow',
            'callflow' => $payload['callflow'] ?? null,
            'alerts' => [[
                'source_id' => $variables['flow_data']['id'] ?? null,
                'alert_id' => null,
                'incident_time' => $this->parseTimestamp($variables['flow_data']['createdTime'] ?? null),
                'location' => $this->extractLocation($variables),
                'emergency_type' => $this->extractEmergencyType($variables),
                'description' => $this->extractDescription($variables),
                'status' => null,
                'contact_info' => $this->extractContactInfo($variables),
                'service_provider' => $variables['service_provider'] ?? null,
                'permit_number' => $variables['permit_number'] ?? null,
                'site_type' => $this->extractSiteType($variables),
                'original_payload' => $payload
            ]]
        ];
    }

    /**
     * Extract from Direct Alert format
     */
    private function extractFromDirectAlert($payload)
    {
        return [
            'format' => 'direct_alert',
            'alerts' => [[
                'source_id' => $payload['source_id'] ?? null,
                'alert_id' => $payload['alert_id'] ?? null,
                'incident_time' => $this->parseTimestamp($payload['incident_time'] ?? null),
                'location' => $this->extractLocation($payload),
                'emergency_type' => $this->extractEmergencyType($payload),
                'description' => $this->extractDescription($payload),
                'status' => $this->extractStatus($payload),
                'contact_info' => $this->extractContactInfo($payload),
                'service_provider' => $payload['service_provider_name'] ?? null,
                'site_type' => $this->extractSiteType($payload),
                'original_payload' => $payload
            ]]
        ];
    }

    /**
     * Extract from Webhook Event format (event + data)
     */
    private function extractFromWebhookEvent($payload)
    {
        $data = $payload['data'] ?? [];

        return [
            'format' => 'webhook_event',
            'event_type' => $payload['event'] ?? null,
            'alerts' => [[
                'source_id' => $data['id'] ?? $data['source_id'] ?? null,
                'alert_id' => $data['alert_id'] ?? $data['id'] ?? null,
                'incident_time' => $this->parseTimestamp($data['timestamp'] ?? $payload['timestamp'] ?? null),
                'location' => $this->extractLocation($data),
                'emergency_type' => $this->extractEmergencyType($data),
                'description' => $this->extractDescription($data),
                'status' => $this->extractStatus($data),
                'contact_info' => $this->extractContactInfo($data),
                'service_provider' => $data['service_provider_name'] ?? null,
                'site_type' => $this->extractSiteType($data),
                'original_payload' => $payload
            ]]
        ];
    }

    /**
     * Generic extraction for unknown formats
     */
    private function extractGeneric($payload)
    {
        return [
            'format' => 'unknown',
            'alerts' => [[
                'source_id' => $this->findValueInPayload($payload, ['source_id', 'id']),
                'alert_id' => $this->findValueInPayload($payload, ['alert_id', 'id']),
                'incident_time' => $this->parseTimestamp($this->findValueInPayload($payload, ['incident_time', 'timestamp', 'created_time'])),
                'location' => $this->extractLocation($payload),
                'emergency_type' => $this->extractEmergencyType($payload),
                'description' => $this->extractDescription($payload),
                'status' => $this->extractStatus($payload),
                'contact_info' => $this->extractContactInfo($payload),
                'service_provider' => $this->findValueInPayload($payload, ['service_provider_name', 'service_provider']),
                'site_type' => $this->extractSiteType($payload),
                'original_payload' => $payload
            ]]
        ];
    }

    /**
     * Extract location data from multiple possible structures
     */
    private function extractLocation($payload)
    {
        // Look for location data in multiple possible locations
        $locationSources = [
            $payload['location'] ?? null,
            $payload['event']['location'] ?? null,
            $payload['variables']['event']['location'] ?? null,
            $payload['variables']['buildings'] ?? null,
            $payload['data']['location'] ?? null
        ];

        $result = [];

        foreach ($locationSources as $location) {
            if (!$location) continue;

            // Extract coordinates
            if (isset($location['geodetic'])) {
                $result['latitude'] = $location['geodetic']['latitude'];
                $result['longitude'] = $location['geodetic']['longitude'];
                $result['accuracy'] = $location['geodetic']['uncertainty_radius'] ?? null;
            } elseif (isset($location['coordinates'])) {
                $result['latitude'] = $location['coordinates']['latitude'];
                $result['longitude'] = $location['coordinates']['longitude'];
                $result['accuracy'] = $location['coordinates']['accuracy'] ?? null;
            } elseif (isset($location['latitude'])) {
                $result['latitude'] = $location['latitude'];
                $result['longitude'] = $location['longitude'];
                $result['accuracy'] = $location['accuracy'] ?? null;
            }

            // Extract civic address
            if (isset($location['civic'])) {
                $result['civic'] = $location['civic'];
            } elseif (isset($location['address'])) {
                // Transform various address formats to civic format
                $address = $location['address'];
                $result['civic'] = [
                    'name' => $address['name'] ?? null,
                    'street_1' => $address['address1'] ?? $address['street_1'] ?? $address['formatted'] ?? null,
                    'street_2' => $address['address2'] ?? $address['street_2'] ?? null,
                    'city' => $address['city'] ?? null,
                    'state' => $address['state'] ?? null,
                    'zip_code' => $address['zip'] ?? $address['zip_code'] ?? $address['postal_code'] ?? null,
                    'country' => $address['country'] ?? null
                ];
            }

            // If we found something, break
            if (!empty($result)) break;
        }

        return !empty($result) ? $result : null;
    }

    /**
     * Extract emergency type from various locations
     */
    private function extractEmergencyType($payload)
    {
        $sources = [
            $payload['emergency_type']['display_name'] ?? null,
            $payload['emergency_type'] ?? null,
            $payload['event']['emergency_type'] ?? null,
            $payload['variables']['alarm_description'] ?? null,
            $payload['variables']['event']['emergency_type'] ?? null,
            $payload['data']['emergency']['type'] ?? null,
            $payload['emergency']['type'] ?? null
        ];

        foreach ($sources as $type) {
            if ($type) return $type;
        }

        return null;
    }

    /**
     * Extract description from various locations
     */
    private function extractDescription($payload)
    {
        $sources = [
            $payload['description'] ?? null,
            $payload['event']['description'] ?? null,
            $payload['variables']['alarm_description'] ?? null,
            $payload['variables']['zone_description'] ?? null,
            $payload['variables']['event']['description'] ?? null,
            $payload['data']['emergency']['description'] ?? null,
            $payload['emergency']['description'] ?? null
        ];

        foreach ($sources as $desc) {
            if ($desc) return $desc;
        }

        return null;
    }

    /**
     * Extract status information
     */
    private function extractStatus($payload)
    {
        $statusSources = [
            $payload['status'] ?? null,
            $payload['disposition'] ?? null
        ];

        foreach ($statusSources as $status) {
            if ($status) {
                return [
                    'name' => $status['name'] ?? null,
                    'display_name' => $status['display_name'] ?? null
                ];
            }
        }

        return null;
    }

    /**
     * Extract contact information
     */
    private function extractContactInfo($payload)
    {
        $contact = [];

        // Look for phone numbers
        $phoneSources = [
            $payload['covering_psap']['phone'] ?? null,
            $payload['authorized_entity']['phone'] ?? null,
            $payload['variables']['central_station_phone'] ?? null,
            $payload['variables']['premise_phone'] ?? null,
            $payload['variables']['buildings']['contactNumber'] ?? null,
            $payload['data']['caller']['phone'] ?? null
        ];

        foreach ($phoneSources as $phone) {
            if ($phone) {
                $contact['phone'] = $phone;
                break;
            }
        }

        // Look for names
        $nameSources = [
            $payload['covering_psap']['name'] ?? null,
            $payload['authorized_entity']['name'] ?? null,
            $payload['variables']['buildings']['name'] ?? null,
            $payload['data']['caller']['name'] ?? null
        ];

        foreach ($nameSources as $name) {
            if ($name) {
                $contact['name'] = $name;
                break;
            }
        }

        return !empty($contact) ? $contact : null;
    }

    /**
     * Extract site type
     */
    private function extractSiteType($payload)
    {
        $sources = [
            $payload['site_type']['display_name'] ?? null,
            $payload['site_type']['name'] ?? null,
            $payload['site_type'] ?? null,
            $payload['event']['site_type'] ?? null,
            $payload['variables']['event']['site_type'] ?? null
        ];

        foreach ($sources as $type) {
            if ($type) return $type;
        }

        return null;
    }

    /**
     * Parse timestamp from various formats
     */
    private function parseTimestamp($timestamp)
    {
        if (!$timestamp) return null;

        // If it's already a timestamp in milliseconds
        if (is_numeric($timestamp) && $timestamp > 1000000000000) {
            return date('Y-m-d H:i:s', $timestamp / 1000);
        }

        // If it's a timestamp in seconds
        if (is_numeric($timestamp) && $timestamp > 1000000000) {
            return date('Y-m-d H:i:s', $timestamp);
        }

        // If it's an ISO string
        if (is_string($timestamp)) {
            $parsed = strtotime($timestamp);
            if ($parsed) {
                return date('Y-m-d H:i:s', $parsed);
            }
        }

        return $timestamp;
    }

    /**
     * Find a value in nested payload using multiple possible keys
     */
    private function findValueInPayload($payload, $keys)
    {
        foreach ($keys as $key) {
            if (isset($payload[$key])) {
                return $payload[$key];
            }
        }

        // Search nested arrays
        foreach ($payload as $value) {
            if (is_array($value)) {
                $found = $this->findValueInPayload($value, $keys);
                if ($found) return $found;
            }
        }

        return null;
    }
}

/**
 * Logger for payload analysis
 */
class PayloadLogger
{
    private $logFile;

    public function __construct()
    {
        $this->logFile = __DIR__ . '/../logs/payload_mapper.log';
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public function log($message)
    {
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message
        ];
        file_put_contents($this->logFile, json_encode($entry) . "\n", FILE_APPEND);
    }

    public function logPayload($type, $payload)
    {
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'payload' => $payload
        ];
        file_put_contents($this->logFile, json_encode($entry) . "\n", FILE_APPEND);
    }
}
