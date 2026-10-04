<?php
/**
 * Saved finish-time estimates ("predictions").
 *
 * HOW IT WORKS
 *   - An estimate is created ONLY when a staff member changes an order's
 *     status (backend/api/staff/orders.php calls generate_order_prediction()).
 *     That is the one and only place Gemini is ever contacted.
 *   - The estimate is saved as a new row in order_predictions and never
 *     recomputed. Old rows are kept as a history.
 *   - Customer endpoints and ws-server.php only READ the newest saved row
 *     (PREDICTION_SELECT + PREDICTION_JOIN + attach_prediction()). They never
 *     call Gemini, so page loads and the WebSocket timer can't change an
 *     estimate or spend API calls.
 */

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/gemini.php';

/* ------------------------------------------------------------------
 * READING (used by customer/orders.php and ws-server.php)
 * ------------------------------------------------------------------ */

/** Extra SELECT columns for the newest saved prediction of each order (alias `o` = orders). */
const PREDICTION_SELECT = '
    p.id                   AS prediction_id,
    p.status_at_prediction AS prediction_status,
    p.remaining_hours      AS prediction_remaining_hours,
    p.progress_percent     AS prediction_progress_percent,
    p.finish_at            AS prediction_finish_at,
    p.message              AS prediction_message,
    p.source               AS prediction_source,
    p.created_at           AS prediction_created_at';

/** Joins each order to its newest saved prediction (or NULLs if staff haven't made one yet). */
const PREDICTION_JOIN = '
    LEFT JOIN order_predictions p
           ON p.id = (SELECT MAX(op.id) FROM order_predictions op WHERE op.order_id = o.id)';

/**
 * Turns the flat prediction_* columns of a joined row into one nested
 * `prediction` object (or null when the order has no saved estimate yet).
 */
function attach_prediction(array $row): array
{
    $prediction = null;

    if (isset($row['prediction_id'])) {
        $prediction = [
            'id'               => (int) $row['prediction_id'],
            'status'           => $row['prediction_status'],
            'remaining_hours'  => $row['prediction_remaining_hours'] !== null ? (float) $row['prediction_remaining_hours'] : null,
            'progress_percent' => $row['prediction_progress_percent'] !== null ? (float) $row['prediction_progress_percent'] : null,
            'finish_at'        => $row['prediction_finish_at'],
            'message'          => (string) $row['prediction_message'],
            'source'           => $row['prediction_source'],
            'predicted_at'     => $row['prediction_created_at'],
        ];
    }

    foreach (array_keys($row) as $key) {
        if (strpos($key, 'prediction_') === 0) {
            unset($row[$key]);
        }
    }
    $row['prediction'] = $prediction;
    return $row;
}

/* ------------------------------------------------------------------
 * WRITING (staff action only)
 * ------------------------------------------------------------------ */

/** Deterministic completion % from the position in STATUS_FLOW (0/20/40/60/80/100). Mirrors format.js. */
function status_progress_percent(string $status): float
{
    $idx = array_search($status, STATUS_FLOW, true);
    if ($idx === false) return 0.0;
    return round(($idx / (count(STATUS_FLOW) - 1)) * 100, 2);
}

/** "now" according to the database, so every timestamp we store/compare uses the same clock and timezone. */
function db_now(PDO $pdo): DateTimeImmutable
{
    return new DateTimeImmutable((string) $pdo->query('SELECT NOW()')->fetchColumn());
}

/** 0.5 -> "30 minutes", 2.4 -> "2.4 hours" */
function describe_hours(float $hours): string
{
    if ($hours < 1) {
        $minutes = max(1, (int) round($hours * 60));
        return $minutes . ($minutes === 1 ? ' minute' : ' minutes');
    }
    $rounded = round($hours, 1);
    return $rounded . ($rounded == 1 ? ' hour' : ' hours');
}

/**
 * Plain estimate used when Gemini can't be reached: the service's stated
 * turnaround, scaled by how far through the work the order is. "Ready for
 * Pickup" is when the laundry itself is done, so that's where the turnaround
 * reaches zero (what's left after it is up to the customer collecting it).
 */
function fallback_estimate(array $order): array
{
    $status   = $order['status'];
    $progress = status_progress_percent($status);
    $etaHours = eta_to_hours((string) ($order['service_eta'] ?? ''));

    $idx      = array_search($status, STATUS_FLOW, true);
    $readyIdx = array_search('Ready for Pickup', STATUS_FLOW, true);

    if ($status === 'Ready for Pickup') {
        return ['remaining' => 0.0, 'progress' => $progress, 'message' => 'Your order is ready for pickup!'];
    }
    if ($etaHours === null || $idx === false) {
        return [
            'remaining' => null,
            'progress'  => $progress,
            'message'   => "Your order is now {$status}. We'll keep you posted as it moves along.",
        ];
    }

    $remaining = round($etaHours * (1 - min(1, $idx / $readyIdx)), 1);
    return [
        'remaining' => $remaining,
        'progress'  => $progress,
        'message'   => "Your order is now {$status} and should be ready for pickup in about " . describe_hours($remaining) . '.',
    ];
}

