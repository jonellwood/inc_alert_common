<?php
// include_once "components/header.php";
// include_once "components/sidebar.php";

$url = "http://10.19.1.52:32001/CAD/CIM/AlarmCallTest/CFS/";
$headers = [
    'Content-Type' => 'application/json',
    'ApiKey' => '5sB+mmNAUppjGKFIDVM2VZXFaFIeCVWBpRF4PCwoWvY='
];
$postUrl = "https://acotocad.berkeleycountysc.gov/api/writeToDB.php";
// move this to a config file - this is for the test CAD only
// function generateUUID()
// {
//     $data = random_bytes(16);
//     $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
//     $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
//     return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
// }

// $UUID = generateUUID();
$dataBaseData = array();
$activityDescription = isset($_POST['ActivityDescription']) ? htmlspecialchars($_POST['ActivityDescription']) : null;
array_push($dataBaseData, $activityDescription);
$addressValidated = isset($_POST['AddressValidated']) ? $_POST['AddressValidated'] : 'off';
array_push($dataBaseData, $addressValidated);
$animalDescription = isset($_POST['AnimalDescription']) ? htmlspecialchars($_POST['AnimalDescription']) : null;
array_push($dataBaseData, $animalDescription);
$animalLocation = isset($_POST['AnimalLocation']) ? htmlspecialchars($_POST['AnimalLocation']) : null;
array_push($dataBaseData, $animalLocation);
$animalSick = isset($_POST['AnimalSick']) ? $_POST['AnimalSick'] : null;
array_push($dataBaseData, $animalSick);
$animalSickDescription = isset($_POST['AnimalSickDescription']) ? htmlspecialchars($_POST['AnimalSickDescription']) : null;
array_push($dataBaseData, $animalSickDescription);
$callType = isset($_POST['CallTypeAlias']) ? $_POST['CallTypeAlias'] : null;
array_push($dataBaseData, $callType);
$callerName = isset($_POST['CallerName']) ? htmlspecialchars($_POST['CallerName']) : null;
array_push($dataBaseData, $callerName);
$callerPhone = isset($_POST['CallerPhone']) ? htmlspecialchars($_POST['CallerPhone']) : null;
array_push($dataBaseData, $callerPhone);
$followUp = isset($_POST['FollowUpMethod']) ? $_POST['FollowUpMethod'] : null;
array_push($dataBaseData, $followUp);
$IncidentAddress = isset($_POST['IncidentAddress']) ? htmlspecialchars($_POST['IncidentAddress']) : null;
array_push($dataBaseData, $IncidentAddress);
$isAggressive = isset($_POST['IsAggressive']) ? $_POST['IsAggressive'] : null;
array_push($dataBaseData, $isAggressive);
$isContained = isset($_POST['IsContained']) ? $_POST['IsContained'] : null;
array_push($dataBaseData, $isContained);
$isAnimalInPain = isset($_POST['IsAnimalInPain']) ? $_POST['IsAnimalInPain'] : null;
array_push($dataBaseData, $isAnimalInPain);
$isInjured = isset($_POST['IsInjured']) ? $_POST['IsInjured'] : null;
array_push($dataBaseData, $isInjured);
$meetWithOfficers = isset($_POST['MeetWithOfficer']) ? $_POST['MeetWithOfficer'] : null;
array_push($dataBaseData, $meetWithOfficers);
$ownerDescription = isset($_POST['OwnerDescription']) ? htmlspecialchars($_POST['OwnerDescription']) : null;
array_push($dataBaseData, $ownerDescription);
$ownerLocated = isset($_POST['OwnerLocated']) ? $_POST['OwnerLocated'] : null;
array_push($dataBaseData, $ownerLocated);
$suspiciousActivity = isset($_POST['SuspiciousActivity']) ? $_POST['SuspiciousActivity'] : null;
array_push($dataBaseData, $suspiciousActivity);
$userid = isset($_POST['user_id']) ? $_POST['user_id'] : null;
array_push($dataBaseData, $userid);
$vehicleDescription = isset($_POST['VehicleDescription']) ? htmlspecialchars($_POST['VehicleDescription']) : null;
array_push($dataBaseData, $vehicleDescription);
$vehicleRunning = isset($_POST['VehicleRunning']) ? $_POST['VehicleRunning'] : null;
array_push($dataBaseData, $vehicleRunning);
$whereToMeet = isset($_POST['WhereToMeet']) ? htmlspecialchars($_POST['WhereToMeet']) : null;
array_push($dataBaseData, $whereToMeet);
$CallNotes = isset($_POST['CallNotes']) ? htmlspecialchars($_POST['CallNotes']) : null;
array_push($dataBaseData, $CallNotes);
$AlarmLevel = isset($_POST['AlarmLevel']) ? $_POST['AlarmLevel'] : null;
array_push($dataBaseData, $AlarmLevel);
$PriorityAlias = isset($_POST['PriorityAlias']) ? $_POST['PriorityAlias'] : null;
array_push($dataBaseData, $PriorityAlias);
$esn = isset($_POST['esn']) ? $_POST['esn'] : null;
array_push($dataBaseData, $esn);
$intersectionType = isset($_POST['intersectionType']) ? $_POST['intersectionType'] : null;
array_push($dataBaseData, $intersectionType);
$intersectionid = isset($_POST['intersectionid']) ? $_POST['intersectionid'] : null;
array_push($dataBaseData, $intersectionid);
$xcoor = isset($_POST['xcoor']) ? $_POST['xcoor'] : null;
array_push($dataBaseData, $xcoor);
$ycoor = isset($_POST['ycoor']) ? $_POST['ycoor'] : null;
array_push($dataBaseData, $ycoor);
$street1 = isset($_POST['street1']) ? $_POST['street1'] : null;
array_push($dataBaseData, $street1);
$street2 = isset($_POST['street2']) ? $_POST['street2'] : null;
array_push($dataBaseData, $street2);
$num = isset($_POST['num']) ? $_POST['num'] : null;
array_push($dataBaseData, $num);
$community = isset($_POST['community']) ? $_POST['community'] : null;
array_push($dataBaseData, $community);
$locationType = isset($_POST['locType']) ? $_POST['locType'] : null;
array_push($dataBaseData, $locationType);
// $IncIntersection = ($street1 . '/' . $street2);
$IncIntersection = "Main St / Jones St";
array_push($dataBaseData, $IncIntersection);


