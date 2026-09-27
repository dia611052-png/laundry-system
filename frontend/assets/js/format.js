/* ============================================================
   FreshTrack — presentation helpers.
   Direct ports of includes/functions.php + includes/constants.php.
   The old app.js already duplicated STATUS_FLOW client-side for its
   live-update renderer; this file is that same idea made official
   and shared by every page (initial load AND live updates use it),
   instead of the initial render being done in PHP and only the
   live-update re-render being done in JS.
   ============================================================ */

const STATUS_FLOW = ['Pending', 'Received', 'Washing', 'Drying', 'Ready for Pickup', 'Completed'];

/** Escapes a value for safe insertion into innerHTML — mirrors PHP's e(). */
function e(value) {
  if (value === null || value === undefined) return '';
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/** Mirrors PHP's fmt_money(): peso sign + 2 decimals. */
function fmt_money(n) {
  return '&#8369;' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/** Accepts a MySQL DATETIME string ("Y-m-d H:i:s") and formats it like PHP's fmt_date(). */
function fmt_date(datetime) {
  if (!datetime) return '&mdash;';
  // Safari/iOS won't parse "Y-m-d H:i:s" directly — swap the space for "T".
  const d = new Date(datetime.replace(' ', 'T'));
  if (isNaN(d.getTime())) return '&mdash;';
  return d.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
}

/** Turns "Ready for Pickup" into "ready-for-pickup" for CSS class names. */
function status_slug(status) {
  return status.toLowerCase().replace(/ /g, '-');
}

/** The status that comes after `status` in STATUS_FLOW, or null if it's the last one / not found. */
function next_status(status) {
  const idx = STATUS_FLOW.indexOf(status);
  if (idx === -1 || idx === STATUS_FLOW.length - 1) return null;
  return STATUS_FLOW[idx + 1];
}

function status_pill(status) {
  return `<span class="status-pill status-${status_slug(status)}">${e(status)}</span>`;
}

/** Renders the horizontal Pending → ... → Completed strip, highlighting progress made so far. */
function render_flow_strip(currentStatus) {
  const idx = STATUS_FLOW.indexOf(currentStatus);
  let html = '<div class="flow-strip" style="margin-top:.7rem;">';
  STATUS_FLOW.forEach((s, i) => {
    let cls = '';
    if (currentStatus !== 'Cancelled' && idx !== -1) {
      if (i < idx) cls = 'done';
      else if (i === idx) cls = 'on';
    }
    html += `<span class="flow-chip ${cls}">${e(s)}</span>`;
    if (i < STATUS_FLOW.length - 1) html += '<span class="flow-arrow">&rarr;</span>';
  });
  html += '</div>';
  return html;
}
