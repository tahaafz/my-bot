<?php
require_once __DIR__ . '/../../env/config.php';
require_once 'functions.php';
$setting = select("setting", "*");
// $admin_ids = select("admin", "id_admin",null,null,"FETCH_COLUMN");
//-----------------------------[  text panel  ]-------------------------------
$sql = "SHOW TABLES LIKE 'textbot'";
$stmt = $pdo->prepare($sql);
  $stmt->execute();
  $result = $stmt->fetchAll();
  $table_exists = count($result) > 0;
$datatextbot = array(
    'buy10accounts'=> '',
    'text_usertest' => '',
    'text_Purchased_services' => '',
    'text_support' => '',
    'text_help' => '',
    'text_start' => '',
    'text_bot_off' => '',
    'text_dec_info' => '',
    'text_dec_usertest' => '',
    'text_fq' => '',
    'text_account' => '',
    'text_sell' => '',
    'text_Add_Balance' => '',
    'text_Discount' => '',
    'text_Tariff_list' => '',

);
if ($table_exists) {
    $textdatabot = select("textbot", "*",null ,null ,"fetchAll");
    $data_text_bot = array();
    foreach ($textdatabot as $row) {
        $data_text_bot[] = array(
            'id_text' => $row['id_text'],
            'text' => $row['text']
        );
    }
    foreach ($data_text_bot as $item) {
        if (isset($datatextbot[$item['id_text']])) {
            $datatextbot[$item['id_text']] = $item['text'];
        }
    }
}
$keyboard = [
    'keyboard' => [
        [['text' => $datatextbot['text_sell']],['text' => $datatextbot['text_usertest']]],
        [['text' => $datatextbot['text_Purchased_services']],['text' => $datatextbot['text_Add_Balance']]],
        [['text' => $datatextbot['text_support']], ['text' => $datatextbot['text_help']]],
        // [['text' => $datatextbot['buy10accounts']]],
    ],
    'resize_keyboard' => true
];
if(in_array($from_id,$admin_ids)){
$keyboard['keyboard'][] = [
        ['text' => "ادمین"],
    ];
}
$keyboard  = json_encode($keyboard);


