 <?php
 set_time_limit(0); // タイムアウトなし
 
 // Define the base URL for the server
 //define('SERVER_URL', 'http://192.168.51.51:8080');
 define('SERVER_URL', 'https://3aca9239-01d0-43b9-80ca-97bb21637841.mock.pstmn.io');
 
 // Function to get the database connection settings
 function getDBConnection() {
    try {
         //develop Env
         $serverName = "DESKTOP-DQGJI2I";
         //Production Env
         //$serverName = "D1ZP3K54\\MSSQLSERVER01";
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
         throw new Exception("Database error: Unable to connect to the DB. Please verify that Windows authentication is available. " . $e->getMessage());
    }
 }
 
 // Function to call the external web API "AMR Real-time Inquiry (/api/v3/vehicles)" and retrieve the workStatus value
 function getVehicleStatus() {
    // Production and Develop Env
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
 function sendMissionWorksRequest($params, $callbackUrl) {
    //Develop Env
     //$apiUrl = 'http://192.168.56.1/api/v3/missionWorks';
    //Production Env
     $apiUrl = SERVER_URL . "/api/v3/missionWorks";
     
     // callbackUrl を設定
     $params['callbackUrl'] = $callbackUrl;
     
     $context = stream_context_create([
         'http' => [
             'method'        => 'POST',
             'header'        => 'Content-Type: application/json',
             'content'       => json_encode($params),
             'ignore_errors' => true
         ]
     ]);
     
     $missionResponse = file_get_contents($apiUrl, false, $context);
 
     //get HTTP Status code 
     $rawStatusLine = isset($http_response_header[0]) ? $http_response_header[0] : '';
     $parts = explode(' ', trim($rawStatusLine));
     $httpCode = isset($parts[1]) ? $parts[1] : null;
     
     //check HTTP Status code 
     if ($missionResponse === false || ($httpCode !== '200' && $httpCode !== '201')) {
         error_log("Error fetching data: HTTP Status Code " . $httpCode);
         error_log("Response: " . $missionResponse);
         die("Error fetching data: HTTP Status Code " . $httpCode);
     }
     
     $data = json_decode($missionResponse, true);
     if (json_last_error() !== JSON_ERROR_NONE) {
         error_log("JSON Decode Error: " . json_last_error_msg());
         error_log("Invalid JSON Response: " . $missionResponse);
     }
     
     // レスポンスの内容をログに出力
     error_log("MissionWorks Response: " . print_r($data, true));
     return isset($data['status']) ? $data['status'] : null;
 }
 
 function getCallbackResponse($missionId) {
    // Production Env
    //$url = 'http://192.168.51.41:8080/api/callback/callback.php?missionId=' . urlencode($missionId);
    // Develop Env
    $url = 'http://192.168.56.1:8080/api/callback/callback.php?missionId=' . urlencode($missionId);
    // GET リクエストを送信
    $response = file_get_contents($url);
    
    if ($response === false) {
        error_log("Failed to fetch callback response from: $url");
        return null;
    }
    
    // JSON をデコード
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Decode Error in getCallbackResponse: " . json_last_error_msg());
        return null;
    }
    
    // callback.php の GET 部分は、有効なレスポンスがあれば {"Status": ...} を返す
    return isset($data['Status']) ? $data['Status'] : null;
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
 
 // Function to retrieve the first pending task from the task list
 function getPendingTask() {
     try {
         $conn = getDBConnection();
         $sql = "SELECT TOP 1 mission_id, status 
                 FROM task_list 
                 WHERE status != 'COMPLETED'
                 ORDER BY sequence DESC, created_at ASC";
         $stmt = $conn->query($sql);
         $result = $stmt->fetch(PDO::FETCH_ASSOC);
         return $result ? $result['mission_id'] : null;
     } catch (Exception $e) {
         error_log("Database error: Unable to retrieve tasks. " . $e->getMessage());
         return null;
     }
 }
 
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
 
 // Function to update the task status in the task list
 function executeWebAPITask() {
     $pendingTask = getPendingTask();
     if (!$pendingTask) {
         echo "No pending tasks found.\n";
         return;
     }
 
     $missionId = $pendingTask;
     // Production Env
     //$callbackUrl = 'http://192.168.51.41:8080/api/callback/callback.php';
     // Develop Env
     $callbackUrl = 'http://192.168.56.1:8080/api/callback/callback.php';

     $startTime = date('Y-m-d H:i:s');
     logTaskExecution($missionId, 'STARTED', 'Task execution started', [
         'start_time' => $startTime,
         'end_time'   => null,
     ]);
 
     // 新しいタスク開始時に前回のレスポンスをクリア
     $clearResponseUrl = $callbackUrl . '?missionId=' . urlencode($missionId) . '&clear=1';
     file_get_contents($clearResponseUrl); // レスポンスをクリアするリクエスト
 
     $maxAttempts = 10;
     $attempts = 0;
     $taskCompleted = false;
 
     // タスク実行ループ内の処理例
while ($attempts < $maxAttempts && !$taskCompleted) {
    $attempts++;
    
    // コールバック確認前に待機
    sleep(3); // 3秒待機

    // コールバックレスポンスを取得
    // タスクの進捗をチェックするループ内の一部
// タスクの進捗をチェックするループ内の一部
$callbackResponse = getCallbackResponse($missionId);
if ($callbackResponse !== null) {
    if (strcasecmp($callbackResponse, 'Success') === 0) {
        // Success が返ってきた場合、"Callback Status: Success" を出力してからタスク完了処理を行う
        echo "Callback Status: " . $callbackResponse . "<br>\n";
        updateTaskStatus($missionId, 'COMPLETED');
        logTaskExecution($missionId, 'COMPLETED', 'Task completed', [
            'start_time' => $startTime,
            'end_time'   => date('Y-m-d H:i:s'),
        ]);
        echo "Task completed<br>\n";
        $taskCompleted = true;
        break;
    } else {
        // Success 以外の場合はその状態を表示する
        echo "Callback Status: " . $callbackResponse . " - Waiting for Success...<br>\n";
        logTaskExecution($missionId, 'WAITING', "Waiting for Success. Current Status: " . $callbackResponse, [
            'start_time' => $startTime,
            'end_time'   => null,
        ]);
    }
} else {
    // レスポンスが null の場合
    echo "Attempt $attempts - No callback response received. Waiting...<br>\n";
    logTaskExecution($missionId, 'WAITING', "No callback response received. Attempt: $attempts", [
        'start_time' => $startTime,
        'end_time'   => null,
    ]);
}


    
    // AMRのステータスも確認（必要に応じて）
    $vehicleStatus = getVehicleStatus();
    if ($vehicleStatus === null) {
        $errorMsg = "Unable to obtain a valid response from the AMR real-time inquiry.";
        logTaskExecution($missionId, 'ERROR', $errorMsg, [
            'error_code' => 1001,
            'start_time' => $startTime,
            'end_time'   => date('Y-m-d H:i:s'),
        ]);
        echo "Error: " . $errorMsg . "\n";
        break;
    }
    
    if ($vehicleStatus == 1) {
        // AMRが利用可能な場合、MissionWorks API を呼び出す処理（ここは既存の処理）
        $missionWorksParams = ['missionId' => $missionId];
        $missionWorks = sendMissionWorksRequest($missionWorksParams, $callbackUrl);
        if ($missionWorks === null) {
            $errorMsg = "Invalid response from MissionWorks.";
            logTaskExecution($missionId, 'ERROR', $errorMsg, [
                'error_code' => 1002,
                'start_time' => $startTime,
                'end_time'   => date('Y-m-d H:i:s'),
            ]);
            echo "Error: " . $errorMsg . "\n";
            break;
        }
        
        // タスクの進捗を記録
        logTaskExecution($missionId, 'PROCESSING', 'Task in progress', [
            'start_time' => $startTime,
            'end_time'   => null,
        ]);
        echo "Task in progress: status = Processing\n";
    } else {
        // AMRが待機中の場合
        logTaskExecution($missionId, 'WAIT', 'AMR waiting', [
            'start_time' => $startTime,
            'end_time'   => null,
        ]);
        echo "AMR is waiting...\n";
        sleep(10); // 10秒待機
    }
}

if (!$taskCompleted && $attempts >= $maxAttempts) {
    logTaskExecution($missionId, 'MAX_ATTEMPTS_REACHED', 'Reached maximum number of attempts (' . $maxAttempts . ')', [
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