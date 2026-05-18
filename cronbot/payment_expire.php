<?php
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../../../env/config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../functions.php';

$expireTimestamp = time() - 1800;
$stmt = $pdo->prepare("SELECT * FROM Payment_message WHERE status = 'active' AND message_id IS NOT NULL AND message_id != ''");
$stmt->execute();

while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $paymentTime = strtotime($result['time']);
    if ($paymentTime === false || $paymentTime > $expireTimestamp) {
        continue;
    }
    deletemessage($result['id_user'], $result['message_id']);
    update("Payment_message", "status", "expire", "id", $result['id']);
}
