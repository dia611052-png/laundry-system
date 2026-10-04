<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_method(['POST']);
$user = require_role('customer');

$body      = json_body();
$serviceId = (int) ($body['service_id'] ?? 0);
$qty       = max(1, (int) ($body['qty'] ?? 1));
$notes     = trim($body['notes'] ?? '');
$dropoff   = $body['dropoff'] ?: null;

$exists = $pdo->prepare('SELECT id FROM services WHERE id = ?');
$exists->execute([$serviceId]);

if (!$exists->fetch()) {
    json_error('Please choose a valid service.', 422);
}

$trackingCode = generate_tracking_code($pdo);

$pdo->beginTransaction();
$pdo->prepare(
    'INSERT INTO orders (tracking_code, customer_id, service_id, qty, notes, preferred_dropoff, status)
     VALUES (?, ?, ?, ?, ?, ?, "Pending")'
)->execute([$trackingCode, $user['id'], $serviceId, $qty, $notes, $dropoff]);
$orderId = $pdo->lastInsertId();
$pdo->prepare('INSERT INTO order_status_history (order_id, status) VALUES (?, "Pending")')->execute([$orderId]);
$pdo->commit();

json_response([
    'tracking_code' => $trackingCode,
    'message'       => "Booked! Your tracking ID is $trackingCode. We'll move it to Received once it's dropped off.",
], 201);
