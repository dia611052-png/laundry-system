<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/env.php';
load_env(__DIR__ . '/../.env');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/websocket.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/response.php';

cors_headers();
