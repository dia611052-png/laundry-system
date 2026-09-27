<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_method(['GET']);

json_response([
    'user' => current_user(), // null if nobody is logged in
]);
