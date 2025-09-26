<?php

$url = "http://10.19.1.52:32001/CAD/CIM/AlarmCallTest/CFS/";
$headers = [
    'Content-Type' => 'application/json',
    'ApiKey' => '5sB+mmNAUppjGKFIDVM2VZXFaFIeCVWBpRF4PCwoWvY='
];

$postUrl = "https://acotocad.berkeleycountysc.gov/api/writeToDB.php";

// Get the raw POST data from the API 
$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);
if ($data === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}
// Prepare the data to send to the CAD system
$cadData = [
    
];

