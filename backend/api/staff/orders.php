<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/gemini.php';
require_once __DIR__ . '/../../includes/gemini.php';
$method = require_method(['GET', 'PATCH']);
$user = require_role('staff');

if ($method === 'PATCH') {
    $body    = json_body();
    $orderId = (int) ($body['order_id'] ?? 0);
    $action  = $body['action'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        json_error('Order not found.', 404);
    }

    $predicted = false; // did this request (re)compute a Gemini estimate?

    if ($action === 'advance') {
        $next = next_status($order['status']);
        if ($next) {
            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$next, $orderId]);
            $pdo->prepare('INSERT INTO order_status_history (order_id, status) VALUES (?, ?)')->execute([$orderId, $next]);

            if ($next === 'Completed') {
                // Terminal state — the outcome is already known, so there's
                // nothing to ask Gemini. Store a deterministic result instead.
                store_prediction($pdo, $orderId, [
                    'remaining_hours'  => 0,
                    'progress_percent' => 100,
                    'finish_at'        => date('Y-m-d H:i:s'),
                    'message'          => 'Your order is complete and ready for pickup!',
                ]);
            } else {
                // The only place a Gemini request ever gets triggered from.
                refresh_order_prediction($pdo, $orderId);
                $predicted = true;
            }
        }
    } elseif ($action === 'cancel') {
        $pdo->prepare('UPDATE orders SET status = "Cancelled" WHERE id = ?')->execute([$orderId]);
        $pdo->prepare('INSERT INTO order_status_history (order_id, status) VALUES (?, "Cancelled")')->execute([$orderId]);
        store_prediction($pdo, $orderId, [
            'remaining_hours'  => null,
            'progress_percent' => 0,
            'finish_at'        => null,
            'message'          => 'This order was cancelled.',
        ]);
    } else {
        json_error('Unknown action.', 422);
    }

    json_response(['success' => true, 'predicted' => $predicted]);
}

// GET
$filter = $_GET['status'] ?? 'All';

$sql = 'SELECT o.*, s.name AS service_name, u.name AS customer_name
        FROM orders o
        JOIN services s ON s.id = o.service_id
        JOIN users u ON u.id = o.customer_id';
$params = [];
if ($filter !== 'All') {
    $sql .= ' WHERE o.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY o.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$totalOrders = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$activeCount = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('Completed','Cancelled')")->fetchColumn();
$readyCount  = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Ready for Pickup'")->fetchColumn();

$filters = array_merge(['All'], STATUS_FLOW, ['Cancelled']);

json_response([
    'orders'  => $orders,
    'filter'  => $filter,
    'filters' => $filters,
    'stats'   => [
        'total'  => $totalOrders,
        'active' => $activeCount,
        'ready'  => $readyCount,
    ],
]);
