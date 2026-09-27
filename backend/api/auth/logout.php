<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_method(['POST']);

logout_user();

json_response(['success' => true]);
