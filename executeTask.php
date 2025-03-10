<?php
set_time_limit(0)

// Define the base URL for the YOUICOMPASS installed server
// Production Env
define('SERVER_URL', 'http://192.168.51.51:8080');

//-----------------------------------------------------------------
// Database connection
//-----------------------------------------------------------------
function getDBConnection() {
    try {
         $serverName = "D1ZP3K54\\MSSQLSERVER01";
         $database   = "amr_task_db";
         $username   = "test";
         $password   = "Koito2025";
         
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
 
//-----------------------------------------------------------------
// AMR status inquiry using vehicles API
//-----------------------------------------------------------------
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
         "workStatus"    => $data["workStatus"] ?? null,
         "abnormalStatus"=> $data["abnormalStatus"] ?? null,
         "battery"       => isset($data["battery"]["battery_value"]) ? $data["battery"]["battery_value"] : null
     ];
}
 
//-----------------------------------------------------------------
// Get SMART_CHARGE configuration from agvFunctionConfigs API
// ※実際の充電制御APIは存在しないため、ここでは設定値を取得するだけ
//-----------------------------------------------------------------
function getSmartChargeConfig() {
    $url = SERVER_URL . "/api/v3/agvFunctionConfigs";
    $response = file_get_contents($url);
    
    if (empty($response)) {
        error_log("agvFunctionConfigs response is empty");
        return null;
    }
    
    $configs = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Decode Error in agvFunctionConfigs: " . json_last_error_msg());
        return null;
    }
    
    // Find config with type SMART_CHARGE (compare in uppercase)
    foreach ($configs as $config) {
        if (isset($config["type"]) && strtoupper($config["type"]) === "SMART_CHARGE") {
            $params = json_decode($config["parameter"], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON Decode Error in SMART_CHARGE parameter: " . json_last_error_msg());
                return null;
            }
            return $params; // 例: mustChargeBatteryValue と canChargeBatteryValue
        }
    }
    error_log("SMART_CHARGE config not found");
    return null;
}
 
//-----------------------------------------------------------------
// MissionWorks API call for task execution request
//-----------------------------------------------------------------
function sendMissionWorksRequest($missionId, $missionCode, $runtimeParam, $callbackUrl) {
    $apiUrl = SERVER_URL . "/api/v3/missionWorks";
 
    $payload = [
        "missionId"    => $missionId,
        "missionCode"  => $missionCode,
        "callbackUrl"  => $callbackUrl,
        "runtimeParam" => $runtimeParam
    ];
    
    $context = stream_context_create([
        "http" => [
            "method"        => "POST",
            "header"        => "Content-Type: application/json",
            "content"       => json_encode($payload),
            "ignore_errors" => true
        ]
    ]);
    
    $missionResponse = file_get_contents($apiUrl, false, $context);
 
    $rawStatusLine = isset($http_response_header[0]) ? $http_response_header[0] : "";
    $parts = explode(" ", trim($rawStatusLine));
    $httpCode = isset($parts[1]) ? $parts[1] : null;
    
    if ($missionResponse === false || ($httpCode !== "200" && $httpCode !== "201")) {
        error_log("Error fetching data: HTTP Status Code " . $httpCode);
        error_log("Response: " . $missionResponse);
        die("Error fetching data: HTTP Status Code " . $httpCode);
    }
    
    $data = json_decode($missionResponse, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Decode Error: " . json_last_error_msg());
        error_log("Invalid JSON Response: " . $missionResponse);
        return null;
    }
    
    error_log("MissionWorks Response: " . print_r($data, true));
    return [
        "status"    => $data["status"] ?? null,
        "runtimeId" => $data["id"] ?? null
    ];
}
 
//-----------------------------------------------------------------
// Get callback response via GET from callback.php
//-----------------------------------------------------------------
function getCallbackResponse($missionId) {
    $url = "http://192.168.51.41:8080/api/callback/callback.php?missionId=" . urlencode($missionId);
    $response = file_get_contents($url);
    
    if ($response === false) {
        error_log("Failed to fetch callback response from: " . $url);
        return null;
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Decode Error in getCallbackResponse: " . json_last_error_msg());
        return null;
    }
    
    return $data["Status"] ?? null;
}
 
