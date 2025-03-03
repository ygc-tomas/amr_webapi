<?php
set_time_limit(0); // タイムアウトなし

// Define the base URL for the YOUICOMPASS installed server
// Develop Env（Mock Server）
define('SERVER_URL', 'https://3aca9239-01d0-43b9-80ca-97bb21637841.mock.pstmn.io');
// Production Env
//define('SERVER_URL', 'http://192.168.51.51:8080');

// Function to get the database connection settings
function getDBConnection() {
    try {
         // Develop Env
         $serverName = "DESKTOP-DQGJI2I";
         // Production Env
         // $serverName = "D1ZP3K54\\MSSQLSERVER01";
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
// 修正: workStatus と abnormalStatus の両方を返す
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
     
     return [
         'workStatus'    => $data['workStatus'] ?? null,
         'abnormalStatus'=> $data['abnormalStatus'] ?? null
     ];
}
 
// Function to call the external web API "Request Parameter Sending (MissionWorks)"
// 仕様に合わせ、missionId、missionCode、runtimeParam、callbackUrl を引数として受け取る
function sendMissionWorksRequest($missionId, $missionCode, $runtimeParam, $callbackUrl) {
    $apiUrl = SERVER_URL . '/api/v3/missionWorks';
 
    // リクエストペイロードを作成
    $payload = [
        "missionId"    => $missionId,
        "missionCode"  => $missionCode,
        "callbackUrl"  => $callbackUrl,
        "runtimeParam" => $runtimeParam
    ];
    
    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => 'Content-Type: application/json',
            'content'       => json_encode($payload),
            'ignore_errors' => true
        ]
    ]);
    
    $missionResponse = file_get_contents($apiUrl, false, $context);
 
    // Get HTTP status code from response headers
    $rawStatusLine = isset($http_response_header[0]) ? $http_response_header[0] : '';
    $parts = explode(' ', trim($rawStatusLine));
    $httpCode = isset($parts[1]) ? $parts[1] : null;
    
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
    
    error_log("MissionWorks Response: " . print_r($data, true));
    return isset($data['status']) ? $data['status'] : null;
}
 
// Function to retrieve the asynchronous callback response from the external web API
// 仕様: GET リクエストで "missionId" パラメータを利用
function getCallbackResponse($missionId) {
    // Develop Env
    $url = 'http://192.168.56.1:8080/api/callback/callback.php?missionId=' . urlencode($missionId);
    // Production Env
    // $url = 'http://192.168.51.41:8080/api/callback/callback.php?missionId=' . urlencode($missionId);
    
    $response = file_get_contents($url);
    
    if ($response === false) {
        error_log("Failed to fetch callback response from: $url");
        return null;
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Decode Error in getCallbackResponse: " . json_last_error_msg());
        return null;
    }
    
    return $data['Status'] ?? null;
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
 
// --- 新規追加: タスク制御API 呼び出し関数 ---
// タスクを続行する
function continueTask() {
    $apiUrl = SERVER_URL . "/api/v3/missionWorks/" . urlencode($missionId) . "/controls/continue";
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json'
        ]
    ]);
    return file_get_contents($apiUrl, false, $context);
}
 
// タスクを再開する
function resumeTask() {
    $apiUrl = SERVER_URL . "/api/v3/missionWorks/" . urlencode($missionId) . "/controls/resume";
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json'
        ]
    ]);
    return file_get_contents($apiUrl, false, $context);
}
 
// タスクを一時停止する
function pauseTask() {
    $apiUrl = SERVER_URL . "/api/v3/missionWorks/" . urlencode($missionId) . "/controls/pause";
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json'
        ]
    ]);
    return file_get_contents($apiUrl, false, $context);
}
 
// Function to execute the Web API task
function executeWebAPITask() {
    while(true) {
        $pendingTask = getPendingTask();
        if (!$pendingTask) {
            echo "No pending tasks found.<br>\n";
            sleep(10); //10秒待機
            continue;
        }
    
        $missionId = $pendingTask;
        // コールバックURLを固定で指定する
        // Develop Env
        $callbackUrl = 'http://192.168.56.1:8080/api/callback/callback.php';
        // Production Env
        // $callbackUrl = 'http://192.168.51.41:8080/api/callback/callback.php';
    
        $startTime = date('Y-m-d H:i:s');
        logTaskExecution($missionId, 'STARTED', 'Task execution started', [
            'start_time' => $startTime,
            'end_time'   => null,
        ]);
    
        // 新しいタスク開始時に前回のレスポンスをクリアする（必要に応じて）
        $clearResponseUrl = $callbackUrl . '?missionId=' . urlencode($missionId) . '&clear=1';
        file_get_contents($clearResponseUrl);
    
        $maxAttempts = 10;
        $attempts = 0;
        $taskCompleted = false;
    
        while ($attempts < $maxAttempts && !$taskCompleted) {
            $attempts++;
    
            // タスク制御前に待機（ここでは10秒）
            sleep(10);
    
            // まず、コールバックレスポンスを取得
            $callbackResponse = getCallbackResponse($missionId);
                if (strcasecmp($callbackResponse, 'Success') === 0) {
                    echo "Callback Status: " . $callbackResponse . "<br>\n";
                    updateTaskStatus($missionId, 'COMPLETED');
                    logTaskExecution($missionId, 'COMPLETED', 'Task completed', [
                        'start_time' => $startTime,
                        'end_time'   => date('Y-m-d H:i:s'),
                    ]);
                    echo "Task completed<br>\n";
                    $taskCompleted = true;
                } else {
                    echo "Callback Status: " . $callbackResponse . " - Waiting for Success...<br>\n";
                    logTaskExecution($missionId, 'WAITING', "Waiting for Success. Current Status: " . $callbackResponse, [
                        'start_time' => $startTime,
                        'end_time'   => null,
                    ]);
                }
        }

        if (!$taskCompleted) {
            echo "Maximum number of attempts reached. Terminating process.\n";
        }
    }
}

// AMRの実行状況を監視し、タスクを制御する関数
function monitorAMRStatus() {
    while (true) {
        // AMRの実行状況照会を実施
        $vehicleStatus = getVehicleStatus();

        if ($vehicleStatus === null) {
            $errorMsg = "Unable to obtain a valid response(status) from the AMR real-time.Try again...\n";
            logTaskExecution($missionId, 'ERROR', $errorMsg, [
                'error_code' => 1001,
                'start_time' => $startTime,
                'end_time'   => date('Y-m-d H:i:s'),
            ]);
            echo "Error: " . $errorMsg . "<br>\n";
            sleep(5);
            continue;
        }

        $workStatus = $vehicleStatus['workStatus'];
        $abnormalStatus = $vehicleStatus['abnormalStatus'];

        // ここで、AMR の状態に基づきタスク制御APIを呼び出す
        // - 正常状態 (workStatus == 1 && abnormalStatus == 1) → タスクを続行する (continueTask)
        // - 充電中 (workStatus == 3) または 異常がある (abnormalStatus != 1) → タスクを一時停止 (pauseTask)
        if ($workStatus == 1 && $abnormalStatus == 1) {
            // 正常状態の場合、タスクを続行
            echo "Called continueTask API<br>\n";
            continueTask();
        } elseif ($workStatus == 3 || $abnormalStatus != 1) {
            // 充電中または異常の場合、タスクを一時停止¥¥
            echo "Called pauseTask API<br>\n";
            pauseTask();
        }

        sleep(10); // 10秒待機
    }
}
 
// **AMR監視を並行処理で実行**
if (pcntl_fork() == 0) {
    monitorAMRStatus();
} else {
    executeWebAPITask();
}
?>

