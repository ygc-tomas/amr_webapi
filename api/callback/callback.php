<?php
date_default_timezone_set('Asia/Bangkok');

// 非同期レスポンスを保存するための一時ファイル
$responseFile = 'callback_response.json';

// AMRから新しいレスポンスがPOSTされた場合
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 前回のレスポンスをクリア
        if (file_exists($responseFile)) {
            unlink($responseFile);
        }
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        file_put_contents('callback_received.log', date('Y-m-d H:i:s') . " - JSON Decode Error: " . json_last_error_msg() . PHP_EOL, FILE_APPEND);
        die('Invalid JSON');
    }

    // レスポンスにタイムスタンプを追加
    $data['timestamp'] = time(); // 現在のUNIXタイムスタンプ
    file_put_contents($responseFile, json_encode($data)); // レスポンスをファイルに保存
    file_put_contents('callback_received.log', date('Y-m-d H:i:s') . " - New Response: " . json_encode($data) . PHP_EOL, FILE_APPEND);
    echo json_encode(['result' => 'success']);
    exit;
}

// APIからの問い合わせ（GETリクエスト）
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $missionId = $_GET['missionId'] ?? '';
    $response = file_exists($responseFile) ? json_decode(file_get_contents($responseFile), true) : null;

    // レスポンスの有効期限をチェック（例: 5分以内のレスポンスのみ有効）
    if ($response && isset($response['Missionworkid']) && $response['Missionworkid'] === $missionId && (time() - $response['timestamp']) <= 300) {
        echo json_encode(['Status' => $response['Status']]); // Status のみ返す
    } else {
        // 有効期限切れまたは不一致の場合、レスポンスをクリア
        if (file_exists($responseFile)) {
            unlink($responseFile); // ファイルを削除
        }
        echo json_encode(['Status' => 'Pending']);
    }
    exit;
}