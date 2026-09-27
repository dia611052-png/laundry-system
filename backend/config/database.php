<?php
/**
 * Database connection.
 * Defaults match a stock XAMPP install (MySQL on localhost, root, no password).
 * Change these four constants if your environment differs.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'freshtrack');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
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
