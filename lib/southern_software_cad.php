<?php

/**
 * Southern Software CAD Integration Client
 * 
 * Handles all communication with the Southern Software CFS Listener API.
 * Supports: POST /CFS (create), PATCH /CFSLocation, PATCH /CFSCallType,
 *           POST /CFSNote, POST /CFSPerson, POST /CFSVehicle, POST /CFSPathAttach
 */

class SouthernSoftwareCAD
{
    private $baseUrl = 'http://10.19.1.52:32001/CAD/CIM/AlarmCallTest';
    private $apiKey = 'Y9YcKfeq+r+dRmluJyk0u+5ZeQOG53gDPYWowHLzYUE=';
    private $logFile;

    public function __construct()
    {
        $this->logFile = __DIR__ . '/../logs/southern_software_cad.log';
    }

    /**
     * PATCH /CFSLocation — Update location on an existing CFS
     */
    public function updateLocation($cfsNumber, $locationData)
    {
        $payload = [
            'CFSNumber' => $cfsNumber,
            'CreateBy' => 'REDFIVE',
        ];

        // Parse street address into components
        if (!empty($locationData['street_1'])) {
            $parts = self::parseStreetAddress($locationData['street_1']);
            if ($parts['number']) $payload['StreetNum'] = $parts['number'];
            if ($parts['name']) $payload['StreetName'] = $parts['name'];
            if ($parts['pre_dir']) $payload['PreDir'] = $parts['pre_dir'];
            if ($parts['apt']) $payload['AptLoc'] = $parts['apt'];
        }

        if (!empty($locationData['street_2'])) {
            $payload['AptLoc'] = $locationData['street_2'];
        }

        if (!empty($locationData['city'])) $payload['Community'] = $locationData['city'];
        if (!empty($locationData['state'])) $payload['State'] = $locationData['state'];

        // Coordinates
        if (isset($locationData['longitude'])) $payload['XCoor'] = (float)$locationData['longitude'];
        if (isset($locationData['latitude'])) $payload['YCoor'] = (float)$locationData['latitude'];
        if (isset($locationData['altitude'])) $payload['ZCoor'] = (float)$locationData['altitude'];

        return $this->sendRequest('PATCH', '/CFSLocation/', $payload);
    }

    /**
     * PATCH /CFSCallType — Update call type on an existing CFS
     */
    public function updateCallType($cfsNumber, $callTypeAlias, $inProgress = null)
    {
        $payload = [
            'CFSNumber' => $cfsNumber,
            'CallTypeAlias' => $callTypeAlias,
            'CreateBy' => 'REDFIVE',
        ];

        if ($inProgress !== null) {
            $payload['InProgress'] = $inProgress;
        }

        return $this->sendRequest('PATCH', '/CFSCallType/', $payload);
    }

    /**
     * POST /CFSNote — Add a note to an existing CFS
     */
    public function addNote($cfsNumber, $note, $createBy = 'REDFIVE')
    {
        $payload = [
            'CFSNumber' => $cfsNumber,
            'Note' => substr($note, 0, 1000), // SS max is 1000 chars
            'CreateBy' => $createBy,
        ];

        return $this->sendRequest('POST', '/CFSNote/', $payload);
    }

    /**
     * POST /CFSPerson — Add a person record to an existing CFS
     */
    public function addPerson($cfsNumber, $personData)
    {
        $payload = [
            'CFSNumber' => $cfsNumber,
            'CreateBy' => 'REDFIVE',
        ];

        $fieldMap = [
            'involvement_type' => 'InvolvementType',
            'last_name' => 'LastName',
            'first_name' => 'FirstName',
            'middle_name' => 'MiddleName',
            'phone' => 'Phone',
            'description' => 'PersonDescriptoin', // SS spelling
        ];

        foreach ($fieldMap as $src => $dest) {
            if (!empty($personData[$src])) {
                $payload[$dest] = $personData[$src];
            }
        }

        return $this->sendRequest('POST', '/CFSPerson/', $payload);
    }

    /**
     * POST /CFSVehicle — Add a vehicle record to an existing CFS
     */
    public function addVehicle($cfsNumber, $vehicleData)
    {
        $payload = [
            'CFSNumber' => $cfsNumber,
            'CreateBy' => 'REDFIVE',
        ];

        $fieldMap = [
            'make' => 'Make',
            'model' => 'Model',
            'color' => 'Color',
            'plate_number' => 'Tag',
            'plate_state' => 'TagState',
            'vin' => 'VIN',
            'year' => 'VehicleYear',
            'description' => 'VehicleDescription',
        ];

        foreach ($fieldMap as $src => $dest) {
            if (!empty($vehicleData[$src])) {
                $payload[$dest] = $vehicleData[$src];
            }
        }

        return $this->sendRequest('POST', '/CFSVehicle/', $payload);
    }

    /**
     * POST /CFSPathAttach — Attach a URL/path to an existing CFS
     */
    public function addAttachment($cfsNumber, $description, $path)
    {
        $payload = [
            'CFSNumber' => $cfsNumber,
            'Description' => substr($description, 0, 100),
            'Path' => substr($path, 0, 1000),
            'CreateBy' => 'REDFIVE',
        ];

        return $this->sendRequest('POST', '/CFSPathAttach/', $payload);
    }

