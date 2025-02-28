<?php
$serverName = "DESKTOP-DQGJI2I"; // SQL Serverのホスト名またはIPアドレス
$connectionInfo = array( "Database"=>"amr_task_db", "UID"=>"test", "PWD"=>"Koito2025");

$conn = sqlsrv_connect( $serverName, $connectionInfo);

if( !$conn ) {
    // 接続失敗時のエラーメッセージ
    die( print_r(sqlsrv_errors(), true));
} else {
    echo "接続成功！";
    sqlsrv_close( $conn); // 接続を閉じる
}
?>
