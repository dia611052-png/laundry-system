<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_method(['GET']);
$user = require_role('customer');

if (!empty($_GET['id'])) {
    $stmt = $pdo->prepare(
        'SELECT o.*, s.name AS service_name FROM orders o
         JOIN services s ON s.id = o.service_id
         WHERE o.id = ? AND o.customer_id = ?'
    );
    $stmt->execute([(int) $_GET['id'], $user['id']]);
    $order = $stmt->fetch();

    if (!$order) {
        json_error('Order not found.', 404);
    }

    $h = $pdo->prepare('SELECT * FROM order_status_history WHERE order_id = ? ORDER BY changed_at ASC');
    $h->execute([$order['id']]);

    json_response([
        'order'   => $order,
        'history' => $h->fetchAll(),
    ]);
}

$stmt = $pdo->prepare(
    'SELECT o.*, s.name AS service_name FROM orders o
     JOIN services s ON s.id = o.service_id
     WHERE o.customer_id = ? ORDER BY o.created_at DESC'
);
$stmt->execute([$user['id']]);

json_response(['orders' => $stmt->fetchAll()]);