    /**
     * Send an HTTP request to the SS CAD API
     */
    private function sendRequest($method, $endpoint, $payload)
    {
        $url = $this->baseUrl . $endpoint;
        $jsonPayload = json_encode($payload);

        $this->log('request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'payload' => $payload,
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'ApiKey: ' . $this->apiKey,
        ]);

        if ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        } elseif ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $responseData = json_decode($response, true);
        $success = $httpCode >= 200 && $httpCode < 300 && !$curlError;

        $result = [
            'success' => $success,
            'http_code' => $httpCode,
            'response' => $responseData,
            'raw_response' => $response,
            'curl_error' => $curlError ?: null,
        ];

        $this->log($success ? 'response_ok' : 'response_error', [
            'method' => $method,
            'endpoint' => $endpoint,
            'http_code' => $httpCode,
            'response' => $response,
            'curl_error' => $curlError ?: null,
        ]);

        return $result;
    }

    /**
     * Parse a street address string into SS components.
     * Duplicated from writeToDB.php parseStreetAddress for standalone use.
     */
    public static function parseStreetAddress($streetAddress)
    {
        if (!$streetAddress) {
            return ['number' => null, 'name' => null, 'pre_dir' => null, 'apt' => null];
        }

        $number = null;
        if (preg_match('/^(\d+)\s+/', $streetAddress, $matches)) {
            $number = (int)$matches[1];
        }

        // Extract apartment/suite
        $apt = null;
        if (preg_match('/\b(?:APT\.?|APARTMENT|LOT\.?|UNIT\.?|SUITE\.?|STE\.?|#)\s*([A-Z0-9#\-\.]+)/i', $streetAddress, $matches)) {
            $apt = trim($matches[0]);
        }

        // Extract pre-direction
        $preDir = null;
        if (preg_match('/^\d+\s+(N|S|E|W|NE|NW|SE|SW|NORTH|SOUTH|EAST|WEST)\s+/i', $streetAddress, $matches)) {
            $dirMap = [
                'NORTH' => 'N',
                'SOUTH' => 'S',
                'EAST' => 'E',
                'WEST' => 'W',
                'N' => 'N',
                'S' => 'S',
                'E' => 'E',
                'W' => 'W',
                'NE' => 'NE',
                'NW' => 'NW',
                'SE' => 'SE',
                'SW' => 'SW',
            ];
            $preDir = $dirMap[strtoupper($matches[1])] ?? null;
        }

        // Extract street name
        $name = $streetAddress;
        $name = preg_replace('/^\d+\s+/', '', $name);
        if ($preDir) {
            $name = preg_replace('/^(N|S|E|W|NE|NW|SE|SW|NORTH|SOUTH|EAST|WEST)\s+/i', '', $name);
        }
        $name = preg_replace('/\b(?:APT\.?|APARTMENT|LOT\.?|UNIT\.?|SUITE\.?|STE\.?|#)\s*[A-Z0-9#\-\.]+/i', '', $name);
        $name = trim($name);

        // Apply street suffix abbreviations (STREET→ST, DRIVE→DR, etc.) and uppercase
        $name = self::abbreviateStreetName($name);

        return [
            'number' => $number,
            'name' => $name,
            'pre_dir' => $preDir,
            'apt' => $apt,
        ];
    }

    /**
     * Abbreviate street suffixes to CAD-standard format and uppercase.
     * e.g. "Etiwan Park Street" → "ETIWAN PARK ST"
     */
    private static function abbreviateStreetName($name)
    {
        if (!$name) return null;

        $abbreviations = [
            'alley' => 'ALY',
            'avenue' => 'AV',
            'boulevard' => 'BLVD',
            'circle' => 'CIR',
            'court' => 'CT',
            'cove' => 'CV',
            'creek' => 'CRK',
            'drive' => 'DR',
            'extension' => 'EXT',
            'expressway' => 'EXPY',
            'highway' => 'HWY',
            'lane' => 'LN',
            'landing' => 'LNDG',
            'loop' => 'LOOP',
            'manor' => 'MNR',
            'parkway' => 'PKWY',
            'pass' => 'PASS',
            'path' => 'PATH',
            'place' => 'PL',
            'plaza' => 'PLZ',
            'point' => 'PT',
            'ridge' => 'RDG',
            'road' => 'RD',
            'row' => 'ROW',
            'run' => 'RUN',
            'square' => 'SQ',
            'street' => 'ST',
            'terrace' => 'TER',
            'trail' => 'TR',
            'turnpike' => 'TPKE',
            'way' => 'WAY',
        ];

        $name = strtolower(trim($name));
        $parts = explode(' ', $name);

        if (count($parts) > 1) {
            $lastPart = array_pop($parts);
            if (isset($abbreviations[$lastPart])) {
                $lastPart = $abbreviations[$lastPart];
            }
            $parts[] = $lastPart;
        }

        return strtoupper(implode(' ', $parts));
    }

    private function log($action, $data)
    {
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'data' => $data,
        ];
        file_put_contents($this->logFile, json_encode($entry, JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND);
        @chmod($this->logFile, 0666);
    }
}
