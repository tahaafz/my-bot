<?php
// کرون جاب هر 1 روز تنظیم شود
require_once __DIR__ . '/../../env/config.php';
require_once 'apipanel.php';
require_once 'botapi.php';

#-------------[  Notification to the user ]-------------#
$list_service = mysqli_query($connect, "
    SELECT *
    FROM invoice
    WHERE DATE(live_duration_updated_at) != CURDATE()
       OR live_duration_updated_at IS NULL
    ORDER BY id_invoice
    LIMIT 50
");

while ($row = mysqli_fetch_assoc($list_service)) {
    // Fetch the Marzban panel details for the service location
    $marzban_list_get = mysqli_fetch_assoc(
        mysqli_query($connect, "SELECT * FROM marzban_panel WHERE name_panel = '{$row['Service_location']}'")
    );

    // Get the username check data from Marzban panel
    $get_username_Check = getuser($row['username'], $marzban_list_get['name_panel']);
$today = date('Y-m-d');
mysqli_query($connect, "UPDATE invoice SET live_duration_updated_at = '$today' WHERE username = '{$row['username']}'");

    if (isset($get_username_Check['status'])) {
        // Calculate remaining time of the service
        $timeservice = $get_username_Check['expire'] - time();
        $day = floor($timeservice / 86400) + 1;
        $output = $get_username_Check['data_limit'] - $get_username_Check['used_traffic'];
        $RemainingVolume = formatBytes($output);
        
        
         mysqli_query($connect, "update invoice set live_duration={$day} where username='{$row['username']}'");


        // If service time is less than 1 day
        if($timeservice < 0) {
             $text = "⭕️ هشدار: سرویس شما منقضی شده و هنوز توسط شما تمدید نشده است
برای جلوگیری از حذف همیشگی آن از طریق منوی تمدید سرویس آنرا تمدید کنید.

نام کاربری : <code>{$row['username']}</code>
";
             sendmessage($row['id_user'], $text, null, 'HTML');
        }
        elseif ($timeservice <= 100000 && $timeservice > 0) {
            $text = "⭕️ کاربر گرامی به پایان زمان سرویس تان  کمتر از ۱ روز مانده است.
برای جلوگیری از قطع سرویس از طریق منوی تمدید سرویس آنرا تمدید کنید.

نام کاربری : <code>{$row['username']}</code>
";
             sendmessage($row['id_user'], $text, null, 'HTML');
        }
        // If service time is less than 3 days
        elseif ((170000 <= $timeservice && $timeservice < 260000) && $timeservice > 0) {
            $text = "⭕️ کاربر گرامی به پایان زمان سرویس تان  کمتر از ۳ روز مانده است.
برای جلوگیری از قطع سرویس از طریق منوی تمدید سرویس آنرا تمدید کنید.

نام کاربری : <code>{$row['username']}</code>
";
            sendmessage($row['id_user'], $text, null, 'HTML');
        }

        // If service expired 3 days ago, remove user
        if ($day <= -3) {
            removeuser($marzban_list_get['name_panel'], $row['username']);
            $stmt = $connect->prepare("DELETE FROM invoice WHERE username = '{$row['username']}'");
            $stmt->execute();
        }

    }
}

#-------------[  Notification to the user ]-------------#
