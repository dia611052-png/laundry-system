<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_method(['GET']);

json_response([
    'wsUrl' => WS_PUBLIC_URL,
]);
