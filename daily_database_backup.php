<?php
/**
 * Daily database backup sender.
 *
 * Cron example:
 * 0 4 * * * php /path/to/bot/daily_database_backup.php >> /path/to/bot/daily_database_backup.log 2>&1
 */

declare(strict_types=1);

ini_set('error_log', __DIR__ . '/daily_database_backup_errors.log');
date_default_timezone_set('Asia/Tehran');

const BACKUP_KEEP_DAYS = 7;
const TELEGRAM_SAFE_UPLOAD_BYTES = 49 * 1024 * 1024;

function backup_log(string $message, array $context = []): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($context !== []) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    error_log($line . PHP_EOL, 3, ini_get('error_log'));
    fwrite(STDERR, $line . PHP_EOL);
}

function load_bot_config(): void
{
    global $pdo, $connect, $APIKEY;

    $paths = [
        __DIR__ . '/../../env/config.php',
        __DIR__ . '/config.php',
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }

    throw new RuntimeException('Config file not found. Checked: ' . implode(', ', $paths));
}

function quote_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function sql_value(PDO $pdo, mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    return $pdo->quote((string) $value);
}

function cleanup_old_backups(string $backupDir): void
{
    $expiresBefore = time() - (BACKUP_KEEP_DAYS * 86400);

    foreach (glob($backupDir . '/database_backup_*') ?: [] as $file) {
        if (is_file($file) && filemtime($file) !== false && filemtime($file) < $expiresBefore) {
            @unlink($file);
        }
    }
}

