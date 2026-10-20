<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_method(['GET']);
$user = require_role('customer');
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
$stmt = $pdo->prepare(
    'SELECT o.*, s.name AS service_name FROM orders o
     JOIN services s ON s.id = o.service_id
     WHERE o.customer_id = ? ORDER BY o.created_at DESC LIMIT ' . $limit
);
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

// Optional: attach each order's status history in one extra query so the
// frontend doesn't have to fire one HTTP request per order. Same data, one
// DB round-trip instead of N.
if (!empty($_GET['include_history']) && $orders) {
    $ids = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $h = $pdo->prepare(
        "SELECT * FROM order_status_history
         WHERE order_id IN ($placeholders)
         ORDER BY changed_at ASC"
    );
    $h->execute($ids);

    $byOrder = [];
    foreach ($h->fetchAll() as $row) {
        $byOrder[$row['order_id']][] = $row;
    }
    foreach ($orders as &$o) {
        $o['history'] = $byOrder[$o['id']] ?? [];
    }
    unset($o);
}

json_response(['orders' => $orders]);
