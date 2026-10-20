/* ============================================================
   FreshTrack — shared layout (Inline version)
   ============================================================ */

/**
 * Applies the active nav link and auth state directly to the inline header.
 */
async function initHeaderFooter(activeNav, prefetchedUser) {
  const base = window.APP_BASE;

  // Target the inline elements directly
  const headerEl = document.querySelector('.tag-nav');

  if (activeNav && headerEl) {
    const link = headerEl.querySelector(`[data-nav="${activeNav}"]`);
    if (link) link.classList.add('active');
  }

  const user = prefetchedUser !== undefined ? prefetchedUser : await currentUser();
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

  // Remove the skeleton loader from the header once auth is resolved
  const headerSkeleton = document.getElementById('header-skeleton');
  if (headerSkeleton) headerSkeleton.remove();

  const authEls = headerEl.querySelectorAll('.auth-only');
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
      // Prevent multiple event listeners if initHeaderFooter is called twice
      logoutLink.replaceWith(logoutLink.cloneNode(true));

      const newLogoutLink = headerEl.querySelector('[data-slot="logout-link"]');
      newLogoutLink.addEventListener('click', async (ev) => {
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

let _mePromise;
async function currentUser() {
  if (!_mePromise) {
    _mePromise = api.get('/auth/me.php')
      .then((data) => data.user)
      .catch(() => null);
  }
  return _mePromise;
}

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
 * Targets the inline sidebar, hides irrelevant links, and sets the user's name.
 */
async function initSidebar(role, activeItem, userName) {
  const el = document.querySelector('.dash-side');
  if (!el) return;

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