function get_admin_ids(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id_admin FROM admin WHERE id_admin IS NOT NULL AND id_admin != ""');

    return array_values(array_unique(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
}

function write_database_dump(PDO $pdo, string $filePath): int
{
    $databaseName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($databaseName === '') {
        throw new RuntimeException('Could not detect current database name.');
    }

    $handle = fopen($filePath, 'wb');
    if ($handle === false) {
        throw new RuntimeException('Could not create backup file: ' . $filePath);
    }

    $bytes = 0;
    $write = static function (string $content) use ($handle, &$bytes): void {
        $written = fwrite($handle, $content);
        if ($written === false) {
            throw new RuntimeException('Failed writing backup file.');
        }
        $bytes += $written;
    };

    try {
        $write("-- Database backup\n");
        $write('-- Database: ' . $databaseName . "\n");
        $write('-- Created at: ' . date('Y-m-d H:i:s') . "\n\n");
        $write("SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
        $write("SET time_zone = \"+00:00\";\n");
        $write("SET FOREIGN_KEY_CHECKS = 0;\n\n");

        $tablesStmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tables = $tablesStmt->fetchAll(PDO::FETCH_NUM);

        foreach ($tables as $tableRow) {
            $table = (string) $tableRow[0];
            $quotedTable = quote_identifier($table);

            $createStmt = $pdo->query('SHOW CREATE TABLE ' . $quotedTable);
            $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
            $createSql = (string) ($createRow['Create Table'] ?? array_values($createRow)[1] ?? '');

            if ($createSql === '') {
                throw new RuntimeException('Could not read CREATE TABLE for ' . $table);
            }

            $write("-- --------------------------------------------------------\n");
            $write("-- Table structure for {$quotedTable}\n\n");
            $write("DROP TABLE IF EXISTS {$quotedTable};\n");
            $write($createSql . ";\n\n");

            $rowsStmt = $pdo->query('SELECT * FROM ' . $quotedTable);
            $columns = [];
            $rowCount = 0;

            while (($row = $rowsStmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                if ($columns === []) {
                    $columns = array_keys($row);
                    $write("-- Data for {$quotedTable}\n");
                }

                $quotedColumns = implode(', ', array_map('quote_identifier', $columns));
                $values = implode(', ', array_map(
                    static fn ($column) => sql_value($pdo, $row[$column]),
                    $columns
                ));

                $write("INSERT INTO {$quotedTable} ({$quotedColumns}) VALUES ({$values});\n");
                $rowCount++;
            }

            if ($rowCount > 0) {
                $write("\n");
            }
        }

        $write("SET FOREIGN_KEY_CHECKS = 1;\n");
    } finally {
        fclose($handle);
    }

    return $bytes;
}

function maybe_gzip_backup(string $sqlFile): string
{
    if (!function_exists('gzencode')) {
        return $sqlFile;
    }

    $content = file_get_contents($sqlFile);
    if ($content === false) {
        return $sqlFile;
    }

    $gzipFile = $sqlFile . '.gz';
    $encoded = gzencode($content, 6);
    if ($encoded === false || file_put_contents($gzipFile, $encoded) === false) {
        return $sqlFile;
    }

    @unlink($sqlFile);
    return $gzipFile;
}

function telegram_send_document(string $apiKey, string $chatId, string $filePath, string $caption): object
{
    if (!extension_loaded('curl')) {
        throw new RuntimeException('PHP curl extension is not loaded.');
    }

    $ch = curl_init('https://api.telegram.org/bot' . $apiKey . '/sendDocument');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_POSTFIELDS => [
            'chat_id' => $chatId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
            'document' => new CURLFile($filePath),
        ],
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Telegram curl error: ' . $error);
    }

    $decoded = json_decode($response);
    if (!is_object($decoded) || empty($decoded->ok)) {
        throw new RuntimeException('Telegram sendDocument failed. HTTP ' . $statusCode . ' response: ' . $response);
    }

    return $decoded;
}

function main(): int
{
    global $pdo, $APIKEY;

    load_bot_config();

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('The config file did not provide a valid $pdo connection.');
    }

    if (!isset($APIKEY) || trim((string) $APIKEY) === '') {
        throw new RuntimeException('The config file did not provide $APIKEY.');
    }

    $backupDir = __DIR__ . '/backups';
    if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
        throw new RuntimeException('Could not create backup directory: ' . $backupDir);
    }

    cleanup_old_backups($backupDir);

    $lockFile = fopen($backupDir . '/daily_database_backup.lock', 'c');
    if ($lockFile === false || !flock($lockFile, LOCK_EX | LOCK_NB)) {
        backup_log('Backup skipped because another backup process is running.');
        return 0;
    }

    $databaseName = preg_replace('/[^A-Za-z0-9_.-]+/', '_', (string) $pdo->query('SELECT DATABASE()')->fetchColumn());
    $timestamp = date('Y-m-d_H-i-s');
    $sqlFile = $backupDir . '/database_backup_' . $databaseName . '_' . $timestamp . '.sql';

    $bytes = write_database_dump($pdo, $sqlFile);
    $sendFile = maybe_gzip_backup($sqlFile);
    $sendFileSize = filesize($sendFile);
    if ($sendFileSize === false) {
        throw new RuntimeException('Could not read backup file size: ' . $sendFile);
    }

    if ($sendFileSize > TELEGRAM_SAFE_UPLOAD_BYTES) {
        throw new RuntimeException(
            'Backup file is too large for safe Telegram upload. File kept at: ' . $sendFile
        );
    }

    $adminIds = get_admin_ids($pdo);

    if ($adminIds === []) {
        throw new RuntimeException('No admin IDs found in admin table.');
    }

    $caption = "📦 بکاپ روزانه دیتابیس\n"
        . "🗄 دیتابیس: <code>{$databaseName}</code>\n"
        . "🕒 زمان: <code>" . date('Y-m-d H:i:s') . "</code>\n"
        . "📄 حجم SQL: <code>" . number_format($bytes) . " bytes</code>";

    $sent = 0;
    foreach ($adminIds as $adminId) {
        try {
            telegram_send_document((string) $APIKEY, $adminId, $sendFile, $caption);
            $sent++;
        } catch (Throwable $e) {
            backup_log('Failed to send backup to admin.', [
                'admin_id' => $adminId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    if ($sent < 1) {
        throw new RuntimeException('Backup was created but could not be sent to any admin: ' . $sendFile);
    }

    @unlink($sendFile);
    if ($sendFile !== $sqlFile) {
        @unlink($sqlFile);
    }

    backup_log('Database backup sent successfully.', [
        'admins_sent' => $sent,
        'admins_total' => count($adminIds),
    ]);

    return 0;
}

try {
    exit(main());
} catch (Throwable $e) {
    backup_log('Database backup failed.', ['error' => $e->getMessage()]);
    exit(1);
}
