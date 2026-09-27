<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_method(['GET']);

$order = ($_GET['order'] ?? 'id') === 'name' ? 'name' : 'id';
$sql = "SELECT * FROM services ORDER BY {$order}";

if (isset($_GET['limit'])) {
    $limit = max(1, (int) $_GET['limit']);
    $sql .= ' LIMIT ' . $limit;
}

$services = $pdo->query($sql)->fetchAll();

json_response(['services' => $services]);