$keyboardPanel = json_encode([
    'inline_keyboard' => [
        [['text' => $datatextbot['text_Discount'] ,'callback_data' => "Discount"]],
    ],
    'resize_keyboard' => true
]);
$keyboardadmin = json_encode([
    'keyboard' => [
        [['text' => "📊 آمار ربات"]],
        [['text' => "✏️ مدیریت پنل"],['text' => "🖥  اضافه کردن پنل"]],
        [['text' => "🔑 تنظیمات اکانت تست"]],
        [['text' => "🏬 بخش فروشگاه"],['text' => "💵 مالی"]],
        [['text' => "👨‍🔧 بخش ادمین"], ['text' => "📝 تنظیم متن ربات"]],
        [['text' => "👤 خدمات کاربر"],['text' => "📨 ارسال پیام"]],
        [['text' => "🔗 ساخت لینک ورود"]],
        [['text' => "👥 تنظیمات زیر مجموعه گیری"]],
        [['text' => "📚 بخش آموزش "], ['text' => "⚙️ تنظیمات"]],
        [['text' => "🏠 بازگشت به منوی اصلی"]]
    ],
    'resize_keyboard' => true
]);
$keyboardpaymentManage = json_encode([
    'keyboard' => [
        [['text' => "💳 تنظبمات درگاه آفلاین"]],
        [['text' => "💵 تنظیمات nowpayment"]],
        [['text' => "💎 درگاه ارزی ریالی"],['text' => "🟡  درگاه آل سات"]],
        [['text' => "🔵 درگاه آقای پرداخت"],['text' => "🔴 درگاه پرفکت مانی"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$CartManage = json_encode([
    'keyboard' => [
        [['text' => "💳 تنظیم شماره کارت"]],
        [['text' => "🔌 وضعیت درگاه آفلاین"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$alsat = json_encode([
    'keyboard' => [
        [['text' => "تنظیم مرچنت"],['text' => "وضعیت درگاه آل سات"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$aqayepardakht = json_encode([
    'keyboard' => [
        [['text' => "تنظیم مرچنت آقای پرداخت"],['text' => "وضعیت درگاه آقای پرداخت "]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$NowPaymentsManage = json_encode([
    'keyboard' => [
        [['text' => "🧩 api nowpayment"]],
        [['text' => "🔌 وضعیت درگاه nowpayments"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$admin_section_panel =  json_encode([
    'keyboard' => [
        [['text' => "👨‍💻 اضافه کردن ادمین"], ['text' => "❌ حذف ادمین"]],
        [['text' => "📜 مشاهده لیست ادمین ها"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]],

    ],
    'resize_keyboard' => true
]);
$keyboard_usertest =  json_encode([
    'keyboard' => [
        [['text' => "➕ محدودیت ساخت اکانت تست برای کاربر"]],
        [['text' => "➕ محدودیت ساخت اکانت تست برای همه"]],
        [['text' => "⏳ زمان سرویس تست"], ['text' => "💾 حجم اکانت تست"]],
        [['text' => "⚙️ تنظیمات پنل‌های تست"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$setting_panel =  json_encode([
    'keyboard' => [
        [['text' => "📡 وضعیت ربات"], ['text' => "♨️ بخش قوانین"]],
        [['text' => "📣 تنظیم کانال گزارش"], ['text' => "📯 تنظیمات کانال"]],
        [['text' => "👤 دکمه نام کاربری"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$valid_Number =  json_encode([
    'keyboard' => [
        [['text' => "📊 وضعیت تایید شماره کاربر"], ['text' => "👈 تایید دستی شماره"]],
        [['text' => "☎️ وضعیت احراز هویت شماره تماس"]],
        [['text' => "👀 مشاهده شماره تلفن کاربر"]],
        [['text' => "تایید شماره ایرانی 🇮🇷"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$PaySettingcard = select("PaySetting", "ValuePay", "NamePay", 'Cartstatus',"select")['ValuePay'];
$PaySettingnow = select("PaySetting", "ValuePay", "NamePay", 'nowpaymentstatus',"select")['ValuePay'];
$PaySettingdigi = select("PaySetting", "ValuePay", "NamePay", 'digistatus',"select")['ValuePay'];
$PaySettingaqayepardakht = select("PaySetting", "ValuePay", "NamePay", 'statusaqayepardakht',"select")['ValuePay'];
$PaySettingperfectmoney = select("PaySetting", "ValuePay", "NamePay", 'status_perfectmoney',"select")['ValuePay'];
$step_payment = [
    'inline_keyboard' => []
    ];
    if($PaySettingcard == "oncard"){
        $step_payment['inline_keyboard'][] = [
            ['text' => "💳 کارت به کارت" ,'callback_data' => "cart_to_offline"],
    ];
    }
   if($PaySettingnow == "onnowpayment"){
        $step_payment['inline_keyboard'][] = [
            ['text' => "💵 پرداخت nowpayments", 'callback_data' => "nowpayments" ]
    ];
    }
   if($PaySettingdigi == "ondigi"){
        $step_payment['inline_keyboard'][] = [
            ['text' => "💎درگاه پرداخت ارزی (ریالی)" , 'callback_data' => "iranpay" ]
    ];
    }
   if($PaySettingaqayepardakht == "onaqayepardakht"){
        $step_payment['inline_keyboard'][] = [
            ['text' => "💳 کارت به کارت" , 'callback_data' => "aqayepardakht" ]
    ];
    }
    if($PaySettingperfectmoney == "onperfectmoney"){
        $step_payment['inline_keyboard'][] = [
            ['text' => "🔴 درگاه پرفکت مانی" , 'callback_data' => "perfectmoney" ]
    ];
    }
    $step_payment['inline_keyboard'][] = [
            ['text' => "❌ بستن لیست" , 'callback_data' => "colselist" ]
    ];
    $step_payment = json_encode($step_payment);
$User_Services = json_encode([
    'keyboard' => [
        [['text' => "📱 احراز هویت شماره"]],
        [['text' => "🔒 مسدود کردن کاربر"], ['text' => "🔓 رفع مسدودی کاربر"]],
        [['text' => "⬆️️️ افزایش موجودی کاربر"], ['text' => "⬇️ کم کردن موجودی"]],
        [['text' => "👁‍🗨 مشاهده اطلاعات کاربر"], ['text' => "🛍 مشاهده سفارشات کاربر"]],
        [['text' => "❌ حذف سرویس کاربر"],['text' => "👥 شارژ همگانی"]],
         [['text' => "➕ اضافه کردن سفارش"], ['text' => "🔑 تغییر تراستد یوزر"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$keyboardhelpadmin = json_encode([
    'keyboard' => [
        [['text' => "📚 اضافه کردن آموزش"], ['text' => "❌ حذف آموزش"]],
        [['text' => "💡 وضعیت بخش آموزش"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$shopkeyboard = json_encode([
    'keyboard' => [
        [['text' => "🛍 اضافه کردن محصول"], ['text' => "❌ حذف محصول"]],
        [['text' => "✏️ ویرایش محصول"]],
        [['text' => "➕ تنظیم قیمت حجم اضافه"]],
        [['text' => "🎁 ساخت کد هدیه"],['text' => "❌ حذف کد هدیه"]],
        [['text' => "🎁 ساخت کد تخفیف"],['text' => "❌ حذف کد تخفیف"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$confrimrolls = json_encode([
    'keyboard' => [
        [['text' => "✅ قوانین را می پذیرم"]],
    ],
    'resize_keyboard' => true
]);
$request_contact = json_encode([
    'keyboard' => [
        [['text' => "☎️ ارسال شماره تلفن", 'request_contact' => true]],
        [['text' => "🏠 بازگشت به منوی اصلی"]]
    ],
    'resize_keyboard' => true
]);
$rollkey = json_encode([
    'keyboard' => [
        [['text' => "💡 روشن / خاموش کردن تایید قوانین"], ['text' => "⚖️ متن قانون"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$sendmessageuser = json_encode([
    'keyboard' => [
        [['text' => "✉️ ارسال همگانی"], ['text' => "📤 فوروارد همگانی"]],
        [['text' => "✍️ ارسال پیام برای یک کاربر"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$Feature_status = json_encode([
    'keyboard' => [
        [['text' => "قابلیت مشاهده اطلاعات اکانت"]],
        [['text' => "قابلیت اکانت تست"], ['text' => "قابلیت آموزش"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$channelkeyboard = json_encode([
    'keyboard' => [
        [['text' => "📣 تنظیم کانال جوین اجباری"]],
        [['text' => "🔑 روشن / خاموش کردن قفل کانال"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$backuser = json_encode([
    'keyboard' => [
        [['text' => "🏠 بازگشت به منوی اصلی"]]
    ],
    'resize_keyboard' => true,
    'input_field_placeholder' =>"برای بازگشت روی دکمه زیر کلیک کنید"
]);
$backadmin = json_encode([
    'keyboard' => [
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true,
    'input_field_placeholder' =>"برای بازگشت روی دکمه زیر کلیک کنید"
]);
$stmt = $pdo->prepare("SHOW TABLES LIKE 'marzban_panel'");
  $stmt->execute();
  $result = $stmt->fetchAll();
  $table_exists = count($result) > 0;
  $namepanel = [];
  $activeNamepanel = [];
  $testNamepanel = [];
  $sellNamepanel = [];
  if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM marzban_panel");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $namepanel[] = [$row['name_panel']];
          if (!isset($row['status']) || (int)$row['status'] === 1) {
            $activeNamepanel[] = [$row['name_panel']];
            if (($row['test_enabled'] ?? '0') === '1') {
                $testNamepanel[] = [$row['name_panel']];
            }
            if (($row['sell_enabled'] ?? '1') === '1') {
                $sellNamepanel[] = [$row['name_panel']];
            }
        }
    }
    $list_marzban_panel = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_marzban_panel['keyboard'][] = [
        ['text' => "🏠 بازگشت به منوی مدیریت"],
    ];
    foreach ($namepanel as $button) {
        $list_marzban_panel['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_marzban_panel = json_encode($list_marzban_panel);
}
$sql = "SHOW TABLES LIKE 'help'";
$stmt = $pdo->prepare($sql);
  $stmt->execute();
  $result = $stmt->fetchAll();
  $table_exists = count($result) > 0;
  if ($table_exists) {
        $help = [];
        $stmt = $pdo->prepare("SELECT * FROM help");
        $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $help[] = [$row['name_os']];
        }
        $help_arr = [
            'keyboard' => [],
            'resize_keyboard' => true,
        ];
        foreach ($help as $button) {
            $help_arr['keyboard'][] = [
                ['text' => $button[0]]
            ];
        }
                $help_arr['keyboard'][] = [
            ['text' => "🏠 بازگشت به منوی اصلی"],
        ];
        $json_list_help = json_encode($help_arr);
    }

$users = select("user", "*", "id", $from_id,"select");
if ($users == false) {
    $users = array();
    $users = array(
        'step' => '',
    );
}
$list_marzban_panel_users = [
        'inline_keyboard' => [],
    ];
if ($users['step'] == "getusernameinfo") {
    foreach ($activeNamepanel as $button) {
    $list_marzban_panel_users['inline_keyboard'][] = [
        ['text' => $button[0] , 'callback_data' => "locationnotuser_{$button[0]}"]
    ];
}
}
else{
        foreach ($sellNamepanel as $button) {
    $list_marzban_panel_users['inline_keyboard'][] = [
        ['text' => $button[0] , 'callback_data' => "location_{$button[0]}"]
    ];
}
}
$list_marzban_panel_users['inline_keyboard'][] = [
    ['text' => "🏠 بازگشت به منوی اصلی", 'callback_data' => "backuser"],
];
$list_marzban_panel_user = json_encode($list_marzban_panel_users);
  $list_marzban_panel_usertest = [
        'inline_keyboard' => [],
    ];
    foreach ($testNamepanel as $buttons) {
    $list_marzban_panel_usertest['inline_keyboard'][] = [
        ['text' => $buttons[0] , 'callback_data' => "locationtests_".$buttons[0]]
    ];
}
$list_marzban_panel_usertest['inline_keyboard'][] = [
    ['text' => "🏠 بازگشت به منوی اصلی", 'callback_data' => "backuser"],
];
$list_marzban_usertest = json_encode($list_marzban_panel_usertest);
$textbot = json_encode([
    'keyboard' => [
        [['text' => "تنظیم متن شروع"], ['text' => "دکمه سرویس خریداری شده"]],
        [['text' => "دکمه اکانت تست"], ['text' => "دکمه سوالات متداول"]],
        [['text' => "متن دکمه 📚 آموزش"], ['text' => "متن دکمه ☎️ پشتیبانی"]],
        [['text' => "دکمه افزایش موجودی"]],
        [['text' => "متن دکمه خرید اشتراک"], ['text' => "متن دکمه لیست تعرفه"]],
        [['text' => "متن توضیحات لیست تعرفه"]],
        [['text' => "متن دکمه حساب کاربری"]],
        [['text' => "📝 تنظیم متن توضیحات عضویت اجباری"]],
        [['text' => "📝 تنظیم متن توضیحات سوالات متداول"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
//--------------------------------------------------
$sql = "SHOW TABLES LIKE 'protocol'";
$stmt = $pdo->prepare($sql);
  $stmt->execute();
  $result = $stmt->fetchAll();
  $table_exists = count($result) > 0;
  if ($table_exists) {
    $getdataprotocol = select("protocol", "*",null ,null ,"fetchAll");
    $protocol = [];
    foreach($getdataprotocol as $result)
    {
        $protocol[] = [['text'=>$result['NameProtocol']]];
    }
    $protocol[] = [['text'=>"🏠 بازگشت به منوی مدیریت"]];
    $keyboardprotocollist = json_encode(['resize_keyboard'=>true,'keyboard'=> $protocol]);
 }
//--------------------------------------------------
$sql = "SHOW TABLES LIKE 'product'";
$stmt = $pdo->prepare($sql);
  $stmt->execute();
  $result = $stmt->fetchAll();
  $table_exists = count($result) > 0;
  if ($table_exists) {
    $product = [];
    $stmt = $pdo->prepare("SELECT * FROM product WHERE Location = :Location OR Location = '/all'");
    $stmt->bindParam(':Location', $text, PDO::PARAM_STR);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $product[] = [$row['name_product']];
    }
    $list_product = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_product['keyboard'][] = [
        ['text' => "🏠 بازگشت به منوی مدیریت"],
    ];
    foreach ($product as $button) {
        $list_product['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_product_list_admin = json_encode($list_product);
    }
//--------------------------------------------------
$sql = "SHOW TABLES LIKE 'Discount'";
$stmt = $pdo->prepare($sql);
  $stmt->execute();
  $result = $stmt->fetchAll();
  $table_exists = count($result) > 0;
  if ($table_exists) {
    $Discount = [];
    $stmt = $pdo->prepare("SELECT * FROM Discount");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $Discount[] = [$row['code']];
    }
    $list_Discount = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_Discount['keyboard'][] = [
        ['text' => "🏠 بازگشت به منوی مدیریت"],
    ];
    foreach ($Discount as $button) {
        $list_Discount['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_Discount_list_admin = json_encode($list_Discount);
}
//--------------------------------------------------
$sql = "SHOW TABLES LIKE 'DiscountSell'";
$stmt = $pdo->prepare($sql);
  $stmt->execute();
  $result = $stmt->fetchAll();
  $table_exists = count($result) > 0;
  $namepanel = [];
  if ($table_exists) {
    $DiscountSell = [];
    $stmt = $pdo->prepare("SELECT * FROM DiscountSell");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $DiscountSell[] = [$row['codeDiscount']];
    }
    $list_Discountsell = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_Discountsell['keyboard'][] = [
        ['text' => "🏠 بازگشت به منوی مدیریت"],
    ];
    foreach ($DiscountSell as $button) {
        $list_Discountsell['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_Discount_list_admin_sell = json_encode($list_Discountsell);
}
$payment = json_encode([
    'inline_keyboard' => [
        [['text' => "💰 پرداخت و دریافت سرویس", 'callback_data' => "confirmandgetservice"]],
        [['text' => "🎁 ثبت کد تخفیف", 'callback_data' => "aptdc"]],
        [['text' => "🏠 بازگشت به منوی اصلی" ,  'callback_data' => "backuser"]]
    ]
]);
$payment2 = json_encode([
    'inline_keyboard' => [
        [['text' => "💰 پرداخت و دریافت سرویس", 'callback_data' => "confirmandgetservice"]],
                 [['text' => "🎁 ثبت کد تخفیف", 'callback_data' => "aptdc"]],
        [['text' => "🏠 بازگشت به منوی اصلی" ,  'callback_data' => "backuser"]]
    ]
]);
$change_product = json_encode([
    'keyboard' => [
        [['text' => "قیمت"], ['text' => "حجم"], ['text' => "زمان"]],
        [['text' => "نام محصول"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$NotProductUser = json_encode([
    'keyboard' => [
        [['text' => "⭕️ نام کاربری من در لیست نیست ⭕️"]],
        [['text' => "🏠 بازگشت به منوی اصلی"]]
    ],
    'resize_keyboard' => true
]);

$keyboardprotocol = json_encode([
    'keyboard' => [
        [['text' => "vless"],['text' => "vmess"],['text' => "trojan"]],
        [['text' => "shadowsocks"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$MethodUsername = json_encode([
    'keyboard' => [
        [['text' => "نام کاربری + عدد به ترتیب"]],
        [['text' => "آیدی عددی + حروف و عدد رندوم"]],
        [['text' => "نام کاربری دلخواه"]],
        [['text' => "متن دلخواه + عدد رندوم"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$optionMarzban = json_encode([
    'keyboard' => [
        [['text' => "🔌 وضعیت اتصال پنل "]],
        [['text' => "✍️ نام پنل"],['text' => "❌ حذف پنل"]],
        [['text'=>"🔗 ویرایش آدرس پنل"],['text' => "👤 ویرایش نام کاربری"]],
        [['text' => "🔐 ویرایش رمز عبور"],['text' => "⚙️ تنظیمات پروتکل"]],
        [['text' => "🍀 قابلیت flow"],['text' => "💡 روش ساخت نام کاربری"]],
        [['text' => "🔗 ارسال لینک سابسکرایبشن"],['text' => "⚙️ارسال کانفیگ"]],
        [['text' => "🛒 قابلیت خرید اشتراک جدید"],['text' => "🗑 قابلیت حذف سرویس توسط کاربر"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$optionX_ui_single = json_encode([
    'keyboard' => [
        [['text' => "🔌 وضعیت اتصال پنل "]],
        [['text' => "✍️ نام پنل"],['text' => "❌ حذف پنل"]],
        [['text' => "💡 روش ساخت نام کاربری"]],
        [['text' => "🔐 ویرایش رمز عبور"],['text' => "👤 ویرایش نام کاربری"]],
        [['text'=>"🔗 ویرایش آدرس پنل"],['text' => "💎 تنظیم شناسه اینباند"]],
        [['text' => "🔗 ارسال لینک سابسکرایبشن"],['text' => "⚙️ارسال کانفیگ"]],
        [['text' => '🔗 دامنه لینک ساب']],
        [['text' => "🛒 قابلیت خرید اشتراک جدید"],['text' => "🗑 قابلیت حذف سرویس توسط کاربر"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$supportoption = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "⁉️ سوالات متداول", 'callback_data' => "fqQuestions"] ,
            ],
            [
                ['text' => "🎟 ارسال پیام به پشتیبانی", 'callback_data' => "support"],
            ],
        ]
    ]);
$perfectmoneykeyboard = json_encode([
    'keyboard' => [
        [['text' => "تنظیم شماره کیف پول"],['text' => "تنظیم شماره اکانت"]],
        [['text' => "تنظیم رمز اکانت"],['text' => "وضعیت پرفکت مانی"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$affiliates =  json_encode([
    'keyboard' => [
        [['text' => "🎁 وضعیت زیرمجموعه گیری"]],
        [['text' => "🧮 تنظیم درصد زیرمجموعه"]],
        [['text' => "🏞 تنظیم بنر زیرمجموعه گیری"]],
        [['text' => "🎁 پورسانت بعد از خرید"],['text' => "🎁 دریافت هدیه "]],
        [['text' => "🌟 مبلغ هدیه استارت"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
$typepanel =  json_encode([
    'keyboard' => [
        [['text' => "marzban"],['text' => "x-ui_single"]],
        [['text' => "🏠 بازگشت به منوی مدیریت"]]
    ],
    'resize_keyboard' => true
]);
