<?php

<<<<<<< Updated upstream
// Define the base URL for the mock server (please enter the production server IP here)
define('SERVER_URL', 'https://3aca9239-01d0-43b9-80ca-97bb21637841.mock.pstmn.io');

// Function to call the external web API "AMR Real-time Inquiry (/api/v3/vehicles)" and retrieve the workStatus value
function getVehicleStatus() {
    // Uncomment the following block to use the cURL method (make sure to enable curl in php.ini)
    /*
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable certificate verification if necessary
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log the HTTP status code
    error_log("HTTP Status Code: " . $http_code);

    // Check if the response is empty
    if (empty($response)) {
        error_log("Response is empty.");
    } else {
        error_log("Response: " . $response);
    }

    // Check for errors during JSON decoding
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Decode Error: " . json_last_error_msg());
    }

    return $data;
    */
    $url = SERVER_URL . "/api/v3/vehicles";

    $options = [
        "http" => [
            "method"  => "GET",
            "header"  => "Content-Type: application/json\r\n"
        ]
=======
// Define the base URL for the YOUICOMPASS installed server
// Develop Env（Mock Server）
//define('SERVER_URL', 'https://3aca9239-01d0-43b9-80ca-97bb21637841.mock.pstmn.io');
// Production Env
define('SERVER_URL', 'http://192.168.51.51:8080');

// Function to get the database connection settings
function getDBConnection() {
    try {
         // Define DB server name.
         // Develop Env
         // $serverName = "DESKTOP-DQGJI2I";
         // Production Env
         $serverName = "D1ZP3K54\\MSSQLSERVER01";
         $database   = "amr_task_db";
         $username   = "test";         // SQL Server 認証ユーザー
         $password   = "Koito2025";     // パスワード
 
         $conn = new PDO(
             "sqlsrv:Server=$serverName;Database=$database;TrustServerCertificate=Yes",
             $username,
             $password
         );
         $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
         return $conn;
    } catch(PDOException $e) {
         throw new Exception("Database error: Unable to connect to the DB. " . $e->getMessage());
    }
}
 
// Function to call the external web API "AMR Real-time Inquiry (/api/v3/vehicles)"
function getVehicleStatus() {
     $url = SERVER_URL . "/api/v3/vehicles";
     $response = file_get_contents($url);
     
     if (empty($response)) {
         error_log("Response is empty.");
         return null;
     }
     
     $data = json_decode($response, true);
     if (json_last_error() !== JSON_ERROR_NONE) {
         error_log("JSON Decode Error: " . json_last_error_msg());
         return null;
     }
     
     return isset($data['workStatus']) ? $data['workStatus'] : null;
}
 
// Function to call the external web API "Request Parameter Sending (MissionWorks)"
// 仕様に合わせ、missionId、missionCode、runtimeParam、callbackUrl を引数として受け取る
function sendMissionWorksRequest($missionId, $missionCode, $runtimeParam, $callbackUrl) {
    // Define YOUICOMPASS server URL.
    // Develop Env
    //$apiUrl = 'http://192.168.56.1/api/v3/missionWorks';    
    // Production Env
    $apiUrl = SERVER_URL . '/api/v3/missionWorks';

    // リクエストペイロードを作成
    $payload = [
        "missionId"    => $missionId,
        "missionCode"  => $missionCode,
        "callbackUrl"  => $callbackUrl,
        "runtimeParam" => $runtimeParam
>>>>>>> Stashed changes
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        die("Error fetching data");
    }
    
    // Check for JSON decode errors
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Decode Error: " . json_last_error_msg());
    }

    // Retrieve and return workStatus; if workStatus does not exist, return null
    return isset($data['workStatus']) ? $data['workStatus'] : null;
}

// Function to call the external web API "Request Parameter Sending (MissionWorks)"
function sendMissionWorksRequest($params) {
    $apiUrl = SERVER_URL . '/api/v3/missionWorks';
    
    // Using file_get_contents method
    $options = [
        'http' => [
            'method'        => 'POST',
            'header'        => 'Content-Type: application/json',
            'content'       => json_encode($params),
            'ignore_errors' => true // Retrieve response even if errors occur
        ]
    ];
    $context = stream_context_create($options);
    
    $missionResponse = file_get_contents($apiUrl, false, $context);
    
    // Get the HTTP status code
    $httpCode = isset($http_response_header) ? substr($http_response_header[0], 9, 3) : null;

    if ($missionResponse === false || $httpCode !== '200') {
        error_log("Error fetching data: HTTP Status Code " . $httpCode);
        error_log("Response: " . $missionResponse);
        die("Error fetching data: HTTP Status Code " . $httpCode);
    }

    // Check for JSON decode errors
    $data = json_decode($missionResponse, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Decode Error: " . json_last_error_msg());
    }

    // Retrieve and return the status; if status does not exist, return null
    return isset($data['status']) ? $data['status'] : null;
}

// Function to retrieve the response from the callback URL of the external web API
function getCallbackResponse($missionId) {
<<<<<<< Updated upstream
    $callbackUrl = SERVER_URL . '/api/callback';

    // Callback request parameters
    $params = [
        'missionId' => $missionId,
    ];
=======
    // missionIDを引数として受け取りGETコールバックURLを作成
    // Develop Env
    //$url = 'http://192.168.56.1:8080/api/callback/callback.php?missionId=' . urlencode($missionId);    
    // Production Env
    $url = 'http://192.168.51.41:8080/api/callback/callback.php?missionId=' . urlencode($missionId);
    
    $response = file_get_contents($url);
>>>>>>> Stashed changes
    
    // Settings for the POST request
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/json',
            'content' => json_encode($params),
        ],
    ];

    $context = stream_context_create($options);
    
    $callbackResponse = file_get_contents($callbackUrl, false, $context);
    
    // Get the HTTP status code
    $httpCode = isset($http_response_header) ? substr($http_response_header[0], 9, 3) : null;
    
    // Check for JSON decode errors
    $data = json_decode($callbackResponse, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Decode Error: " . json_last_error_msg());
    }

    // Retrieve and return the Status; if Status does not exist, return null
    return isset($data['Status']) ? $data['Status'] : null;
}

