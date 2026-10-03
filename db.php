<?php
declare(strict_types=1);


ini_set('display_errors', '0');
error_reporting(E_ALL);

$dsn = 'mysql:host=localhost;dbname=practicum4;charset=utf8mb4';
$dbUser = 'root';
$dbPassword = '';

try {
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Помилка підключення до бази даних.'], JSON_UNESCAPED_UNICODE);
    exit;
}
