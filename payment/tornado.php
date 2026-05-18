<?php

ini_set('error_log', 'error_log');

require_once __DIR__ . '/../../../env/config.php';


use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve the raw POST data
    $rawData = file_get_contents('php://input');

    // Decode the JSON data into an associative array
    $postData = json_decode($rawData, true);

    // Check if the JSON decoding was successful
    if (json_last_error() === JSON_ERROR_NONE) {
        // Prepare an SQL statement to insert data into the payments table
        $stmt = $pdo->prepare('INSERT INTO payments (UniqueCode, PaymentID, UserTelegramId, Wallet, Hash, TronAmount, IsPaid, PaymentDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

        // Execute the statement with the POST data
        $stmt->execute([
            $postData['UniqueCode'],
            $postData['PaymentID'],
            $postData['UserTelegramId'],
            $postData['Wallet'],
            $postData['Hash'],
            $postData['TronAmount'],
            $postData['IsPaid'],
            $postData['PaymentDate']
        ]);

        echo 'Data saved successfully';
    } else {
        // Handle JSON decoding error
        http_response_code(400); // Bad Request
        echo 'Invalid JSON data';
    }
} else {
    // Handle non-POST requests
    http_response_code(405); // Method Not Allowed
    echo 'Only POST requests are allowed';
}
?>