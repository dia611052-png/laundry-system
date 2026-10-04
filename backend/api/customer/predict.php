<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/gemini.php';
require_once __DIR__ . '/../../includes/gemini.php';
require_method(['GET']);
$user = require_role('customer');

$orderId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT o.*, s.name AS service_name, s.eta AS service_eta FROM orders o
     JOIN services s ON s.id = o.service_id
     WHERE o.id = ? AND o.customer_id = ?'
);
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    json_error('Order not found.', 404);
}

$lastChangedStmt = $pdo->prepare('SELECT changed_at FROM order_status_history WHERE order_id = ? ORDER BY changed_at DESC LIMIT 1');
$lastChangedStmt->execute([$order['id']]);
$lastChangedAt = $lastChangedStmt->fetchColumn() ?: $order['created_at'];

// Completed/Cancelled are terminal — no need to call Gemini for those.
if ($order['status'] === 'Completed') {
    json_response([
        'remaining_hours'  => 0,
        'progress_percent' => 100,
        'finish_at'        => $lastChangedAt,
        'message'          => 'This order is already completed.',
    ]);
}
if ($order['status'] === 'Cancelled') {
    json_response([
        'remaining_hours'  => null,
        'progress_percent' => 0,
        'finish_at'        => null,
        'message'          => 'This order was cancelled.',
    ]);
}

$now = new DateTime();
$created = new DateTime($order['created_at']);
$elapsedHours = round(($now->getTimestamp() - $created->getTimestamp()) / 3600, 2);

$lastChanged = new DateTime($lastChangedAt);
$timeInStatusHours = round(($now->getTimestamp() - $lastChanged->getTimestamp()) / 3600, 2);

$examples = load_training_examples($order['service_name']);
$prompt = build_prediction_prompt($order, $elapsedHours, $timeInStatusHours, $examples);

try {
    $prediction = call_gemini($prompt, GEMINI_PREDICTION_SCHEMA);
} catch (Throwable $e) {
    json_error('Prediction unavailable: ' . $e->getMessage(), 502);
}

$remaining = isset($prediction['predicted_remaining_hours']) ? max(0, (float) $prediction['predicted_remaining_hours']) : null;
$progress  = isset($prediction['progress_percent']) ? max(0, min(100, (float) $prediction['progress_percent'])) : null;
$message   = is_string($prediction['message'] ?? null) ? $prediction['message'] : '';

$finishAt = null;
if ($remaining !== null) {
    // Whole seconds, not "+X hours" with a fractional X — DateTime::modify()
    // does not parse fractional hour values correctly (e.g. "+2.4 hours"
    // silently produces the wrong time).
    $finish = clone $now;
    $finish->modify('+' . (int) round($remaining * 3600) . ' seconds');
    $finishAt = $finish->format('Y-m-d H:i:s');
}

json_response([
    'remaining_hours'  => $remaining,
    'progress_percent' => $progress,
    'finish_at'        => $finishAt,
    'message'          => $message,
]);
