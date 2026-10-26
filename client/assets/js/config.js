/* ============================================================
   FreshTrack — frontend config.
   Every page sets `window.APP_BASE` before this script loads,
   using the same convention the old PHP pages used for $base:
   '' at the project root (index.html, services.html), '../' one
   folder down (auth/, customer/, staff/, admin/).

   The backend lives one level above the frontend folder itself,
   so the API base is always APP_BASE + '../backend/api' — for a
   root page that's '../backend/api', for a one-level-deep page
   that's '../../backend/api'. This works no matter what the
   project folder itself is named inside htdocs.
   ============================================================ */
window.APP_BASE = window.APP_BASE ?? '';
window.API_BASE = window.APP_BASE + '../backend/api';
