document.addEventListener('DOMContentLoaded', async () => {
  try {
    // Run header load and API fetch at the same time
    const [_, response] = await Promise.all([
      initHeaderFooter('home'),
      api.get('/services.php?order=id&limit=3')
    ]);
    
    const { services } = response;
    const grid = document.getElementById('popular-services');
    
    if (!services || services.length === 0) {
      grid.innerHTML = '<p class="text-muted" style="grid-column: 1 / -1; text-align: center;">No services available at the moment.</p>';
      return;
    }
    
    grid.innerHTML = services.map(serviceCardHtml).join('');
  } catch (err) {
    console.error('Could not load services', err);
    // Show the error directly on the screen so you know what's wrong
    document.getElementById('popular-services').innerHTML = `
      <div class="empty-state" style="border-color: var(--warn); color: var(--warn); grid-column: 1 / -1;">
        <h3>Could not connect to the database</h3>
        <p>${e(err.message)}</p>
      </div>
    `;
  }
});

function serviceCardHtml(s) {
  return `
    <div class="tag-card">
      <span class="price">${fmt_money(s.price)} <span class="text-muted" style="font-family:var(--font-body); font-weight:400; font-size:.8rem;">${e(s.unit)}</span></span>
      <h3>${e(s.name)}</h3>
      <p>${e(s.description)}</p>
      <span class="meta">Est. ${e(s.eta)}</span>
    </div>
  `;
}