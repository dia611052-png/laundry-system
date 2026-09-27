<?php
/**
 * The order status flow, in order. Cancelled is a separate terminal
 * state reachable from any non-final status, so it's kept out of this
 * sequence and handled as a special case wherever the flow is used.
 */
define('STATUS_FLOW', ['Pending', 'Received', 'Washing', 'Drying', 'Ready for Pickup', 'Completed']);

/** Turns "Ready for Pickup" into "ready-for-pickup" for CSS class names. */
function status_slug(string $status): string
{
    return strtolower(str_replace(' ', '-', $status));
}
