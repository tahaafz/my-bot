<?php
/**
 * Trusted receipt review cron.
 *
 * Reminders (hourly):
 *   0 * * * * php /path/to/trusted_receipt_cron.php remind >> /dev/null 2>&1
 *
 * Nightly report (23:50 Tehran):
 *   50 23 * * * php /path/to/trusted_receipt_cron.php report >> /dev/null 2>&1
 *
 * Both in one run:
 *   php /path/to/trusted_receipt_cron.php
 */

declare(strict_types=1);

ini_set('error_log', __DIR__ . '/trusted_receipt_cron_errors.log');
date_default_timezone_set('Asia/Tehran');

require_once __DIR__ . '/../../env/config.php';
require_once __DIR__ . '/botapi.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/jdf.php';

const TRUSTED_RECEIPT_REMIND_AFTER_DAYS = 3;
const TRUSTED_RECEIPT_REPORT_SETTING = 'trusted_receipt_last_report';

function trusted_receipt_cron_log(string $message, array $context = []): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($context !== []) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    error_log($line . PHP_EOL, 3, ini_get('error_log'));
}

function trusted_receipt_status_label(string $status): string
{
    return [
        'pending' => 'بررسی نشده',
        'reviewed' => 'بررسی شد',
        'problem' => 'گزارش مشکل',
    ][$status] ?? $status;
}

