<?php
date_default_timezone_set('Asia/Bangkok');

// File used to store the asynchronous callback response temporarily
$responseFile = 'callback_response.json';

// When a new response is POSTed from AMR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear any previous response if it exists
    if (file_exists($responseFile)) {
        unlink($responseFile);
    }
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        file_put_contents('callback_received.log', date('Y-m-d H:i:s') . " - JSON Decode Error: " . json_last_error_msg() . PHP_EOL, FILE_APPEND);
        die('Invalid JSON');
    }
    // Add a timestamp to the response
    $data['timestamp'] = time(); // current UNIX timestamp
    file_put_contents($responseFile, json_encode($data)); // save response to file
    file_put_contents('callback_received.log', date('Y-m-d H:i:s') . " - New Response: " . json_encode($data) . PHP_EOL, FILE_APPEND);
    echo json_encode(['result' => 'success']);
    exit;
}

// When a GET request is received (i.e. polling for callback response)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Use runtimeId as the parameter name (instead of missionId)
    $runtimeId = $_GET['runtimeId'] ?? '';
    $response = file_exists($responseFile) ? json_decode(file_get_contents($responseFile), true) : null;
    
    // Check if the response is valid (within 5 minutes) and matches the provided runtimeId
    if ($response && isset($response['Missionworkid']) && $response['Missionworkid'] === $runtimeId && (time() - $response['timestamp']) <= 300) {
        echo json_encode(['Status' => $response['Status']]);
    } else {
        // If expired or not matching, clear the response file and return Pending
        if (file_exists($responseFile)) {
            unlink($responseFile);
        }
        echo json_encode(['Status' => 'Pending']);
    }
    exit;
}
?>
