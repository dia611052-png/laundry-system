document.addEventListener('DOMContentLoaded', async () => {
  const user = await requireRole('customer');
  if (!user) return;

  await initHeaderFooter(null);
  await initSidebar('customer', 'overview', user.name);

  document.getElementById('welcomeHeading').textContent = `Welcome back, ${user.name.split(' ')[0]}`;

  let orders = [];
  try {
    ({ orders } = await api.get('/customer/orders.php'));
  } catch (err) {
    console.error('Could not load orders', err);
  }

  renderDashboard(orders);

  connectLiveUpdates('customer', { customer_id: user.id }, (data) => {
    updateActiveOrderCards(data.activeOrders);
  });
});

function renderDashboard(orders) {
  const active = orders.filter((o) => !['Completed', 'Cancelled'].includes(o.status));
  const completed = orders.filter((o) => o.status === 'Completed');

  document.getElementById('statTotal').textContent = orders.length;
  document.getElementById('statActive').textContent = active.length;
  document.getElementById('statCompleted').textContent = completed.length;

  const container = document.getElementById('activeOrders');
  if (!active.length) {
    container.innerHTML = `
      <div class="empty-state">
        <h3>Nothing in progress</h3>
        <p>Book a service to see it tracked here.</p>
      </div>
    `;
    return;
  }

  container.innerHTML = active.map(orderCardHtml).join('');
}

function orderCardHtml(o) {
  return `
    <div class="tag-card" style="margin-bottom:1rem;" data-tracking="${e(o.tracking_code)}" data-status="${e(o.status)}">
      <span class="meta">${e(o.tracking_code)} &middot; ${fmt_date(o.created_at)}</span>
      <h3>${e(o.service_name)} <span class="text-muted" style="font-family:var(--font-body); font-weight:400; font-size:.85rem;">&times; ${o.qty}</span></h3>
      <div class="live-region">
        ${render_flow_strip(o.status)}
        ${render_progress_bar(o.status)}
        ${render_predict_box(o)}
      </div>
    </div>
  `;
}

/**
 * Refreshes the flow strip, progress bar, and Gemini estimate together as
 * one block per card — a bulk innerHTML swap rather than juggling several
 * individual node references (simpler, and avoids a stale-reference bug
 * a piecemeal replaceWith() approach had here previously).
 */
function updateActiveOrderCards(activeOrders) {
  const cards = document.querySelectorAll('#activeOrders [data-tracking]');
  if (!cards.length) return;

  const byTrackingCode = new Map(activeOrders.map((o) => [o.tracking_code, o]));
  let updatedCount = 0;

  cards.forEach((card) => {
    const order = byTrackingCode.get(card.dataset.tracking);
    if (!order) return;

    const region = card.querySelector('.live-region');
    if (region) {
      region.innerHTML = render_flow_strip(order.status) + render_progress_bar(order.status) + render_predict_box(order);
    }

    card.dataset.status = order.status;
    updatedCount++;
  });

  if (updatedCount > 0) showToast(`Updated ${updatedCount} order progress indicators`, 'success');
}
