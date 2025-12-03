<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include database configuration
require_once '../secrets/db.php';

try {
    // Database connection
    $config = new acoConfig();
    $serverName = $config->serverName;
    $database = $config->database;
    $uid = $config->uid;
    $pwd = $config->pwd;

    $conn = new PDO("sqlsrv:Server=$serverName;Database=$database;ConnectionPooling=0;TrustServerCertificate=1;Encrypt=0", $uid, $pwd);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get query parameters for filtering
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 1000;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $emergency_type = isset($_GET['emergency_type']) ? $_GET['emergency_type'] : null;
    $search = isset($_GET['search']) ? $_GET['search'] : null;

    // Build the query
    $sql = "SELECT 
                id,
                sSourceSystem,
                sSourceId,
                sSourceReferenceNumber,
                sEmergencyType,
                sSiteType,
                sServiceProviderName,
                sDescription,
                sComments,
                sStatus,
                sContactFullName,
                sContactPhone,
                sCentralStationPhone,
                sAgency,
                sStreetAddress,
                sApartmentNumber,
                sCity,
                sState,
                sCountry,
                iZipCode,
                sFullAddress,
                iLatitude,
                iLongitude,
                sLocationUncertainty,
                sLocationName,
                sIncidentTimeRaw,
                sSubmittedTimeRaw,
                sCfsNumber,
                sCadStatus,
                sCadErrorMessage,
                dtCreatedDateTime,
                dtUpdatedDateTime,
                dtCadPostedDateTime,
                iRetryCount
            FROM IncomingAlertData 
            WHERE 1=1";

    $params = [];

    // Add filters
    if ($status) {
        $sql .= " AND sCadStatus = :status";
        $params['status'] = $status;
    }

    if ($emergency_type) {
        $sql .= " AND sEmergencyType = :emergency_type";
        $params['emergency_type'] = $emergency_type;
    }

    if ($search) {
        $sql .= " AND (
            sContactFullName LIKE :search OR 
            sStreetAddress LIKE :search OR 
            sCity LIKE :search OR 
            sDescription LIKE :search OR 
            sCfsNumber LIKE :search OR 
            sEmergencyType LIKE :search
        )";
        $params['search'] = '%' . $search . '%';
    }

    // Add ordering
    $sql .= " ORDER BY dtCreatedDateTime DESC";

    // Add pagination
    $sql .= " OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";
    $params['offset'] = $offset;
    $params['limit'] = $limit;

    // Execute query
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        if ($key === 'offset' || $key === 'limit') {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':' . $key, $value);
        }
    }
    $stmt->execute();
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination info
    $countSql = "SELECT COUNT(*) as total FROM IncomingAlertData WHERE 1=1";
    $countParams = [];

    if ($status) {
        $countSql .= " AND sCadStatus = :status";
        $countParams['status'] = $status;
    }

    if ($emergency_type) {
        $countSql .= " AND sEmergencyType = :emergency_type";
        $countParams['emergency_type'] = $emergency_type;
    }

    if ($search) {
        $countSql .= " AND (
            sContactFullName LIKE :search OR 
            sStreetAddress LIKE :search OR 
            sCity LIKE :search OR 
            sDescription LIKE :search OR 
            sCfsNumber LIKE :search OR 
            sEmergencyType LIKE :search
        )";
        $countParams['search'] = '%' . $search . '%';
    }

    $countStmt = $conn->prepare($countSql);
    foreach ($countParams as $key => $value) {
        $countStmt->bindValue(':' . $key, $value);
    }
    $countStmt->execute();
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Format the response
    $response = [
        'success' => true,
        'data' => $alerts,
        'pagination' => [
            'total' => (int)$totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + count($alerts)) < $totalCount
        ]
    ];

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
