<?php
require_once __DIR__ . '/../../env/config.php';
require_once 'apipanel.php';
require_once 'panels.php';
require_once 'botapi.php';

$ManagePanel = new ManagePanel();

define('LOG_FILE', __DIR__ . '/invoice_cron_errors.log');

function log_error_to_file(string $message, array $context = []): void
{
    $date = date('Y-m-d H:i:s');
    $ctx = $context ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    @file_put_contents(LOG_FILE, "[$date] $message$ctx\n", FILE_APPEND);
}

$batchSize = 50;
$lockExpireMinutes = 10;
$lockTime = date('Y-m-d H:i:s');

$excludedLocation = 'مولتی لوکیشن🇩🇪🇫🇮🇺🇸';

// 1) load panels once
$panelsRes = mysqli_query($connect, "SELECT * FROM marzban_panel");
$panels = [];
while ($p = mysqli_fetch_assoc($panelsRes)) {
    $panels[$p['name_panel']] = $p;
}

// 2) lock/reserve a fair batch — only invoices NOT in the excluded location
$excludedEsc = mysqli_real_escape_string($connect, $excludedLocation);
mysqli_query($connect, "
  UPDATE invoice
  SET live_check_locked_at = '{$lockTime}'
  WHERE
    Service_location != '{$excludedEsc}'
    AND (live_check_locked_at IS NULL OR live_check_locked_at < (NOW() - INTERVAL {$lockExpireMinutes} MINUTE))
  ORDER BY
    COALESCE(live_check_last_at, '1970-01-01 00:00:00') ASC,
    id_invoice ASC
  LIMIT {$batchSize}
");

// 3) fetch just locked rows
$list_service = mysqli_query($connect, "
  SELECT * FROM invoice
  WHERE live_check_locked_at = '{$lockTime}'
    AND Service_location != '{$excludedEsc}'
");

while ($row = mysqli_fetch_assoc($list_service)) {
    $invoiceId = mysqli_real_escape_string($connect, $row['id_invoice']);

    $panel = $panels[$row['Service_location']] ?? null;
    if (!$panel) {
        log_error_to_file('Panel not found for invoice', [
            'id_invoice' => $row['id_invoice'] ?? null,
            'username' => $row['username'] ?? null,
            'panel' => $row['Service_location'] ?? null,
        ]);
        mysqli_query($connect, "UPDATE invoice SET live_check_last_at=NOW(), live_check_locked_at=NULL WHERE id_invoice='{$invoiceId}'");
        continue;
    }

    try {
        $userData = $ManagePanel->DataUser($panel['name_panel'], $row['username']);

        if (empty($userData['username'])) {
            log_error_to_file('Panel user data not found for invoice', [
                'id_invoice' => $row['id_invoice'] ?? null,
                'username' => $row['username'] ?? null,
                'panel' => $row['Service_location'] ?? null,
                'response' => $userData,
            ]);
            mysqli_query($connect, "UPDATE invoice SET live_check_last_at=NOW(), live_check_locked_at=NULL WHERE id_invoice='{$invoiceId}'");
            continue;
        }

        $data_limit   = (int)($userData['data_limit'] ?? 0);
        $used_traffic = (int)($userData['used_traffic'] ?? 0);
        $expire       = (int)($userData['expire'] ?? 0);

        $output       = max(0, $data_limit - $used_traffic); // remaining bytes
        $liveValue    = (int)floor($output / 1073741824); // GB for live_volume
        $liveDuration = $expire > 0 ? max(0, (int)ceil(($expire - time()) / 86400)) : 0;
        $serviceVolume = $data_limit > 0 ? (int)ceil($data_limit / 1073741824) : 0;

        mysqli_query($connect, "
          UPDATE invoice
          SET live_volume = {$liveValue},
              live_volume_updated_at = NOW(),
              live_duration = {$liveDuration},
              live_duration_updated_at = NOW(),
              Volume = IF(Volume IS NULL OR Volume = '' OR Volume = '0', '{$serviceVolume}', Volume),
              Service_time = IF(Service_time IS NULL OR Service_time = '' OR Service_time = '0', '{$liveDuration}', Service_time)
          WHERE id_invoice = '{$invoiceId}'
        ");

        // reset notify flags when user topped up above threshold
        // 3221225472 = 3 GB | 524288000 = 500 MB | 104857600 = 100 MB
        if ($used_traffic && $output >= 3221225472) mysqli_query($connect, "UPDATE invoice SET `3_gig_notified_at`=NULL, `5_gig_notified_at`=NULL, `1_gig_notified_at`=NULL WHERE id_invoice='{$invoiceId}'");
        elseif ($used_traffic && $output >= 524288000) mysqli_query($connect, "UPDATE invoice SET `5_gig_notified_at`=NULL, `1_gig_notified_at`=NULL WHERE id_invoice='{$invoiceId}'");
        elseif ($used_traffic && $output >= 104857600) mysqli_query($connect, "UPDATE invoice SET `1_gig_notified_at`=NULL WHERE id_invoice='{$invoiceId}'");

        // notifications
        $status = $userData['status'] ?? '';
        if ($output > 0 && $status === "active" && $data_limit > 0) {
            $date = date('Y-m-d H:i:s');

            if (is_null($row['1_gig_notified_at']) && $output <= 104857600) {
                $text = "
⭕️ کاربر گرامی از حجم سرویس تان کمتر از 100 مگابایت مانده است.
برای جلوگیری از قطع سرویس از طریق منوی تمدید سرویس آنرا تمدید کنید.

نام کاربری : <code>{$row['username']}</code>
";
                sendmessage($row['id_user'], $text, null, 'HTML');
                mysqli_query($connect, "UPDATE invoice SET `1_gig_notified_at`='{$date}', `5_gig_notified_at`=COALESCE(`5_gig_notified_at`,'{$date}'), `3_gig_notified_at`=COALESCE(`3_gig_notified_at`,'{$date}') WHERE id_invoice='{$invoiceId}'");
            } elseif (is_null($row['5_gig_notified_at']) && $output <= 524288000) {
                $text = "
⭕️ کاربر گرامی از حجم سرویس تان کمتر از 500 مگابایت مانده است.
برای جلوگیری از قطع سرویس از طریق منوی تمدید سرویس آنرا تمدید کنید.

نام کاربری : <code>{$row['username']}</code>
";
                sendmessage($row['id_user'], $text, null, 'HTML');
                mysqli_query($connect, "UPDATE invoice SET `5_gig_notified_at`='{$date}', `3_gig_notified_at`=COALESCE(`3_gig_notified_at`,'{$date}') WHERE id_invoice='{$invoiceId}'");
            } 
        }

    } catch (\Throwable $e) {
        log_error_to_file('Exception in invoice cron', [
            'id_invoice' => $row['id_invoice'] ?? null,
            'username' => $row['username'] ?? null,
            'panel' => $row['Service_location'] ?? null,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    // 4) always unlock + record last check
    mysqli_query($connect, "
      UPDATE invoice
      SET live_check_last_at = NOW(),
          live_check_locked_at = NULL
      WHERE id_invoice = '{$invoiceId}'
    ");
}
