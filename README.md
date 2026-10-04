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
2. `backend/.env` already has the stock XAMPP MySQL defaults (`localhost`,
   user `root`, no password, port `3306`) and local WebSocket defaults — it
   should work as-is for local development. See **Environment variables
   (.env)** below for what each setting does and how to change it for a
   real deployment.
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

## Environment variables (.env)

`backend/.env` is the one file you edit per environment (local XAMPP,
staging, production) — everything in it is read by
`backend/config/database.php` and `backend/config/websocket.php` via PHP's
`getenv()`. `backend/includes/env.php` is a small loader (no Composer
package needed) that reads `.env` into `getenv()`/`$_ENV` before those
config files run; a real environment variable set by Apache or your
hosting platform always wins over whatever `.env` says.

`backend/.env.example` documents every key — copy it to `backend/.env` and
fill in real values when you deploy:

```
# --- Database (backend/config/database.php) ---------------------------
DB_HOST=localhost      # MySQL host
DB_PORT=3306           # MySQL port
DB_NAME=freshtrack     # database name
DB_USER=root           # MySQL user
DB_PASS=               # MySQL password (blank for stock XAMPP)

# --- WebSocket live-updates server (backend/config/websocket.php) -----
WS_HOST=0.0.0.0        # interface ws-server.php itself binds to
WS_PORT=8080           # port ws-server.php itself listens on
WS_BROADCAST_INTERVAL=2  # seconds between pushed updates

# The ws:// or wss:// address the BROWSER connects to. Leave blank
# locally (it auto-detects the page's own host on WS_PORT). Set it
# explicitly once you're behind a reverse proxy, using TLS, or serving
# the WebSocket on a different public host/port than WS_PORT, e.g.:
#   WS_PUBLIC_URL=wss://freshtrack.example.com/ws
WS_PUBLIC_URL=

# --- Gemini API key (backend/config/gemini.php) ------------------------
# Powers "Predict finish time" on the customer dashboard/orders page.
# Get a free key at https://aistudio.google.com/apikey — leave blank to
# disable the feature (it returns a clear error instead of failing).
GEMINI_KEY=

# Optional: defaults to gemini-flash-lite-latest (fastest/cheapest tier).
GEMINI_MODEL=

# Optional: used if the primary model keeps returning "503 model is
# overloaded" after a few retries. Defaults to a pinned Flash-Lite version.
GEMINI_FALLBACK_MODEL=
```

`backend/.env` is listed in `.gitignore` so your real credentials never
get committed — only `.env.example` (safe placeholder values) is meant to
go into version control. If you deploy somewhere that injects config as
real environment variables instead of a file (many hosts do), you don't
need `.env` at all — those variables take priority automatically.

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

## Progress bar & predicting finish time with Gemini

The customer dashboard and the order-tracking page (`customer/dashboard.html`,
`customer/orders.html`) show two things for each active order, on top of the
existing Pending → ... → Completed flow strip:

- **A progress bar** — a deterministic percentage based on how far along
  STATUS_FLOW the order is (`0% / 20% / 40% / 60% / 80% / 100%`). No API
  call, always accurate, computed client-side in `format.js`
  (`render_progress_bar()`).
- **"Predict finish time"** — a button that asks Gemini for a time-aware
  estimate (`assets/js/predict.js` → `GET /api/customer/predict.php?id=N`).
  It's click-to-run rather than automatic, so viewing your orders never by
  itself triggers an external API call.

`backend/api/customer/predict.php` builds its estimate from
`backend/data/freshtrack_training_data.csv` — for the order's service, it
pulls every non-cancelled historical example (status, qty, hours booked,
hours in current status, how much longer it actually took, progress at that
point) and sends that as few-shot context to Gemini, asking it to estimate
`predicted_remaining_hours` and `progress_percent` for the current order,
plus a one-sentence customer-facing message. The response is forced into
strict JSON via Gemini's `responseSchema`, so it's always parseable —
`finish_at` is then computed in PHP (`now + remaining_hours`) rather than
trusted from the model, since LLMs are unreliable at exact date arithmetic.
Completed/Cancelled orders are answered directly without calling Gemini at
all — their outcome is already known.

**Model choice.** This task — "look at ~20 CSV rows and estimate a number"
— doesn't need Pro-level reasoning, so it defaults to
`gemini-flash-lite-latest`: the fastest, cheapest tier, which is plenty
here. Google's "503 The model is overloaded" error is a known, Gemini-wide
capacity issue that hits every tier intermittently (not a sign one model
is a bad choice), so `backend/includes/gemini.php` retries on 429/500/503
with exponential backoff, and falls back to `GEMINI_FALLBACK_MODEL` — a
*pinned* model string rather than a rolling alias — if the primary model
is still failing once retries are exhausted. Both are configurable in
`.env` (see above) if you want to pin exact versions instead of the
auto-updating defaults.

To swap in your own historical data, replace
`backend/data/freshtrack_training_data.csv` — it just needs a `service_name`
column matching your `services.name` values, plus `status`, `qty`,
`elapsed_hours`, `time_in_status_hours`, `predicted_remaining_hours`, and
`progress_percent` columns.

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
| GET    | `/api/customer/predict.php?id=N` | customer   | Gemini estimate of remaining time for order `N` |
| GET    | `/api/staff/orders.php`          | staff      | Order queue (`?status=X` to filter) |
| PATCH  | `/api/staff/orders.php`          | staff      | `{ order_id, action: "advance"\|"cancel" }` |
| GET    | `/api/admin/users.php`           | admin      | List users + role counts |
| POST   | `/api/admin/users.php`           | admin      | Create a staff/admin account |
| DELETE | `/api/admin/users.php?id=N`      | admin      | Remove an account |
| GET    | `/api/admin/reports.php`         | admin      | Status counts + per-service revenue |

All protected endpoints require the PHP session cookie set by
`auth/login.php` — the frontend sends it automatically via
`credentials: 'same-origin'` in every fetch.
