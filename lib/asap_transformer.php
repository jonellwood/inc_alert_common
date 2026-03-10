<?php

declare(strict_types=1);

/**
 * ASAP/APCO XML Transformer
 *
 * Extracts structured data from APCO/TMA ASAP alarm XML payloads.
 * All fields are optional and null when absent; arrays may be empty.
 *
 * Based on the APCO ASAP-to-PSAP standard (Automated Secure Alarm Protocol).
 */
function transform_apco(SimpleXMLElement $xml): array
{
    $ns = [
        'apco-alarm' => 'http://www.apcointl.com/new/commcenter911/external-alarm.xsd',
        'nc' => 'http://niem.gov/niem/niem-core/2.0',
        'em' => 'http://niem.gov/niem/domains/emergencyManagement/2.0',
        'j' => 'http://niem.gov/niem/domains/jxdm/4.0',
        's' => 'http://niem.gov/niem/structures/2.0',
    ];

    foreach ($ns as $prefix => $uri) {
        $xml->registerXPathNamespace($prefix, $uri);
    }

    $pick = function (array $paths) use ($xml): ?string {
        foreach ($paths as $path) {
            $nodes = $xml->xpath($path);
            if ($nodes && isset($nodes[0])) {
                $value = trim((string) $nodes[0]);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return null;
    };

    $pickAttr = function (array $paths) use ($xml): ?string {
        foreach ($paths as $path) {
            $nodes = $xml->xpath($path);
            if ($nodes && isset($nodes[0])) {
                $value = trim((string) $nodes[0]);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return null;
    };

    $pickAll = function (array $paths) use ($xml): array {
        $results = [];
        foreach ($paths as $path) {
            $nodes = $xml->xpath($path);
            if ($nodes) {
                foreach ($nodes as $node) {
                    $value = trim((string) $node);
                    if ($value !== '') {
                        $results[] = $value;
                    }
                }
            }
        }
        return $results;
    };

    // Core event context
    $eventId = $pick([
        '//apco-alarm:AlarmEvent/nc:ActivityIdentification/nc:IdentificationID',
        '//apco-alarm:AlarmIdentifier',
        '//apco-alarm:AlarmEventNumber',
        '//*[local-name()="AlarmIdentifier"]',
        '//*[local-name()="AlarmEventNumber"]',
    ]);

    $alarmType = $pick([
        '//apco-alarm:AlarmEvent/em:AlarmEventCategoryText',
        '//apco-alarm:AlarmEvent/nc:ActivityCategoryText',
        '//apco-alarm:AlarmTypeText',
        '//*[local-name()="AlarmTypeText"]',
    ]);

    return [
        'alarm_version' => $pickAttr(['//apco-alarm:AlarmPayload/@alarmVersion']),
        'event_id' => $eventId,
        'alarm_type' => $alarmType,
        'event' => [
            'id' => $eventId,
            'category' => $pick(['//apco-alarm:AlarmEvent/nc:ActivityCategoryText']),
            'type' => $pick(['//apco-alarm:AlarmEvent/em:AlarmEventCategoryText']),
            'location_category' => $pick(['//apco-alarm:AlarmEvent/em:AlarmEventLocationCategoryText']),
            'details' => $pick(['//apco-alarm:AlarmEvent/em:AlarmEventDetailsText']),
            'privacy_bypass_code' => $pick(['//apco-alarm:AlarmEvent/em:AlarmEventCallPrivacyBypassCode']),
            'datetime' => $pick(['//apco-alarm:AlarmEvent/nc:ActivityDate/nc:DateTime']),
            'permit' => [
                'id' => $pick(['//apco-alarm:AlarmEvent/em:AlarmEventPermit/em:PermitIdentification/nc:IdentificationID']),
                'category' => $pick(['//apco-alarm:AlarmEvent/em:AlarmEventPermit/em:PermitCategoryText']),
            ],
            'dispatch_agency' => [
                'id' => $pick(['//apco-alarm:AlarmEvent/em:AlarmEventDispatchAgency/nc:OrganizationIdentification/nc:IdentificationID']),
                'name' => $pick(['//apco-alarm:AlarmEvent/em:AlarmEventDispatchAgency/nc:OrganizationName']),
            ],
            'audible_description' => $pick(['//apco-alarm:AlarmEvent/apco-alarm:AlarmEventAugmentation/apco-alarm:AlarmAudibleDescriptionText']),
            'confirmation_text' => $pick(['//apco-alarm:AlarmEvent/apco-alarm:AlarmEventAugmentation/apco-alarm:AlarmConfirmationText']),
            'confirmation_uri' => $pick(['//apco-alarm:AlarmEvent/apco-alarm:AlarmEventAugmentation/apco-alarm:AlarmConfirmationURI']),
            'sensor_details' => $pick(['//apco-alarm:AlarmEvent/apco-alarm:AlarmEventAugmentation/apco-alarm:BuildingSensorDetailsText']),
            'call_to_premise_text' => $pick(['//apco-alarm:AlarmEvent/apco-alarm:AlarmEventAugmentation/apco-alarm:CallToPremiseText']),
        ],
        'monitoring_station' => [
            'id' => $pick(['//apco-alarm:AlarmMonitoringStation/nc:OrganizationIdentification/nc:IdentificationID']),
            'name' => $pick(['//apco-alarm:AlarmMonitoringStation/nc:OrganizationName']),
            'operator_id' => $pick(['//apco-alarm:AlarmMonitoringStation/apco-alarm:AlarmMonitoringStationAugmentation/nc:PersonCurrentEmploymentAssociation/nc:EmployeeIdentification/nc:IdentificationID']),
            'source_id' => $pick(['//apco-alarm:AlarmMonitoringStation/apco-alarm:AlarmMonitoringStationAugmentation/nc:SourceIDText']),
        ],
        'service_location' => [
            'address_full' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationAddress/nc:AddressFullText']),
            'building' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationAddress/nc:StructuredAddress/nc:AddressBuildingText']),
            'unit' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationAddress/nc:StructuredAddress/nc:AddressSecondaryUnitText']),
            'street' => [
                'number' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationAddress/nc:StructuredAddress/nc:LocationStreet/nc:StreetNumberText']),
                'predirectional' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationAddress/nc:StructuredAddress/nc:LocationStreet/nc:StreetPredirectionalText']),
                'name' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationAddress/nc:StructuredAddress/nc:LocationStreet/nc:StreetName']),
                'category' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationAddress/nc:StructuredAddress/nc:LocationStreet/nc:StreetCategoryText']),
                'postdirectional' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationAddress/nc:StructuredAddress/nc:LocationStreet/nc:StreetPostdirectionalText']),
            ],
            'city' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationAddress/nc:StructuredAddress/nc:LocationCityName']),
            'county' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationAddress/nc:StructuredAddress/nc:LocationCountyName']),
            'state' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationAddress/nc:StructuredAddress/nc:LocationStateName']),
            'postal_code' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationAddress/nc:StructuredAddress/nc:LocationPostalCode']),
            'altitude' => [
                'value' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationAltitudeMeasure/nc:MeasurePointValue']),
                'unit' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationAltitudeMeasure/nc:MeasureUnitText']),
            ],
            'cross_street' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationCrossStreet/nc:CrossStreetDescriptionText']),
            'description' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationDescriptionText']),
            'map' => [
                'lat_text' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationMapLocation/nc:MapHorizontalCoordinateText']),
                'lon_text' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationMapLocation/nc:MapVerticalCoordinateText']),
            ],
            'name' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationName']),
            'geo' => [
                'datum' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationTwoDimensionalGeographicCoordinate/nc:GeographicDatumCode']),
                'lat_deg' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationTwoDimensionalGeographicCoordinate/nc:GeographicCoordinateLatitude/nc:LatitudeDegreeValue']),
                'lat_min' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationTwoDimensionalGeographicCoordinate/nc:GeographicCoordinateLatitude/nc:LatitudeMinuteValue']),
                'lat_sec' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationTwoDimensionalGeographicCoordinate/nc:GeographicCoordinateLatitude/nc:LatitudeSecondValue']),
                'lon_deg' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationTwoDimensionalGeographicCoordinate/nc:GeographicCoordinateLongitude/nc:LongitudeDegreeValue']),
                'lon_min' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationTwoDimensionalGeographicCoordinate/nc:GeographicCoordinateLongitude/nc:LongitudeMinuteValue']),
                'lon_sec' => $pick(['//apco-alarm:AlarmServiceLocation/nc:LocationTwoDimensionalGeographicCoordinate/nc:GeographicCoordinateLongitude/nc:LongitudeSecondValue']),
            ],
            'directions' => $pick(['//apco-alarm:AlarmServiceLocation/em:AlarmEventLocationAugmentation/em:LocationDirectionsText']),
            'info' => $pick(['//apco-alarm:AlarmServiceLocation/em:AlarmEventLocationAugmentation/em:LocationInformationText']),
            'capture_time' => $pick(['//apco-alarm:AlarmServiceLocation/apco-alarm:AlarmServiceLocationAugmentation/apco-alarm:LocationCaptureDateTime']),
        ],
        'service_organization' => [
            'id' => $pick(['//apco-alarm:AlarmServiceOrganization/nc:OrganizationIdentification/nc:IdentificationID']),
            'name' => $pick(['//apco-alarm:AlarmServiceOrganization/nc:OrganizationName']),
            'phone' => $pick(['//apco-alarm:AlarmServiceOrganization/nc:OrganizationPrimaryContactInformation/nc:ContactTelephoneNumber//nc:TelephoneNumberFullID']),
        ],
        'contacts' => [
            'operator' => $pickAll(['//nc:ContactInformation[@s:id="cxt1"]/nc:ContactTelephoneNumber//nc:TelephoneNumberFullID']),
            'subscriber' => $pickAll(['//nc:ContactInformation[@s:id="cxt2"]/nc:ContactTelephoneNumber//nc:TelephoneNumberFullID']),
        ],
        'subscriber' => [
            'given' => $pick(['//apco-alarm:Subscriber/nc:RoleOfPersonReference/../nc:PersonReference/../nc:Person/nc:PersonGivenName', '//nc:Person[@s:id="per1"]/nc:PersonName/nc:PersonGivenName']),
            'middle' => $pick(['//nc:Person[@s:id="per1"]/nc:PersonName/nc:PersonMiddleName']),
            'surname' => $pick(['//nc:Person[@s:id="per1"]/nc:PersonName/nc:PersonSurName']),
        ],
        'vehicle' => [
            'color' => $pick(['//nc:Vehicle/nc:ConveyanceColorPrimaryText']),
            'style_code' => $pick(['//nc:Vehicle/nc:VehicleStyleCode']),
            'plate_id' => $pick(['//nc:Vehicle/nc:ConveyanceRegistrationPlateIdentification/nc:IdentificationID']),
            'plate_source' => $pick(['//nc:Vehicle/nc:ConveyanceRegistrationPlateIdentification/nc:IdentificationSourceText']),
            'make_code' => $pick(['//nc:Vehicle/nc:VehicleMakeCode']),
            'model_code' => $pick(['//nc:Vehicle/nc:VehicleModelCode']),
            'vin' => $pick(['//nc:Vehicle/nc:VehicleVINAText']),
        ],
        'raw_namespaces' => $xml->getDocNamespaces(true),
    ];
}
