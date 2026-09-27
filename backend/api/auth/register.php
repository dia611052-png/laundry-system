<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_method(['POST']);

$body     = json_body();
$name     = trim($body['name'] ?? '');
$email    = trim($body['email'] ?? '');
$password = (string) ($body['password'] ?? '');

if ($name === '' || $email === '' || strlen($password) < 6) {
    json_error('Fill in every field — password needs at least 6 characters.', 422);
}

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    json_error('That email is already registered.', 422);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'customer')");
$stmt->execute([$name, $email, $hash]);

login_user([
    'id'    => $pdo->lastInsertId(),
    'name'  => $name,
    'email' => $email,
    'role'  => 'customer',
]);

json_response([
    'user' => current_user(),
], 201);