/** Asks Gemini for the estimate. Throws if the call fails or the answer isn't usable. */
function estimate_with_gemini(PDO $pdo, array $order, DateTimeImmutable $now): array
{
    $created      = new DateTimeImmutable($order['created_at']);
    $elapsedHours = max(0.0, round(($now->getTimestamp() - $created->getTimestamp()) / 3600, 2));

    $stmt = $pdo->prepare('SELECT changed_at FROM order_status_history WHERE order_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$order['id']]);
    $lastChangedAt = $stmt->fetchColumn() ?: $order['created_at'];
    $timeInStatusHours = max(0.0, round(($now->getTimestamp() - (new DateTimeImmutable($lastChangedAt))->getTimestamp()) / 3600, 2));

    $examples = load_training_examples($order['service_name']);
    $prompt   = build_prediction_prompt($order, $elapsedHours, $timeInStatusHours, $examples);
    $result   = call_gemini($prompt, GEMINI_PREDICTION_SCHEMA);

    if (!isset($result['predicted_remaining_hours']) || !is_numeric($result['predicted_remaining_hours'])) {
        throw new RuntimeException('Gemini did not return a usable estimate.');
    }

    $progress = (isset($result['progress_percent']) && is_numeric($result['progress_percent']))
        ? max(0.0, min(100.0, (float) $result['progress_percent']))
        : status_progress_percent($order['status']);
    $message = (is_string($result['message'] ?? null) && trim($result['message']) !== '')
        ? trim($result['message'])
        : "Your order is now {$order['status']}.";

    return [
        'remaining' => max(0.0, (float) $result['predicted_remaining_hours']),
        'progress'  => $progress,
        'message'   => $message,
    ];
}

/**
 * Builds and SAVES the estimate for an order that a staff member has just
 * moved to `$order['status']` (so $order must carry the NEW status, plus
 * id, created_at, qty, service_name and service_eta).
 *
 * Never throws for a Gemini problem: if the model is unavailable the saved
 * estimate falls back to the service's stated turnaround and a warning is
 * returned so the staff member knows. Throws only if the database write
 * itself fails.
 *
 * @return array{prediction: array, warning: ?string}
 */
function generate_order_prediction(PDO $pdo, array $order, ?int $staffId): array
{
    $now     = db_now($pdo);
    $status  = $order['status'];
    $warning = null;

    if ($status === 'Completed') {
        $source = 'system';
        $est    = ['remaining' => 0.0, 'progress' => 100.0, 'message' => 'This order is completed.'];
    } elseif ($status === 'Cancelled') {
        $source = 'system';
        $est    = ['remaining' => null, 'progress' => 0.0, 'message' => 'This order was cancelled.'];
    } else {
        try {
            $est    = estimate_with_gemini($pdo, $order, $now);
            $source = 'gemini';
        } catch (Throwable $e) {
            $est     = fallback_estimate($order);
            $source  = 'fallback';
            $warning = 'AI estimate unavailable (' . $e->getMessage() . '). A standard estimate based on the service turnaround was sent to the customer instead.';
        }
    }

    // finish_at is fixed here, once, from the DB clock. Whole seconds, because
    // DateTime::modify() mis-parses fractional values like "+2.4 hours".
    $finishAt = null;
    if ($est['remaining'] !== null) {
        $finishAt = $now->modify('+' . (int) round($est['remaining'] * 3600) . ' seconds')->format('Y-m-d H:i:s');
    }
    $createdAt = $now->format('Y-m-d H:i:s');

    $pdo->prepare(
        'INSERT INTO order_predictions
            (order_id, status_at_prediction, remaining_hours, progress_percent, finish_at, message, source, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $order['id'], $status, $est['remaining'], $est['progress'], $finishAt, $est['message'], $source, $staffId, $createdAt,
    ]);

    return [
        'prediction' => [
            'id'               => (int) $pdo->lastInsertId(),
            'status'           => $status,
            'remaining_hours'  => $est['remaining'],
            'progress_percent' => $est['progress'],
            'finish_at'        => $finishAt,
            'message'          => $est['message'],
            'source'           => $source,
            'predicted_at'     => $createdAt,
        ],
        'warning' => $warning,
    ];
}
