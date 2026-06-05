<?php
/**
 * Cron_main.php — کرون یکپارچه برای همه کانفیگ‌های اصلی
 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 * زمان‌بندی پیشنهادی: هر ۱ دقیقه یکبار
 *   * * * * * php /path/to/Cron_main.php >> /dev/null 2>&1
 *
 * ⚠️  قبل از اولین اجرا این SQL را یک‌بار روی دیتابیس اجرا کنید:
 * ─────────────────────────────────────────────────────────────
 *   ALTER TABLE invoice
 *     ADD COLUMN notif_3day_at     DATETIME NULL DEFAULT NULL,
 *     ADD COLUMN notif_1day_at     DATETIME NULL DEFAULT NULL,
 *     ADD COLUMN notif_expired_at  DATETIME NULL DEFAULT NULL,
 *     ADD COLUMN notif_limited_at  DATETIME NULL DEFAULT NULL,
 *     ADD COLUMN deleted_at        DATETIME NULL DEFAULT NULL;
 * ─────────────────────────────────────────────────────────────
 *
 * وضعیت‌های status:
 *   active           →  سرویس فعال
 *   not_found        →  بار اول در پنل پیدا نشد
 *   second_not_found →  بار دوم هم پیدا نشد
 *   expired          →  منقضی شده (زمان)
 *   deleted          →  سافت دیلیت (هیچ‌وقت از DB حذف نمی‌شود)
 *
 * نگاشت ستون‌های alert حجم (ستون‌های موجود، معنای اصلاح‌شده):
 *   `3_gig_notified_at`  →  الرت ۳ گیگابایت باقی‌مانده
 *   `1_gig_notified_at`  →  الرت ۱ گیگابایت باقی‌مانده
 *   `5_gig_notified_at`  →  الرت ۵۰۰ مگابایت باقی‌مانده
 */

ini_set('error_log', __DIR__ . '/cron_main_errors.log');
date_default_timezone_set('Asia/Tehran');

require_once __DIR__ . '/../../env/config.php';
require_once __DIR__ . '/apipanel.php';
require_once __DIR__ . '/panels.php';
require_once __DIR__ . '/botapi.php';
require_once __DIR__ . '/functions.php';

// ═══════════════════════════════════════════════════════════════
//  تنظیمات
// ═══════════════════════════════════════════════════════════════
const BATCH_SIZE        = 30;   // تعداد سرویس‌ها در هر اجرا
const LOCK_TTL_MIN      = 3;    // دقیقه — قفل قدیمی‌تر از این آزاد می‌شود
const DELETE_AFTER_DAYS = 3;    // روز بعد از انقضا، سرویس از پنل و دیتابیس حذف شود

// آستانه‌های حجم (بایت)
const BYTES_3G   = 3_221_225_472;   // 3 GB
const BYTES_1G   = 1_073_741_824;   // 1 GB
const BYTES_500M =   524_288_000;   // 500 MB

// ═══════════════════════════════════════════════════════════════
//  تابع لاگ
// ═══════════════════════════════════════════════════════════════
function cron_log(string $msg, array $ctx = []): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($ctx) {
        $line .= ' | ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    @error_log($line . PHP_EOL, 3, ini_get('error_log'));
}

// ═══════════════════════════════════════════════════════════════
//  ۱. بارگذاری همه پنل‌ها یک‌بار (جلوگیری از N+1 query)
// ═══════════════════════════════════════════════════════════════
$panelRows = $pdo->query("SELECT * FROM marzban_panel")->fetchAll(PDO::FETCH_ASSOC);
$panels = [];
foreach ($panelRows as $p) {
    $panels[$p['name_panel']] = $p;
}

// ═══════════════════════════════════════════════════════════════
//  ۲. قفل‌گذاری batch — قدیمی‌ترین چک‌نشده‌ها اول
// ═══════════════════════════════════════════════════════════════
$lockTime = date('Y-m-d H:i:s');

$pdo->prepare("
    UPDATE invoice
    SET    live_check_locked_at = ?
    WHERE  status IN ('active', 'limited', 'not_found', 'second_not_found')
      AND  (
               live_check_locked_at IS NULL
            OR live_check_locked_at < (NOW() - INTERVAL " . LOCK_TTL_MIN . " MINUTE)
           )
    ORDER BY COALESCE(live_check_last_at, '1970-01-01') ASC,
             id_invoice ASC
    LIMIT  " . BATCH_SIZE
)->execute([$lockTime]);

