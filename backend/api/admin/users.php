<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$method = require_method(['GET', 'POST', 'DELETE']);
$user = require_role('admin');

if ($method === 'POST') {
    $body     = json_body();
    $name     = trim($body['name'] ?? '');
    $email    = trim($body['email'] ?? '');
    $password = (string) ($body['password'] ?? '');
    $role     = in_array($body['role'] ?? '', ['staff', 'admin'], true) ? $body['role'] : 'staff';

    if ($name === '' || $email === '' || strlen($password) < 6) {
        json_error('Fill in every field — password needs at least 6 characters.', 422);
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        json_error('That email is already registered.', 422);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)')
        ->execute([$name, $email, $hash, $role]);

    json_response([
        'message' => ucfirst($role) . " account created for $name.",
    ], 201);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);

    if ($id === $user['id']) {
        // an admin can't remove their own account
        json_error("You can't remove your own account.", 422);
    }

    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    json_response(['message' => 'Account removed.']);
}

// GET
// Deliberately not SELECT * — this row goes out as JSON to the browser now,
// so the password hash must never be in it.
$users = $pdo->query('SELECT id, name, email, role, created_at FROM users ORDER BY role, name')->fetchAll();
$counts = ['customer' => 0, 'staff' => 0, 'admin' => 0];
foreach ($users as $u) {
    $counts[$u['role']] = ($counts[$u['role']] ?? 0) + 1;
}

json_response([
    'users'  => $users,
    'counts' => $counts,
]);