//-----------------------------------------------------------------
// Task control API calls using runtimeId
//-----------------------------------------------------------------
function continueTaskAPI($runtimeId) {
    $apiUrl = SERVER_URL . "/api/v3/missionWorks/" . urlencode($runtimeId) . "/controls/continue";
    $context = stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => "Content-Type: application/json"
        ]
    ]);
    return file_get_contents($apiUrl, false, $context);
}
 
function resumeTaskAPI($runtimeId) {
    $apiUrl = SERVER_URL . "/api/v3/missionWorks/" . urlencode($runtimeId) . "/controls/resume";
    $context = stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => "Content-Type: application/json"
        ]
    ]);
    return file_get_contents($apiUrl, false, $context);
}
 
function pauseTaskAPI($runtimeId) {
    $apiUrl = SERVER_URL . "/api/v3/missionWorks/" . urlencode($runtimeId) . "/controls/pause";
    $context = stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => "Content-Type: application/json"
        ]
    ]);
    return file_get_contents($apiUrl, false, $context);
}
 
//-----------------------------------------------------------------
// Record task execution log into database
//-----------------------------------------------------------------
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
            ":mission_id" => $missionId,
            ":mission_code" => $additionalData["mission_code"] ?? "unknown_code",
            ":runtime_id" => null,
            ":status" => $status,
            ":allocation_status" => $additionalData["allocation_status"] ?? "unassigned",
            ":sequence" => $additionalData["sequence"] ?? 2,
            ":details" => $details,
            ":error_code" => $additionalData["error_code"] ?? null,
            ":message" => $additionalData["message"] ?? null,
            ":start_time" => $additionalData["start_time"] ?? null,
            ":end_time" => $additionalData["end_time"] ?? null
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Database error: Unable to record task execution status. " . $e->getMessage());
        return false;
    }
}
 
//-----------------------------------------------------------------
// Get pending task from database (mission id)
//-----------------------------------------------------------------
function getPendingTask() {
    try {
        $conn = getDBConnection();
        $sql = "SELECT TOP 1 mission_id, status 
                FROM task_list 
                WHERE status != 'COMPLETED'
                ORDER BY sequence DESC, created_at ASC";
        $stmt = $conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result["mission_id"] : null;
    } catch (Exception $e) {
        error_log("Database error: Unable to retrieve tasks. " . $e->getMessage());
        return null;
    }
}
 
//-----------------------------------------------------------------
// Update task status in database
//-----------------------------------------------------------------
function updateTaskStatus($missionId, $status) {
    try {
        $conn = getDBConnection();
        $sql = "UPDATE task_list SET status = :status, updated_at = GETDATE() WHERE mission_id = :mission_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ":status" => $status,
            ":mission_id" => $missionId
        ]);
    } catch (Exception $e) {
        error_log("Database error: Unable to update task status. " . $e->getMessage());
    }
}
 
