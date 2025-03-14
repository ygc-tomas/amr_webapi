<?php
set_time_limit(0); // タイムアウトなし

// Define the base URL for the YOUICOMPASS installed server
// Production Env
define('SERVER_URL', 'http://192.168.51.51:8080');
// For development, you may use a mock server URL
// define('SERVER_URL', 'https://3aca9239-01d0-43b9-80ca-97bb21637841.mock.pstmn.io');

//-------------------------------------------------
// Database connection
//-------------------------------------------------
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
 
//-------------------------------------------------
// AMR status inquiry using vehicles API
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
     
     return [
         "workStatus"    => $data["workStatus"] ?? null,
         "abnormalStatus"=> $data["abnormalStatus"] ?? null,
         "battery_value" => isset($data["battery"]["battery_value"]) ? $data["battery"]["battery_value"] : null
     ];
}
 
//-------------------------------------------------
// Get SMART_CHARGE configuration from agvFunctionConfigs API
//-------------------------------------------------
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
    
    foreach ($configs as $config) {
        if (isset($config["type"]) && strtoupper($config["type"]) === "SMART_CHARGE") {
            $params = json_decode($config["parameter"], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON Decode Error in SMART_CHARGE parameter: " . json_last_error_msg());
                return null;
            }
            return $params;
        }
    }
    error_log("SMART_CHARGE config not found");
    return null;
}
 
//-------------------------------------------------
// Smart Charge control API calls
//-------------------------------------------------
function setSmartChargeOff() {
    $url = SERVER_URL . "/api/v3/agvFunctionConfigs/smart_charge_common";
    $payload = [
        "id" => "smart_charge_common",
        "type" => "SMART_CHARGE",
        "parameter" => "{\"isEnable\":1,\"mustChargeBatteryValue\":20,\"canChargeBatteryValue\":45,\"minChargeTime\":300,\"freeTime\":\"0\",\"chargePlan\":1,\"chargeTime\":\"\",\"interruptBatteryValue\":20,\"chargeMode\":1,\"chargeMarkerId\":\"\",\"retryTime\":3}",
        "createTime" => "",
        "updateTime" => ""
    ];
    $context = stream_context_create([
        "http" => [
            "method" => "PUT",
            "header" => "Content-Type: application/json",
            "content" => json_encode($payload),
            "ignore_errors" => true
        ]
    ]);
    return file_get_contents($url, false, $context);
}

function setSmartChargeOn() {
    $url = SERVER_URL . "/api/v3/agvFunctionConfigs/smart_charge_common";
    $payload = [
        "id" => "smart_charge_common",
        "type" => "SMART_CHARGE",
        "parameter" => "{\"isEnable\":0,\"mustChargeBatteryValue\":20,\"canChargeBatteryValue\":45,\"minChargeTime\":300,\"freeTime\":\"0\",\"chargePlan\":1,\"chargeTime\":\"\",\"interruptBatteryValue\":20,\"chargeMode\":1,\"chargeMarkerId\":\"\",\"retryTime\":3}",
        "createTime" => "",
        "updateTime" => ""
    ];
    $context = stream_context_create([
        "http" => [
            "method" => "PUT",
            "header" => "Content-Type: application/json",
            "content" => json_encode($payload),
            "ignore_errors" => true
        ]
    ]);
    return file_get_contents($url, false, $context);
}
 
//-------------------------------------------------
// MissionWorks API call for task execution request
//-------------------------------------------------
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
 
//-------------------------------------------------
// Get callback response via GET from callback.php using runtime id
//-------------------------------------------------
function getCallbackResponse($runtimeId) {
    $url = "http://192.168.51.41:8080/api/callback/callback.php?runtimeId=" . urlencode($runtimeId);
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
    error_log("Callback JSON response: " . $response);
    return $data["Status"] ?? null;
}
 