function close($session_attributes, $fulfillment_state, $message, $intent_request)
{
    $intent_request['sessionState']['intent']['state'] = $fulfillment_state;

    $response = array(
        'sessionState' => array(
            'sessionAttributes' => $session_attributes,
            'dialogAction' => array('type' => 'Close'),
            'intent' => $intent_request['sessionState']['intent']
        ),
        'messages' => array($message),
        'sessionId' => $intent_request['sessionId'],
        'requestAttributes' => isset($intent_request['requestAttributes']) ? $intent_request['requestAttributes'] : null
    );

    return $response;
}

// THESE ARE THE FUNCTIONS THAT WERE CONVERTED FROM PYTHON TO PHP
// THESE WILL MOST LIKELY END UP JUST BEING REMOVED 
function check_for_unknown($input_string)
{
    $known_values = ['unknown', 'unk', 'idk'];
    if (in_array(strtolower($input_string), $known_values)) {
        $IncStreetName = 'Unknown';
        return $IncStreetName;
    }
}


// END OF FUNCTIONS CONVERTED FROM PYTHON TO PHP
// START OF NEW FUNCTIONS MADE FOR THIS PROJECT AND NOT THE PYTHON REFACTORS

function extract_directional_value($input_string)
{
    $directions = ["North", "NORTH", "South", "SOUTH", "East", "EAST", "West", "WEST"];
    $IncPreDir = null;
    $IncStreetName = $input_string;

    foreach ($directions as $direction) {
        if (stripos($input_string, $direction) !== false) {
            $IncPreDir = $direction;
            $IncStreetName = str_ireplace($direction, "", $input_string);
            $IncStreetName = trim($IncStreetName);
            break;
        }
    }

    return $IncPreDir;
}
function extract_street_name($input_string)
{
    $directions = ["North", "South", "East", "West"];
    $IncPreDir = null;
    $IncStreetName = $input_string;

    foreach ($directions as $direction) {
        if (stripos($input_string, $direction) !== false) {
            $IncPreDir = $direction;
            $IncStreetName = str_ireplace($direction, "", $input_string);
            $IncStreetName = trim($IncStreetName);
            break;
        }
    }

    return $IncStreetName;
}
function extractStreetNumber($addressString)
{
    // Regular expression pattern to extract street number
    $pattern = '/^(\d+)\s+/i';

    // Match the pattern against the address string
    preg_match($pattern, $addressString, $matches);

    // Extract street number from the matched group
    $streetNumber = $matches[1] ?? null;

    return $streetNumber;
};
function extractApartmentNumber($addressString)
{
    // Regular expression pattern to extract apartment number
    $pattern = '/\b(APT|LOT|UNIT|SUITE)\s*(\w+)/i';

    // Match the pattern against the address string
    preg_match($pattern, $addressString, $matches);

    $aptType = $matches[1] ?? null;
    // Extract apartment number from the matched group
    $aptNumber = $matches[2] ?? null;

    return $aptType . " " . $aptNumber;
};
function extractPreDirection($addressString)
{
    // Regular expression pattern to extract pre-direction value
    $pattern = '/^\d+\s+([NSEW])\s+.*?,.*$/i';

    // Match the pattern against the address string
    preg_match($pattern, $addressString, $matches);

    // Extract the pre-direction value from the matched group
    $preDirection = $matches[1] ?? null;

    return $preDirection;
}

