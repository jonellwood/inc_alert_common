<?php
// Payload Structure Analyzer
// Analyzes different RapidSOS payload formats to identify patterns

class PayloadAnalyzer
{

    private $analysisLog;

    public function __construct()
    {
        $this->analysisLog = __DIR__ . '/../logs/payload_analysis.log';
        $logDir = dirname($this->analysisLog);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Comprehensive analysis of a payload structure
     */
    public function analyzeStructure($payload, $payloadName = 'unknown')
    {
        $analysis = [
            'payload_name' => $payloadName,
            'timestamp' => date('Y-m-d H:i:s'),
            'format_detected' => $this->identifyFormat($payload),
            'structure_analysis' => [
                'has_location' => $this->hasLocationData($payload),
                'location_formats' => $this->findLocationFormats($payload),
                'has_civic_address' => $this->hasCivicAddress($payload),
                'has_coordinates' => $this->hasCoordinates($payload),
                'emergency_type_paths' => $this->findEmergencyTypePaths($payload),
                'description_paths' => $this->findDescriptionPaths($payload),
                'contact_info_paths' => $this->findContactInfoPaths($payload),
                'timestamp_paths' => $this->findTimestampPaths($payload),
                'id_paths' => $this->findIdPaths($payload)
            ],
            'field_inventory' => $this->getAllFields($payload),
            'data_completeness' => $this->assessDataCompleteness($payload),
            'recommendations' => $this->generateRecommendations($payload)
        ];

        $this->logAnalysis($analysis);
        return $analysis;
    }

    /**
     * Batch analyze multiple payloads to find patterns
     */
    public function batchAnalyze($payloads)
    {
        $batchResults = [];
        $commonFields = null;
        $allFormats = [];

        foreach ($payloads as $name => $payload) {
            $analysis = $this->analyzeStructure($payload, $name);
            $batchResults[$name] = $analysis;
            $allFormats[] = $analysis['format_detected'];

            // Find common fields across all payloads
            $fields = array_keys($analysis['field_inventory']);
            if ($commonFields === null) {
                $commonFields = $fields;
            } else {
                $commonFields = array_intersect($commonFields, $fields);
            }
        }

        return [
            'individual_analyses' => $batchResults,
            'summary' => [
                'total_payloads' => count($payloads),
                'unique_formats' => array_unique($allFormats),
                'common_fields' => $commonFields,
                'format_distribution' => array_count_values($allFormats),
                'compatibility_matrix' => $this->buildCompatibilityMatrix($batchResults)
            ],
            'extraction_strategy' => $this->generateExtractionStrategy($batchResults)
        ];
    }

    /**
     * Identify payload format by structure
     */
    private function identifyFormat($payload)
    {
        if (isset($payload['alerts']) && is_array($payload['alerts'])) {
            return 'alerts_api';
        }
        if (isset($payload['callflow']) && isset($payload['variables'])) {
            return 'webhook_callflow';
        }
        if (isset($payload['event']) && isset($payload['data']) && is_string($payload['event'])) {
            return 'webhook_event';
        }
        if (isset($payload['alert_id']) || isset($payload['source_id'])) {
            return 'direct_alert';
        }
        return 'unknown';
    }

    /**
     * Check if payload has any location data
     */
    private function hasLocationData($payload)
    {
        $locationIndicators = [
            'location',
            'latitude',
            'longitude',
            'coordinates',
            'geodetic',
            'civic',
            'address'
        ];

        return $this->hasAnyField($payload, $locationIndicators);
    }

    /**
     * Find all location data formats in payload
     */
    private function findLocationFormats($payload)
    {
        $formats = [];

        $this->searchForPatterns($payload, [
            'geodetic_coordinates' => ['geodetic.latitude', 'geodetic.longitude'],
            'direct_coordinates' => ['latitude', 'longitude'],
            'coordinates_object' => ['coordinates.latitude', 'coordinates.longitude'],
            'civic_address' => ['civic.street_1', 'civic.city', 'civic.state'],
            'address_object' => ['address.address1', 'address.city', 'address.state'],
            'formatted_address' => ['address.formatted']
        ], $formats);

        return $formats;
    }

    /**
     * Check if payload has civic address
     */
    private function hasCivicAddress($payload)
    {
        $civicIndicators = ['civic', 'address', 'street_1', 'city', 'state'];
        return $this->hasAnyField($payload, $civicIndicators);
    }

    /**
     * Check if payload has coordinates
     */
    private function hasCoordinates($payload)
    {
        return $this->hasAnyField($payload, ['latitude', 'longitude', 'geodetic']);
    }

    /**
     * Find all paths where emergency type might be located
     */
    private function findEmergencyTypePaths($payload)
    {
        return $this->findFieldPaths($payload, [
            'emergency_type',
            'alarm_description',
            'type',
            'emergency'
        ]);
    }

    /**
     * Find all paths where description might be located
     */
    private function findDescriptionPaths($payload)
    {
        return $this->findFieldPaths($payload, [
            'description',
            'alarm_description',
            'zone_description',
            'message'
        ]);
    }

    /**
     * Find all paths where contact info might be located
     */
    private function findContactInfoPaths($payload)
    {
        return $this->findFieldPaths($payload, [
            'phone',
            'contactNumber',
            'central_station_phone',
            'premise_phone',
            'name'
        ]);
    }

    /**
     * Find all paths where timestamps might be located
     */
    private function findTimestampPaths($payload)
    {
        return $this->findFieldPaths($payload, [
            'timestamp',
            'incident_time',
            'created_time',
            'createdTime',
            'last_updated_time'
        ]);
    }

    /**
     * Find all paths where IDs might be located
     */
    private function findIdPaths($payload)
    {
        return $this->findFieldPaths($payload, [
            'id',
            'alert_id',
            'source_id',
            'account'
        ]);
    }

    /**
     * Get all fields in payload with their types and paths
     */
    private function getAllFields($data, $prefix = '')
    {
        $fields = [];

        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "$prefix.$key" : $key;

            if (is_array($value)) {
                $fields = array_merge($fields, $this->getAllFields($value, $fullKey));
            } else {
                $fields[$fullKey] = [
                    'type' => gettype($value),
                    'value' => is_string($value) ? substr($value, 0, 100) : $value
                ];
            }
        }

        return $fields;
    }

