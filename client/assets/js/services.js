document.addEventListener('DOMContentLoaded', async () => {
  try {
    // Run header load and API fetch at the same time
    const [user, response] = await Promise.all([
      initHeaderFooter('services'),
      api.get('/services.php?order=id')
    ]);
    
    const { services } = response;
    const grid = document.getElementById('all-services');
    
    // Once data arrives, clear skeletons
    if (!services || services.length === 0) {
      grid.innerHTML = '<p class="text-muted" style="grid-column: 1 / -1; text-align: center;">No services available at the moment.</p>';
      return;
    }
    
    grid.innerHTML = services.map((s) => serviceCardHtml(s, user)).join('');
  } catch (err) {
    console.error('Could not load services', err);
    document.getElementById('all-services').innerHTML = `
      <div class="empty-state" style="border-color: var(--warn); color: var(--warn); grid-column: 1 / -1;">
        <h3>Could not connect to the database</h3>
        <p>${e(err.message)}</p>
      </div>
    `;
  }
});

function serviceCardHtml(s, user) {
  let bookLink = '';
  if (user && user.role === 'customer') {
    bookLink = `<a class="btn-tag small marigold" href="customer/book.html?service=${s.id}">Book this service</a>`;
  } else if (!user) {
    bookLink = `<a class="btn-tag small marigold" href="auth/register.html">Book this service</a>`;
  }

  return `
    <div class="tag-card">
      <span class="price">${fmt_money(s.price)} <span class="text-muted" style="font-family:var(--font-body); font-weight:400; font-size:.8rem;">${e(s.unit)}</span></span>
      <h3>${e(s.name)}</h3>
      <p>${e(s.description)}</p>
      <span class="meta">Est. turnaround: ${e(s.eta)}</span>
      <div style="margin-top:.9rem;">${bookLink}</div>
    </div>
  `;
}