function extractStreetName($addressString)
{
    // Regular expression pattern to extract street name
    $pattern = '/^\d+\s+((?:North|South|East|West)?\s*.*?)\s*,/i';

    // Match the pattern against the address string
    preg_match($pattern, $addressString, $matches);

    // Extract street name from the matched group
    $streetName = $matches[1] ?? null;

    return $streetName;
};
function extractCommunity($addressString)
{
    // Regular expression pattern to extract the community value after the comma
    $pattern = '/,\s*(.*?)$/i';

    // Match the pattern against the address string
    preg_match($pattern, $addressString, $matches);

    // Extract the community value from the matched group
    $community = $matches[1] ?? null;

    return $community;
}
function cleanStreetName($StreetName, $IncDir)
{
    $pattern = '/^' . preg_quote($IncDir) . '\s+/i';
    $cleanedStreetName = preg_replace($pattern, '', $StreetName);

    // Remove apartment number from the street name
    $cleanedStreetName = preg_replace('/\b(APT|LOT|UNIT|SUITE)\s*\w+/i', '', $cleanedStreetName);

    return trim($cleanedStreetName);
}

function replaceStreetAbbreviations($streetName)
{
    $abbreviations = [
        'street' => 'ST',
        'road' => 'RD',
        'circle' => 'CIR',
        'avenue' => 'AV',
        'way' => 'WAY',
        'lane' => 'LN',
        'court' => 'CT',
        'trail' => 'TR',
        'row' => 'ROW',
        'boulevard' => 'BLVD',
        'loop' => 'LOOP',
        'drive' => 'DR',
        'terrace' => 'TER',
        'extension' => 'EXT',
        'path' => 'PATH',
        'place' => 'PL',
        'cove' => 'CV',
        'point' => 'PT',
        'run' => 'RUN',
        'pass' => 'PASS',
        'manor' => 'MNR',
        'fifty two' => '52',
        'fifty too' => '52'
    ];

    $streetName = strtolower($streetName);

    $parts = explode(' ', $streetName);

    if (count($parts) > 1) {
        $lastPart = array_pop($parts);
        foreach ($abbreviations as $search => $replace) {
            if ($lastPart == $search) {
                $lastPart = $replace;
                break;
            }
        }
        $parts[] = $lastPart;
    }

    // foreach ($abbreviations as $search => $replace) {
    //     $streetName = str_replace($search, $replace, $streetName);
    // }
    $result = implode(' ', $parts);
    // return strtoupper($streetName);
    return strtoupper($result);
}
function makeYesOrNo($value)
{
    if ($value == "1" || $value == "off") {
        return "No";
    } elseif ($value == "2" || $value == "on") {
        return "Yes";
    } else {
        return "Unknown";
    }
}




