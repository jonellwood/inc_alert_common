<?php
// Official RapidSOS WebSocket Events API v1.1.1 Data Mapper
// Based on official OpenAPI specification

require_once __DIR__ . '/rapidsos_data_mapper.php'; // For PayloadLogger class

class RapidSOSWebSocketMapper
{

    private $logger;

    public function __construct()
    {
        $this->logger = new PayloadLogger();
    }

    /**
     * Extract alert data from official WebSocket Events API format
     */
    public function extractWebSocketEvent($payload)
    {
        $this->logger->logPayload('websocket_event', $payload);

        // Validate WebSocket event structure
        if (!isset($payload['event']) || !isset($payload['body'])) {
            throw new Exception('Invalid WebSocket event format - missing event or body');
        }

        $eventType = $payload['event'];
        $body = $payload['body'];
        $timestamp = $payload['timestamp'] ?? null;

        $this->logger->log("Processing WebSocket event: $eventType");

        switch ($eventType) {
            case 'alert.new':
                return $this->extractAlertNew($body, $timestamp);

            case 'alert.status_update':
                return $this->extractAlertStatusUpdate($body, $timestamp);

            case 'alert.location_update':
                return $this->extractAlertLocationUpdate($body, $timestamp);

            case 'alert.disposition_update':
                return $this->extractAlertDispositionUpdate($body, $timestamp);

            case 'alert.chat':
                return $this->extractAlertChat($body, $timestamp);

            case 'alert.milestone':
                return $this->extractAlertMilestone($body, $timestamp);

            case 'alert.multi_trip_signal':
                return $this->extractAlertMultiTripSignal($body, $timestamp);

            default:
                throw new Exception("Unsupported WebSocket event type: $eventType");
        }
    }

    /**
     * Extract alert.new event (primary alert creation)
     */
    private function extractAlertNew($body, $timestamp)
    {
        return [
            'format' => 'websocket_alert_new',
            'event_type' => 'alert.new',
            'event_timestamp' => $this->parseTimestamp($timestamp),
            'alerts' => [[
                'alert_id' => $body['alert_id'] ?? null,
                'source_id' => $body['source_id'] ?? null,
                'incident_time' => $this->parseTimestamp($body['incident_time'] ?? null),
                'created_time' => $this->parseTimestamp($body['created_time'] ?? null),
                'last_updated_time' => $this->parseTimestamp($body['last_updated_time'] ?? null),

                // Location data (official structure)
                'location' => $this->extractLocationData($body['location'] ?? null),

                // Emergency information
                'emergency_type' => $this->extractEmergencyType($body['emergency_type'] ?? null),
                'description' => $body['description'] ?? null,
                'site_type' => $this->extractSiteType($body['site_type'] ?? null),

                // Service provider info
                'service_provider' => $body['service_provider_name'] ?? null,

                // Status information
                'status' => $this->extractStatus($body['status'] ?? null),

                // PSAP and entity info
                'covering_psap' => $this->extractPSAPInfo($body['covering_psap'] ?? null),
                'authorized_entity' => $this->extractEntityInfo($body['authorized_entity'] ?? null),

                // Timing information
                'dispatch_requested_time' => $this->parseTimestamp($body['dispatch_requested_time'] ?? null),
                'sla_expiration_time' => $this->parseTimestamp($body['sla_expiration_time'] ?? null),
                'covering_psap_first_viewed_time' => $this->parseTimestamp($body['covering_psap_first_viewed_time'] ?? null),

                // Additional flags
                'is_chat_enabled' => $body['is_chat_enabled'] ?? false,
                'is_media_enabled' => $body['is_media_enabled'] ?? false,
                'supplemental_only' => $body['supplemental_only'] ?? false,

                // Followers
                'followers' => $body['followers'] ?? [],

                // Location history
                'location_history' => $this->extractLocationHistory($body['location_history'] ?? []),

                // Original payload for debugging
                'original_payload' => $body
            ]]
        ];
    }

    /**
     * Extract alert.status_update event
     */
    private function extractAlertStatusUpdate($body, $timestamp)
    {
        return [
            'format' => 'websocket_status_update',
            'event_type' => 'alert.status_update',
            'event_timestamp' => $this->parseTimestamp($timestamp),
            'alert_id' => $body['alert_id'] ?? null,
            'status' => $this->extractStatus($body['status'] ?? null),
            'operator_info' => $this->extractOperatorInfo($body),
            'created_at' => $this->parseTimestamp($body['created_at'] ?? null),
            'original_payload' => $body
        ];
    }

