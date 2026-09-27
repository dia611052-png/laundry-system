document.addEventListener('DOMContentLoaded', async () => {
  const user = await initHeaderFooter('services');

  try {
    const { services } = await api.get('/services.php?order=id');
    const grid = document.getElementById('all-services');
    grid.innerHTML = services.map((s) => serviceCardHtml(s, user)).join('');
  } catch (err) {
    console.error('Could not load services', err);
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
