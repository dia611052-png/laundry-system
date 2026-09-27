document.addEventListener('DOMContentLoaded', async () => {
  const user = await requireRole('customer');
  if (!user) return;

  await initHeaderFooter(null);
  await initSidebar('customer', 'book', user.name);

  const select = document.getElementById('service_id');
  const note = document.getElementById('serviceNote');
  const errorMsg = document.getElementById('errorMsg');
  const successMsg = document.getElementById('successMsg');

  const preselect = new URLSearchParams(window.location.search).get('service');

  let services = [];
  try {
    ({ services } = await api.get('/services.php?order=name'));
  } catch (err) {
    showFormMessage(errorMsg, 'Could not load services: ' + err.message, 'error');
    return;
  }

  select.innerHTML = services
    .map((s) => `
      <option value="${s.id}" data-desc="${e(s.description)}" data-eta="${e(s.eta)}"
        ${String(s.id) === preselect ? 'selected' : ''}>
        ${e(s.name)} &mdash; ${fmt_money(s.price)} ${e(s.unit)}
      </option>
    `)
    .join('');

  const updateNote = () => {
    const opt = select.selectedOptions[0];
    if (!opt) return;
    note.textContent = `${opt.dataset.desc} Est. turnaround: ${opt.dataset.eta}.`;
  };
  select.addEventListener('change', updateNote);
  updateNote();

  const dropoff = document.getElementById('dropoff');
  const today = new Date().toISOString().slice(0, 10);
  dropoff.value = today;
  dropoff.min = today;

  document.getElementById('bookForm').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    showFormMessage(errorMsg, '');
    showFormMessage(successMsg, '');

    const body = {
      service_id: Number(select.value),
      qty: Number(document.getElementById('qty').value) || 1,
      notes: document.getElementById('notes').value.trim(),
      dropoff: dropoff.value || null,
    };

    try {
      const data = await api.post('/customer/book.php', body);
      showFormMessage(successMsg, data.message, 'success');
      document.getElementById('qty').value = 1;
      document.getElementById('notes').value = '';
      dropoff.value = today;
    } catch (err) {
      showFormMessage(errorMsg, err.message, 'error');
    }
  });
});