    /**
     * Extract alert.location_update event
     */
    private function extractAlertLocationUpdate($body, $timestamp)
    {
        return [
            'format' => 'websocket_location_update',
            'event_type' => 'alert.location_update',
            'event_timestamp' => $this->parseTimestamp($timestamp),
            'alert_id' => $body['alert_id'] ?? null,
            'location' => [
                'provided_location' => $body['provided_location'] ?? null,
                'geodetic' => $this->extractGeodeticData($body),
                'civic' => $this->extractCivicData($body)
            ],
            'created_at' => $this->parseTimestamp($body['created_at'] ?? null),
            'original_payload' => $body
        ];
    }

    /**
     * Extract alert.disposition_update event
     */
    private function extractAlertDispositionUpdate($body, $timestamp)
    {
        return [
            'format' => 'websocket_disposition_update',
            'event_type' => 'alert.disposition_update',
            'event_timestamp' => $this->parseTimestamp($timestamp),
            'alert_id' => $body['alert_id'] ?? null,
            'disposition' => $this->extractDisposition($body['disposition'] ?? null),
            'operator_info' => $this->extractOperatorInfo($body),
            'created_at' => $this->parseTimestamp($body['created_at'] ?? null),
            'original_payload' => $body
        ];
    }

    /**
     * Extract alert.chat event
     */
    private function extractAlertChat($body, $timestamp)
    {
        return [
            'format' => 'websocket_chat',
            'event_type' => 'alert.chat',
            'event_timestamp' => $this->parseTimestamp($timestamp),
            'alert_id' => $body['alert_id'] ?? null,
            'message' => $body['message'] ?? null,
            'operator_info' => $this->extractOperatorInfo($body),
            'created_at' => $this->parseTimestamp($body['created_at'] ?? null),
            'original_payload' => $body
        ];
    }

    /**
     * Extract alert.milestone event
     */
    private function extractAlertMilestone($body, $timestamp)
    {
        return [
            'format' => 'websocket_milestone',
            'event_type' => 'alert.milestone',
            'event_timestamp' => $this->parseTimestamp($timestamp),
            'alert_id' => $body['alert_id'] ?? null,
            'message' => $body['message'] ?? null,
            'message_type' => $body['message_type'] ?? null,
            'operator_info' => $this->extractOperatorInfo($body),
            'created_at' => $this->parseTimestamp($body['created_at'] ?? null),
            'original_payload' => $body
        ];
    }

    /**
     * Extract alert.multi_trip_signal event
     */
    private function extractAlertMultiTripSignal($body, $timestamp)
    {
        return [
            'format' => 'websocket_multi_trip_signal',
            'event_type' => 'alert.multi_trip_signal',
            'event_timestamp' => $this->parseTimestamp($timestamp),
            'alert_id' => $body['alert_id'] ?? null,
            'message' => $body['message'] ?? null,
            'message_type' => $body['message_type'] ?? null,
            'operator_info' => $this->extractOperatorInfo($body),
            'created_at' => $this->parseTimestamp($body['created_at'] ?? null),
            'original_payload' => $body
        ];
    }

    /**
     * Extract location data according to official schema
     */
    private function extractLocationData($location)
    {
        if (!$location) return null;

        return [
            'provided_location' => $location['provided_location'] ?? null, // CIVIC|GEODETIC|BOTH
            'geodetic' => $this->extractGeodeticData($location),
            'civic' => $this->extractCivicData($location)
        ];
    }

    /**
     * Extract geodetic coordinates according to official schema
     */
    private function extractGeodeticData($data)
    {
        $geodetic = $data['geodetic'] ?? $data;

        if (!isset($geodetic['latitude']) && !isset($geodetic['longitude'])) {
            return null;
        }

        return [
            'latitude' => $geodetic['latitude'] ?? null,
            'longitude' => $geodetic['longitude'] ?? null,
            'uncertainty_radius' => $geodetic['uncertainty_radius'] ?? null
        ];
    }

    /**
     * Extract civic address according to official schema
     */
    private function extractCivicData($data)
    {
        $civic = $data['civic'] ?? $data;

        if (!isset($civic['street_1']) && !isset($civic['city']) && !isset($civic['name'])) {
            return null;
        }

        return [
            'name' => $civic['name'] ?? null,
            'street_1' => $civic['street_1'] ?? null,
            'street_2' => $civic['street_2'] ?? null,
            'city' => $civic['city'] ?? null,
            'state' => $civic['state'] ?? null,
            'country' => $civic['country'] ?? null,
            'zip_code' => $civic['zip_code'] ?? null
        ];
    }

    /**
     * Extract emergency type information
     */
    private function extractEmergencyType($emergencyType)
    {
        if (!$emergencyType) return null;

        return [
            'name' => $emergencyType['name'] ?? null,
            'display_name' => $emergencyType['display_name'] ?? null
        ];
    }

    /**
     * Extract site type information
     */
    private function extractSiteType($siteType)
    {
        if (!$siteType) return null;

        return [
            'name' => $siteType['name'] ?? null,
            'display_name' => $siteType['display_name'] ?? null
        ];
    }

