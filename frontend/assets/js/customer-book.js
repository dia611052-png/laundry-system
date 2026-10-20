document.addEventListener('DOMContentLoaded', async () => {
  // 1. Fire off the layout loading in parallel (don't await yet)
  const layoutPromise = initHeaderFooter(null);
  const sidebarPromise = initSidebar('customer', 'book', 'Loading...'); // Show placeholder name

  // 2. Check auth at the same time
  const user = await requireRole('customer');
  if (!user) return; // If not logged in, it redirects and stops here

  // 3. Wait for layout to finish, then update the sidebar with the real name
  await layoutPromise;
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

    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.textContent;

    // 1. Disable button and change text
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';

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
    } finally {
      // 2. Always restore the button state, whether it succeeded or failed
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;
    }
  });
});
