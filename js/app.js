/* ============================================================
   FreshTrack — shared app logic: session/auth, nav rendering,
   toast + modal helpers, and small formatting utilities.
   ============================================================ */

const Auth = {
  SESSION_KEY: "ft_session",

  current() {
    try { return JSON.parse(sessionStorage.getItem(this.SESSION_KEY)); }
    catch { return null; }
  },
  login(email, password) {
    const user = DB.findUserByEmail(email);
    if (!user || user.password !== password) {
      return { ok: false, error: "Email or password not recognized." };
    }
    sessionStorage.setItem(
      this.SESSION_KEY,
      JSON.stringify({ id: user.id, name: user.name, role: user.role })
    );
    return { ok: true, user };
  },
  register({ name, email, password }) {
    if (DB.findUserByEmail(email)) {
      return { ok: false, error: "That email is already registered." };
    }
    const users = DB.getUsers();
    const user = { id: "u-" + Date.now(), name, email, password, role: "customer" };
    users.push(user);
    DB.saveUsers(users);
    sessionStorage.setItem(
      this.SESSION_KEY,
      JSON.stringify({ id: user.id, name: user.name, role: user.role })
    );
    return { ok: true, user };
  },
  logout() {
    sessionStorage.removeItem(this.SESSION_KEY);
    window.location.href = "index.html";
  },
  requireRole(role) {
    const s = this.current();
    if (!s || s.role !== role) {
      window.location.href = "login.html";
      return null;
    }
    return s;
  },
};

/* ---------- Formatting helpers ---------- */
function fmtDate(ts) {
  return new Date(ts).toLocaleString("en-PH", {
    month: "short", day: "numeric", hour: "numeric", minute: "2-digit",
  });
}
function fmtMoney(n) {
  return "\u20b1" + Number(n).toFixed(2);
}
function statusClass(status) {
  return "status-" + String(status).toLowerCase().replace(/\s+/g, "-");
}

/* ---------- Toast notifications ---------- */
const Toast = {
  _stack: null,
  _init() {
    if (this._stack) return;
    this._stack = document.createElement("div");
    this._stack.className = "toast-stack";
    this._stack.setAttribute("aria-live", "polite");
    this._stack.setAttribute("aria-atomic", "true");
    document.body.appendChild(this._stack);
  },
  show(message, type = "info", duration = 3200) {
    this._init();
    const el = document.createElement("div");
    el.className = `toast toast-${type}`;
    el.setAttribute("role", "status");
    const icon = type === "success" ? "\u2713" : type === "error" ? "!" : "i";
    el.innerHTML = `
      <span class="toast-icon">${icon}</span>
      <span class="toast-msg"></span>
      <button class="toast-close" aria-label="Dismiss">&times;</button>
    `;
    el.querySelector(".toast-msg").textContent = message;
    this._stack.appendChild(el);
    requestAnimationFrame(() => el.classList.add("show"));

    const dismiss = () => {
      el.classList.remove("show");
      setTimeout(() => el.remove(), 260);
    };
    el.querySelector(".toast-close").addEventListener("click", dismiss);
    setTimeout(dismiss, duration);
  },
  success(m, d) { this.show(m, "success", d); },
  error(m, d)   { this.show(m, "error", d); },
  info(m, d)    { this.show(m, "info", d); },
};

/* ---------- Promise-based confirm dialog ---------- */
function confirmDialog(message, opts = {}) {
  const {
    title = "Are you sure?",
    confirmText = "Confirm",
    cancelText = "Cancel",
    danger = false,
  } = opts;

  return new Promise((resolve) => {
    const overlay = document.createElement("div");
    overlay.className = "modal-overlay";
    overlay.setAttribute("role", "dialog");
    overlay.setAttribute("aria-modal", "true");
    overlay.innerHTML = `
      <div class="modal">
        <h3></h3>
        <p></p>
        <div class="modal-actions">
          <button type="button" class="btn-tag ghost" data-cancel></button>
          <button type="button" class="btn-tag ${danger ? "danger" : "marigold"}" data-confirm></button>
        </div>
      </div>
    `;
    overlay.querySelector("h3").textContent = title;
    overlay.querySelector("p").textContent = message;
    overlay.querySelector("[data-cancel]").textContent = cancelText;
    overlay.querySelector("[data-confirm]").textContent = confirmText;

    document.body.appendChild(overlay);
    requestAnimationFrame(() => overlay.classList.add("show"));

    const confirmBtn = overlay.querySelector("[data-confirm]");
    setTimeout(() => confirmBtn.focus(), 50);

    const close = (val) => {
      overlay.classList.remove("show");
      document.removeEventListener("keydown", onKey);
      setTimeout(() => overlay.remove(), 220);
      resolve(val);
    };
    const onKey = (e) => { if (e.key === "Escape") close(false); };

    overlay.querySelector("[data-cancel]").addEventListener("click", () => close(false));
    confirmBtn.addEventListener("click", () => close(true));
    overlay.addEventListener("click", (e) => { if (e.target === overlay) close(false); });
    document.addEventListener("keydown", onKey);
  });
}

/* ---------- Nav rendering (session-aware + mobile drawer) ---------- */
function renderNav() {
  const actions = document.getElementById("navActions");
  if (!actions) return;

  const nav = document.querySelector(".tag-nav");

  // Inject mobile burger once
  if (nav && !nav.querySelector(".nav-burger")) {
    const burger = document.createElement("button");
    burger.type = "button";
    burger.className = "nav-burger";
    burger.setAttribute("aria-label", "Toggle navigation");
    burger.setAttribute("aria-expanded", "false");
    burger.innerHTML = "<span></span><span></span><span></span>";
    nav.querySelector(".container-tag").appendChild(burger);

    burger.addEventListener("click", () => {
      const open = document.body.classList.toggle("nav-open");
      burger.setAttribute("aria-expanded", String(open));
    });

    // Close drawer when a link is clicked
    nav.querySelectorAll("nav a").forEach((a) => {
      a.addEventListener("click", () => {
        document.body.classList.remove("nav-open");
        burger.setAttribute("aria-expanded", "false");
      });
    });
  }

  // Session-aware actions
  const session = Auth.current();
  if (session) {
    const dashHref =
      session.role === "admin" ? "dashboard-admin.html"
      : session.role === "staff" ? "dashboard-staff.html"
      : "dashboard-customer.html";
    actions.innerHTML = `
      <span class="badge-role">${session.role}</span>
      <a class="btn-tag ghost small" href="${dashHref}">Dashboard</a>
      <button class="btn-tag small" id="navLogout" type="button">Log out</button>
    `;
    document.getElementById("navLogout").addEventListener("click", () => Auth.logout());
  } else {
    actions.innerHTML = `
      <a class="btn-tag ghost small" href="login.html">Log in</a>
      <a class="btn-tag marigold small" href="register.html">Book now</a>
    `;
  }

  // Active link
  const current = document.body.getAttribute("data-nav-current");
  if (current) {
    document.querySelectorAll('.tag-nav nav a[data-nav]').forEach((a) => {
      if (a.getAttribute("data-nav") === current) a.classList.add("active");
    });
  }
}

document.addEventListener("DOMContentLoaded", renderNav);