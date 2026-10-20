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

  // Prefetch all details to prevent loading/refresh on click
  const trackingCache = {};
  if (orders.length > 0) {
    await Promise.all(orders.map(async (o) => {
      try {
        const data = await api.get(`/customer/orders.php?id=${encodeURIComponent(o.id)}`);
        trackingCache[o.id] = data;
      } catch (err) {
        console.error('Could not prefetch detail for', o.id, err);
      }
    }));
  }

  let currentTrackingId = selectedId;

  // If URL has an id, show it immediately
  if (selectedId && trackingCache[selectedId]) {
    renderTrackingDetail(trackingCache[selectedId].order, trackingCache[selectedId].history);
    positionTrackingDetail(selectedId);
  }

  // Handle Track button clicks
  document.getElementById('ordersBody').addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-track');
    if (!btn) return;
    
    e.preventDefault(); // Prevents page reload

    const id = btn.dataset.id;
    const data = trackingCache[id];
    if (!data) {
      alert('Tracking data is not available yet.');
      return;
    }

    currentTrackingId = id;
    renderTrackingDetail(data.order, data.history);
    positionTrackingDetail(id);
  });

  // Handle resizing (e.g. rotating phone to desktop) to reset layout
  window.addEventListener('resize', () => {
    if (currentTrackingId) {
      positionTrackingDetail(currentTrackingId, true);
    }
  });

  function positionTrackingDetail(id, isResize = false) {
    const isMobile = window.innerWidth <= 700;
    const main = document.querySelector('.dash-main');
    
    // Grab the actual DOM elements
    let actualHead = document.querySelector('.section-head');
    let actualDetail = document.getElementById('trackingDetail');
    if (!actualHead || !actualDetail) return;

    // Clean up previous wrapper if it exists
    const existing = document.getElementById('tracking-wrapper');
    if (existing) {
      main.appendChild(actualHead);
      main.appendChild(actualDetail);
      existing.remove();
    }

    if (isMobile) {
      const clickedRow = document.getElementById(`order-row-${id}`);
      if (!clickedRow) return;

      // Create a new table row to hold the tracking detail
      const tr = document.createElement('tr');
      tr.id = 'tracking-wrapper';
      tr.className = 'tracking-row';
      const td = document.createElement('td');
      td.colSpan = 6;

      td.appendChild(actualHead);
      td.appendChild(actualDetail);
      tr.appendChild(td);

      // Insert it right below the clicked card
      clickedRow.parentNode.insertBefore(tr, clickedRow.nextSibling);

      actualHead.style.display = 'block';
      actualDetail.style.display = 'block';

      if (!isResize) {
        tr.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    } else {
      // On Desktop, push it back to the bottom of the page
      main.appendChild(actualHead);
      main.appendChild(actualDetail);
      actualHead.style.display = 'block';
      actualDetail.style.display = 'block';
    }
  }

  function renderOrdersTable(orders, selectedId) {
    const tbody = document.getElementById('ordersBody');
    if (!orders.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-muted">No orders yet.</td></tr>';
      return;
    }

    tbody.innerHTML = orders.map((o) => `
      <tr id="order-row-${o.id}" style="${selectedId && String(o.id) === selectedId ? 'background:var(--paper-deep);' : ''}">
        <td>${e(o.tracking_code)}</td>
        <td>${e(o.service_name)}</td>
        <td>${o.qty}</td>
        <td>${fmt_date(o.created_at)}</td>
        <td>${status_pill(o.status)}</td>
        <td><button type="button" class="btn-tag ghost small btn-track" data-id="${o.id}">Track</button></td>
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

    const detailEl = document.getElementById('trackingDetail');
    detailEl.innerHTML = `
      <div class="tag-card">
        <span class="meta">booked ${fmt_date(order.created_at)}</span>
        ${notesLine}
        ${render_flow_strip(order.status)}
        ${render_progress_bar(order.status)}
        ${render_predict_box(order)}
        <div style="margin-top:1.1rem;">
          <span class="meta" style="display:block; margin-bottom:.4rem;">HISTORY</span>
          ${historyLines}
        </div>
      </div>
    `;
  }
});