// Function to get the database connection settings
function getDBConnection() {
   try {
        $serverName = "D1ZP3K54";
        $database   = "amr_task_db";
        
        // Using Windows authentication (set username and password to null)
        $conn = new PDO(
            "sqlsrv:Server=$serverName;Database=$database;TrustServerCertificate=1",
            null,  // For Windows authentication, use null
            null
        );
    /*
        $serverName = "D1ZP3K54";  // 1433以外のポートを使っている場合
        $database = "amr_task_db";

        $conn = new PDO(
            "odbc:Driver={ODBC Driver 18 for SQL Server};Server=$serverName;Database=$database;TrustServerCertificate=Yes",
            null,
            null
        );

      */  
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;

   } catch(PDOException $e) {
        throw new Exception("Database error: Unable to connect to the DB. Please verify that Windows authentication is available. " . $e->getMessage());
   }
}

// Function to record the task execution status in the DB
function logTaskExecution($missionId, $status, $details, $additionalData = []) {
   try {
       $conn = getDBConnection();

        $sql = "INSERT INTO task_logs (
                    mission_id, 
                    mission_code,
                    runtime_id,
                    status,
                    allocation_status,
                    sequence,
                    details,
                    error_code,
                    message,
                    start_time,
                    end_time
               ) VALUES (
                    :mission_id, :mission_code, :runtime_id, :status, 
                    :allocation_status, :sequence, :details, :error_code, 
                    :message, :start_time, :end_time
               )";
       
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':mission_id'        => $missionId,
            ':mission_code'      => $additionalData['mission_code'] ?? 'unknown_code',
            ':runtime_id'        => $additionalData['runtime_id'] ?? null,
            ':status'            => $status,
            ':allocation_status' => $additionalData['allocation_status'] ?? 'unassigned',
            ':sequence'          => $additionalData['sequence'] ?? 2,
            ':details'           => $details,
            ':error_code'        => $additionalData['error_code'] ?? null,
            ':message'           => $additionalData['message'] ?? null,
            ':start_time'        => $additionalData['start_time'] ?? null,
            ':end_time'          => $additionalData['end_time'] ?? null,
        ]);

       return true;
   } catch (Exception $e) {
       error_log("Database error: Unable to record task execution status. " . $e->getMessage());
       return false;
   }
}

// Function to retrieve the first pending task (with status not equal to 'COMPLETED') from the task list
function getPendingTask() {
    try {
        $conn = getDBConnection();
        
        $sql = "SELECT TOP 1 mission_id, status 
                FROM task_list 
                WHERE status != 'COMPLETED'
                ORDER BY sequence DESC, created_at ASC;";

        $stmt = $conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['mission_id'] : null;
    } catch (Exception $e) {
        error_log("Database error: Unable to retrieve tasks. " . $e->getMessage());
        return null;
    }
}

// Function to update the task status in the task list
function updateTaskStatus($missionId, $status) {
    try {
        $conn = getDBConnection();
        $sql = "UPDATE task_list SET status = :status, updated_at = GETDATE() WHERE mission_id = :mission_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':status'     => $status,
            ':mission_id' => $missionId
        ]);
    } catch (Exception $e) {
        error_log("Database error: Unable to update task status. " . $e->getMessage());
    }
}

