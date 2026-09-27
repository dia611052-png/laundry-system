document.addEventListener('DOMContentLoaded', async () => {
  await initHeaderFooter('home');

  try {
    const { services } = await api.get('/services.php?order=id&limit=3');
    const grid = document.getElementById('popular-services');
    grid.innerHTML = services.map(serviceCardHtml).join('');
  } catch (err) {
    console.error('Could not load services', err);
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
