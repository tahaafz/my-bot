<?php
// Tronado payment webhook — authoritative, idempotent wallet crediting.
// Credits the user by the ACTUAL TRON amount received (re-queried from Tronado),
// not by the requested invoice amount.

ini_set('error_log', 'error_log');

// Same config location used by index.php (../../env/config.php from bot root) -> provides $pdo.
require_once __DIR__ . '/../../../env/config.php';
// Helper files live alongside index.php (one level up from /payment).
require_once __DIR__ . '/../botapi.php';     // sendmessage()
require_once __DIR__ . '/../functions.php';  // select(), update(), tronado* helpers

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Only POST requests are allowed';
    return;
}

$rawData  = file_get_contents('php://input');
$postData = json_decode($rawData, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($postData)) {
    http_response_code(400);
    echo 'Invalid JSON data';
    return;
}

$paymentId = $postData['PaymentID'] ?? null;

// --- Audit: store the raw callback (best-effort, never blocks crediting) ---
try {
    $stmt = $pdo->prepare('INSERT INTO payments (UniqueCode, PaymentID, UserTelegramId, Wallet, Hash, TronAmount, ActualTronAmount, IsPaid, PaymentDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $postData['UniqueCode']      ?? null,
        $paymentId,
        $postData['UserTelegramId']  ?? null,
        $postData['Wallet']          ?? null,
        $postData['Hash']            ?? null,
        $postData['TronAmount']      ?? null,
        $postData['ActualTronAmount'] ?? null,
        isset($postData['IsPaid']) ? (int) (bool) $postData['IsPaid'] : null,
        $postData['PaymentDate']     ?? null,
    ]);
} catch (Throwable $e) {
    error_log('Tronado webhook: payments insert failed: ' . $e->getMessage());
}

if (empty($paymentId)) {
    error_log('Tronado webhook: missing PaymentID');
    echo 'ok';
    return;
}

// --- Match to our invoice ---
$Payment_report = select("Payment_report", "*", "id_order", $paymentId, "select");
if (!$Payment_report) {
    error_log('Tronado webhook: no Payment_report for PaymentID ' . $paymentId);
    echo 'ok';
    return;
}

// --- Idempotency guard: only the first caller flips Unpaid -> paid and credits ---
$guard = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid' WHERE id_order = ? AND payment_Status <> 'paid'");
$guard->execute([$paymentId]);
if ($guard->rowCount() === 0) {
    // Already credited by a previous callback.
    echo 'ok';
    return;
}

// --- Authoritative status re-query (don't trust the raw callback for crediting) ---
$status = tronadoStatusByPaymentID($paymentId);
$isPaid = $status && (
    (isset($status['IsPaid']) && $status['IsPaid'] === true) ||
    (isset($status['OrderStatusID']) && (int) $status['OrderStatusID'] === 30)
);

if (!$isPaid) {
    // Not actually paid yet — roll the guard back so a later callback can credit.
    $pdo->prepare("UPDATE Payment_report SET payment_Status = 'Unpaid' WHERE id_order = ?")->execute([$paymentId]);
    error_log('Tronado webhook: status not paid for PaymentID ' . $paymentId);
    echo 'ok';
    return;
}

// --- Determine the ACTUAL TRON received ---
$paidTron = null;
if (isset($status['ActualTronAmount']) && $status['ActualTronAmount'] !== null) {
    $paidTron = (float) $status['ActualTronAmount'];
} elseif (isset($status['TronAmount']) && $status['TronAmount'] !== null) {
    $paidTron = (float) $status['TronAmount'];
}

$rate = tronadoPriceToman();

if ($paidTron === null || $paidTron <= 0 || $rate === null) {
    // Can't safely compute the credited amount — leave for manual review, don't credit the requested price.
    $pdo->prepare("UPDATE Payment_report SET payment_Status = 'Unpaid' WHERE id_order = ?")->execute([$paymentId]);
    error_log('Tronado webhook: cannot compute credit for PaymentID ' . $paymentId
        . ' (paidTron=' . var_export($paidTron, true) . ', rate=' . var_export($rate, true) . ')');
    echo 'ok';
    return;
}

$creditToman = (int) floor($paidTron * $rate);

// --- Credit the wallet by the actual amount ---
$userRow        = select("user", "*", "id", $Payment_report['id_user'], "select");
$newBalance     = intval($userRow['Balance']) + $creditToman;
update("user", "Balance", $newBalance, "id", $Payment_report['id_user']);
// Best-effort traceability — never let a missing column abort after crediting.
try {
    update("Payment_report", "credited_toman", $creditToman, "id_order", $paymentId);
} catch (Throwable $e) {
    error_log('Tronado webhook: credited_toman update skipped: ' . $e->getMessage());
}

// --- Notify user ---
$creditFmt = number_format($creditToman);
sendmessage(
    $Payment_report['id_user'],
    "💎 کاربر گرامی مبلغ {$creditFmt} تومان به کیف پول شما واریز گردید با تشکر از پرداخت شما.\n\n🛒 کد پیگیری شما: {$paymentId}",
    mainMenuKeyboard($Payment_report['id_user']),
    'HTML'
);

// --- Channel report ---
$setting = select("setting", "*");
if (!empty($setting['Channel_Report'])) {
    $text_report = "💵 پرداخت جدید\n\nآیدی عددی کاربر : {$Payment_report['id_user']}\nمبلغ واریزی : {$creditFmt} تومان\nمقدار ترون : {$paidTron}\nروش پرداخت : درگاه ترونادو";
    sendmessage($setting['Channel_Report'], $text_report, null, 'HTML');
}

echo 'ok';
