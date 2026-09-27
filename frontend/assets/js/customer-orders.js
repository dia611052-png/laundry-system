document.addEventListener('DOMContentLoaded', async () => {
  const user = await requireRole('customer');
  if (!user) return;

  await initHeaderFooter(null);
  await initSidebar('customer', 'orders', user.name);

  const selectedId = new URLSearchParams(window.location.search).get('id');

  let orders = [];
  try {
    ({ orders } = await api.get('/customer/orders.php'));
  } catch (err) {
    console.error('Could not load orders', err);
  }

  renderOrdersTable(orders, selectedId);

  if (selectedId) {
    try {
      const { order, history } = await api.get(`/customer/orders.php?id=${encodeURIComponent(selectedId)}`);
      renderTrackingDetail(order, history);
    } catch (err) {
      console.error('Could not load order detail', err);
    }
  }
});

function renderOrdersTable(orders, selectedId) {
  const tbody = document.getElementById('ordersBody');
  if (!orders.length) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-muted">No orders yet.</td></tr>';
    return;
  }

  tbody.innerHTML = orders.map((o) => `
    <tr style="${selectedId && String(o.id) === selectedId ? 'background:var(--paper-deep);' : ''}">
      <td>${e(o.tracking_code)}</td>
      <td>${e(o.service_name)}</td>
      <td>${o.qty}</td>
      <td>${fmt_date(o.created_at)}</td>
      <td>${status_pill(o.status)}</td>
      <td><a class="btn-tag ghost small" href="orders.html?id=${o.id}">Track</a></td>
    </tr>
  `).join('');
}

function renderTrackingDetail(order, history) {
  document.getElementById('trackingHeading').textContent = `${order.tracking_code} — ${order.service_name}`;

  const notesLine = order.notes ? `<p class="text-muted">Notes: ${e(order.notes)}</p>` : '';
  const historyLines = history.map((h) => `
    <div style="font-size:.85rem; margin-bottom:.2rem;">
      ${status_pill(h.status)} <span class="text-muted">${fmt_date(h.changed_at)}</span>
    </div>
  `).join('');

  document.getElementById('trackingDetail').innerHTML = `
    <div class="tag-card">
      <span class="meta">booked ${fmt_date(order.created_at)}</span>
      ${notesLine}
      ${render_flow_strip(order.status)}
      <div style="margin-top:1.1rem;">
        <span class="meta" style="display:block; margin-bottom:.4rem;">HISTORY</span>
        ${historyLines}
      </div>
    </div>
  `;
}
