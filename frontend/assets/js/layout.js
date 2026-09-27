/* ============================================================
   FreshTrack — shared layout: loads partials/*.html (this is the
   HTML/JS stand-in for PHP's `require includes/header.php` etc.),
   then wires up the bits that used to be filled in server-side.
   ============================================================ */

async function loadPartial(url) {
  const res = await fetch(url);
  return res.text();
}

/**
 * Injects the header into #app-header and the footer into #app-footer,
 * sets the active nav link, and shows/hides the login-vs-dashboard
 * actions once we know whether anyone is logged in.
 * `activeNav` matches a header link's data-nav ("home" | "services").
 */
async function initHeaderFooter(activeNav) {
  const base = window.APP_BASE;

  const [headerHtml, footerHtml] = await Promise.all([
    loadPartial(base + 'partials/header.html'),
    loadPartial(base + 'partials/footer.html'),
  ]);

  const headerEl = document.getElementById('app-header');
  const footerEl = document.getElementById('app-footer');
  if (headerEl) headerEl.innerHTML = headerHtml.replaceAll('%%BASE%%', base);
  if (footerEl) footerEl.innerHTML = footerHtml.replaceAll('%%BASE%%', base);

  if (activeNav && headerEl) {
    const link = headerEl.querySelector(`[data-nav="${activeNav}"]`);
    if (link) link.classList.add('active');
  }

  const user = await currentUser();
  applyAuthState(headerEl, user, base);
  return user;
}

function dashboardUrlFor(role, base) {
  if (role === 'admin') return base + 'admin/dashboard.html';
  if (role === 'staff') return base + 'staff/dashboard.html';
  return base + 'customer/dashboard.html';
}

function applyAuthState(headerEl, user, base) {
  if (!headerEl) return;
  const authEls  = headerEl.querySelectorAll('.auth-only');
  const guestEls = headerEl.querySelectorAll('.guest-only');

  if (user) {
    authEls.forEach((el) => (el.hidden = false));
    guestEls.forEach((el) => (el.hidden = true));

    const roleBadge = headerEl.querySelector('[data-slot="role"]');
    if (roleBadge) roleBadge.textContent = user.role;

    const dashLink = headerEl.querySelector('[data-slot="dashboard-link"]');
    if (dashLink) dashLink.href = dashboardUrlFor(user.role, base);

    const logoutLink = headerEl.querySelector('[data-slot="logout-link"]');
    if (logoutLink) {
      logoutLink.href = base + 'auth/login.html';
      logoutLink.addEventListener('click', async (ev) => {
        ev.preventDefault();
        try { await api.post('/auth/logout.php'); } catch (err) { /* log out client-side regardless */ }
        window.location.href = base + 'index.html';
      });
    }
  } else {
    authEls.forEach((el) => (el.hidden = true));
    guestEls.forEach((el) => (el.hidden = false));
  }
}

/** GET /auth/me.php — returns the session user, or null. */
async function currentUser() {
  try {
    const data = await api.get('/auth/me.php');
    return data.user;
  } catch (err) {
    return null;
  }
}

/**
 * Client-side mirror of require_role()/require_login(): redirects to
 * login (with ?denied=1 on a role mismatch) before the page shows
 * anything it shouldn't. The backend still enforces this for real on
 * every API call — this just avoids a flash of someone else's page.
 */
async function requireRole(role) {
  const base = window.APP_BASE;
  const user = await currentUser();
  if (!user) {
    window.location.href = base + 'auth/login.html';
    return null;
  }
  if (user.role !== role) {
    window.location.href = base + 'auth/login.html?denied=1';
    return null;
  }
  return user;
}

/**
 * Loads the sidebar partial into #app-sidebar, shows only the links
 * for `role`, marks `activeItem` active, and fills in the user's name
 * — same effect as sidebar.php's per-role if/elseif.
 */
async function initSidebar(role, activeItem, userName) {
  const base = window.APP_BASE;
  const html = await loadPartial(base + 'partials/sidebar.html');
  const el = document.getElementById('app-sidebar');
  if (!el) return;
  el.innerHTML = html;

  const who = el.querySelector('[data-slot="who"]');
  if (who) who.textContent = userName;

  el.querySelectorAll('[data-role]').forEach((link) => {
    if (link.dataset.role !== role) {
      link.remove();
      return;
    }
    if (link.dataset.item === activeItem) link.classList.add('active');
  });
}