// Function to execute the Web API task
function executeWebAPITask() {

    // Retrieve the first pending task (from task_list) that is not yet completed
    $pendingTask = getPendingTask();
    if (!$pendingTask) {
        echo "No pending tasks found.\n";
        return;
    }
<<<<<<< Updated upstream

    // Get the timestamp at the start of the task
=======
 
    $missionId = $pendingTask;
    // コールバックURLを固定で指定する
    // Develop Env
    // $callbackUrl = 'http://192.168.56.1:8080/api/callback/callback.php';  
    // Production Env
    $callbackUrl = 'http://192.168.51.41:8080/api/callback/callback.php';

>>>>>>> Stashed changes
    $startTime = date('Y-m-d H:i:s');

    // Record the task start in the log
    logTaskExecution($pendingTask, 'STARTED', 'Task execution started', [
       'start_time' => $startTime,
       'end_time'   => null,
    ]);
<<<<<<< Updated upstream
    
    // Set the maximum number of attempts (to avoid exceeding the AMR inquiry limit)
    $maxAttempts = 3;
=======
 
    // 新しいタスク開始時に前回のレスポンスをクリアする（必要に応じて）
    $clearResponseUrl = $callbackUrl . '?missionId=' . urlencode($missionId) . '&clear=1';
    file_get_contents($clearResponseUrl);
 
    $maxAttempts = 1;
>>>>>>> Stashed changes
    $attempts = 0;

    // Loop every 10 seconds until an available AMR is found
    while ($attempts < $maxAttempts) {
       $attempts++;

       // Call the AMR real-time inquiry
       $vehicleStatus = getVehicleStatus();

       if ($vehicleStatus === null) {
           $errorMsg = "Unable to obtain a valid response from the AMR real-time inquiry.";

           logTaskExecution($pendingTask, 'ERROR', $errorMsg, [
               'error_code' => 1001,
               'start_time' => $startTime,
               'end_time'   => date('Y-m-d H:i:s'),
           ]);
           echo "Error: Task execution aborted due to invalid AMR response. " . $errorMsg . "\n";
           break;
       }

       if ($vehicleStatus == 1) {
            $missionWorksParams = ['missionId' => $pendingTask];
            $missionWorks = sendMissionWorksRequest($missionWorksParams);

            if ($missionWorks == null) {
                $errorMsg = "Invalid response from MissionWorks.";

                logTaskExecution($pendingTask, 'ERROR', $errorMsg, [
                   'error_code' => 1002,
                   'start_time' => $startTime,
                   'end_time'   => date('Y-m-d H:i:s'),
                ]);
                echo "Error: Task execution failed. " . $errorMsg . "\n";
                break;
            }

            $callbackResponse = getCallbackResponse($pendingTask);
           
            if (!in_array($missionWorks, ['Create', 'Assigned', 'Wait', 'Running', 'Success'])) {
               $errorMsg = "Invalid response from the callback URL.";
               logTaskExecution($pendingTask, 'ERROR', $errorMsg, [
                   'error_code' => 1003,
                   'start_time' => $startTime,
                   'end_time'   => date('Y-m-d H:i:s'),
               ]);
               echo "Error: " . $errorMsg . "\n";
               break;
            }

            if ($missionWorks == "Success" || $callbackResponse == "Success") {
                updateTaskStatus($pendingTask, 'COMPLETED');
                logTaskExecution($pendingTask, 'COMPLETED', 'Task completed', [
                    'start_time' => $startTime,
                    'end_time'   => date('Y-m-d H:i:s'),
                ]);
                echo "Task completed\n";
                break;
            } else {
                logTaskExecution($pendingTask, 'PROCESSING', 'Task in progress', [
                    'start_time' => $startTime,
                    'end_time'   => null,
                ]);
                echo "Task in progress: status = Processing\n";
            }
       } else {
            // If no available AMR is found, log the wait status
            logTaskExecution($pendingTask, 'Wait', 'AMR waiting', [
                'start_time' => $startTime,
                'end_time'   => null,
            ]);
           
           sleep(10); // Wait for 10 seconds before retrying
       }
   }
    if ($attempts >= $maxAttempts) {
        logTaskExecution($pendingTask, 'MAX_ATTEMPTS_REACHED', 
            'Reached maximum number of attempts (' . $maxAttempts . ')', [
                'error_code' => 1004,
                'start_time' => $startTime,
                'end_time'   => date('Y-m-d H:i:s'),
            ]);
        echo "Maximum number of attempts reached. Terminating process.\n";
   }
}

// Execute the main process
executeWebAPITask();

?>
