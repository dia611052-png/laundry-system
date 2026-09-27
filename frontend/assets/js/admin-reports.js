document.addEventListener('DOMContentLoaded', async () => {
  const user = await requireRole('admin');
  if (!user) return;

  await initHeaderFooter(null);
  await initSidebar('admin', 'reports', user.name);

  let data;
  try {
    data = await api.get('/admin/reports.php');
  } catch (err) {
    console.error('Could not load reports', err);
    return;
  }

  renderStatusGrid(data.statusCounts);
  renderServiceStats(data.serviceStats);

  connectLiveUpdates('admin', {}, (update) => {
    renderStatusGrid(update.statusCounts);
  });
});

function renderStatusGrid(statusCounts) {
  const grid = document.getElementById('statusGrid');
  grid.innerHTML = Object.entries(statusCounts).map(([status, count]) => `
    <div class="tag-card">
      <div class="n" style="font-family:var(--font-display); font-size:2rem; color:var(--indigo-deep);">${count}</div>
      <div>${status_pill(status)}</div>
    </div>
  `).join('');
}

function renderServiceStats(serviceStats) {
  const tbody = document.getElementById('serviceStatsBody');
  if (!serviceStats.length) {
    tbody.innerHTML = '<tr><td colspan="3" class="text-muted">No services yet.</td></tr>';
    return;
  }
  tbody.innerHTML = serviceStats.map((s) => `
    <tr>
      <td>${e(s.name)}</td>
      <td>${s.order_count}</td>
      <td>${fmt_money(s.revenue)}</td>
    </tr>
  `).join('');
}
