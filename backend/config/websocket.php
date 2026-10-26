<?php

define('WS_HOST', getenv('WS_HOST') ?: '0.0.0.0');
define('WS_PORT', (int) (getenv('WS_PORT') ?: 8080));

define('WS_BROADCAST_INTERVAL', (float) (getenv('WS_BROADCAST_INTERVAL') ?: 2));

$wsDefaultHost = (php_sapi_name() !== 'cli' && !empty($_SERVER['SERVER_NAME']))
    ? $_SERVER['SERVER_NAME']
    : 'localhost';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

$defaultProtocol = $isHttps ? 'wss://' : 'ws://';

define('WS_PUBLIC_URL', getenv('WS_PUBLIC_URL') ?: ($defaultProtocol . $wsDefaultHost . ':' . WS_PORT));
