/* ============================================================
   FreshTrack — API client. Talks to backend/api/* as JSON.
   ============================================================ */

/**
 * Calls `${API_BASE}${path}` and returns the parsed JSON body.
 * Throws an Error whose .message is the backend's `error` string
 * (or a fallback) when the response isn't ok, so callers can just
 * try/catch and show err.message.
 */
async function apiFetch(path, options = {}) {
  const opts = {
    credentials: 'same-origin', // send the PHP session cookie
    headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
    ...options,
  };
  if (opts.body && typeof opts.body !== 'string') {
    opts.body = JSON.stringify(opts.body);
  }

  let res;
  try {
    res = await fetch(window.API_BASE + path, opts);
  } catch (err) {
    throw new Error('Could not reach the server. Is Apache/MySQL running in XAMPP?');
  }

  let data = null;
  try {
    data = await res.json();
  } catch (err) {
    // no/invalid JSON body — fall through, res.ok check below handles it
  }

  if (!res.ok) {
    throw new Error((data && data.error) || `Request failed (${res.status}).`);
  }
  return data;
}

const api = {
  get:    (path)         => apiFetch(path, { method: 'GET' }),
  post:   (path, body)   => apiFetch(path, { method: 'POST', body }),
  patch:  (path, body)   => apiFetch(path, { method: 'PATCH', body }),
  delete: (path)         => apiFetch(path, { method: 'DELETE' }),
};
