/* ============================================================
   FreshTrack — read-only "Gemini estimate" display.

   This used to be a customer-clickable button; it isn't anymore.
   Predictions are now only ever generated server-side, triggered by
   staff advancing an order's status (see backend/api/staff/orders.php),
   and persisted on the order row (predicted_remaining_hours,
   predicted_progress_percent, predicted_finish_at, predicted_message,
   predicted_at). The customer just displays whatever's already there —
   which is also why it survives a page refresh with no extra request.
   ============================================================ */

/**
 * Renders the Gemini estimate box for one order, purely from fields
 * already present on the order object (no fetch). Three states:
 *  - no prediction yet (predicted_message is null) → a quiet placeholder
 *  - a prediction exists → the stored message + "~X left · ready around Y"
 *  - terminal orders (Completed/Cancelled) get their message with no meta
 */
function render_predict_box(order) {
  if (!order.predicted_message) {
    return `
      <div class="predict-box placeholder">
        <div class="predict-head">
          <span class="predict-title">✦ GEMINI ESTIMATE</span>
        </div>
        <div class="predict-message">An estimate will appear here once staff starts processing your order.</div>
      </div>
    `;
  }

  const hasEta = order.predicted_finish_at && order.predicted_remaining_hours !== null;
  const meta = hasEta
    ? `<div class="predict-meta">~${formatRemainingHours(order.predicted_remaining_hours)} left \u00B7 ready around ${fmt_date(order.predicted_finish_at)}</div>`
    : '';

  return `
    <div class="predict-box">
      <div class="predict-head">
        <span class="predict-title">✦ GEMINI ESTIMATE</span>
      </div>
      <div class="predict-message">${e(order.predicted_message)}</div>
      ${meta}
    </div>
  `;
}

function formatRemainingHours(hours) {
  const h = Number(hours);
  if (h < 1) return Math.round(h * 60) + ' min';
  return (Math.round(h * 10) / 10) + (h === 1 ? ' hr' : ' hrs');
}