//-----------------------------------------------------------------
// Task execution process
//-----------------------------------------------------------------
function executeWebAPITask() {
    $pendingTask = getPendingTask();
    if (!$pendingTask) {
        echo "No pending tasks found<br>\n";
        return;
    }
 
    $missionId = $pendingTask;
    $callbackUrl = "http://192.168.51.41:8080/api/callback/callback.php";
    $startTime = date("Y-m-d H:i:s");
    logTaskExecution($missionId, "STARTED", "Task execution started", [
        "start_time" => $startTime,
        "end_time" => null
    ]);
 
    // Clear previous callback response
    $clearResponseUrl = $callbackUrl . "?missionId=" . urlencode($missionId) . "&clear=1";
    file_get_contents($clearResponseUrl);
 
    // Call MissionWorks API to start task and obtain runtime id
    $missionCode = "";
    $runtimeParam = ["marker1" => ""];
    $runtimeIdData = sendMissionWorksRequest($missionId, $missionCode, $runtimeParam, $callbackUrl);
    if (!$runtimeIdData || empty($runtimeIdData["runtimeId"])) {
        error_log("Failed to obtain runtimeId from MissionWorks response");
        echo "Error: Unable to obtain runtimeId<br>\n";
        return;
    }
    $runtimeId = $runtimeIdData["runtimeId"];
    echo "Received runtimeId " . $runtimeId . " from MissionWorks API<br>\n";
 
    $maxAttempts = 10;
    $attempts = 0;
    $taskCompleted = false;
 
    while ($attempts < $maxAttempts && !$taskCompleted) {
        $attempts++;
        sleep(10); // Wait 10 seconds
 
        // Retrieve callback response using mission id
        $callbackResponse = getCallbackResponse($missionId);
        if ($callbackResponse !== null) {
            if (strcasecmp($callbackResponse, "Success") === 0) {
                echo "Callback Status: " . $callbackResponse . "<br>\n";
                updateTaskStatus($missionId, "COMPLETED");
                logTaskExecution($missionId, "COMPLETED", "Task completed", [
                    "start_time" => $startTime,
                    "end_time" => date("Y-m-d H:i:s")
                ]);
                echo "Task completed<br>\n";
                $taskCompleted = true;
                break;
            } else {
                echo "Callback Status: " . $callbackResponse . " - Waiting for Success...<br>\n";
                logTaskExecution($missionId, "WAITING", "Waiting for Success. Current Callback Status: " . $callbackResponse, [
                    "start_time" => $startTime,
                    "end_time" => null
                ]);
            }
        } else {
            echo "Attempt " . $attempts . " - No callback response received. Waiting...<br>\n";
            logTaskExecution($missionId, "WAITING", "No callback response received. Attempt: " . $attempts, [
                "start_time" => $startTime,
                "end_time" => null
            ]);
        }
 
        // Get AMR status from vehicles API
        $vehicleData = getVehicleStatus();
        if ($vehicleData === null) {
            $errorMsg = "Unable to obtain a valid response from the AMR real time inquiry";
            logTaskExecution($missionId, "ERROR", $errorMsg, [
                "error_code" => 1001,
                "start_time" => $startTime,
                "end_time" => date("Y-m-d H:i:s")
            ]);
            echo "Error: " . $errorMsg . "<br>\n";
            break;
        }
 
        $workStatus = $vehicleData["workStatus"];
        $abnormalStatus = $vehicleData["abnormalStatus"];
        $battery = $vehicleData["battery_value"];
 
        // Battery check: if battery level is less than or equal to 10 percent,judge as battery charging. then wait until battery level is at least 30 percent
        if ($battery !== null && $battery <= 10) {
            echo "Battery low at " . $battery . "%. Initiating charge process...<br>\n";
            $chargeConfig = getSmartChargeConfig();
            if ($chargeConfig !== null) {
                // ここでは必ず30%以上になるまで待機する
                echo "SMART_CHARGE config: mustChargeBatteryValue " . $chargeConfig["mustChargeBatteryValue"] . ", canChargeBatteryValue " . $chargeConfig["canChargeBatteryValue"] . "<br>\n";
                echo "Waiting for battery to charge until it reaches at least 30%...<br>\n";
                while (true) {
                    sleep(10);
                    $vehicleData = getVehicleStatus();
                    $battery = $vehicleData["battery"];
                    if ($battery !== null && $battery >= 30) {
                        echo "Battery charged to " . $battery . "%. Proceeding with task...<br>\n";
                        break;
                    } else {
                        echo "Waiting for battery to charge. Current level: " . $battery . "%<br>\n";
                    }
                }
            }
        }
 
        // Task control decision based on AMR status
        if ($workStatus == 1 && $abnormalStatus == 1) {
            echo "AMR available (workStatus: " . $workStatus . ", abnormalStatus: " . $abnormalStatus . ")<br>\n";
            echo "Calling continueTask API with runtime id " . $runtimeId . "<br>\n";
            $continueResponse = continueTaskAPI($runtimeId);
            echo "continueTaskAPI response: " . $continueResponse . "<br>\n";
        } elseif ($workStatus == 3 || $abnormalStatus != 1) {
            echo "AMR not available (workStatus: " . $workStatus . ", abnormalStatus: " . $abnormalStatus . ")<br>\n";
            echo "Calling pauseTask API with runtime id " . $runtimeId . "<br>\n";
            $pauseResponse = pauseTaskAPI($runtimeId);
            echo "pauseTaskAPI response: " . $pauseResponse . "<br>\n";
        }
    }
 
    if (!$taskCompleted && $attempts >= $maxAttempts) {
        updateTaskStatus($missionId, "MAX_ATTEMPTS_REACHED");
        logTaskExecution($missionId, "MAX_ATTEMPTS_REACHED", "Reached maximum number of attempts: " . $maxAttempts, [
            "error_code" => 1004,
            "start_time" => $startTime,
            "end_time" => date("Y-m-d H:i:s")
        ]);
        echo "Maximum number of attempts reached. Terminating process.<br>\n";
    }
}
 
