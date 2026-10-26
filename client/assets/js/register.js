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

    if (name === '' || email === '' || password.length < 6) {
      showFormMessage(msgEl, 'Fill in every field — password needs at least 6 characters.', 'error');
      return;
    }

    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.textContent;

    // Show loading state
    submitBtn.disabled = true;
    submitBtn.textContent = 'Registering...';
    showFormMessage(msgEl, ''); // Clear previous errors

    try {
      await api.post('/auth/register.php', { name, email, password: await hashPassword(email, password) });
      window.location.href = base + 'customer/dashboard.html';
    } catch (err) {
      showFormMessage(msgEl, err.message, 'error');
      // Restore button only on failure (on success, the page redirects)
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;
    }
  });
});