// Define an object to hold the variables
$callData = new stdClass();
$callData->IncStreetNum = extractStreetNumber($IncidentAddress);
//$callData->IncStreetName = extractStreetName($IncidentAddress);
$callData->IncStreetName = replaceStreetAbbreviations(cleanStreetName(extractStreetName($IncidentAddress), extractPreDirection($IncidentAddress)));
$callData->IncPreDir = extractPreDirection($IncidentAddress);
if (!$locationType == null) {
    $callData->IncAptLoc = $intersectionType;
} else {
    $callData->IncAptLoc = extractApartmentNumber($IncidentAddress);
}
$callData->IncCommunity = extractCommunity($IncidentAddress);
$callData->AddressValidated = isset($_POST['AddressValidated']) ? $_POST['AddressValidated'] : 'off';
$callData->CallType = isset($_POST['CallTypeAlias']) ? $_POST['CallTypeAlias'] : null;
$callData->CallerPhone = isset($_POST['CallerPhone']) ? htmlspecialchars($_POST['CallerPhone']) : null;
$callData->CallerName = isset($_POST['CallerName']) ? htmlspecialchars($_POST['CallerName']) : null;
$callData->MeetWithOfficer = isset($_POST['MeetWithOfficer']) ? $_POST['MeetWithOfficer'] : null;
$callData->WhereToMeet = isset($_POST['WhereToMeet']) ? htmlspecialchars($_POST['WhereToMeet']) : null;
$callData->FollowUpMethod = isset($_POST['FollowUpMethod']) ? $_POST['FollowUpMethod'] : null;
$callData->IsContained = isset($_POST['IsContained']) ? $_POST['IsContained'] : null;
$callData->IsAggressive = isset($_POST['IsAggressive']) ? $_POST['IsAggressive'] : null;
$callData->IsInjured = isset($_POST['IsInjured']) ? $_POST['IsInjured'] : null;
$callData->AnimalDescription = isset($_POST['AnimalDescription']) ? htmlspecialchars($_POST['AnimalDescription']) : null;
$callData->OwnerLocated = isset($_POST['OwnerLocated']) ? $_POST['OwnerLocated'] : null;
$callData->OwnerLocation = isset($_POST['OwnerLocation']) ? htmlspecialchars($_POST['OwnerLocation']) : null;
$callData->OwnerDescription = isset($_POST['OwnerDescription']) ? htmlspecialchars($_POST['OwnerDescription']) : null;
$callData->IsAnimalInPain = isset($_POST['IsAnimalInPain']) ? $_POST['IsAnimalInPain'] : null;
$callData->SuspiciousActivity = isset($_POST['SuspiciousActivity']) ? $_POST['SuspiciousActivity'] : null;
$callData->ActivityDescription = isset($_POST['ActivityDescription']) ? htmlspecialchars($_POST['ActivityDescription']) : null;
$callData->AnimalSick = isset($_POST['AnimalSick']) ? $_POST['AnimalSick'] : null;
$callData->AnimalSickDescription = isset($_POST['AnimalSickDescription']) ? htmlspecialchars($_POST['AnimalSickDescription']) : null;
$callData->VehicleDescription = isset($_POST['VehicleDescription']) ? htmlspecialchars($_POST['VehicleDescription']) : null;
$callData->VehicleRunning = isset($_POST['VehicleRunning']) ? $_POST['VehicleRunning'] : null;
$callData->AnimalLocation = isset($_POST['AnimalLocation']) ? htmlspecialchars($_POST['AnimalLocation']) : null;
$callData->CallNotes = isset($_POST['CallNotes']) ? htmlspecialchars($_POST['CallNotes']) : null;
$callData->AlarmLevel = isset($_POST['AlarmLevel']) ? $_POST['AlarmLevel'] : null;
$callData->PriorityAlias = isset($_POST['PriorityAlias']) ? $_POST['PriorityAlias'] : null;
$callData->Esn = isset($_POST['esn']) ? $_POST['esn'] : null;
$callData->Intersectiontype = isset($_POST['intersectiontype']) ? $_POST['intersectiontype'] : null;
$callData->Intersectionid = isset($_POST['intersectionid']) ? $_POST['intersectionid'] : null;
$callData->Xcoor = isset($_POST['xcoor']) ? $_POST['xcoor'] : null;
$callData->Ycoor = isset($_POST['ycoor']) ? $_POST['ycoor'] : null;
$callData->Street1 = isset($_POST['street1']) ? $_POST['street1'] : null;
$callData->Street2 = isset($_POST['street2']) ? $_POST['street2'] : null;
$callData->Num = isset($_POST['num']) ? $_POST['num'] : null;
$callData->Community = isset($_POST['community']) ? $_POST['community'] : null;
if (!$locationType == null) {
    $callData->IncIntersection = ($Street1 . '/' . $Street2);
} else {
    $callData->IncIntersection = null;
}



// END OF FUNCTIONS FOR FORMAT THE ADDRESS FOR CAD
// START OF FUNCTIONS FOR ENTERING CALLS INTO CAD

function post_request($call, $url, $postUrl)
{
    $session_attributes = ['currentCall' => $call];

    //$text = prepend_completed_msg($response);
    $message = [
        'contentType' => 'PlainText',
        'content' => 'TEST $text'
    ];
    $fulfillment_state = "Fulfilled";
    $ch = curl_init();
    // Set the URL
    curl_setopt($ch, CURLOPT_URL, $url);
    // Set the request method to POST
    curl_setopt($ch, CURLOPT_POST, 1);
    // Set the POST data
    curl_setopt($ch, CURLOPT_POSTFIELDS, $session_attributes['currentCall']);
    // Set headers if needed
    // curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    // Set the Content-Type header
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'ApiKey: Y9YcKfeq+r+dRmluJyk0u+5ZeQOG53gDPYWowHLzYUE='
    ));

    // Return the response instead of outputting it
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // #######################################
    // Execute the request
    $response = curl_exec($ch);

    // Check for errors
    if ($response === false) {
        // Handle error
        $error = curl_error($ch);
        // Close curl handle
        curl_close($ch);
        // Handle error
    } else {
        // Close curl handle
        curl_close($ch);
        // Process response
        $responseData = json_decode($response, true);
        echo json_encode(($responseData), JSON_PRETTY_PRINT);
    }
}