    /**
     * Assess how complete the data is
     */
    private function assessDataCompleteness($payload)
    {
        $requiredFields = [
            'id' => $this->hasAnyField($payload, ['id', 'alert_id', 'source_id']),
            'timestamp' => $this->hasAnyField($payload, ['timestamp', 'incident_time', 'created_time']),
            'location' => $this->hasLocationData($payload),
            'emergency_type' => $this->hasAnyField($payload, ['emergency_type', 'alarm_description', 'type']),
            'description' => $this->hasAnyField($payload, ['description', 'alarm_description', 'message']),
            'coordinates' => $this->hasCoordinates($payload),
            'civic_address' => $this->hasCivicAddress($payload)
        ];

        $completeness = array_sum($requiredFields) / count($requiredFields) * 100;

        return [
            'score' => round($completeness, 1),
            'missing_fields' => array_keys(array_filter($requiredFields, function ($v) {
                return !$v;
            })),
            'present_fields' => array_keys(array_filter($requiredFields))
        ];
    }

    /**
     * Generate recommendations for data extraction
     */
    private function generateRecommendations($payload)
    {
        $recommendations = [];

        $format = $this->identifyFormat($payload);

        switch ($format) {
            case 'alerts_api':
                $recommendations[] = "Use alerts[0] to access main alert data";
                $recommendations[] = "Check for multiple alerts in the array";
                break;
            case 'webhook_callflow':
                $recommendations[] = "Extract data from variables object";
                $recommendations[] = "Check buildings.address for location data";
                break;
            case 'webhook_event':
                $recommendations[] = "Extract data from data object";
                $recommendations[] = "Use event field to determine alert type";
                break;
        }

        if (!$this->hasCoordinates($payload)) {
            $recommendations[] = "No coordinates found - may need geocoding";
        }

        if (!$this->hasCivicAddress($payload)) {
            $recommendations[] = "No civic address found - may need reverse geocoding";
        }

        return $recommendations;
    }

