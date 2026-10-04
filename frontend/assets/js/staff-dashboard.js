document.addEventListener('DOMContentLoaded', async () => {
  const user = await requireRole('staff');
  if (!user) return;

  await initHeaderFooter(null);
  await initSidebar('staff', 'queue', user.name);

  await loadAndRender();

  const filter = new URLSearchParams(window.location.search).get('status') || 'All';
  connectLiveUpdates('staff', filter !== 'All' ? { status: filter } : {}, (data) => {
    renderOrdersTable(data.activeOrders);
    showToast(`Order queue updated (${data.activeOrders.length} active)`, 'success');
  });
});

async function loadAndRender() {
  const filter = new URLSearchParams(window.location.search).get('status') || 'All';

  let data;
  try {
    data = await api.get(`/staff/orders.php?status=${encodeURIComponent(filter)}`);
  } catch (err) {
    console.error('Could not load order queue', err);
    return;
  }

  document.getElementById('statTotal').textContent = data.stats.total;
  document.getElementById('statActive').textContent = data.stats.active;
  document.getElementById('statReady').textContent = data.stats.ready;

  renderFilterChips(data.filters, data.filter);
  renderOrdersTable(data.orders);
}

function renderFilterChips(filters, activeFilter) {
  const strip = document.getElementById('filterStrip');
  strip.innerHTML = filters.map((f) => `
    <a class="flow-chip ${f === activeFilter ? 'on' : ''}" href="dashboard.html${f !== 'All' ? '?status=' + encodeURIComponent(f) : ''}" style="text-decoration:none;">${e(f)}</a>
  `).join('');
}

function renderOrdersTable(orders) {
  const tbody = document.getElementById('ordersBody');
  if (!orders.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-muted">No orders match this filter.</td></tr>';
    return;
  }

  tbody.innerHTML = orders.map((o) => {
    const next = next_status(o.status);
    const canCancel = !['Completed', 'Cancelled'].includes(o.status);

    const advanceBtn = next
      ? `<button type="button" class="btn-tag small" data-action="advance" data-id="${o.id}" data-tracking="${e(o.tracking_code)}">Mark ${e(next)}</button>`
      : '';
    const cancelBtn = canCancel
      ? `<button type="button" class="btn-tag danger small" data-action="cancel" data-id="${o.id}" data-tracking="${e(o.tracking_code)}">Cancel</button>`
      : '';

    return `
      <tr>
        <td>${e(o.tracking_code)}</td>
        <td>${e(o.customer_name)}</td>
        <td>${e(o.service_name)}</td>
        <td>${o.qty}</td>
        <td>${fmt_date(o.created_at)}</td>
        <td>${status_pill(o.status)}</td>
        <td><div style="display:flex; gap:.4rem; flex-wrap:wrap;">${advanceBtn}${cancelBtn}</div></td>
      </tr>
    `;
  }).join('');

  tbody.querySelectorAll('[data-action]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const action = btn.dataset.action;
      const orderId = Number(btn.dataset.id);
      const tracking = btn.dataset.tracking;

      if (action === 'cancel' && !confirmAction(`Cancel order ${tracking}?`)) return;

      // Advancing can now also trigger a Gemini call server-side (unless
      // it's advancing straight to Completed), so this can take a moment.
      const originalText = btn.textContent;
      btn.disabled = true;
      if (action === 'advance') btn.textContent = 'Updating…';

      try {
        const result = await api.patch('/staff/orders.php', { order_id: orderId, action });
        await loadAndRender();
        if (result.predicted) showToast(`Estimate updated for ${tracking}`, 'success');
      } catch (err) {
        btn.disabled = false;
        btn.textContent = originalText;
        showToast(err.message, 'error');
      }
    });
  });
}
