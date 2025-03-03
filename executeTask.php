<?php
set_time_limit(0); // タイムアウトなし

// Define the base URL for the YOUICOMPASS installed server
// Develop Env（Mock Server）
define('SERVER_URL', 'https://3aca9239-01d0-43b9-80ca-97bb21637841.mock.pstmn.io');
// Production Env
//define('SERVER_URL', 'http://192.168.51.51:8080');

//-------------------------------------------------
// DB接続
//-------------------------------------------------
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
 
//-------------------------------------------------
// AMR実行状況照会（vehicles API）
//-------------------------------------------------
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
     
     // workStatus と abnormalStatus を返す
     return [
         'workStatus'    => $data['workStatus'] ?? null,
         'abnormalStatus'=> $data['abnormalStatus'] ?? null
     ];
}
 
//-------------------------------------------------
// MissionWorks API 呼び出し（タスク実行リクエスト）
//-------------------------------------------------
/*
 仕様:
 {
    "missionId": "752170cb-f1e9-4c0a-8b07-530e533c1d6c",
    "missionCode": "",
    "callbackUrl": "",
    "runtimeParam": {
         "marker1": ""
    }
 }
 ※レスポンスには、"status" とタスク実行時に生成される一意の runtime id (フィールド名 "id") が含まれる
*/
function sendMissionWorksRequest($missionId, $missionCode, $runtimeParam, $callbackUrl) {
    $apiUrl = SERVER_URL . '/api/v3/missionWorks';
 
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
 
    // HTTPステータス取得
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
    // 返り値は、status と runtime id (フィールド名 "id")
    return [
        'status'    => $data['status'] ?? null,
        'runtimeId' => $data['id'] ?? null
    ];
}
 
//-------------------------------------------------
// コールバックレスポンス取得（GETリクエスト）
// ※仕様では、コールバックのGETパラメータは元のタスクID（missionId）を利用する
function getCallbackResponse($missionId) {
    // DBに登録されているタスクID（missionId）で検索
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
 
//-------------------------------------------------
// タスクログ記録（DBへ）
//-------------------------------------------------
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
            ':mission_id'        => $missionId, // DBには元のタスクID (missionId) が登録されている
            ':mission_code'      => $additionalData['mission_code'] ?? 'unknown_code',
            ':runtime_id'        => $missionId, // DB上は mission_id のみ登録
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
 
//-------------------------------------------------
// DBから未完了タスクの mission_id を取得
//-------------------------------------------------
function getPendingTask() {
    try {
        $conn = getDBConnection();
        $sql = "SELECT TOP 1 mission_id, runtime_id, status 
                FROM task_list 
                WHERE status != 'COMPLETED'
                ORDER BY sequence DESC, created_at ASC";
        $stmt = $conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // ここでは、元のタスクID（mission_id）を返す
        return $result ? $result['mission_id'] : null;
    } catch (Exception $e) {
        error_log("Database error: Unable to retrieve tasks. " . $e->getMessage());
        return null;
    }
}
 
//-------------------------------------------------
// タスク状態の更新（DB）
//-------------------------------------------------
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
 
//-------------------------------------------------
// タスク制御API 呼び出し
//-------------------------------------------------
function continueTaskAPI($runtimeId) {
    $apiUrl = SERVER_URL . "/api/v3/missionWorks/" . urlencode($runtimeId) . "/controls/continue";
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json'
        ]
    ]);
    return file_get_contents($apiUrl, false, $context);
}
 
function resumeTaskAPI($runtimeId) {
    $apiUrl = SERVER_URL . "/api/v3/missionWorks/" . urlencode($runtimeId) . "/controls/resume";
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json'
        ]
    ]);
    return file_get_contents($apiUrl, false, $context);
}
 
function pauseTaskAPI($runtimeId) {
    $apiUrl = SERVER_URL . "/api/v3/missionWorks/" . urlencode($runtimeId) . "/controls/pause";
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json'
        ]
    ]);
    return file_get_contents($apiUrl, false, $context);
}
 
