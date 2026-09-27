# FreshTrack — Laundry Booking & Order Tracking

This project was restructured into three parts:

```
laundry-system/
├── frontend/   Static HTML + CSS + JS. Fetches everything from the backend API.
├── backend/    PHP REST API. Every endpoint returns JSON — no HTML rendering.
└── database/   schema.sql — import this into MySQL to create the freshtrack database.
```

Nothing about the original business logic changed — validation rules, the
order status flow, tracking-code generation, password hashing, and the
live-updates WebSocket server all work exactly as before. What changed is
*where* HTML gets built: it used to happen in PHP on the server; now PHP only
returns data, and the browser (frontend JS) builds the page from it.

## 1. Set up the database

1. Start MySQL from the XAMPP control panel.
2. Open phpMyAdmin (or the `mysql` CLI) and run `database/schema.sql`. It
   creates the `freshtrack` database, its tables, some seed services, and
   three demo accounts (see the bottom of the file for their passwords).

## 2. Set up the backend

1. Copy (or symlink) this whole `laundry-system/` folder into your XAMPP
   `htdocs/` directory.
2. `backend/config/database.php` assumes the stock XAMPP MySQL defaults
   (`localhost`, user `root`, no password). Edit that file if yours differ.
3. (Optional, only needed for live status updates) From inside `backend/`:
   ```
   composer install
   php ws-server.php
   ```
   This starts the WebSocket server the dashboards use to push order-status
   changes in real time. If you skip this step, everything still works —
   pages just won't auto-refresh; reload to see the latest status.

With Apache running, the API is now reachable at, for example:
```
http://localhost/laundry-system/backend/api/services.php
```

## 3. Open the frontend

With Apache running, visit:
```
http://localhost/laundry-system/frontend/index.html
```

**Important:** open it through `http://localhost/...`, not by double-clicking
the HTML file in a file browser. The frontend's `fetch()` calls rely on the
frontend and backend being served from the same origin (same host/port) so
the PHP session cookie (used for login) is sent along automatically —
opening the file directly (`file://...`) breaks that.

## How the pieces talk to each other

- Every frontend page loads `assets/js/config.js` first, which works out the
  backend's URL relative to the current page (mirrors the old `$base`
  convention from the PHP includes).
- `assets/js/api.js` is a small `fetch()` wrapper (`api.get/post/patch/delete`)
  used by every page-specific script.
- Login still uses a normal PHP session (`$_SESSION`), set via
  `backend/includes/auth_middleware.php`. The frontend calls
  `GET /api/auth/me.php` on every page load to find out who (if anyone) is
  logged in.
- `frontend/partials/{header,footer,sidebar}.html` are the frontend's
  equivalent of the old `includes/{header,footer,sidebar}.php` — small HTML
  fragments fetched and injected by `assets/js/layout.js`, instead of being
  `require`'d server-side.
- Role protection happens on the backend (`require_login()` / `require_role()`
  in `backend/includes/auth_middleware.php` return a 401/403 JSON error).
  The frontend's `requireRole()` in `layout.js` is just a client-side
  shortcut that redirects to the login page early, so someone without
  access doesn't briefly see a page full of "failed to load" errors before
  the API rejects every request.

## API reference

| Method | Endpoint                        | Role       | Purpose |
|--------|----------------------------------|------------|---------|
| GET    | `/api/config.php`                | any        | WebSocket URL for live updates |
| POST   | `/api/auth/login.php`            | any        | Log in |
| POST   | `/api/auth/register.php`         | any        | Create a customer account + log in |
| POST   | `/api/auth/logout.php`           | any        | Log out |
| GET    | `/api/auth/me.php`                | any        | Current session user, or `null` |
| GET    | `/api/services.php`              | any        | List services (`?order=id\|name`, `?limit=N`) |
| GET    | `/api/customer/orders.php`       | customer   | List my orders (`?id=N` for one order + history) |
| POST   | `/api/customer/book.php`         | customer   | Book a new order |
| GET    | `/api/staff/orders.php`          | staff      | Order queue (`?status=X` to filter) |
| PATCH  | `/api/staff/orders.php`          | staff      | `{ order_id, action: "advance"\|"cancel" }` |
| GET    | `/api/admin/users.php`           | admin      | List users + role counts |
| POST   | `/api/admin/users.php`           | admin      | Create a staff/admin account |
| DELETE | `/api/admin/users.php?id=N`      | admin      | Remove an account |
| GET    | `/api/admin/reports.php`         | admin      | Status counts + per-service revenue |

All protected endpoints require the PHP session cookie set by
`auth/login.php` — the frontend sends it automatically via
`credentials: 'same-origin'` in every fetch.
