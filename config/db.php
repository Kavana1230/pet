<?php

declare(strict_types=1);

function pawnet_db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $config = require __DIR__ . '/database.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['database']
    );

    try {
        $connection = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $error) {
        error_log('Paw Net database connection failed: ' . $error->getMessage());
        throw new RuntimeException('Database service is currently unavailable. Please verify MySQL is running in XAMPP.', 0, $error);
    }

    return $connection;
}
