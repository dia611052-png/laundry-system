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
    <div class="tag-card" style="margin-bottom:1rem;" data-tracking="${e(o.tracking_code)}">
      <span class="meta">${e(o.tracking_code)} &middot; ${fmt_date(o.created_at)}</span>
      <h3>${e(o.service_name)} <span class="text-muted" style="font-family:var(--font-body); font-weight:400; font-size:.85rem;">&times; ${o.qty}</span></h3>
      ${render_flow_strip(o.status)}
    </div>
  `;
}

/** Updates just the flow-strip progress bars in place — same effect as the old updateCustomerOrderProgress(). */
function updateActiveOrderCards(activeOrders) {
  const cards = document.querySelectorAll('#activeOrders [data-tracking]');
  if (!cards.length) return;

  const byTrackingCode = new Map(activeOrders.map((o) => [o.tracking_code, o]));
  let updatedCount = 0;

  cards.forEach((card) => {
    const order = byTrackingCode.get(card.dataset.tracking);
    if (!order) return;
    const strip = card.querySelector('.flow-strip');
    if (strip) {
      strip.outerHTML = render_flow_strip(order.status);
      updatedCount++;
    }
  });

  if (updatedCount > 0) showToast(`Updated ${updatedCount} order progress indicators`, 'success');
}