//-------------------------------------------------
// タスク実行処理（executeWebAPITask）
//-------------------------------------------------
function executeWebAPITask() {
    while (true) {
        $pendingTask = getPendingTask();
        if (!$pendingTask) {
            echo "No pending tasks found.<br>\n";
            sleep(10);
            continue;
        }
    
        // DBには mission_id (元のタスクID) が登録されている
        $missionId = $pendingTask;
        // コールバックURL（開発環境）
        $callbackUrl = 'http://192.168.56.1:8080/api/callback/callback.php';
        // Production Env 例: $callbackUrl = 'http://192.168.51.41:8080/api/callback/callback.php';
    
        $startTime = date('Y-m-d H:i:s');
        logTaskExecution($missionId, 'STARTED', 'Task execution started', [
            'start_time' => $startTime,
            'end_time'   => null,
        ]);
    
        // タスク開始時、前回のコールバックレスポンスをクリア
        $clearResponseUrl = $callbackUrl . '?missionId=' . urlencode($missionId) . '&clear=1';
        file_get_contents($clearResponseUrl);
    
        $maxAttempts = 10;
        $attempts = 0;
        $taskCompleted = false;
        $runtimeId = null; // 後続のタスク制御用 runtimeId（生成された値）
    
        while ($attempts < $maxAttempts && !$taskCompleted) {
            $attempts++;
    
            sleep(10); // 10秒待機
    
            // コールバックレスポンスの取得（DBの mission_id を使って検索）
            $callbackResponse = getCallbackResponse($missionId);
            if ($callbackResponse !== null) {
                if (strcasecmp($callbackResponse, 'Success') === 0) {
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
                    echo "Callback Status: " . $callbackResponse . " - Waiting for Success...<br>\n";
                    logTaskExecution($missionId, 'WAITING', "Waiting for Success. Current Callback Status: " . $callbackResponse, [
                        'start_time' => $startTime,
                        'end_time'   => null,
                    ]);
                }
            } else {
                echo "Attempt $attempts - No callback response received. Waiting...<br>\n";
                logTaskExecution($missionId, 'WAITING', "No callback response received. Attempt: $attempts", [
                    'start_time' => $startTime,
                    'end_time'   => null,
                ]);
            }
    
            // AMRの実行状況取得
            $vehicleData = getVehicleStatus();
            if ($vehicleData === null) {
                $errorMsg = "Unable to obtain a valid response from the AMR real-time inquiry.";
                logTaskExecution($missionId, 'ERROR', $errorMsg, [
                    'error_code' => 1001,
                    'start_time' => $startTime,
                    'end_time'   => date('Y-m-d H:i:s'),
                ]);
                echo "Error: " . $errorMsg . "<br>\n";
                break;
            }
    
            $workStatus = $vehicleData['workStatus'];
            $abnormalStatus = $vehicleData['abnormalStatus'];
    
            // 条件 U：正常状態の場合 (workStatus == 1 AND abnormalStatus == 1)
            if ($workStatus == 1 && $abnormalStatus == 1) {
                // 正常状態なら、MissionWorks API を送信してタスク実行リクエスト
                $missionCode  = ""; // 任意
                $runtimeParam = ["marker1" => ""]; // 任意のランタイムパラメータ
                $missionWorksResponse = sendMissionWorksRequest($missionId, $missionCode, $runtimeParam, $callbackUrl);
                if ($missionWorksResponse === null || $missionWorksResponse['status'] === null) {
                    $errorMsg = "Invalid response from MissionWorks.";
                    logTaskExecution($missionId, 'ERROR', $errorMsg, [
                        'error_code' => 1002,
                        'start_time' => $startTime,
                        'end_time'   => date('Y-m-d H:i:s'),
                    ]);
                    echo "Error: " . $errorMsg . "<br>\n";
                    break;
                }
                // 生成された runtimeId を取得し、以降のタスク制御APIに利用
                $runtimeId = $missionWorksResponse['runtimeId'];
                echo "MissionWorks API sent. Received runtimeId: " . $runtimeId . "<br>\n";
                logTaskExecution($missionId, 'PROCESSING', 'Task in progress', [
                    'start_time' => $startTime,
                    'end_time'   => null,
                ]);
                echo "Task in progress: status = Processing<br>\n";
            } else {
                // 条件 U 以外の場合：指定異常として処理終了
                echo "Vehicle status abnormal (workStatus: $workStatus, abnormalStatus: $abnormalStatus).<br>\n";
                logTaskExecution($missionId, 'ERROR', "Specified abnormal condition encountered in vehicle status (workStatus: $workStatus, abnormalStatus: $abnormalStatus)", [
                    'error_code' => 999,
                    'start_time' => $startTime,
                    'end_time'   => date('Y-m-d H:i:s'),
                ]);
                break;
            }
        }
    
        if (!$taskCompleted && $attempts >= $maxAttempts) {
            logTaskExecution($missionId, 'MAX_ATTEMPTS_REACHED', 'Reached maximum number of attempts (' . $maxAttempts . ')', [
                'error_code' => 1004,
                'start_time' => $startTime,
                'end_time'   => date('Y-m-d H:i:s'),
            ]);
            echo "Maximum number of attempts reached. Terminating process.<br>\n";
        }
    }
}
 
//-------------------------------------------------
// AMR状態監視プロセス（独立プロセスで実行）
//-------------------------------------------------
function monitorAMRStatus() {
    while (true) {
        $missionId = getPendingTask();
        if (!$missionId) {
            echo "Monitor: No pending task.<br>\n";
            sleep(10);
            continue;
        }
    
        $vehicleData = getVehicleStatus();
        if ($vehicleData === null) {
            $errorMsg = "Unable to obtain a valid response from the AMR real-time inquiry in monitor.";
            error_log($errorMsg);
            echo $errorMsg . "<br>\n";
            sleep(5);
            continue;
        }
    
        $workStatus = $vehicleData['workStatus'];
        $abnormalStatus = $vehicleData['abnormalStatus'];
    
        // Monitor側は、取得した missionId を利用してタスク制御を実行
        if ($workStatus == 1 && $abnormalStatus == 1) {
            echo "Monitor: AMR is normal. Calling continueTask API for missionId: $missionId<br>\n";
            $response = continueTaskAPI($missionId); // ※本来は runtimeId を利用すべきだが、ここでは例として missionId を渡す
            echo "Monitor continueTask response: " . $response . "<br>\n";
        } elseif ($workStatus == 3 || $abnormalStatus != 1) {
            echo "Monitor: AMR is charging or abnormal. Calling pauseTask API for missionId: $missionId<br>\n";
            $response = pauseTaskAPI($missionId); // ※本来は runtimeId を利用すべきだが、ここでは例として missionId を渡す
            echo "Monitor pauseTask response: " . $response . "<br>\n";
        }
    
        sleep(10); // 10秒毎に監視
    }
}
 
//-------------------------------------------------
// 並行処理実行（pcntl_fork を利用）
//-------------------------------------------------
if (function_exists('pcntl_fork')) {
    $pid = pcntl_fork();
    if ($pid == -1) {
        die("Could not fork");
    } else if ($pid == 0) {
        // 子プロセス: AMR状態監視
        monitorAMRStatus();
        exit(0);
    } else {
        // 親プロセス: タスク実行
        executeWebAPITask();
    }
} else {
    echo "pcntl_fork() is not available. Running tasks sequentially.<br>\n";
    executeWebAPITask();
}
?>
