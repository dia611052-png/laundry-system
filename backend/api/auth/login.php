<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_method(['POST']);

$body     = json_body();
$email    = trim($body['email'] ?? '');
$password = (string) ($body['password'] ?? '');

$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    json_error('Email or password not recognized.', 401);
}

login_user($user);

json_response([
    'user' => current_user(),
]);
