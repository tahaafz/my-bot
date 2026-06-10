<?php
function ActiveVoucher($ev_number, $ev_code){
    global $connect;
    $Payer_Account = select("PaySetting", "ValuePay", "NamePay", 'perfectmoney_Payer_Account',"select")['ValuePay'];
    $AccountID = select("PaySetting", "ValuePay", "NamePay", 'perfectmoney_AccountID',"select")['ValuePay'];
    $PassPhrase = select("PaySetting", "ValuePay", "NamePay", 'perfectmoney_PassPhrase',"select")['ValuePay'];
    $opts = array(
        'socket' => array(
            'bindto' => 'ip',
        )
    );

    $context = stream_context_create($opts);

    $voucher = file_get_contents("https://perfectmoney.com/acct/ev_activate.asp?AccountID=" . $AccountID . "&PassPhrase=" . $PassPhrase . "&Payee_Account=" . $Payer_Account . "&ev_number=" . $ev_number . "&ev_code=" . $ev_code);
    return $voucher;
}
function update($table, $field, $newValue, $whereField = null, $whereValue = null) {
    global $pdo,$user;

    if ($whereField !== null) {
        $stmt = $pdo->prepare("SELECT $field FROM $table WHERE $whereField = ? FOR UPDATE");
        $stmt->execute([$whereValue]);
        $currentValue = $stmt->fetchColumn();
        $stmt = $pdo->prepare("UPDATE $table SET $field = ? WHERE $whereField = ?");
        $stmt->execute([$newValue, $whereValue]);
    } else {
        $stmt = $pdo->prepare("UPDATE $table SET $field = ?");
        $stmt->execute([$newValue]);
    }
}
function deleteRow($table, $whereField = null, $whereValue = null) {
    global $pdo;
    if ($whereField !== null) {
        $stmt = $pdo->prepare("DELETE FROM $table WHERE $whereField = ?");
        $stmt->execute([$whereValue]);
    }
}

function step($step, $from_id){
    global $pdo;
    $stmt = $pdo->prepare('UPDATE user SET step = ? WHERE id = ?');
    $stmt->execute([$step, $from_id]);


}
function select($table, $field, $whereField = null, $whereValue = null, $type = "select") {
    global $pdo;

    $query = "SELECT $field FROM $table";

    if ($whereField !== null) {
        $query .= " WHERE $whereField = :whereValue";
    }

    try {
        $stmt = $pdo->prepare($query);

        if ($whereField !== null) {
            $stmt->bindParam(':whereValue', $whereValue , PDO::PARAM_STR);
        }

        $stmt->execute();

        if ($type == "count") {
            return $stmt->rowCount();
        } elseif ($type == "FETCH_COLUMN") {
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }elseif ($type == "fetchAll") {
            return $stmt->fetchAll();
        } else {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}

function generateUUID() {
    $data = openssl_random_pseudo_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); 

    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

    return $uuid;
}
function tronratee(){
    $tronrate = [];
    $tronrate['results'] = [];
    $requests = json_decode(file_get_contents('https://eswap.ir/fa/rates'), true);
    $tronrate['result']['USD'] = $requests['fiats'][0]['price'];
    $tronrate['result']['TRX'] = $requests['coins'][0]['price']*$requests['fiats'][0]['price'];
    return $tronrate;
}
function nowPayments($payment, $price_amount, $order_id, $order_description){
    $apinowpayments = select("PaySetting", "ValuePay", "NamePay", 'apinowpayment',"select")['ValuePay'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.nowpayments.io/v1/' . $payment,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT_MS => 4500,
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => 1,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'x-api-key:' . $apinowpayments,
            'Content-Type: application/json'
        ),
    ));
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
        'price_amount' => $price_amount,
        'price_currency' => 'usd',
        'pay_currency' => 'trx',
        'order_id' => $order_id,
        'order_description' => $order_description,
    ]));

    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response);
}
function StatusPayment($paymentid){
    $apinowpayments = select("PaySetting", "ValuePay", "NamePay", 'apinowpayment',"select")['ValuePay'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.nowpayments.io/v1/payment/' . $paymentid,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'x-api-key:' . $apinowpayments
        ),
    ));
    $response = curl_exec($curl);
    $response = json_decode($response, true);
    curl_close($curl);
    return $response;
}

// ===== Tronado (TRON) gateway helpers =====
const TRONADO_BASE = 'https://bot.tronado.cloud';