//-----------------------------------------------------------------
// AMR status monitor process (runs concurrently)
//-----------------------------------------------------------------
function monitorAMRStatus() {
    while (true) {
        $missionId = getPendingTask();
        if (!$missionId) {
            echo "Monitor: No pending task.<br>\n";
            sleep(10);
            continue;
        }
    
        // In monitor process, if runtime id is not generated, use mission id as control id
        $runtimeId = $missionId;
        $vehicleData = getVehicleStatus();
        if ($vehicleData === null) {
            echo "Monitor: Unable to obtain valid AMR response.<br>\n";
            sleep(5);
            continue;
        }
    
        $workStatus = $vehicleData["workStatus"];
        $abnormalStatus = $vehicleData["abnormalStatus"];
        $battery = $vehicleData["battery_value"];
    
        // Battery check in monitor process
        if ($battery !== null && $battery <= 10) {
            echo "Monitor: Battery low at " . $battery . "%. Retrieving SMART_CHARGE config...<br>\n";
            $chargeConfig = getSmartChargeConfig();
            if ($chargeConfig !== null) {
                echo "Monitor: SMART_CHARGE config: mustChargeBatteryValue " . $chargeConfig["mustChargeBatteryValue"] . ", canChargeBatteryValue " . $chargeConfig["canChargeBatteryValue"] . "<br>\n";
                echo "Monitor: Waiting for battery to charge until at least 30%...<br>\n";
                while (true) {
                    sleep(10);
                    $vehicleData = getVehicleStatus();
                    $battery = $vehicleData["battery"];
                    if ($battery !== null && $battery >= 30) {
                        echo "Monitor: Battery charged to " . $battery . "%. Proceeding.<br>\n";
                        break;
                    } else {
                        echo "Monitor: Waiting for battery to charge. Current level: " . $battery . "%<br>\n";
                    }
                }
            }
        }
    
        if ($workStatus == 1 && $abnormalStatus == 1) {
            echo "Monitor: AMR normal. Calling continueTask API with runtime id " . $runtimeId . "<br>\n";
            $response = continueTaskAPI($runtimeId);
            echo "Monitor: continueTaskAPI response: " . $response . "<br>\n";
        } elseif ($workStatus == 3 || $abnormalStatus != 1) {
            echo "Monitor: AMR charging or abnormal. Calling pauseTask API with runtime id " . $runtimeId . "<br>\n";
            $response = pauseTaskAPI($runtimeId);
            echo "Monitor: pauseTaskAPI response: " . $response . "<br>\n";
        }
    
        sleep(10);
    }
}
 
//-----------------------------------------------------------------
// Concurrent process execution using pcntl_fork
//-----------------------------------------------------------------
if (function_exists("pcntl_fork")) {
    $pid = pcntl_fork();
    if ($pid == -1) {
        die("Could not fork");
    } else if ($pid == 0) {
        monitorAMRStatus();
        exit(0);
    } else {
        executeWebAPITask();
    }
} else {
    echo "pcntl_fork is not available. Running tasks sequentially.<br>\n";
    executeWebAPITask();
}
?>
