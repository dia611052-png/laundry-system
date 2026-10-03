<?php
/**
 * Database connection.
 * Reads DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS from the
 * environment (see .env.example), falling back to a stock XAMPP install
 * (MySQL on localhost:3306, root, no password) if they're not set.
 */
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'freshtrack');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Original behaviour died with an HTML message. This is now a JSON API,
    // so every response — including this failure — has to be JSON too.
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Database connection failed. Make sure MySQL is running in XAMPP and that the '
            . '"freshtrack" database has been imported from database/schema.sql. (' . $e->getMessage() . ')',
    ]);
    exit;
}
