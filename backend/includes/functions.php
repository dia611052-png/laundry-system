<?php
/** The status that comes after $status in STATUS_FLOW, or null if it's the last one / not found. */
function next_status(string $status): ?string
{
    $idx = array_search($status, STATUS_FLOW, true);
    if ($idx === false || $idx === count(STATUS_FLOW) - 1) return null;
    return STATUS_FLOW[$idx + 1];
}

/** Builds the next sequential tracking code, e.g. FT-10023, based on the highest one stored. */
function generate_tracking_code(PDO $pdo): string
{
    $last = $pdo->query("SELECT tracking_code FROM orders ORDER BY id DESC LIMIT 1")->fetchColumn();
    $num  = $last ? (int) str_replace('FT-', '', $last) : 10020;
    return 'FT-' . ($num + 1);
}