function send_trusted_receipt_reminders(PDO $pdo): int
{
    ensureTrustedReceiptReviewTable();
    $cutoff = date('Y-m-d H:i:s', time() - (TRUSTED_RECEIPT_REMIND_AFTER_DAYS * 86400));
    $stmt = $pdo->prepare("SELECT * FROM trusted_receipt_review
        WHERE status = 'pending' AND reminded_at IS NULL AND sent_at <= ?");
    $stmt->execute([$cutoff]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $sent = 0;

    foreach ($rows as $row) {
        $claim = $pdo->prepare("UPDATE trusted_receipt_review
            SET reminded_at = ?
            WHERE id_order = ? AND status = 'pending' AND reminded_at IS NULL");
        $claim->execute([date('Y-m-d H:i:s'), $row['id_order']]);
        if ($claim->rowCount() < 1) {
            continue;
        }

        $payload = [
            'chat_id' => $row['reviewer_chat_id'],
            'text' => "این رسید را بررسی نکردی.\n\n🛒 کد پیگیری: <code>{$row['id_order']}</code>",
            'parse_mode' => 'HTML',
        ];
        if (!empty($row['telegram_message_id'])) {
            $payload['reply_to_message_id'] = $row['telegram_message_id'];
            $payload['allow_sending_without_reply'] = true;
        }
        telegram('sendmessage', $payload);
        $sent++;
    }

    return $sent;
}

function trusted_receipt_report_day_already_sent(PDO $pdo, string $day): bool
{
    $stmt = $pdo->prepare("SELECT ValuePay FROM PaySetting WHERE NamePay = ? LIMIT 1");
    $stmt->execute([TRUSTED_RECEIPT_REPORT_SETTING]);
    $value = $stmt->fetchColumn();
    return (string)$value === $day;
}

function trusted_receipt_mark_report_sent(PDO $pdo, string $day): void
{
    $stmt = $pdo->prepare("INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE ValuePay = VALUES(ValuePay)");
    $stmt->execute([TRUSTED_RECEIPT_REPORT_SETTING, $day]);
}

function reviewer_display_name(PDO $pdo, string $userId): string
{
    $stmt = $pdo->prepare("SELECT username FROM user WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $username = (string)$stmt->fetchColumn();
    if ($username !== '' && $username !== 'none' && $username !== 'NOT_USERNAME') {
        return "@{$username} ({$userId})";
    }
    return $userId;
}

function send_trusted_receipt_daily_report(PDO $pdo, bool $force = false): bool
{
    ensureTrustedReceiptReviewTable();
    $day = date('Y-m-d');
    if (!$force && trusted_receipt_report_day_already_sent($pdo, $day)) {
        trusted_receipt_cron_log('Daily report already sent.', ['day' => $day]);
        return false;
    }

    $start = $day . ' 00:00:00';
    $end = $day . ' 23:59:59';
    $stmt = $pdo->prepare("SELECT * FROM trusted_receipt_review WHERE sent_at BETWEEN ? AND ? ORDER BY sent_at ASC");
    $stmt->execute([$start, $end]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $total = count($rows);
    $counts = ['pending' => 0, 'reviewed' => 0, 'problem' => 0];
    $reviewers = [];
    foreach ($rows as $row) {
        $status = (string)($row['status'] ?? 'pending');
        if (!isset($counts[$status])) {
            $counts[$status] = 0;
        }
        $counts[$status]++;
        if (in_array($status, ['reviewed', 'problem'], true) && !empty($row['reviewed_by'])) {
            $reviewerId = (string)$row['reviewed_by'];
            if (!isset($reviewers[$reviewerId])) {
                $reviewers[$reviewerId] = ['reviewed' => 0, 'problem' => 0];
            }
            $reviewers[$reviewerId][$status]++;
        }
    }

    $jalali = function_exists('jdate') ? jdate('Y/m/d', '', '', 'Asia/Tehran', 'en') : $day;
    $summary = "گزارش رسیدهای تراستد — {$jalali}

ارسال‌شده: {$total}
بررسی‌شده: {$counts['reviewed']}
گزارش مشکل: {$counts['problem']}
بررسی‌نشده: {$counts['pending']}";

    if ($reviewers !== []) {
        $summary .= "\n\nبررسی‌کنندگان:";
        foreach ($reviewers as $reviewerId => $stats) {
            $name = reviewer_display_name($pdo, (string)$reviewerId);
            $summary .= "\n- {$name}: {$stats['reviewed']} بررسی شد، {$stats['problem']} گزارش مشکل";
        }
    }

    $fileLines = [
        "گزارش رسیدهای تراستد {$jalali}",
        "ارسال‌شده: {$total} | بررسی‌شده: {$counts['reviewed']} | گزارش مشکل: {$counts['problem']} | بررسی‌نشده: {$counts['pending']}",
        '',
        'کد پیگیری | مبلغ | وضعیت | بررسی‌کننده | زمان ارسال',
    ];
    foreach ($rows as $row) {
        $fileLines[] = implode(' | ', [
            (string)$row['id_order'],
            (string)($row['price'] ?? ''),
            trusted_receipt_status_label((string)($row['status'] ?? 'pending')),
            (string)($row['reviewed_by'] ?? '-'),
            (string)($row['sent_at'] ?? ''),
        ]);
    }
    $fileContents = implode("\n", $fileLines) . "\n";
    $filePath = sys_get_temp_dir() . '/trusted_receipt_report_' . $day . '.txt';
    file_put_contents($filePath, $fileContents);

    $recipients = trustedReceiptMainAdminRecipients();
    if ($recipients === []) {
        @unlink($filePath);
        throw new RuntimeException('No main admin recipients found for trusted receipt report.');
    }

    $sent = 0;
    foreach ($recipients as $adminId) {
        $result = telegram('sendDocument', [
            'chat_id' => $adminId,
            'document' => new CURLFile($filePath, 'text/plain', 'trusted_receipt_report_' . $day . '.txt'),
            'caption' => $summary,
        ]);
        if (is_object($result) && !empty($result->ok)) {
            $sent++;
        } else {
            sendmessage($adminId, $summary, null, 'HTML');
            $sent++;
        }
    }
    @unlink($filePath);

    trusted_receipt_mark_report_sent($pdo, $day);
    trusted_receipt_cron_log('Daily report sent.', [
        'day' => $day,
        'total' => $total,
        'admins_sent' => $sent,
    ]);
    return true;
}

function main(array $argv): int
{
    global $pdo;
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Database connection is not available.');
    }

    $mode = $argv[1] ?? ($_GET['mode'] ?? 'auto');
    $mode = is_string($mode) ? $mode : 'auto';
    if (!in_array($mode, ['auto', 'remind', 'report'], true)) {
        fwrite(STDERR, "Usage: php trusted_receipt_cron.php [auto|remind|report]\n");
        return 1;
    }

    if ($mode === 'remind' || $mode === 'auto') {
        $reminded = send_trusted_receipt_reminders($pdo);
        trusted_receipt_cron_log('Reminders processed.', ['sent' => $reminded]);
    }

    $shouldReport = $mode === 'report' || ($mode === 'auto' && (int)date('G') === 23);
    if ($shouldReport) {
        send_trusted_receipt_daily_report($pdo, $mode === 'report');
    }

    return 0;
}

try {
    exit(main($argv ?? []));
} catch (Throwable $e) {
    trusted_receipt_cron_log('Trusted receipt cron failed.', ['error' => $e->getMessage()]);
    exit(1);
}
