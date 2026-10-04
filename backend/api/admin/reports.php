<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_method(['GET']);
require_role('admin');

$statusCounts = [];
foreach (array_merge(STATUS_FLOW, ['Cancelled']) as $s) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE status = ?');
    $stmt->execute([$s]);
    $statusCounts[$s] = (int) $stmt->fetchColumn();
}

$serviceStats = $pdo->query(
    'SELECT s.name, s.price, s.unit, COUNT(o.id) AS order_count, COALESCE(SUM(o.qty * s.price), 0) AS revenue
     FROM services s
     LEFT JOIN orders o ON o.service_id = s.id
     GROUP BY s.id, s.name, s.price, s.unit
     ORDER BY s.name'
)->fetchAll();

json_response([
    'statusCounts' => $statusCounts,
    'serviceStats' => $serviceStats,
]);