// Low-level POST to a Tronado endpoint. Returns decoded array or null on failure.
// API key comes from config.php ($tronado_api_key).
function tronadoPost($path, array $body = []) {
    global $tronado_api_key;
    $ch = curl_init(TRONADO_BASE . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-api-key: ' . $tronado_api_key,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('Tronado curl error (' . $path . '): ' . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

// Current TRON->Toman price. Returns float rate or null on failure.
function tronadoPriceToman() {
    $res = tronadoPost('/Tron/GetPriceToToman');
    if (isset($res['TronPriceToman']) && $res['TronPriceToman'] > 0) {
        return (float) $res['TronPriceToman'];
    }
    return null;
}

// Authoritative order status looked up by OUR PaymentID.
// Returns decoded array (IsPaid, OrderStatusID, ActualTronAmount, TronAmount, Hash, Wallet, PaymentDate) or null.
function tronadoStatusByPaymentID($paymentId) {
    return tronadoPost('/Order/GetStatusByPaymentID', ['Id' => $paymentId]);
}

// Build the main-menu reply keyboard for a SPECIFIC recipient.
// Self-contained (queries textbot labels once, cached) so it works in both
// index.php and the cron jobs. The "ادمین" button is added only when the
// RECIPIENT is an admin — so an admin sending to a normal user never leaks it.
// Returns a JSON reply keyboard, or null if labels are missing (so the message
// still sends and the user's existing persistent keyboard is kept).
function mainMenuKeyboard($targetId)
{
    global $pdo, $admin_ids;
    static $labels = null;

    if ($labels === null) {
        $labels = [
            'text_sell'               => '',
            'text_usertest'           => '',
            'text_Purchased_services' => '',
            'text_Add_Balance'        => '',
            'text_support'            => '',
            'text_help'               => '',
        ];
        try {
            $stmt = $pdo->query("SELECT id_text, text FROM textbot");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if (array_key_exists($r['id_text'], $labels)) {
                    $labels[$r['id_text']] = $r['text'];
                }
            }
        } catch (\Throwable $e) {
            // اگر جدول/ستون نبود، با لیبل خالی ادامه می‌دهیم
        }
    }

    // اگر هر کدام از لیبل‌ها خالی باشد، کیبورد نامعتبر می‌شود و کل پیام رد می‌شود؛
    // در این حالت null برمی‌گردانیم تا پیام بدون دکمه ولی سالم ارسال شود.
    foreach ($labels as $v) {
        if ($v === '' || $v === null) {
            return null;
        }
    }

    $kb = [
        'keyboard' => [
            [['text' => $labels['text_sell']],               ['text' => $labels['text_usertest']]],
            [['text' => $labels['text_Purchased_services']], ['text' => $labels['text_Add_Balance']]],
            [['text' => $labels['text_support']],            ['text' => $labels['text_help']]],
        ],
        'resize_keyboard' => true,
    ];

    if (is_array($admin_ids) && in_array($targetId, $admin_ids)) {
        $kb['keyboard'][] = [['text' => 'ادمین']];
    }

    return json_encode($kb);
}

function formatBytes($bytes, $precision = 2): string
{
    $base = log($bytes, 1024);
    $power = $bytes > 0 ? floor($base) : 0;
    $suffixes = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت'];
    return round(pow(1024, $base - $power), $precision) . ' ' . $suffixes[$power];
}
#---------------------[ ]--------------------------#
function generateUsername($from_id,$Metode,$username,$randomString,$text)
{
    global $connect;
    $setting = select("setting", "*");
    global $connect;
    if($Metode == "آیدی عددی + حروف و عدد رندوم"){
        return $from_id."_".$randomString;
    }
    elseif($Metode == "نام کاربری + حروف و عدد رندوم"){
        return $username."_".$randomString;
    }
    elseif($Metode == "نام کاربری + عدد به ترتیب"){
        $statistics = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(id_user)  FROM invoice WHERE id_user = '$from_id'"));
        $countInvoice = intval($statistics['COUNT(id_user)']) + 1 ;
        return $username."_".$countInvoice;
    }
    elseif($Metode == "نام کاربری دلخواه")return $text;
    elseif($Metode == "متن دلخواه + عدد رندوم")return $setting['namecustome']."_".$randomString;
}

function outputlunk($text){
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $text);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);             
$userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
$response = curl_exec($ch);
if($response === false) {
    $error = curl_error($ch);
    return "";
} else {
    return $response;
}

curl_close($ch);
}
function savedata($type,$namefiled,$valuefiled){
    global $from_id;
    if($type == "clear"){
        $datauser = [];
        $datauser[$namefiled] = $valuefiled;
        $data = json_encode($datauser);
        update("user","Processing_value",$data,"id",$from_id);
    }elseif($type == "save"){
        $userdata = select("user","*","id",$from_id,"select");
        $dataperevieos = json_decode($userdata['Processing_value'],true);
        $dataperevieos[$namefiled] = $valuefiled;
        update("user","Processing_value",json_encode($dataperevieos),"id",$from_id);
    }
}
function sanitizeUserName($userName) {
    $forbiddenCharacters = [
        "'", "\"", "<", ">", "--", "#", ";", "\\", "%", "(", ")"
    ];

    foreach ($forbiddenCharacters as $char) {
        $userName = str_replace($char, "", $userName);
    }

    return $userName;
}