document.addEventListener('DOMContentLoaded', async () => {
  const user = await requireRole('admin');
  if (!user) return;

  await initHeaderFooter(null);
  await initSidebar('admin', 'users', user.name);

  await loadAndRender(user.id);

  document.getElementById('addUserForm').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const errorMsg = document.getElementById('errorMsg');
    const successMsg = document.getElementById('successMsg');
    showFormMessage(errorMsg, '');
    showFormMessage(successMsg, '');

    const body = {
      name: document.getElementById('name').value.trim(),
      email: document.getElementById('email').value.trim(),
      password: document.getElementById('password').value,
      role: document.getElementById('role').value,
    };

    try {
      const data = await api.post('/admin/users.php', body);
      showFormMessage(successMsg, data.message, 'success');
      ev.target.reset();
      await loadAndRender(user.id);
    } catch (err) {
      showFormMessage(errorMsg, err.message, 'error');
    }
  });
});

async function loadAndRender(currentUserId) {
  let data;
  try {
    data = await api.get('/admin/users.php');
  } catch (err) {
    console.error('Could not load users', err);
    return;
  }

  document.getElementById('statCustomers').textContent = data.counts.customer || 0;
  document.getElementById('statStaff').textContent = data.counts.staff || 0;
  document.getElementById('statAdmins').textContent = data.counts.admin || 0;

  renderUsersTable(data.users, currentUserId);
}

function renderUsersTable(users, currentUserId) {
  const tbody = document.getElementById('usersBody');
  tbody.innerHTML = users.map((u) => {
    const removeBtn = u.id === currentUserId
      ? '<span class="text-muted" style="font-size:.85rem;">(you)</span>'
      : `<button type="button" class="btn-tag danger small" data-id="${u.id}" data-name="${e(u.name)}">Remove</button>`;

    return `
      <tr>
        <td>${e(u.name)}</td>
        <td>${e(u.email)}</td>
        <td><span class="status-pill">${e(u.role)}</span></td>
        <td>${fmt_date(u.created_at)}</td>
        <td>${removeBtn}</td>
      </tr>
    `;
  }).join('');

  tbody.querySelectorAll('[data-id]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!confirmAction(`Remove ${btn.dataset.name}'s account? This can't be undone.`)) return;
      try {
        await api.delete(`/admin/users.php?id=${btn.dataset.id}`);
        await loadAndRender(currentUserId);
      } catch (err) {
        showToast(err.message, 'error');
      }
    });
  });
}