    /**
     * Helper: Check if payload has any of the specified fields
     */
    private function hasAnyField($data, $fields, $path = '')
    {
        foreach ($data as $key => $value) {
            $currentPath = $path ? "$path.$key" : $key;

            if (in_array($key, $fields)) {
                return true;
            }

            if (is_array($value) && $this->hasAnyField($value, $fields, $currentPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Helper: Find all paths where specified fields exist
     */
    private function findFieldPaths($data, $fields, $path = '')
    {
        $paths = [];

        foreach ($data as $key => $value) {
            $currentPath = $path ? "$path.$key" : $key;

            if (in_array($key, $fields)) {
                $paths[] = $currentPath;
            }

            if (is_array($value)) {
                $subPaths = $this->findFieldPaths($value, $fields, $currentPath);
                $paths = array_merge($paths, $subPaths);
            }
        }

        return $paths;
    }

    /**
     * Search for specific patterns in payload
     */
    private function searchForPatterns($data, $patterns, &$found, $path = '')
    {
        foreach ($patterns as $patternName => $requiredFields) {
            $hasPattern = true;

            foreach ($requiredFields as $field) {
                if (!$this->hasFieldAtPath($data, $field)) {
                    $hasPattern = false;
                    break;
                }
            }

            if ($hasPattern) {
                $found[$patternName] = $path ?: 'root';
            }
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $newPath = $path ? "$path.$key" : $key;
                $this->searchForPatterns($value, $patterns, $found, $newPath);
            }
        }
    }

    /**
     * Check if field exists at specific path
     */
    private function hasFieldAtPath($data, $fieldPath)
    {
        $parts = explode('.', $fieldPath);
        $current = $data;

        foreach ($parts as $part) {
            if (!is_array($current) || !isset($current[$part])) {
                return false;
            }
            $current = $current[$part];
        }

        return true;
    }

    /**
     * Build compatibility matrix between formats
     */
    private function buildCompatibilityMatrix($analyses)
    {
        $matrix = [];

        foreach ($analyses as $name1 => $analysis1) {
            foreach ($analyses as $name2 => $analysis2) {
                if ($name1 !== $name2) {
                    $commonFields = array_intersect(
                        array_keys($analysis1['field_inventory']),
                        array_keys($analysis2['field_inventory'])
                    );

                    $matrix[$name1][$name2] = [
                        'common_fields' => count($commonFields),
                        'compatibility_score' => round(count($commonFields) / max(
                            count($analysis1['field_inventory']),
                            count($analysis2['field_inventory'])
                        ) * 100, 1)
                    ];
                }
            }
        }

        return $matrix;
    }

    /**
     * Generate extraction strategy based on analysis
     */
    private function generateExtractionStrategy($analyses)
    {
        $strategy = [
            'universal_fields' => [],
            'format_specific_extractors' => [],
            'fallback_strategies' => []
        ];

        // Find fields that exist in all formats
        $allFields = [];
        foreach ($analyses as $analysis) {
            $allFields[] = array_keys($analysis['field_inventory']);
        }

        if (!empty($allFields)) {
            $strategy['universal_fields'] = array_intersect(...$allFields);
        }

        // Generate format-specific strategies
        foreach ($analyses as $name => $analysis) {
            $format = $analysis['format_detected'];
            $strategy['format_specific_extractors'][$format] = [
                'recommended_paths' => [
                    'emergency_type' => $analysis['structure_analysis']['emergency_type_paths'],
                    'description' => $analysis['structure_analysis']['description_paths'],
                    'location' => $analysis['structure_analysis']['location_formats'],
                    'contact_info' => $analysis['structure_analysis']['contact_info_paths']
                ],
                'completeness_score' => $analysis['data_completeness']['score']
            ];
        }

        return $strategy;
    }

    /**
     * Log analysis results
     */
    private function logAnalysis($analysis)
    {
        file_put_contents($this->analysisLog, json_encode($analysis, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);
    }

    /**
     * Generate human-readable analysis report
     */
    public function generateReport($analysis)
    {
        $report = "=== PAYLOAD ANALYSIS REPORT ===\n";
        $report .= "Payload: {$analysis['payload_name']}\n";
        $report .= "Timestamp: {$analysis['timestamp']}\n";
        $report .= "Format: {$analysis['format_detected']}\n\n";

        $report .= "DATA COMPLETENESS: {$analysis['data_completeness']['score']}%\n";
        $report .= "Present: " . implode(', ', $analysis['data_completeness']['present_fields']) . "\n";
        $report .= "Missing: " . implode(', ', $analysis['data_completeness']['missing_fields']) . "\n\n";

        $report .= "LOCATION DATA:\n";
        $report .= "- Has Location: " . ($analysis['structure_analysis']['has_location'] ? 'Yes' : 'No') . "\n";
        $report .= "- Has Coordinates: " . ($analysis['structure_analysis']['has_coordinates'] ? 'Yes' : 'No') . "\n";
        $report .= "- Has Civic Address: " . ($analysis['structure_analysis']['has_civic_address'] ? 'Yes' : 'No') . "\n";

        if (!empty($analysis['structure_analysis']['location_formats'])) {
            $report .= "- Location Formats: " . implode(', ', array_keys($analysis['structure_analysis']['location_formats'])) . "\n";
        }

        $report .= "\nRECOMMENDATIONS:\n";
        foreach ($analysis['recommendations'] as $rec) {
            $report .= "- $rec\n";
        }

        return $report;
    }
}
