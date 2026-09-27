document.addEventListener('DOMContentLoaded', async () => {
  const base = window.APP_BASE;
  const user = await initHeaderFooter(null);

  if (user) {
    window.location.href = dashboardUrlFor(user.role, base);
    return;
  }

  wirePasswordHint(document.getElementById('password'));
  const msgEl = document.getElementById('formMsg');

  document.getElementById('registerForm').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    try {
      await api.post('/auth/register.php', { name, email, password });
      window.location.href = base + 'customer/dashboard.html';
    } catch (err) {
      showFormMessage(msgEl, err.message, 'error');
    }
  });
});