function enter_barking_dog($url, $callData, $postUrl)
{
    $CallTypeAlias = "105 ANIMAL";
    $CallSubType = "BARKING DOG";
    $PriorityAlias = "Charlie";
    $AlarmLevel = "2";

    $IncIntersection = isset($callData->IncIntersection) ? $callData->IncIntersection : null;
    $IncStreetNum = $callData->IncStreetNum ?? null;
    $IncDir = isset($callData->IncPreDir) ? $callData->IncPreDir : null;
    $IncStreetName = $callData->IncStreetName ?? null;
    $IncAptLoc = isset($callData->IncAptLoc) ? $callData->IncAptLoc : null;
    $IncCommunity = isset($callData->IncCommunity) ? $callData->IncCommunity : null;
    $CallerPhone = isset($callData->CallerPhone) ? $callData->CallerPhone : null;
    $CallerName = isset($callData->CallerName) ? $callData->CallerName : null;
    $MeetWithOfficer = isset($callData->MeetWithOfficer) ? $callData->MeetWithOfficer : null;
    $WhereToMeet = isset($callData->WhereToMeet) ? $callData->WhereToMeet : null;
    $CallNotes = isset($callData->CallNotes) ? $callData->CallNotes : null;

    $AnimalLocation = isset($callData->AnimalLocation) ? $callData->AnimalLocation : null;

    $AnimalDescription = isset($callData->AnimalDescription) ? $callData->AnimalDescription : null;
    $AnythingSuspicious = isset($callData->SuspiciousActivity) ? $callData->SuspiciousActivity : null;
    $MeetWithOfficer = isset($callData->MeetWithOfficer) ? $callData->MeetWithOfficer : null;
    $AddressValidated = isset($callData->AddressValidated) ? $callData->AddressValidated : null;
    $ActivityDescription = isset($callData->ActivityDescription) ? $callData->ActivityDescription : null;

    $AddressValidated = makeYesOrNo($AddressValidated);
    $FollowUpMethod = isset($callData->FollowUpMethod) ? $callData->FollowUpMethod : null;

    $CFSNote = "\n Call Sub Type: $CallSubType \n Animal Location: $AnimalLocation \n Description of Animal: $AnimalDescription \n Anything suspicious? $AnythingSuspicious \n Suspicious Activity? $ActivityDescription \n meetWithOfficer? $MeetWithOfficer \n Where To Meet? $WhereToMeet \n Address GIS Confirmed? $AddressValidated \n Follow Up Method: $FollowUpMethod";
    $CallerAddress = $IncStreetName;
    // Create JSON payload
    $call = json_encode([
        'IncIntersection' => $IncIntersection,
        'CallTypeAlias' => $CallTypeAlias,
        'IncPreDir' => $IncDir,
        'IncStreetNum' => $IncStreetNum,
        'IncStreetName' => $IncStreetName,
        'IncAptLoc' => $IncAptLoc,
        'IncCommunity' => $IncCommunity,
        'CallerPhone' => $CallerPhone,
        'CallerName' => $CallerName,
        //'CallerAddress' => $CallerAddress,
        'AlarmLevel' => $AlarmLevel,
        'PriorityAlias' => $PriorityAlias,
        'Comment' => $CallNotes,
        'CFSNote' => $CFSNote
    ]);
    // echo $call;
    post_request($call, $url, $postUrl);
}


function enter_stray_or_found($url, $callData, $postUrl)
{
    $CallTypeAlias = "105 ANIMAL";
    $CallSubType = "STRAY OR FOUND";
    $PriorityAlias = "Charlie";
    $AlarmLevel = "2";

    $IncIntersection = isset($callData->IncIntersection) ? $callData->IncIntersection : null;
    $IncStreetNum = $callData->IncStreetNum ?? null;
    $IncDir = isset($callData->IncPreDir) ? $callData->IncPreDir : null;
    $IncStreetName = $callData->IncStreetName ?? null;
    $IncAptLoc = isset($callData->IncAptLoc) ? $callData->IncAptLoc : null;
    $IncCommunity = isset($callData->IncCommunity) ? $callData->IncCommunity : null;
    $CallerPhone = isset($callData->CallerPhone) ? $callData->CallerPhone : null;
    $CallerName = isset($callData->CallerName) ? $callData->CallerName : null;
    $MeetWithOfficer = isset($callData->MeetWithOfficer) ? $callData->MeetWithOfficer : null;
    $WhereToMeet = isset($callData->WhereToMeet) ? $callData->WhereToMeet : null;
    $CallNotes = isset($callData->CallNotes) ? $callData->CallNotes : null;
    $AddressValidated = isset($callData->AddressValidated) ? $callData->AddressValidated : null;
    $MeetWithOfficer = $MeetWithOfficer;
    $AddressValidated = makeYesOrNo($AddressValidated);
    $FollowUpMethod = isset($callData->FollowUpMethod) ? $callData->FollowUpMethod : null;
    $IsContained = isset($callData->IsContained) ? $callData->IsContained : null;
    $IsAggressive = isset($callData->IsAggressive) ? $callData->IsAggressive : null;
    $IsInjured = isset($callData->IsInjured) ? $callData->IsInjured : null;
    $AnimalDescription = isset($callData->AnimalDescription) ? $callData->AnimalDescription : null;

    $CFSNote = "\n Call Sub Type: $CallSubType \n Meet with Officer: $MeetWithOfficer \n Where to Meet: $WhereToMeet \n Address GIS Confirmed: $AddressValidated \n Follow Up Method: $FollowUpMethod \n Is Contained: $IsContained \n Is Aggressive: $IsAggressive \n Is Injured: $IsInjured \n Description of Animal: $AnimalDescription";
    $CallerAddress = $IncStreetName;

    // Create JSON payload
    $call = json_encode([
        'IncIntersection' => $IncIntersection,
        'CallTypeAlias' => $CallTypeAlias,
        'IncPreDir' => $IncDir,
        'IncStreetNum' => $IncStreetNum,
        'IncStreetName' => $IncStreetName,
        'IncAptLoc' => $IncAptLoc,
        'IncCommunity' => $IncCommunity,
        'CallerPhone' => $CallerPhone,
        'CallerName' => $CallerName,
        // 'CallerAddress' => $CallerAddress,
        'AlarmLevel' => $AlarmLevel,
        'PriorityAlias' => $PriorityAlias,
        'Comment' => $CallNotes,
        'CFSNote' => $CFSNote
    ]);
    // echo $call;
    post_request($call, $url, $postUrl);
};

