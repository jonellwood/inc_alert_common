<?php

/**
 * API endpoint for managing Flock ALPR alert settings.
 * GET  - Returns current settings
 * POST - Updates settings
 *
 * Protected by admin auth (level 2+).
 */

require_once __DIR__ . '/../lib/auth_check_admin.php';

header('Content-Type: application/json');

$settingsFile = __DIR__ . '/../config/flock_alert_settings.json';

/**
 * Load current settings from JSON file
 */
function loadSettings($file)
{
    if (!file_exists($file)) {
        return null;
    }
    $json = file_get_contents($file);
    return json_decode($json, true);
}

/**
 * Save settings to JSON file
 */
function saveSettings($file, $settings)
{
    $settings['last_updated'] = date('Y-m-d H:i:s');
    // Get the logged-in user from session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $settings['updated_by'] = $_SESSION['redfive_username'] ?? 'unknown';

    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $result = file_put_contents($file, $json);
    if ($result === false) {
        return false;
    }
    @chmod($file, 0664);
    return true;
}

// Handle GET - return current settings
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $settings = loadSettings($settingsFile);
    if ($settings === null) {
        http_response_code(500);
        echo json_encode(['error' => 'Settings file not found']);
        exit;
    }
    echo json_encode($settings);
    exit;
}

// Handle POST - update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON payload']);
        exit;
    }

    $currentSettings = loadSettings($settingsFile);
    if ($currentSettings === null) {
        http_response_code(500);
        echo json_encode(['error' => 'Settings file not found']);
        exit;
    }

    // Update alert_types if provided
    if (isset($input['alert_types']) && is_array($input['alert_types'])) {
        foreach ($input['alert_types'] as $typeName => $typeSettings) {
            // Sanitize the type name
            $typeName = substr(strip_tags(trim($typeName)), 0, 100);
            if (empty($typeName)) {
                continue;
            }

            if (isset($currentSettings['alert_types'][$typeName])) {
                // Update existing type
                if (isset($typeSettings['enabled'])) {
                    $currentSettings['alert_types'][$typeName]['enabled'] = (bool) $typeSettings['enabled'];
                }
                if (isset($typeSettings['send_to_cad'])) {
                    $currentSettings['alert_types'][$typeName]['send_to_cad'] = (bool) $typeSettings['send_to_cad'];
                }
                if (isset($typeSettings['call_type_alias'])) {
                    $currentSettings['alert_types'][$typeName]['call_type_alias'] = substr(strip_tags(trim($typeSettings['call_type_alias'])), 0, 50);
                }
            } else {
                // New alert type discovered - add it
                $currentSettings['alert_types'][$typeName] = [
                    'enabled' => (bool) ($typeSettings['enabled'] ?? $currentSettings['global_settings']['default_new_type_enabled']),
                    'send_to_cad' => (bool) ($typeSettings['send_to_cad'] ?? false),
                    'description' => substr(strip_tags(trim($typeSettings['description'] ?? '')), 0, 200),
                    'call_type_alias' => substr(strip_tags(trim($typeSettings['call_type_alias'] ?? 'FLOCK BOLO')), 0, 50),
                ];
            }
        }
    }

    // Update global_settings if provided
    if (isset($input['global_settings']) && is_array($input['global_settings'])) {
        $gs = $input['global_settings'];
        if (isset($gs['send_all_to_cad'])) {
            $currentSettings['global_settings']['send_all_to_cad'] = (bool) $gs['send_all_to_cad'];
        }
        if (isset($gs['log_filtered_alerts'])) {
            $currentSettings['global_settings']['log_filtered_alerts'] = (bool) $gs['log_filtered_alerts'];
        }
        if (isset($gs['min_plate_confidence'])) {
            $val = (float) $gs['min_plate_confidence'];
            $currentSettings['global_settings']['min_plate_confidence'] = max(0, min(100, $val));
        }
        if (isset($gs['default_new_type_enabled'])) {
            $currentSettings['global_settings']['default_new_type_enabled'] = (bool) $gs['default_new_type_enabled'];
        }
    }

    if (saveSettings($settingsFile, $currentSettings)) {
        echo json_encode(['success' => true, 'settings' => $currentSettings]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save settings file']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
