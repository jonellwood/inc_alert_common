<?php

declare(strict_types=1);

/**
 * Map ASAP transformed payloads to Southern Software Generic CFS Listener fields.
 * Input: output of transform_apco() plus a record/correlation ID.
 * Output: associative array ready to JSON-encode for the CAD API.
 */
function map_asap_to_cad(array $asap, string $recordId): array
{
    $loc = $asap['service_location'] ?? [];
    $street = $loc['street'] ?? [];
    $event = $asap['event'] ?? [];
    $subscriber = $asap['subscriber'] ?? [];
    $contacts = $asap['contacts']['subscriber'] ?? [];

    $callerName = trim(implode(' ', array_filter([
        $subscriber['given'] ?? null,
        $subscriber['middle'] ?? null,
        $subscriber['surname'] ?? null,
    ]))) ?: null;

    $callerPhone = null;
    if (is_array($contacts) && count($contacts) > 0) {
        $callerPhone = $contacts[0];
    }

    $streetNameParts = array_filter([
        $street['name'] ?? null,
        $street['category'] ?? null,
        $street['postdirectional'] ?? null,
    ]);
    $streetName = $streetNameParts ? implode(' ', $streetNameParts) : null;

    $notes = [];
    $notes[] = 'Alarm Type: ' . ($asap['alarm_type'] ?? 'Unknown');
    $notes[] = 'Event Details: ' . ($event['details'] ?? 'N/A');
    $notes[] = 'Dispatch Agency: ' . (($event['dispatch_agency']['id'] ?? '') . ' ' . ($event['dispatch_agency']['name'] ?? ''));
    $notes[] = 'Monitoring Station: ' . (($asap['monitoring_station']['id'] ?? '') . ' ' . ($asap['monitoring_station']['name'] ?? ''));
    $notes[] = 'Permit: ' . (($event['permit']['id'] ?? '') . ' ' . ($event['permit']['category'] ?? ''));
    $notes[] = 'Audible: ' . ($event['audible_description'] ?? 'Unknown');
    $notes[] = 'Confirmation: ' . ($event['confirmation_text'] ?? '');
    $notes[] = 'Confirmation URI: ' . ($event['confirmation_uri'] ?? '');
    $notes[] = 'Sensor Details: ' . ($event['sensor_details'] ?? '');
    $notes[] = 'Call To Premise: ' . ($event['call_to_premise_text'] ?? '');

    return [
        // Interface metadata
        'InterfaceRecordID' => $recordId,

        // Core call info
        'CallTypeAlias' => $asap['alarm_type'] ?? $event['type'] ?? 'ALARM', // tune to your CAD config
        'CFSStartWhen' => $event['datetime'] ?? null,

        // Location
        'IncStreetNum' => isset($street['number']) ? (int) $street['number'] : null,
        'IncPreDir' => $street['predirectional'] ?? null,
        'IncStreetName' => $streetName,
        'IncAptLoc' => $loc['unit'] ?? null,
        'IncCommunity' => $loc['city'] ?? null,

        // Caller/contact
        'CallerName' => $callerName,
        'CallerPhone' => $callerPhone,

        // Coordinates (XCoor = longitude, YCoor = latitude)
        'XCoor' => isset($loc['map']['lon_text']) ? (float) $loc['map']['lon_text'] : null,
        'YCoor' => isset($loc['map']['lat_text']) ? (float) $loc['map']['lat_text'] : null,

        // Comments/notes
        'Comment' => $event['details'] ?? null,
        'CFSNote' => implode("\n", array_filter($notes)),
    ];
}