// ۳. دریافت فقط همان سطرهای قفل‌شده
$stmt = $pdo->prepare("
    SELECT * FROM invoice
    WHERE  live_check_locked_at = ?
      AND  status IN ('active', 'limited', 'not_found', 'second_not_found')
");
$stmt->execute([$lockTime]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    exit;
}

// ═══════════════════════════════════════════════════════════════
//  ۳. گروه‌بندی بر اساس پنل
//     (هر پنل یک‌بار authenticate می‌شود)
// ═══════════════════════════════════════════════════════════════
$byPanel = [];
foreach ($rows as $row) {
    $byPanel[$row['Service_location']][] = $row;
}

$ManagePanel = new ManagePanel();

// ═══════════════════════════════════════════════════════════════
//  ۴. پردازش پنل به پنل
// ═══════════════════════════════════════════════════════════════
foreach ($byPanel as $panelName => $invoices) {

    $panel = $panels[$panelName] ?? null;

    if (!$panel) {
        // پنل وجود ندارد — قفل را آزاد کن
        $ids = implode(',', array_map(fn($r) => $pdo->quote($r['id_invoice']), $invoices));
        $pdo->exec("UPDATE invoice SET live_check_locked_at = NULL, live_check_last_at = NOW() WHERE id_invoice IN ($ids)");
        cron_log('panel not found', ['panel' => $panelName]);
        continue;
    }

    // ── دریافت توکن یک‌بار برای Marzban ─────────────────────────
    if ($panel['type'] === 'marzban') {
        $token = token_panel(
            $panel['url_panel'],
            $panel['username_panel'],
            $panel['password_panel']
        );
        if (!isset($token['access_token'])) {
            $ids = implode(',', array_map(fn($r) => $pdo->quote($r['id_invoice']), $invoices));
            $pdo->exec("UPDATE invoice SET live_check_locked_at = NULL, live_check_last_at = NOW() WHERE id_invoice IN ($ids)");
            cron_log('token error', ['panel' => $panelName, 'response' => $token]);
            continue;
        }
        $GLOBALS['panel_apikey'] = $token['access_token'];
    }

    // ── پردازش هر سرویس این پنل ──────────────────────────────────
    foreach ($invoices as $row) {
        $inv    = $row['id_invoice'];
        $userId = $row['id_user'];
        $uname  = $row['username'];

        try {
            // ── دریافت اطلاعات از پنل ────────────────────────────
            $ud = $ManagePanel->DataUser($panelName, $uname);

            if (empty($ud['username'])) {
                $errMsg  = strtolower((string)($ud['msg'] ?? ''));
                $notFound = str_contains($errMsg, 'not found')
                         || str_contains($errMsg, 'notfound');

                if ($notFound) {
                    $currentStatus = $row['Status'] ?? $row['status'] ?? '';

                    if ($currentStatus === 'second_not_found') {
                        // ── بار سوم پیدا نشد → سافت دیلیت ──────────
                        $pdo->prepare("
                            UPDATE invoice
                            SET status               = 'deleted',
                                deleted_at           = NOW(),
                                live_check_last_at   = NOW(),
                                live_check_locked_at = NULL
                            WHERE id_invoice = ?
                        ")->execute([$inv]);
                        cron_log('user not in panel (3rd check) → soft deleted', [
                            'inv'   => $inv,
                            'user'  => $uname,
                            'panel' => $panelName,
                        ]);

                    } elseif ($currentStatus === 'not_found') {
                        // ── بار دوم پیدا نشد → second_not_found ──
                        $pdo->prepare("
                            UPDATE invoice
                            SET status               = 'second_not_found',
                                live_check_last_at   = NOW(),
                                live_check_locked_at = NULL
                            WHERE id_invoice = ?
                        ")->execute([$inv]);
                        cron_log('user not in panel (2nd check) → marked second_not_found', [
                            'inv'   => $inv,
                            'user'  => $uname,
                            'panel' => $panelName,
                        ]);

                    } else {
                        // ── بار اول پیدا نشد → not_found ─────────
                        $pdo->prepare("
                            UPDATE invoice
                            SET status               = 'not_found',
                                live_check_last_at   = NOW(),
                                live_check_locked_at = NULL
                            WHERE id_invoice = ?
                        ")->execute([$inv]);
                        cron_log('user not in panel (1st check) → marked not_found', [
                            'inv'   => $inv,
                            'user'  => $uname,
                            'panel' => $panelName,
                        ]);
                    }
                } else {
                    // ── پنل آفلاین یا خطای موقت ──────────────────
                    // دست نمی‌زنیم، دفعه بعد دوباره چک می‌شود
                    cron_log('panel unreachable or api error', [
                        'inv'   => $inv,
                        'user'  => $uname,
                        'panel' => $panelName,
                        'msg'   => $ud['msg'] ?? '',
                    ]);
                    goto unlock;
                }
                continue;
            }

            $data_limit   = (int)($ud['data_limit']   ?? 0);
            $used_traffic = (int)($ud['used_traffic'] ?? 0);
            $expire       = (int)($ud['expire']       ?? 0);
            $status       = (string)($ud['status']    ?? '');

            $remaining  = max(0, $data_limit - $used_traffic);   // بایت
            $remainSecs = $expire > 0 ? ($expire - time()) : PHP_INT_MAX;
            $remainDays = $expire > 0 ? (int)ceil($remainSecs / 86400) : PHP_INT_MAX;
            $liveVolGb  = (int)floor($remaining / BYTES_1G);

            // ── به‌روزرسانی live_volume و live_duration ──────────
            $pdo->prepare("
                UPDATE invoice
                SET live_volume              = ?,
                    live_volume_updated_at   = NOW(),
                    live_duration            = ?,
                    live_duration_updated_at = NOW()
                WHERE id_invoice = ?
            ")->execute([
                $liveVolGb,
                $expire > 0 ? max(0, $remainDays) : 0,
                $inv,
            ]);

            $now = date('Y-m-d H:i:s');

            // ══════════════════════════════════════════════════════
            //  الرت‌های حجم
            //  (فقط سرویس active با data_limit مشخص و حجم باقی‌مانده)
            // ══════════════════════════════════════════════════════
            if ($status === 'active' && $data_limit > 0) {

                // ── ریست فلگ‌ها هنگام شارژ / تمدید حجم ──────────
                // وقتی حجم باقی‌مانده از آستانه بالا رفت،
                // فلگ‌های مربوطه ریست می‌شوند تا دفعه بعد الرت صادر شود
                if ($remaining >= BYTES_3G) {
                    $pdo->prepare("
                        UPDATE invoice
                        SET `3_gig_notified_at` = NULL,
                            `1_gig_notified_at` = NULL,
                            `5_gig_notified_at` = NULL,
                            notif_limited_at    = NULL,
                            Status              = 'active'
                        WHERE id_invoice = ?
                    ")->execute([$inv]);

                } elseif ($remaining >= BYTES_1G) {
                    $pdo->prepare("
                        UPDATE invoice
                        SET `1_gig_notified_at` = NULL,
                            `5_gig_notified_at` = NULL
                        WHERE id_invoice = ?
                    ")->execute([$inv]);

                } elseif ($remaining >= BYTES_500M) {
                    $pdo->prepare("
                        UPDATE invoice
                        SET `5_gig_notified_at` = NULL
                        WHERE id_invoice = ?
                    ")->execute([$inv]);
                }

                // ── ارسال الرت (از کمترین به بیشترین، فقط یک‌بار) ─
                if ($remaining > 0) {

                    if (is_null($row['5_gig_notified_at']) && $remaining <= BYTES_500M) {
                        // ۵۰۰ مگابایت
                        sendmessage($userId,
                            "⚠️ <b>هشدار حجم سرویس</b>\n\n" .
                            "کمتر از <b>۵۰۰ مگابایت</b> از حجم سرویس شما باقی مانده است.\n\n" .
                            "👤 نام کاربری: <code>$uname</code>\n\n" .
                            "🔄 برای جلوگیری از قطعی سرویس از منوی تمدید اقدام کنید.",
                            null, 'HTML'
                        );
                        // ثبت همه فلگ‌های بالاتر به صورت COALESCE (اگر قبلاً ست نشده)
                        $pdo->prepare("
                            UPDATE invoice
                            SET `5_gig_notified_at` = ?,
                                `1_gig_notified_at` = COALESCE(`1_gig_notified_at`, ?),
                                `3_gig_notified_at` = COALESCE(`3_gig_notified_at`, ?)
                            WHERE id_invoice = ?
                        ")->execute([$now, $now, $now, $inv]);

                    } elseif (is_null($row['1_gig_notified_at']) && $remaining <= BYTES_1G) {
                        // ۱ گیگابایت
                        sendmessage($userId,
                            "⚠️ <b>هشدار حجم سرویس</b>\n\n" .
                            "کمتر از <b>۱ گیگابایت</b> از حجم سرویس شما باقی مانده است.\n\n" .
                            "👤 نام کاربری: <code>$uname</code>\n\n" .
                            "🔄 برای جلوگیری از قطعی سرویس از منوی تمدید اقدام کنید.",
                            null, 'HTML'
                        );
                        $pdo->prepare("
                            UPDATE invoice
                            SET `1_gig_notified_at` = ?,
                                `3_gig_notified_at` = COALESCE(`3_gig_notified_at`, ?)
                            WHERE id_invoice = ?
                        ")->execute([$now, $now, $inv]);

                    } elseif (is_null($row['3_gig_notified_at']) && $remaining <= BYTES_3G) {
                        // ۳ گیگابایت
                        sendmessage($userId,
                            "⚠️ <b>هشدار حجم سرویس</b>\n\n" .
                            "کمتر از <b>۳ گیگابایت</b> از حجم سرویس شما باقی مانده است.\n\n" .
                            "👤 نام کاربری: <code>$uname</code>\n\n" .
                            "🔄 برای جلوگیری از قطعی سرویس از منوی تمدید اقدام کنید.",
                            null, 'HTML'
                        );
                        $pdo->prepare("
                            UPDATE invoice SET `3_gig_notified_at` = ? WHERE id_invoice = ?
                        ")->execute([$now, $inv]);
                    }
                }
            }

            // ══════════════════════════════════════════════════════
            //  وضعیت limited — حجم به اتمام رسیده
            // ══════════════════════════════════════════════════════
            if ($status === 'limited') {

                // ── ثبت status در DB و ارسال نوتیف (یک‌بار) ──────
                if (is_null($row['notif_limited_at'] ?? null)) {
                    sendmessage($userId,
                        "🚫 <b>حجم سرویس شما به اتمام رسید</b>\n\n" .
                        "👤 نام کاربری: <code>$uname</code>\n\n" .
                        "🔄 برای ادامه استفاده از منوی تمدید سرویس اقدام کنید.",
                        null, 'HTML'
                    );
                    $pdo->prepare("
                        UPDATE invoice
                        SET Status           = 'limited',
                            notif_limited_at = ?
                        WHERE id_invoice = ?
                    ")->execute([$now, $inv]);

                } else {
                    // ── اگر ۳ روز از limited شدن گذشت → سافت دیلیت ─
                    $limitedSince = strtotime($row['notif_limited_at']);
                    if ($limitedSince && (time() - $limitedSince) >= DELETE_AFTER_DAYS * 86400) {
                        $ManagePanel->RemoveUser($panelName, $uname);
                        $pdo->prepare("
                            UPDATE invoice
                            SET status               = 'deleted',
                                deleted_at           = NOW(),
                                live_check_last_at   = NOW(),
                                live_check_locked_at = NULL
                            WHERE id_invoice = ?
                        ")->execute([$inv]);
                        cron_log('service soft-deleted (limited 3+ days)', [
                            'inv'   => $inv,
                            'user'  => $uname,
                            'panel' => $panelName,
                        ]);
                        continue;
                    }
                }
            }

            // ══════════════════════════════════════════════════════
            //  الرت‌های زمان  (فقط سرویس‌های با expire مشخص)
            // ══════════════════════════════════════════════════════
            if ($expire > 0) {

                // ── ریست فلگ‌های زمانی هنگام تمدید ───────────────
                // اگر کاربر تمدید کرد و بیشتر از ۷ روز زمان داشت،
                // فلگ‌ها ریست می‌شوند تا دوباره الرت دریافت کند
                if ($remainSecs > 7 * 86400) {
                    $needs_reset = (!is_null($row['notif_3day_at']    ?? null))
                                || (!is_null($row['notif_1day_at']    ?? null))
                                || (!is_null($row['notif_expired_at'] ?? null));
                    if ($needs_reset) {
                        $pdo->prepare("
                            UPDATE invoice
                            SET notif_3day_at    = NULL,
                                notif_1day_at    = NULL,
                                notif_expired_at = NULL
                            WHERE id_invoice = ?
                        ")->execute([$inv]);
                    }
                }

                if ($remainSecs <= 0) {
                    // ── سرویس منقضی شده ───────────────────────────
                    if (is_null($row['notif_expired_at'] ?? null)) {
                        sendmessage($userId,
                            "🔴 <b>سرویس شما منقضی شده است</b>\n\n" .
                            "👤 نام کاربری: <code>$uname</code>\n\n" .
                            "🔄 برای جلوگیری از حذف دائمی، از منوی تمدید سرویس اقدام کنید.",
                            null, 'HTML'
                        );
                        $pdo->prepare("
                            UPDATE invoice
                            SET notif_expired_at = ?,
                                notif_1day_at    = COALESCE(notif_1day_at, ?),
                                notif_3day_at    = COALESCE(notif_3day_at, ?)
                            WHERE id_invoice = ?
                        ")->execute([$now, $now, $now, $inv]);
                    }

                    // ── سافت دیلیت بعد از DELETE_AFTER_DAYS روز ──
                    if ($remainDays !== PHP_INT_MAX && $remainDays <= -DELETE_AFTER_DAYS) {
                        $removeResult = $ManagePanel->RemoveUser($panelName, $uname);
                        $pdo->prepare("
                            UPDATE invoice
                            SET status               = 'deleted',
                                deleted_at           = NOW(),
                                live_check_last_at   = NOW(),
                                live_check_locked_at = NULL
                            WHERE id_invoice = ?
                        ")->execute([$inv]);
                        cron_log('service soft-deleted (expired ' . abs((int)$remainDays) . ' days ago)', [
                            'inv'         => $inv,
                            'user'        => $uname,
                            'panel'       => $panelName,
                            'panel_remove' => ($removeResult['status'] ?? 'failed'),
                        ]);
                        continue;
                    }

                } elseif (is_null($row['notif_1day_at'] ?? null) && $remainSecs < 86400) {
                    // ── کمتر از ۱ روز ────────────────────────────
                    sendmessage($userId,
                        "⚠️ <b>هشدار زمان سرویس</b>\n\n" .
                        "کمتر از <b>۱ روز</b> به پایان زمان سرویس شما مانده است.\n\n" .
                        "👤 نام کاربری: <code>$uname</code>\n\n" .
                        "🔄 از منوی تمدید سرویس اقدام کنید.",
                        null, 'HTML'
                    );
                    $pdo->prepare("
                        UPDATE invoice
                        SET notif_1day_at  = ?,
                            notif_3day_at  = COALESCE(notif_3day_at, ?)
                        WHERE id_invoice = ?
                    ")->execute([$now, $now, $inv]);

                } elseif (is_null($row['notif_3day_at'] ?? null) && $remainSecs < 259200) {
                    // ── کمتر از ۳ روز ────────────────────────────
                    sendmessage($userId,
                        "⚠️ <b>هشدار زمان سرویس</b>\n\n" .
                        "کمتر از <b>۳ روز</b> به پایان زمان سرویس شما مانده است.\n\n" .
                        "👤 نام کاربری: <code>$uname</code>\n\n" .
                        "🔄 از منوی تمدید سرویس اقدام کنید.",
                        null, 'HTML'
                    );
                    $pdo->prepare("
                        UPDATE invoice SET notif_3day_at = ? WHERE id_invoice = ?
                    ")->execute([$now, $inv]);
                }
            }

        } catch (\Throwable $e) {
            cron_log('exception', [
                'inv'   => $inv,
                'user'  => $uname,
                'panel' => $panelName,
                'error' => $e->getMessage(),
                'line'  => $e->getLine(),
                'file'  => basename($e->getFile()),
            ]);
        }

        // ── آزادسازی قفل و ثبت زمان آخرین چک ─────────────────────
        unlock:
        $pdo->prepare("
            UPDATE invoice
            SET live_check_last_at   = NOW(),
                live_check_locked_at = NULL
            WHERE id_invoice = ?
        ")->execute([$inv]);
    }
}