function enter_animal_abuse($url, $callData, $postUrl)
{
    $CallTypeAlias = "105 ANIMAL";
    $CallSubType = "ANIMAL CRUELTY";
    $PriorityAlias = "Charlie";
    $AlarmLevel = "1";

    $IncIntersection = isset($callData->IncIntersection) ? $callData->IncIntersection : null;
    $IncStreetNum = $callData->IncStreetNum ?? null;
    $IncDir = isset($callData->IncPreDir) ? $callData->IncPreDir : null;
    $IncStreetName = $callData->IncStreetName ?? null;
    $IncAptLoc = isset($callData->IncAptLoc) ? $callData->IncAptLoc : null;
    $IncCommunity = isset($callData->IncCommunity) ? $callData->IncCommunity : null;
    $CallerPhone = isset($callData->CallerPhone) ? $callData->CallerPhone : null;
    $CallerName = isset($callData->CallerName) ? $callData->CallerName : null;
    $MeetWithOfficer = isset($callData->MeetWithOfficer) ? $callData->MeetWithOfficer : null;
    $WhereToMeet = isset($callData->WhereToMeet) ? $callData->WhereToMeet : null;
    $CallNotes = isset($callData->CallNotes) ? $callData->CallNotes : null;
    $AddressValidated = isset($callData->AddressValidated) ? $callData->AddressValidated : null;

    $AddressValidated = makeYesOrNo($AddressValidated);
    $FollowUpMethod = isset($callData->FollowUpMethod) ? $callData->FollowUpMethod : null;

    $AnimalDescription = isset($callData->AnimalDescription) ? $callData->AnimalDescription : null;
    $OwnerLocated = isset($callData->OwnerLocated) ? $callData->OwnerLocated : null;
    $OwnerLocation = isset($callData->OwnerLocation) ? $callData->OwnerLocation : null;
    $OwnerDescription = isset($callData->OwnerDescription) ? $callData->OwnerDescription : null;

    $CFSNote = "\n Call Sub Type: $CallSubType \n Meet with Officer: $MeetWithOfficer \n Where To Meet: $WhereToMeet \n Address GIS Confirmed: $AddressValidated \n Follow Up Method: $FollowUpMethod \n Description of Animal: $AnimalDescription \n Owner Located: $OwnerLocated \n Owner Location: $OwnerLocation \n Owner Description: $OwnerDescription";

    $CallerAddress = $IncStreetName;
    $call = json_encode([
        'IncIntersection' => $IncIntersection,
        'CallTypeAlias' => $CallTypeAlias,
        'IncPreDir' => $IncDir,
        'IncStreetNum' => $IncStreetNum,
        'IncStreetName' => $IncStreetName,
        'IncAptLoc' => $IncAptLoc,
        'IncCommunity' => $IncCommunity,
        'CallerPhone' => $CallerPhone,
        'CallerName' => $CallerName,
        // 'CallerAddress' => $CallerAddress,
        'AlarmLevel' => $AlarmLevel,
        'PriorityAlias' => $PriorityAlias,
        'Comment' => $CallNotes,
        'CFSNote' => $CFSNote
    ]);
    // echo $call;
    post_request($call, $url, $postUrl);
};
function enter_injured_animal($url, $callData, $postUrl)
{
    $CallTypeAlias = "105 ANIMAL";
    $CallSubType = "INJURED ANIMAL";
    $PriorityAlias = "Charlie";
    $AlarmLevel = "4";

    $IncIntersection = isset($callData->IncIntersection) ? $callData->IncIntersection : null;
    $IncStreetNum = $callData->IncStreetNum ?? null;
    $IncDir = isset($callData->IncPreDir) ? $callData->IncPreDir : null;
    $IncStreetName = $callData->IncStreetName ?? null;
    $IncAptLoc = isset($callData->IncAptLoc) ? $callData->IncAptLoc : null;
    $IncCommunity = isset($callData->IncCommunity) ? $callData->IncCommunity : null;
    $CallerPhone = isset($callData->CallerPhone) ? $callData->CallerPhone : null;
    $CallerName = isset($callData->CallerName) ? $callData->CallerName : null;
    $MeetWithOfficer = isset($callData->MeetWithOfficer) ? $callData->MeetWithOfficer : null;
    $WhereToMeet = isset($callData->WhereToMeet) ? $callData->WhereToMeet : null;
    $CallNotes = isset($callData->CallNotes) ? $callData->CallNotes : null;
    $AddressValidated = isset($callData->AddressValidated) ? $callData->AddressValidated : null;
    // $MeetWithOfficer = $MeetWithOfficer;
    $AddressValidated = makeYesOrNo($AddressValidated);
    $FollowUpMethod = isset($callData->FollowUpMethod) ? $callData->FollowUpMethod : null;

    $AnimalDescription = isset($callData->AnimalDescription) ? $callData->AnimalDescription : null;
    $AnimalLocation = isset($callData->AnimalLocation) ? $callData->AnimalLocation : null;
    $IsAnimalInPain = isset($callData->IsAnimalInPain) ? $callData->IsAnimalInPain : null;

    $CFSNote = "\n Call Sub Type: $CallSubType \n Meet with Officer: $MeetWithOfficer \n Where To Meet: $WhereToMeet \n Address GIS Confirmed: $AddressValidated \n Follow Up Method: $FollowUpMethod \n Description of Animal: $AnimalDescription \n Animal Location: $AnimalLocation \n Is Animal in Pain: $IsAnimalInPain";

    $CallerAddress = $IncStreetName;
    $call = json_encode([
        'IncIntersection' => $IncIntersection,
        'CallTypeAlias' => $CallTypeAlias,
        'IncPreDir' => $IncDir,
        'IncStreetNum' => $IncStreetNum,
        'IncStreetName' => $IncStreetName,
        'IncAptLoc' => $IncAptLoc,
        'IncCommunity' => $IncCommunity,
        'CallerPhone' => $CallerPhone,
        'CallerName' => $CallerName,
        // 'CallerAddress' => $CallerAddress,
        'AlarmLevel' => $AlarmLevel,
        'PriorityAlias' => $PriorityAlias,
        'Comment' => $CallNotes,
        'CFSNote' => $CFSNote
    ]);
    // echo $call;
    post_request($call, $url, $postUrl);
};

