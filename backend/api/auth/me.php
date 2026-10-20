<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_method(['GET']);

$user = current_user();
release_session_lock();   // this endpoint is hit first on every dashboard

json_response(['user' => $user]);
