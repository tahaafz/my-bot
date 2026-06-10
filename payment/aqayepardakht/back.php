<?php
$rootPath = filter_input(INPUT_SERVER, 'DOCUMENT_ROOT', FILTER_SANITIZE_STRING);
$PHP_SELF = filter_input(INPUT_SERVER, 'PHP_SELF', FILTER_SANITIZE_STRING);
$Pathfile = dirname(dirname($PHP_SELF, 2));
$Pathfiles = $rootPath.$Pathfile;
$Pathfile = $Pathfiles.'/config.php';
$jdf = $Pathfiles.'/jdf.php';
$botapi = $Pathfiles.'/botapi.php';
$botapi = $Pathfiles.'/functions.php';
require_once $Pathfile;
require_once $jdf;
require_once $botapi;
$invoice_id = htmlspecialchars($_POST['invoice_id'], ENT_QUOTES, 'UTF-8');
$PaySetting = select("PaySetting", "ValuePay", "NamePay", "merchant_id_aqayepardakht","select")['ValuePay'];
$Payment_report = select("Payment_report", "price", "id_order", $invoice_id,"select")['price'];

// verify Transaction

$data = [
'pin'    => $PaySetting,
'amount'    => $Payment_report,
'transid' => $_POST['transid'],
];
$data = json_encode($data);
$ch = curl_init('https://panel.aqayepardakht.ir/api/v2/verify');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLINFO_HEADER_OUT, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

curl_setopt($ch, CURLOPT_HTTPHEADER, array(
'Content-Type: application/json',
'Content-Length: ' . strlen($data))
);
$result = curl_exec($ch);
curl_close($ch);
$result = json_decode($result);
if ($result->code == "1") {
    $payment_status = "پرداخت موفق";
    $price = $Payment_report;
    $dec_payment_status = "از انجام تراکنش متشکریم!";
    $Payment_report = select("Payment_report", "price", "id_order", $invoice_id,"select");
    if($Payment_report['payment_Status'] != "paid"){
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'],"select");
    $Balance_confrim = intval($Balance_id['Balance']) + $price;
    update("user", "Balance", $Balance_confrim, "id",$Payment_report['id_user']);
    update("Payment_report", "payment_Status", "paid", "id",$Payment_report['id_order']);
    sendmessage($Payment_report['id_user'],"💎 کاربر گرامی مبلغ $price تومان به کیف پول شما واریز گردید با تشکر از پرداخت شما.
    
    🛒 کد پیگیری شما: {$Payment_report['id_order']}",mainMenuKeyboard($Payment_report['id_user']),'HTML');
    $setting = select("setting", "*");
$text_report = "💵 پرداخت جدید
        
آیدی عددی کاربر : $from_id
مبلغ تراکنش $price
روش پرداخت :  درگاه آقای پرداخت";
    if (strlen($setting['Channel_Report']) > 0) {
        sendmessage($setting['Channel_Report'], $text_report, null, 'HTML');
    }
}
}else {
        $payment_status = [
        '0' => "پرداخت انجام نشد",
        '2' => "تراکنش قبلا وریفای و پرداخت شده است",

    ][$result->code];
     $dec_payment_status = "";
}
?>
<html>
<head>
    <title>فاکتور پرداخت</title>
    <style>
    @font-face {
    font-family: 'vazir';
    src: url('/Vazir.eot');
    src: local('☺'), url('../fonts/Vazir.woff') format('woff'), url('../fonts/Vazir.ttf') format('truetype');
}

        body {
            font-family:vazir;
            background-color: #f2f2f2;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .confirmation-box {
            background-color: #ffffff;
            border-radius: 8px;
            width:25%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
        }

        h1 {
            color: #333333;
            margin-bottom: 20px;
        }

        p {
            color: #666666;
            margin-bottom: 10px;
        }
        .btn{
            display:block;
            margin : 10px 0;
            padding:10px 20px;
            background-color:#49b200;
            color:#fff;
            text-decoration :none;
            border-radius:10px;
        }
    </style>
</head>
<body>
    <div class="confirmation-box">
        <h1><?php echo $payment_status ?></h1>
        <p>شماره تراکنش:<span><?php echo $invoice_id ?></span></p>
        <p>مبلغ پرداختی:  <span><?php echo  $Payment_report; ?></span>تومان</p>
        <p>تاریخ: <span>  <?php echo jdate('Y/m/d')  ?>  </span></p>
        <p><?php echo $dec_payment_status ?></p>
        <a class = "btn" href = "https://t.me/<?php echo $usernamebot ?>">بازگشت به ربات</a>
    </div>
</body>
</html>
