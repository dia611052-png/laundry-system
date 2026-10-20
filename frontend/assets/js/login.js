document.addEventListener('DOMContentLoaded', async () => {
  const base = window.APP_BASE;
  const user = await initHeaderFooter(null);

  if (user) {
    window.location.href = dashboardUrlFor(user.role, base);
    return;
  }

  const msgEl = document.getElementById('formMsg');
  const params = new URLSearchParams(window.location.search);
  if (params.get('denied')) {
    showFormMessage(msgEl, 'You need to log in with the right account for that page.', 'error');
  }

  document.getElementById('loginForm').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.textContent;
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.textContent = 'Logging in...';
    showFormMessage(msgEl, ''); // Clear previous errors

    try {
      const { user } = await api.post('/auth/login.php', { email, password: await hashPassword(email, password) });
      window.location.href = dashboardUrlFor(user.role, base);
    } catch (err) {
      showFormMessage(msgEl, err.message, 'error');
      // Restore button only on failure (on success, the page redirects)
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;
    }
  });
});