//-------------------------------------------------
// Record task execution log into database
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
            ":mission_id" => $missionId,
            ":mission_code" => $additionalData["mission_code"] ?? "unknown_code",
            ":runtime_id" => isset($additionalData["runtime_id"]) ? $additionalData["runtime_id"] : null,
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
 
//-------------------------------------------------
// Get pending task from database (mission id)
//-------------------------------------------------
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
 
//-------------------------------------------------
// Update task status in database
//-------------------------------------------------
function updateTaskStatus($missionId, $status) {
    error_log("updateTaskStatus: missionId=" . $missionId . " status=" . $status);
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
 
//-------------------------------------------------
// Task control API calls using runtime id
//-------------------------------------------------
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
 
//-------------------------------------------------
// Global mapping of mission id to runtime id (in memory)
//-------------------------------------------------
$runtimeMapping = array();
 
//-------------------------------------------------
// Task execution process (continuously process pending tasks)
//-------------------------------------------------
function executeWebAPITask() {
    global $runtimeMapping;
    
    while (true) {
        $pendingTask = getPendingTask();
        if (!$pendingTask) {
            echo "No pending tasks found<br>\n";
            sleep(10);
            continue;
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
    
        // Disable Smart Charge for task execution
        setSmartChargeOff();
        echo "Smart Charge turned OFF for task execution<br>\n";
    
        // Call MissionWorks API to start task and obtain runtime id
        $missionCode = "";
        $runtimeParam = ["marker1" => ""];
        $runtimeIdData = sendMissionWorksRequest($missionId, $missionCode, $runtimeParam, $callbackUrl);
        if (!$runtimeIdData || empty($runtimeIdData["runtimeId"])) {
            error_log("Failed to obtain runtimeId from MissionWorks response");
            echo "Error: Unable to obtain runtimeId<br>\n";
            continue;
        }
        $runtimeId = $runtimeIdData["runtimeId"];
        // Store runtime id in global mapping
        $runtimeMapping[$missionId] = $runtimeId;
        echo "Received runtimeId " . $runtimeId . " from MissionWorks API<br>\n";
    
        $maxAttempts = 10;
        $attempts = 0;
        $taskCompleted = false;
    
        while ($attempts < $maxAttempts && $taskCompleted === false) {
            $attempts++;
            sleep(10); // Wait 10 seconds
    
            // Retrieve callback response using runtime id
            $callbackResponse = getCallbackResponse($runtimeId);
            error_log("Callback response for runtimeId $runtimeId: " . $callbackResponse);
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
    
            // Battery check: if battery <= 10, wait until charged to at least the canChargeBatteryValue
            if ($battery !== null && $battery <= 10) {
                echo "Battery low at " . $battery . "%. Initiating charge process...<br>\n";
                $chargeConfig = getSmartChargeConfig();
                if ($chargeConfig !== null) {
                    echo "SMART_CHARGE config: mustChargeBatteryValue " . $chargeConfig["mustChargeBatteryValue"] . ", canChargeBatteryValue " . $chargeConfig["canChargeBatteryValue"] . "<br>\n";
                    echo "Waiting for battery to charge until it reaches at least " . $chargeConfig["canChargeBatteryValue"] . "%...<br>\n";
                    while (true) {
                        sleep(10);
                        $vehicleData = getVehicleStatus();
                        $battery = $vehicleData["battery_value"];
                        if ($battery !== null && $battery >= $chargeConfig["canChargeBatteryValue"]) {
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
                echo "Calling resumeTask API with runtime id " . $runtimeId . "<br>\n";
                $response = resumeTaskAPI($runtimeId);
                echo "resumeTaskAPI response: " . $response . "<br>\n";
            } elseif ($workStatus == 3 || $abnormalStatus != 1) {
                echo "AMR not available (workStatus: " . $workStatus . ", abnormalStatus: " . $abnormalStatus . ")<br>\n";
                echo "Calling pauseTask API with runtime id " . $runtimeId . "<br>\n";
                $response = pauseTaskAPI($runtimeId);
                echo "pauseTaskAPI response: " . $response . "<br>\n";
            }
        }
    
        if ($taskCompleted === false && $attempts >= $maxAttempts) {
            updateTaskStatus($missionId, "MAX_ATTEMPTS_REACHED");
            logTaskExecution($missionId, "MAX_ATTEMPTS_REACHED", "Reached maximum number of attempts: " . $maxAttempts, [
                "error_code" => 1004,
                "start_time" => $startTime,
                "end_time" => date("Y-m-d H:i:s")
            ]);
            echo "Maximum number of attempts reached. Terminating process.<br>\n";
        }
    
        // After task completion, check battery level and if battery is 50% or below, enable smart charge
        $vehicleData = getVehicleStatus();
        if ($vehicleData !== null && isset($vehicleData["battery_value"])) {
            $battery = $vehicleData["battery_value"];
            if ($battery <= 50) {
                echo "Battery is low at " . $battery . "%. Enabling Smart Charge...<br>\n";
                setSmartChargeOn();
            }
        }
    
        // Continue loop to check for new pending tasks
    }
}
 
//-------------------------------------------------
// AMR status monitor process (runs concurrently if pcntl_fork is available)
//-------------------------------------------------
function monitorAMRStatus() {
    global $runtimeMapping;
    while (true) {
        $missionId = getPendingTask();
        if (!$missionId) {
            echo "Monitor: No pending task.<br>\n";
            sleep(10);
            continue;
        }
    
        if (isset($runtimeMapping[$missionId])) {
            $runtimeId = $runtimeMapping[$missionId];
        } else {
            echo "Monitor: Runtime id not available for mission " . $missionId . ".<br>\n";
            sleep(10);
            continue;
        }
    
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
                echo "Monitor: Waiting for battery to charge until at least " . $chargeConfig["canChargeBatteryValue"] . "%...<br>\n";
                while (true) {
                    sleep(10);
                    $vehicleData = getVehicleStatus();
                    $battery = $vehicleData["battery_value"];
                    if ($battery !== null && $battery >= $chargeConfig["canChargeBatteryValue"]) {
                        echo "Monitor: Battery charged to " . $battery . "%. Proceeding.<br>\n";
                        break;
                    } else {
                        echo "Monitor: Waiting for battery to charge. Current level: " . $battery . "%<br>\n";
                    }
                }
            }
        }
    
        // AMR status decision in monitor process
        if ($workStatus == 1 && $abnormalStatus == 1) {
            echo "Monitor: AMR normal. Calling resumeTask API for runtime id " . $runtimeId . "<br>\n";
            $response = resumeTaskAPI($runtimeId);
            echo "Monitor: resumeTaskAPI response: " . $response . "<br>\n";
        } elseif ($workStatus == 3 || $abnormalStatus != 1) {
            echo "Monitor: AMR charging or abnormal. Calling pauseTask API for runtime id " . $runtimeId . "<br>\n";
            $response = pauseTaskAPI($runtimeId);
            echo "Monitor: pauseTaskAPI response: " . $response . "<br>\n";
        }
    
        sleep(10);
    }
}
 
//-------------------------------------------------
// Main execution process
//-------------------------------------------------
global $runtimeMapping;
$runtimeMapping = array();
 
if (function_exists("pcntl_fork")) {
    $pid = pcntl_fork();
    if ($pid == -1) {
        die("Could not fork");
    } else if ($pid == 0) {
        // Child process: Monitor AMR status
        monitorAMRStatus();
        exit(0);
    } else {
        // Parent process: Execute tasks continuously
        executeWebAPITask();
    }
} else {
    echo "pcntl_fork is not available. Running tasks sequentially.<br>\n";
    while (true) {
        executeWebAPITask();
        sleep(10);
    }
}
?>