function enter_animal_bite($url, $callData, $postUrl)
{
    $CallTypeAlias = "105 ANIMAL";
    $CallSubType = "MINOR BITE";
    $PriorityAlias = "Alpha";
    $AlarmLevel = "2";

    $IncIntersection = isset($callData->IncIntersection) ? $callData->IncIntersection : null;
    $IncStreetNum = $callData->IncStreetNum ?? null;
    $IncDir = isset($callData->IncPreDir) ? $callData->IncPreDir : null;
    $IncStreetName = $callData->IncStreetName ?? null;
    $IncAptLoc = isset($callData->IncAptLoc) ? $callData->IncAptLoc : null;
    $IncCommunity = isset($callData->IncCommunity) ? $callData->IncCommunity : null;
    $CallerPhone = isset($callData->CallerPhone) ? $callData->CallerPhone : null;
    $CallerName = isset($callData->CallerName) ? $callData->CallerName : null;
    $MeetWithOfficer = isset($callData->MeetWithOfficer) ? $callData->MeetWithOfficer : null;
    $WhereToMeet = isset($callData->WhereToMeet) ? $callData->WhereToMeet : null;
    $CallNotes = isset($callData->CallNotes) ? $callData->CallNotes : null;

    $AnimalLocation = isset($callData->AnimalLocation) ? $callData->AnimalLocation : null;
    $AnimalDescription = isset($callData->AnimalDescription) ? $callData->AnimalDescription : null;

    $CFSNote = "\n Call Sub Type: $CallSubType \n Animal Location: $AnimalLocation \n Description of Animal: $AnimalDescription \n Meet With Officer: $MeetWithOfficer \n Where to Meet: $WhereToMeet";

    $CallerAddress = $IncStreetName;

    $call = json_encode([
        'IncIntersection' => $IncIntersection,
        'CallTypeAlias' => $CallTypeAlias,
        'IncPreDir' => $IncDir,
        'IncStreetNum' => $IncStreetNum,
        'IncStreetName' => $IncStreetName,
        'IncAptLoc' => $IncAptLoc,
        'IncCommunity' => $IncCommunity,
        'CallerPhone' => $CallerPhone,
        'CallerName' => $CallerName,
        // 'CallerAddress' => $CallerAddress,
        'AlarmLevel' => $AlarmLevel,
        'PriorityAlias' => $PriorityAlias,
        'Comment' => $CallNotes,
        'CFSNote' => $CFSNote
    ]);

    // echo $call;
    post_request($call, $url, $postUrl);
};
function enter_animal_in_vehicle($url, $callData, $postUrl)
{
    $CallTypeAlias = "105 ANIMAL";
    $CallSubType = "ANIMAL CRUELTY";
    $PriorityAlias = "Charlie";
    $AlarmLevel = "1";

    $IncIntersection = isset($callData->IncIntersection) ? $callData->IncIntersection : null;
    $IncStreetNum = $callData->IncStreetNum ?? null;
    $IncDir = isset($callData->IncPreDir) ? $callData->IncPreDir : null;
    $IncStreetName = $callData->IncStreetName ?? null;
    $IncAptLoc = isset($callData->IncAptLoc) ? $callData->IncAptLoc : null;
    $IncCommunity = isset($callData->IncCommunity) ? $callData->IncCommunity : null;
    $CallerPhone = isset($callData->CallerPhone) ? $callData->CallerPhone : null;
    $CallerName = isset($callData->CallerName) ? $callData->CallerName : null;
    $MeetWithOfficer = isset($callData->MeetWithOfficer) ? $callData->MeetWithOfficer : null;
    $WhereToMeet = isset($callData->WhereToMeet) ? $callData->WhereToMeet : null;
    $CallNotes = isset($callData->CallNotes) ? $callData->CallNotes : null;

    $AnimalLocation = isset($callData->AnimalLocation) ? $callData->AnimalLocation : null;
    $AnimalDescription = isset($callData->AnimalDescription) ? $callData->AnimalDescription : null;
    $VehicleDescription = isset($callData->VehicleDescription) ? $callData->VehicleDescription : null;
    $VehicleRunning = isset($callData->VehicleRunning) ? $callData->VehicleRunning : null;
    $animalSick = isset($callData->AnimalSick) ? $callData->AnimalSick : null;
    $AnimalSickDescription = isset($callData->AnimalSickDescription) ? $callData->AnimalSickDescription : null;

    $CFSNote = "\n Call Sub Type: $CallSubType \n Animal Location: $AnimalLocation \n Description of Animal: $AnimalDescription \n Vehicle Description: $VehicleDescription \n Vehicle Running: $VehicleRunning \n Animal Sick: $animalSick \n Animal Sick Description: $AnimalSickDescription \n Meet With Officer: $MeetWithOfficer \n Where to Meet: $WhereToMeet";

    $CallerAddress = $IncStreetName;

    $call = json_encode([
        'IncIntersection' => $IncIntersection,
        'CallTypeAlias' => $CallTypeAlias,
        'IncPreDir' => $IncDir,
        'IncStreetNum' => $IncStreetNum,
        'IncStreetName' => $IncStreetName,
        'IncAptLoc' => $IncAptLoc,
        'IncCommunity' => $IncCommunity,
        'CallerPhone' => $CallerPhone,
        'CallerName' => $CallerName,
        // 'CallerAddress' => $CallerAddress,
        'AlarmLevel' => $AlarmLevel,
        'PriorityAlias' => $PriorityAlias,
        'Comment' => $CallNotes,
        'CFSNote' => $CFSNote
    ]);

    // echo $call;
    post_request($call, $url, $postUrl);
};



function enter_call_by_type($callType, $url, $callData, $postUrl)
{
    if ($callType == "1") {
        enter_stray_or_found($url, $callData, $postUrl);
    } elseif ($callType == "4") {
        enter_animal_abuse($url, $callData, $postUrl);
    } elseif ($callType == "5") {
        enter_injured_animal($url, $callData, $postUrl);
    } elseif ($callType == "7") {
        enter_barking_dog($url, $callData, $postUrl);
    } elseif ($callType == "8") {
        enter_animal_bite($url, $callData, $postUrl);
    } elseif ($callType == "10") {
        enter_animal_in_vehicle($url, $callData, $postUrl);
    }
}
enter_call_by_type($callType, $url, $callData, $postUrl);

// END OF FUNCTIONS FOR ENTERING CALLS INTO CAD