    /**
     * Extract status information
     */
    private function extractStatus($status)
    {
        if (!$status) return null;

        return [
            'name' => $status['name'] ?? null,
            'display_name' => $status['display_name'] ?? null,
            'sla_expiration_time' => $this->parseTimestamp($status['sla_expiration_time'] ?? null)
        ];
    }

    /**
     * Extract disposition information
     */
    private function extractDisposition($disposition)
    {
        if (!$disposition) return null;

        return [
            'name' => $disposition['name'] ?? null,
            'display_name' => $disposition['display_name'] ?? null
        ];
    }

    /**
     * Extract PSAP information
     */
    private function extractPSAPInfo($psap)
    {
        if (!$psap) return null;

        return [
            'id' => $psap['id'] ?? null,
            'name' => $psap['name'] ?? null,
            'phone' => $psap['phone'] ?? null
        ];
    }

    /**
     * Extract authorized entity information
     */
    private function extractEntityInfo($entity)
    {
        if (!$entity) return null;

        return [
            'id' => $entity['id'] ?? null,
            'name' => $entity['name'] ?? null,
            'phone' => $entity['phone'] ?? null
        ];
    }

    /**
     * Extract operator information from update events
     */
    private function extractOperatorInfo($body)
    {
        return [
            'sender' => $body['sender'] ?? null,
            'sender_id' => $body['sender_id'] ?? null,
            'entity_display_name' => $body['entity_display_name'] ?? null,
            'entity_id' => $body['entity_id'] ?? null,
            'message_time' => $this->parseTimestamp($body['message_time'] ?? null)
        ];
    }

    /**
     * Extract location history
     */
    private function extractLocationHistory($history)
    {
        if (!is_array($history)) return [];

        $locations = [];
        foreach ($history as $location) {
            $locations[] = [
                'provided_location' => $location['provided_location'] ?? null,
                'geodetic' => $this->extractGeodeticData($location),
                'civic' => $this->extractCivicData($location)
            ];
        }

        return $locations;
    }

    /**
     * Parse timestamp from milliseconds to readable format
     */
    private function parseTimestamp($timestamp)
    {
        if (!$timestamp) return null;

        // Convert milliseconds to seconds if needed
        if (is_numeric($timestamp) && $timestamp > 1000000000000) {
            return date('Y-m-d H:i:s', $timestamp / 1000);
        }

        if (is_numeric($timestamp) && $timestamp > 1000000000) {
            return date('Y-m-d H:i:s', $timestamp);
        }

        if (is_string($timestamp)) {
            $parsed = strtotime($timestamp);
            if ($parsed) {
                return date('Y-m-d H:i:s', $parsed);
            }
        }

        return $timestamp;
    }
}

/**
 * Emergency Type Constants from Official API
 */
class RapidSOSEmergencyTypes
{
    const BURGLARY = 'BURGLARY';
    const TEST_BURGLARY = 'TEST_BURGLARY';
    const HOLDUP = 'HOLDUP';
    const TEST_HOLDUP = 'TEST_HOLDUP';
    const SILENT_ALARM = 'SILENT_ALARM';
    const TEST_SILENT_ALARM = 'TEST_SILENT_ALARM';
    const CRASH = 'CRASH';
    const TEST_CRASH = 'TEST_CRASH';
    const MEDICAL = 'MEDICAL';
    const TEST_MEDICAL = 'TEST_MEDICAL';
    const FIRE = 'FIRE';
    const TEST_FIRE = 'TEST_FIRE';
    const CO = 'CO';
    const TEST_CO = 'TEST_CO';
    const OTHER = 'OTHER';
    const TEST_OTHER = 'TEST_OTHER';
    const ACTIVE_ASSAILANT = 'ACTIVE_ASSAILANT';
    const TEST_ACTIVE_ASSAILANT = 'TEST_ACTIVE_ASSAILANT';
    const MOBILE_PANIC = 'MOBILE_PANIC';
    const TEST_MOBILE_PANIC = 'TEST_MOBILE_PANIC';
    const TRAIN_DERAILMENT = 'TRAIN_DERAILMENT';
    const TEST_TRAIN_DERAILMENT = 'TEST_TRAIN_DERAILMENT';
}

/**
 * Status Constants from Official API
 */
class RapidSOSStatusTypes
{
    const NEW = 'NEW';
    const IGNORED = 'IGNORED';
    const DISPATCH_REQUESTED = 'DISPATCH_REQUESTED';
    const ACCEPTED = 'ACCEPTED';
    const DECLINED = 'DECLINED';
    const TIMEOUT = 'TIMEOUT';
    const CANCELED = 'CANCELED';
}
