<?php
declare(strict_types=1);

$databaseHost = getenv('WAGERTRACK_DB_HOST') ?: '127.0.0.1';
$databaseName = getenv('WAGERTRACK_DB_NAME') ?: 'wagertrack';
$databaseUser = getenv('WAGERTRACK_DB_USER') ?: 'root';
$databasePassword = getenv('WAGERTRACK_DB_PASSWORD') ?: '';

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=utf8mb4',
    $databaseHost,
    $databaseName
);

return new PDO($dsn, $databaseUser, $databasePassword, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
