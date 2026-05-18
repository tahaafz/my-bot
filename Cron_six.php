<?php
require_once __DIR__ . '/../../env/config.php';
require_once 'apipanel.php';
require_once 'botapi.php';
define('LOG_FILE', __DIR__ . '/invoice_cron_errors.log');

function log_error_to_file(string $message, array $context = []): void
{
    $date = date('Y-m-d H:i:s');
    $ctx  = $context ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    @file_put_contents(LOG_FILE, "[$date] $message$ctx\n", FILE_APPEND);
}
$batchSize = 50;
$lockExpireMinutes = 10;
$lockTime = date('Y-m-d H:i:s');

// 1) load panels once (avoid N+1 query)
$panelsRes = mysqli_query($connect, "SELECT * FROM marzban_panel");
$panels = [];
while ($p = mysqli_fetch_assoc($panelsRes)) {
    $panels[$p['name_panel']] = $p;
}

// 2) lock/reserve a fair batch (oldest checked first)
mysqli_query($connect, "
  UPDATE invoice
  SET live_check_locked_at = '{$lockTime}'
  WHERE 
    (live_check_locked_at IS NULL OR live_check_locked_at < (NOW() - INTERVAL {$lockExpireMinutes} MINUTE))
  ORDER BY 
    COALESCE(live_check_last_at, '1970-01-01 00:00:00') ASC,
    id_invoice ASC
  LIMIT {$batchSize}
");

// 3) fetch just locked rows
$list_service = mysqli_query($connect, "
  SELECT * FROM invoice
  WHERE live_check_locked_at = '{$lockTime}'
");

while ($row = mysqli_fetch_assoc($list_service)) {

    $panel = $panels[$row['Service_location']] ?? null;
    if (!$panel) {
        // unlock so it doesn't get stuck
        mysqli_query($connect, "UPDATE invoice SET live_check_last_at=NOW(), live_check_locked_at=NULL WHERE id_invoice='{$row['id_invoice']}'");
        continue;
    }

    try {
        $get_username_Check = getuser($row['username'], $panel['name_panel']);
        if (!isset($get_username_Check['status'])) {
            mysqli_query($connect, "UPDATE invoice SET live_check_last_at=NOW(), live_check_locked_at=NULL WHERE id_invoice='{$row['id_invoice']}'");
            continue;
        }

        $data_limit = (int)($get_username_Check['data_limit'] ?? 0);
        $used_traffic = (int)($get_username_Check['used_traffic'] ?? 0);

        $output = $data_limit - $used_traffic; // remaining bytes
        $liveValue = (int)floor(($output) / 1073741824);

        // combine updates in one query (faster)
        mysqli_query($connect, "
          UPDATE invoice
          SET live_volume = {$liveValue},
              live_volume_updated_at = NOW()
          WHERE id_invoice = '{$row['id_invoice']}'
        ");

        // reset notify flags when user topped up
        if ($used_traffic && $liveValue >= 3) mysqli_query($connect, "UPDATE invoice SET 3_gig_notified_at=NULL WHERE id_invoice='{$row['id_invoice']}'");
        if ($used_traffic && $liveValue >= 1) mysqli_query($connect, "UPDATE invoice SET 1_gig_notified_at=NULL WHERE id_invoice='{$row['id_invoice']}'");
        if ($used_traffic && $liveValue >= 5) mysqli_query($connect, "UPDATE invoice SET 5_gig_notified_at=NULL WHERE id_invoice='{$row['id_invoice']}'");

        // notifications
        $status = $get_username_Check['status'] ?? '';
        if ($output > 0 && $status === "active" && $data_limit > 0) {
            $date = date('Y-m-d H:i:s');

            if (is_null($row['1_gig_notified_at']) && $output <= 1073741824) {
$text = "
⭕️ کاربر گرامی از حجم سرویس تان کمتر از 1 گیگ مانده است.
برای جلوگیری از قطع سرویس از طریق منوی تمدید سرویس آنرا تمدید کنید.

نام کاربری : <code>{$row['username']}</code>
";
sendmessage($row['id_user'], $text, null, 'HTML');
                mysqli_query($connect, "UPDATE invoice SET 1_gig_notified_at='{$date}', 3_gig_notified_at=COALESCE(3_gig_notified_at,'{$date}') WHERE id_invoice='{$row['id_invoice']}'");
            } elseif (is_null($row['3_gig_notified_at']) && $output <= 3221225472) {
           $text = "
⭕️ کاربر گرامی از حجم سرویس تان کمتر از 3 گیگ مانده است.
برای جلوگیری از قطع سرویس از طریق منوی تمدید سرویس آنرا تمدید کنید.

نام کاربری : <code>{$row['username']}</code>
";
sendmessage($row['id_user'], $text, null, 'HTML');
                mysqli_query($connect, "UPDATE invoice SET 3_gig_notified_at='{$date}', 5_gig_notified_at=COALESCE(5_gig_notified_at,'{$date}') WHERE id_invoice='{$row['id_invoice']}'");
            } elseif (is_null($row['5_gig_notified_at']) && $output <= 5368709120) {
           $text = "
⭕️ کاربر گرامی از حجم سرویس تان کمتر از 5 گیگ مانده است.
برای جلوگیری از قطع سرویس از طریق منوی تمدید سرویس آنرا تمدید کنید.مانده است

نام کاربری : <code>{$row['username']}</code>
";
sendmessage($row['id_user'], $text, null, 'HTML');
                mysqli_query($connect, "UPDATE invoice SET 5_gig_notified_at='{$date}' WHERE id_invoice='{$row['id_invoice']}'");
            }
        }

    } catch (\Throwable $e) {
            log_error_to_file('Exception in invoice cron', [
        'id_invoice' => $row['id_invoice'] ?? null,
        'username'   => $row['username'] ?? null,
        'panel'      => $row['Service_location'] ?? null,
        'error'      => $e->getMessage(),
        'file'       => $e->getFile(),
        'line'       => $e->getLine(),
    ]);
    }

    // 4) always unlock + record last check
    mysqli_query($connect, "
      UPDATE invoice
      SET live_check_last_at = NOW(),
          live_check_locked_at = NULL
      WHERE id_invoice = '{$row['id_invoice']}'
    ");
}